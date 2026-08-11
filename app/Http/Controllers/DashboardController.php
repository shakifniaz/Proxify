<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\ExamSchedule;
use App\Models\LeaveRequest;
use App\Models\Notice;
use App\Models\ProxyRun;
use App\Models\Routine;
use App\Models\TeacherProfile;
use App\Models\TeacherLeaveAllowance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = strtolower($user?->role ?? 'admin');
        $institutionId = $user?->institution_id;
        $today = now();
        $routine = $this->activeRoutine($institutionId);

        return match ($role) {
            'teacher' => Inertia::render('TeacherDashboard', $this->teacherPayload($request, $routine, $today)),
            'student' => Inertia::render('StudentDashboard', $this->studentPayload($request, $routine, $today)),
            default => Inertia::render('Dashboard', $this->adminPayload($request, $routine, $today)),
        };
    }

    private function adminPayload(Request $request, ?Routine $routine, Carbon $today): array
    {
        $institutionId = $request->user()?->institution_id;
        $approvedProxy = $this->proxyForDate($routine, $today);
        $grid = $approvedProxy?->proxy_generated_grid ?: ($routine?->generated_grid ?? []);
        $teacherSchedule = $approvedProxy?->proxy_teacher_schedule ?: ($routine?->teacher_schedule ?? []);
        $dayLabel = $this->dayLabel($routine, $today);
        $classes = $routine?->classes ?? [];
        $teachers = $routine?->teachers ?? [];
        $notices = $this->visibleNotices('admin', $institutionId);
        $activeExam = $this->activeExam($institutionId);

        $unresolved = $this->countUnresolved($grid, $dayLabel);
        $assigned = $this->countAssigned($grid, $dayLabel);
        $proxyCount = $this->countProxyChanges($grid, $dayLabel);
        $leaveCount = LeaveRequest::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->count();

        $sections = collect($classes)->sum(fn ($class) => count($class['sections'] ?? []));
        $periods = collect($routine?->periods ?? [])->where('type', 'class')->count();
        $coverage = $assigned + $unresolved > 0 ? round(($assigned / max(1, $assigned + $unresolved)) * 100) : 0;

        return [
            'dateLabel' => $today->format('l, F j, Y'),
            'hero' => [
                'routineName' => $routine?->name ?? 'No active routine',
                'day' => $dayLabel ?: $today->format('D'),
                'coverage' => $coverage,
                'status' => $unresolved > 0 ? 'Needs attention' : 'Ready for today',
            ],
            'stats' => [
                ['label' => 'Classes', 'value' => $sections, 'sub' => 'active sections', 'tone' => 'green'],
                ['label' => 'Teachers', 'value' => count($teachers), 'sub' => 'in routine pool', 'tone' => 'dark'],
                ['label' => 'Proxy changes', 'value' => $proxyCount, 'sub' => $approvedProxy ? $approvedProxy->name : 'none active today', 'tone' => 'mint'],
                ['label' => 'Leaves today', 'value' => $leaveCount, 'sub' => 'approved absences', 'tone' => 'amber'],
            ],
            'routineHealth' => [
                'coverage' => $coverage,
                'assigned' => $assigned,
                'unresolved' => $unresolved,
                'periods' => $periods,
            ],
            'todaySchedule' => $this->adminScheduleSnapshot($teacherSchedule, $routine?->periods ?? [], $dayLabel),
            'notices' => $notices->take(4)->values(),
            'exam' => $this->examSnapshot($activeExam, $today, 'admin'),
            'leaveQueue' => LeaveRequest::query()
                ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
                ->where('status', 'pending')
                ->latest()
                ->take(4)
                ->get()
                ->map(fn (LeaveRequest $leave) => [
                    'id' => $leave->id,
                    'teacher' => $leave->teacher_name,
                    'type' => $this->titleText($leave->type),
                    'date' => $leave->start_date?->format('d M'),
                    'duration' => $leave->duration,
                ]),
            'activity' => $this->adminActivity($approvedProxy, $notices, $activeExam, $leaveCount),
        ];
    }

    private function teacherPayload(Request $request, ?Routine $routine, Carbon $today): array
    {
        $user = $request->user();
        $teacherProfile = $user?->teacherProfile;
        $teacherId = (string) ($user?->teacher_profile_id ?? $teacherProfile?->id ?? '');
        $teacherName = $teacherProfile?->name ?? $user?->name ?? 'Teacher';
        $institutionId = $user?->institution_id;
        $tomorrow = $today->copy()->addDay();

        $todaySchedule = $this->teacherScheduleForDate($routine, $today, $teacherId, $teacherName);
        $tomorrowSchedule = $this->teacherScheduleForDate($routine, $tomorrow, $teacherId, $teacherName);
        $notices = $this->visibleNotices('teacher', $institutionId)->take(3)->values();
        $exam = $this->examSnapshot($this->activeExam($institutionId), $today, 'teacher', $teacherName);
        $leaveStats = $this->teacherLeaveStats($routine, $institutionId, $teacherId, $today);

        return [
            'teacherName' => $teacherName,
            'dateLabel' => $today->format('l, F j, Y'),
            'nextDayLabel' => $tomorrow->format('l, F j'),
            'routineName' => $routine?->name ?? 'No active routine',
            'stats' => [
                'classesToday' => collect($todaySchedule)->where('type', 'class')->count(),
                'proxiesToday' => collect($todaySchedule)->where('isProxy', true)->count(),
                'classesTomorrow' => collect($tomorrowSchedule)->where('type', 'class')->count(),
            ],
            'todaySchedule' => $todaySchedule,
            'tomorrowSchedule' => $tomorrowSchedule,
            'proxyAssignments' => collect($todaySchedule)->where('isProxy', true)->values(),
            'urgentNotices' => $notices,
            'exam' => $exam,
            'leaveStats' => $leaveStats,
            'classroomUpdates' => $this->classroomHints($routine, $teacherId, 'teacher'),
            'classroomFeed' => array_merge(
                $this->classroomFeedContext($routine, $institutionId, 'teacher', $teacherId, $teacherName),
                ['authorId' => 'teacher-'.($user?->id ?? 'guest')]
            ),
        ];
    }

    private function studentPayload(Request $request, ?Routine $routine, Carbon $today): array
    {
        $user = $request->user();
        $institutionId = $user?->institution_id;
        $section = $user?->classSection;
        $sectionId = (string) ($user?->class_section_id ?? '');
        $todayRoutine = $this->studentScheduleForDate($routine, $today, $sectionId);
        $notices = $this->visibleNotices('student', $institutionId)->take(3)->values();
        $exam = $this->examSnapshot($this->activeExam($institutionId), $today, 'student', $sectionId);

        return [
            'studentName' => $user?->name ?? 'Student',
            'classLabel' => $section ? "{$section->class_name} {$section->section_name}" : $this->studentClassLabel($routine, $sectionId),
            'dateLabel' => $today->format('l, F j, Y'),
            'routineName' => $routine?->name ?? 'No active routine',
            'stats' => [
                'classesToday' => collect($todayRoutine)->where('type', 'class')->count(),
                'notices' => $notices->count(),
                'assignments' => count($this->classroomHints($routine, $sectionId, 'student')),
            ],
            'todayRoutine' => $todayRoutine,
            'notices' => $notices,
            'classroomUpdates' => $this->classroomHints($routine, $sectionId, 'student'),
            'exam' => $exam,
            'classroomFeed' => $this->classroomFeedContext($routine, $institutionId, 'student', $sectionId),
        ];
    }

    private function activeRoutine(?int $institutionId): ?Routine
    {
        return Routine::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('status', 'Active')
            ->latest()
            ->first()
            ?? Routine::query()
                ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
                ->latest()
                ->first();
    }

    private function proxyForDate(?Routine $routine, Carbon $date): ?ProxyRun
    {
        if (! $routine) {
            return null;
        }

        $day = $this->dayLabel($routine, $date);

        return ProxyRun::query()
            ->where('routine_id', $routine->id)
            ->where('status', 'Approved')
            ->where(function ($query) use ($date, $day) {
                $query->whereDate('date', $date->toDateString());
                if ($day) {
                    $query->orWhere(fn ($inner) => $inner->whereNull('date')->where('day_label', $day));
                }
            })
            ->latest('approved_at')
            ->first();
    }

    private function dayLabel(?Routine $routine, Carbon $date): string
    {
        $days = $routine?->days ?? [];
        if (! $days) {
            return $date->format('D');
        }

        $weekday = strtolower($date->format('D'));

        return collect($days)->first(fn ($day) => str_starts_with(strtolower((string) $day), $weekday[0]) && strtolower(substr((string) $day, 0, 3)) === $weekday)
            ?? collect($days)->first(fn ($day) => strtolower((string) $day) === strtolower($date->format('l')))
            ?? $date->format('D');
    }

    private function teacherScheduleForDate(?Routine $routine, Carbon $date, string $teacherId, string $teacherName): array
    {
        if (! $routine) {
            return [];
        }

        $proxy = $this->proxyForDate($routine, $date);
        $schedule = $proxy?->proxy_teacher_schedule ?: ($routine->teacher_schedule ?? []);
        $day = $this->dayLabel($routine, $date);
        $teacher = collect($schedule[$day] ?? [])->first(function ($item) use ($teacherId, $teacherName) {
            return ($teacherId !== '' && (string) ($item['id'] ?? '') === $teacherId)
                || strcasecmp((string) ($item['name'] ?? ''), $teacherName) === 0;
        });

        return $this->scheduleCells($routine->periods ?? [], $teacher['cells'] ?? [], $date);
    }

    private function studentScheduleForDate(?Routine $routine, Carbon $date, string $sectionId): array
    {
        if (! $routine) {
            return [];
        }

        $proxy = $this->proxyForDate($routine, $date);
        $grid = $proxy?->proxy_generated_grid ?: ($routine->generated_grid ?? []);
        $day = $this->dayLabel($routine, $date);
        $section = collect($grid)->first(fn ($item) => (string) ($item['sectionId'] ?? $item['id'] ?? '') === $sectionId);

        return $this->scheduleCells($routine->periods ?? [], $section['days'][$day] ?? [], $date);
    }

    private function scheduleCells(array $periods, array $cells, Carbon $date): array
    {
        return collect($periods)->map(function ($period) use ($cells, $date) {
            $key = (string) ($period['key'] ?? '');
            $cell = $cells[$key] ?? null;
            $isClassPeriod = ($period['type'] ?? 'class') === 'class';
            $isProxy = ! empty($cell['proxyChanged']) || ($cell['type'] ?? '') === 'proxy';

            if (! $isClassPeriod) {
                return [
                    'period' => $period['label'] ?? strtoupper($key),
                    'time' => $this->periodTime($period),
                    'type' => 'break',
                    'label' => $period['label'] ?? 'Break',
                    'date' => $date->format('d/m/y'),
                ];
            }

            if (! $cell || ! in_array($cell['type'] ?? '', ['class', 'proxy', 'unresolved'], true)) {
                return [
                    'period' => $period['label'] ?? strtoupper($key),
                    'time' => $this->periodTime($period),
                    'type' => 'empty',
                    'subject' => 'Free period',
                    'date' => $date->format('d/m/y'),
                ];
            }

            return [
                'id' => md5($date->toDateString().$key.($cell['teacherId'] ?? '').($cell['subject'] ?? '')),
                'period' => $period['label'] ?? strtoupper($key),
                'time' => $this->periodTime($period),
                'type' => 'class',
                'subject' => $cell['subject'] ?? 'Subject',
                'teacher' => $cell['teacherName'] ?? null,
                'classLabel' => $cell['classLabel'] ?? $cell['sectionLabel'] ?? '',
                'room' => $cell['room'] ?? '',
                'isProxy' => $isProxy,
                'coveringFor' => $cell['proxyForTeacherName'] ?? null,
                'date' => $date->format('d/m/y'),
            ];
        })->values()->all();
    }

    private function adminScheduleSnapshot(array $teacherSchedule, array $periods, string $day): array
    {
        return collect($teacherSchedule[$day] ?? [])
            ->map(function ($teacher) {
                $classes = collect($teacher['cells'] ?? [])->filter(fn ($cell) => in_array($cell['type'] ?? '', ['class', 'proxy'], true));

                return [
                    'teacher' => $teacher['name'] ?? 'Teacher',
                    'classes' => $classes->count(),
                    'proxy' => $classes->filter(fn ($cell) => ! empty($cell['proxyChanged']) || ($cell['type'] ?? '') === 'proxy')->count(),
                    'subjects' => $classes->pluck('subject')->filter()->unique()->take(3)->implode(', '),
                ];
            })
            ->filter(fn ($row) => $row['classes'] > 0)
            ->sortByDesc('classes')
            ->take(6)
            ->values()
            ->all();
    }

    private function visibleNotices(string $role, ?int $institutionId): Collection
    {
        $visibility = match ($role) {
            'student' => ['All'],
            'teacher' => ['All', 'Teachers'],
            default => ['All', 'Teachers', 'Admins'],
        };

        return Notice::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where(function ($query) use ($visibility, $role) {
                $query->whereIn('visibility', $visibility);
                if (in_array($role, ['admin', 'teacher'], true)) {
                    $query->orWhere('board', 'staff');
                }
            })
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (Notice $notice) => [
                'id' => $notice->id,
                'title' => $notice->title,
                'message' => str($notice->message)->limit(110)->toString(),
                'urgency' => $this->titleText($notice->urgency),
                'board' => $this->titleText($notice->board),
                'date' => $notice->created_at?->format('M j'),
            ]);
    }

    private function activeExam(?int $institutionId): ?ExamSchedule
    {
        return ExamSchedule::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('status', 'Active')
            ->latest()
            ->first();
    }

    private function examSnapshot(?ExamSchedule $exam, Carbon $date, string $role, ?string $filter = null): array
    {
        if (! $exam) {
            return ['active' => false, 'title' => 'No active exam schedule', 'items' => []];
        }

        $items = collect($exam->exam_grid ?? [])
            ->flatMap(fn ($hallSlots, $dateKey) => collect($hallSlots)->flatMap(function ($slots, $hallId) use ($dateKey) {
                return collect($slots)->map(fn ($slot, $slotId) => array_merge($slot, [
                    'date' => $dateKey,
                    'hallId' => $hallId,
                    'slotId' => $slotId,
                ]));
            }))
            ->filter(function ($slot) use ($role, $filter) {
                if (! $filter) {
                    return true;
                }

                if ($role === 'teacher') {
                    return collect($slot['guards'] ?? [])->contains(fn ($guard) => strcasecmp((string) ($guard['name'] ?? $guard), $filter) === 0);
                }

                if ($role === 'student') {
                    return collect($slot['groups'] ?? [])->contains(fn ($group) => (string) ($group['sectionId'] ?? '') === (string) $filter);
                }

                return true;
            })
            ->sortBy('date')
            ->take(4)
            ->map(fn ($slot) => [
                'date' => $this->formatExamDate($slot['date'] ?? ''),
                'time' => $slot['timeLabel'] ?? $slot['slotLabel'] ?? 'Exam slot',
                'title' => collect($slot['groups'] ?? [])->map(fn ($group) => trim(($group['classLabel'] ?? '').' - '.($group['subject'] ?? '')))->filter()->take(2)->implode(', ') ?: 'Scheduled exam',
                'hall' => $slot['hallName'] ?? $slot['hallLabel'] ?? 'Hall',
            ])
            ->values()
            ->all();

        return [
            'active' => true,
            'title' => $exam->name,
            'subtitle' => $exam->start_date?->format('d M').' - '.$exam->end_date?->format('d M'),
            'items' => $items,
        ];
    }

    private function classroomHints(?Routine $routine, string $ownerId, string $role): array
    {
        if (! $routine) {
            return [];
        }

        if ($role === 'teacher') {
            return collect($routine->generated_grid ?? [])
                ->flatMap(fn ($section) => collect($section['days'] ?? [])->flatMap(fn ($cells) => collect($cells)->filter(fn ($cell) => (string) ($cell['teacherId'] ?? '') === $ownerId)))
                ->pluck('subject')
                ->filter()
                ->unique()
                ->take(3)
                ->map(fn ($subject) => ['subject' => $subject, 'message' => 'Classroom stream ready for updates and assignments.', 'due' => 'Firebase classroom'])
                ->values()
                ->all();
        }

        $section = collect($routine->classes ?? [])
            ->flatMap(fn ($class) => collect($class['sections'] ?? []))
            ->first(fn ($section) => (string) ($section['id'] ?? '') === $ownerId);

        return collect($section['subjects'] ?? [])
            ->pluck('name')
            ->filter()
            ->take(3)
            ->map(fn ($subject) => ['subject' => $subject, 'message' => 'Check classroom for recent posts, assignments, and files.', 'due' => 'Classroom'])
            ->values()
            ->all();
    }

    private function classroomFeedContext(
        ?Routine $routine,
        ?int $institutionId,
        string $role,
        string $ownerId,
        ?string $teacherName = null
    ): array {
        $sectionIds = collect();

        if ($routine && $role === 'teacher') {
            $gridSections = collect($routine->generated_grid ?? [])
                ->filter(function ($section) use ($ownerId, $teacherName) {
                    return collect($section['days'] ?? [])->flatten(1)->contains(function ($cell) use ($ownerId, $teacherName) {
                        return ($ownerId !== '' && (string) ($cell['teacherId'] ?? '') === $ownerId)
                            || ($teacherName && strcasecmp((string) ($cell['teacherName'] ?? ''), $teacherName) === 0);
                    });
                })
                ->map(fn ($section) => (string) ($section['sectionId'] ?? $section['id'] ?? ''));

            $configuredSections = collect($routine->classes ?? [])->flatMap(function ($class) use ($ownerId, $teacherName) {
                return collect($class['sections'] ?? [])->filter(function ($section) use ($ownerId, $teacherName) {
                    $isClassTeacher = (string) ($section['classTeacherId'] ?? '') === $ownerId;
                    $teachesSubject = collect($section['subjects'] ?? [])->contains(function ($subject) use ($ownerId, $teacherName) {
                        if (! is_array($subject)) {
                            return false;
                        }

                        return (string) ($subject['teacherId'] ?? $subject['teacher_id'] ?? '') === $ownerId
                            || ($teacherName && strcasecmp((string) ($subject['teacherName'] ?? $subject['teacher'] ?? ''), $teacherName) === 0);
                    });

                    return $isClassTeacher || $teachesSubject;
                })->map(fn ($section) => (string) ($section['id'] ?? ''));
            });

            $directorySections = ClassSection::query()
                ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
                ->where('class_teacher_profile_id', $ownerId)
                ->pluck('id')
                ->map(fn ($id) => (string) $id);

            $sectionIds = $gridSections->merge($configuredSections)->merge($directorySections);
        } elseif ($ownerId !== '') {
            $sectionIds = collect([$ownerId]);
        }

        return [
            'institutionId' => $institutionId ? (string) $institutionId : 'global',
            'sectionIds' => $sectionIds->filter()->unique()->values()->all(),
            'teacherName' => $teacherName,
            'firebaseConfig' => [
                'apiKey' => config('services.firebase.api_key'),
                'authDomain' => config('services.firebase.auth_domain'),
                'projectId' => config('services.firebase.project_id'),
                'storageBucket' => config('services.firebase.storage_bucket'),
                'messagingSenderId' => config('services.firebase.messaging_sender_id'),
                'appId' => config('services.firebase.app_id'),
            ],
        ];
    }

    private function teacherLeaveStats(?Routine $routine, ?int $institutionId, string $teacherId, Carbon $today): array
    {
        $approvedDays = LeaveRequest::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('teacher_id', $teacherId)
            ->where('status', 'approved')
            ->whereYear('start_date', $today->year)
            ->sum('days');

        $pending = LeaveRequest::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('teacher_id', $teacherId)
            ->where('status', 'pending')
            ->count();

        $allowance = $routine
            ? TeacherLeaveAllowance::query()
                ->where('routine_id', $routine->id)
                ->where('teacher_id', $teacherId)
                ->where('year', $today->year)
                ->value('max_leaves')
            : null;
        $maximum = (int) ($allowance ?? 12);

        return [
            'used' => (int) $approvedDays,
            'remaining' => max(0, $maximum - (int) $approvedDays),
            'maximum' => $maximum,
            'pending' => $pending,
        ];
    }

    private function countUnresolved(array $grid, string $day): int
    {
        return collect($grid)->sum(fn ($section) => collect($section['days'][$day] ?? [])->where('type', 'unresolved')->count());
    }

    private function countAssigned(array $grid, string $day): int
    {
        return collect($grid)->sum(fn ($section) => collect($section['days'][$day] ?? [])->whereIn('type', ['class', 'proxy'])->count());
    }

    private function countProxyChanges(array $grid, string $day): int
    {
        return collect($grid)->sum(fn ($section) => collect($section['days'][$day] ?? [])->filter(fn ($cell) => ! empty($cell['proxyChanged']) || ($cell['type'] ?? '') === 'proxy')->count());
    }

    private function periodTime(array $period): string
    {
        return trim(($period['start'] ?? '').(($period['start'] ?? null) && ($period['end'] ?? null) ? ' - ' : '').($period['end'] ?? '')) ?: 'Time not set';
    }

    private function titleText(?string $value): string
    {
        return str_replace('_', ' ', ucfirst((string) $value));
    }

    private function formatExamDate(string $date): string
    {
        try {
            return Carbon::parse($date)->format('d M');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function studentClassLabel(?Routine $routine, string $sectionId): string
    {
        $match = collect($routine?->classes ?? [])
            ->flatMap(fn ($class) => collect($class['sections'] ?? [])->map(fn ($section) => [
                'id' => (string) ($section['id'] ?? ''),
                'label' => trim(($class['name'] ?? '').' '.($section['name'] ?? $section['sectionName'] ?? '')),
            ]))
            ->firstWhere('id', $sectionId);

        return $match['label'] ?? 'My class';
    }

    private function adminActivity(?ProxyRun $proxy, Collection $notices, ?ExamSchedule $exam, int $leaveCount): array
    {
        return collect([
            $proxy ? ['text' => $proxy->name.' is active for '.$proxy->day_label, 'time' => $proxy->approved_at?->format('g:i A') ?? 'Today', 'tone' => 'green'] : null,
            $leaveCount ? ['text' => $leaveCount.' approved leave handoff'.($leaveCount === 1 ? '' : 's').' ready for proxy planning', 'time' => 'Today', 'tone' => 'amber'] : null,
            $notices->first() ? ['text' => 'Latest notice: '.$notices->first()['title'], 'time' => $notices->first()['date'], 'tone' => 'mint'] : null,
            $exam ? ['text' => $exam->name.' is the active exam schedule', 'time' => $exam->start_date?->format('d M'), 'tone' => 'dark'] : null,
        ])->filter()->values()->all();
    }
}
