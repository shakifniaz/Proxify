<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\ExamSchedule;
use App\Models\LeaveRequest;
use App\Models\Notice;
use App\Models\ProxyRun;
use App\Models\Routine;
use App\Models\TeacherProfile;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $institutionId = $request->user()?->institution_id;
        $today = now()->startOfDay();
        $from = $today->copy()->subDays(29);
        $activeRoutine = Routine::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('status', 'Active')
            ->latest()
            ->first();

        $teachers = TeacherProfile::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $sections = ClassSection::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->orderBy('sort_order')
            ->orderBy('class_name')
            ->orderBy('section_name')
            ->get();

        $leaves = LeaveRequest::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->whereDate('start_date', '<=', $today->copy()->addDays(30))
            ->whereDate('end_date', '>=', $from)
            ->get();

        $proxyRuns = ProxyRun::query()
            ->when($activeRoutine, fn ($query) => $query->where('routine_id', $activeRoutine->id))
            ->where(function ($query) use ($from) {
                $query->whereNull('date')->orWhereDate('date', '>=', $from);
            })
            ->latest('date')
            ->get();

        $notices = Notice::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('created_at', '>=', $from)
            ->get();

        $examSchedules = ExamSchedule::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->latest()
            ->get();

        $routineAnalytics = $this->routineAnalytics($activeRoutine, $teachers, $sections);
        $proxyAnalytics = $this->proxyAnalytics($proxyRuns);
        $leaveAnalytics = $this->leaveAnalytics($leaves, $teachers, $from, $today);
        $noticeAnalytics = $this->noticeAnalytics($notices, $teachers->count());
        $examAnalytics = $this->examAnalytics($examSchedules);

        return Inertia::render('Analytics/Index', [
            'activeRoutineName' => $activeRoutine?->name,
            'dateRangeLabel' => $from->format('d M').' - '.$today->format('d M Y'),
            'stats' => [
                [
                    'label' => 'Routine coverage',
                    'value' => $routineAnalytics['coverage'].'%',
                    'detail' => $routineAnalytics['assignedSlots'].' assigned, '.$routineAnalytics['unallocated'].' unallocated',
                    'tone' => $routineAnalytics['coverage'] >= 95 ? 'good' : ($routineAnalytics['coverage'] >= 80 ? 'warn' : 'bad'),
                ],
                [
                    'label' => 'Proxy resolution',
                    'value' => $proxyAnalytics['resolutionRate'].'%',
                    'detail' => $proxyAnalytics['resolved'].' resolved, '.$proxyAnalytics['unresolved'].' unresolved',
                    'tone' => $proxyAnalytics['unresolved'] === 0 ? 'good' : 'warn',
                ],
                [
                    'label' => 'Approved leave days',
                    'value' => (string) $leaveAnalytics['approvedDays'],
                    'detail' => $leaveAnalytics['pending'].' pending requests',
                    'tone' => $leaveAnalytics['pending'] ? 'warn' : 'good',
                ],
                [
                    'label' => 'Exam readiness',
                    'value' => $examAnalytics['active'] ? $examAnalytics['scheduledGroups'].' groups' : 'No active exam',
                    'detail' => $examAnalytics['active'] ? $examAnalytics['active']['name'] : $examAnalytics['drafts'].' draft schedules',
                    'tone' => $examAnalytics['active'] ? 'good' : 'neutral',
                ],
            ],
            'routine' => $routineAnalytics,
            'proxy' => $proxyAnalytics,
            'leaves' => $leaveAnalytics,
            'notices' => $noticeAnalytics,
            'exams' => $examAnalytics,
        ]);
    }

    private function routineAnalytics(?Routine $routine, Collection $teachers, Collection $sections): array
    {
        $metrics = $routine?->metrics ?? [];
        $assignedSlots = (int) ($metrics['assignedSlots'] ?? 0);
        $unallocated = (int) ($metrics['unallocatedAssignments'] ?? count($metrics['unallocated'] ?? []));
        $coverage = $assignedSlots + $unallocated > 0
            ? round(($assignedSlots / max(1, $assignedSlots + $unallocated)) * 100)
            : 0;

        $teacherNames = collect($routine?->teachers ?? [])
            ->mapWithKeys(fn ($teacher) => [(string) ($teacher['id'] ?? $teacher['name'] ?? '') => $teacher['name'] ?? 'Teacher'])
            ->filter();
        $teacherLoad = [];
        $classCoverage = [];

        foreach ($routine?->generated_grid ?? [] as $section) {
            $classLabel = $section['label'] ?? trim(($section['className'] ?? 'Class').' '.($section['sectionName'] ?? ''));
            $scheduled = 0;
            foreach (($section['days'] ?? []) as $day) {
                foreach ($day as $cell) {
                    if (($cell['type'] ?? '') !== 'class') {
                        continue;
                    }
                    $scheduled++;
                    $teacherId = (string) ($cell['teacherId'] ?? '');
                    $teacherName = $teacherNames[$teacherId] ?? ($cell['teacherName'] ?? 'Unassigned');
                    $teacherLoad[$teacherName] = ($teacherLoad[$teacherName] ?? 0) + 1;
                }
            }
            if ($classLabel) {
                $classCoverage[] = ['className' => $classLabel, 'scheduled' => $scheduled];
            }
        }

        arsort($teacherLoad);

        return [
            'teacherCount' => $teachers->count() ?: (int) ($metrics['teacherCount'] ?? count($routine?->teachers ?? [])),
            'sectionCount' => $sections->count() ?: count($routine?->generated_grid ?? []),
            'routineCount' => Routine::query()->when($routine?->institution_id, fn ($query) => $query->where('institution_id', $routine->institution_id))->count(),
            'assignedSlots' => $assignedSlots,
            'unallocated' => $unallocated,
            'coverage' => $coverage,
            'teacherLoad' => collect($teacherLoad)->take(8)->map(fn ($count, $teacher) => ['teacher' => $teacher, 'count' => $count])->values()->all(),
            'classCoverage' => collect($classCoverage)->sortBy('className')->values()->all(),
            'unallocatedItems' => collect($metrics['unallocated'] ?? [])->take(8)->values()->all(),
        ];
    }

    private function proxyAnalytics(Collection $proxyRuns): array
    {
        $resolved = 0;
        $unresolved = 0;
        $manualOrProxy = 0;
        $swapCount = 0;
        $teacherLoad = [];
        $absences = [];

        foreach ($proxyRuns as $run) {
            $metrics = $run->metrics ?? [];
            $resolved += (int) ($metrics['resolved'] ?? 0);
            $unresolved += (int) ($metrics['unresolved'] ?? 0);
            $swapCount += (int) ($metrics['swapCount'] ?? 0);
            $manualOrProxy += (int) ($metrics['proxyCount'] ?? 0);

            foreach ($run->absent_teachers ?? [] as $teacher) {
                $name = $teacher['teacherName'] ?? $teacher['name'] ?? 'Teacher';
                $absences[$name] = ($absences[$name] ?? 0) + count($teacher['periodKeys'] ?? [1]);
            }

            foreach ($run->assignments ?? [] as $period) {
                foreach ($period['items'] ?? [] as $item) {
                    if (($item['status'] ?? '') !== 'resolved') {
                        $unresolved++;
                        continue;
                    }
                    $teacher = $item['assignedTeacher'] ?? 'Unassigned';
                    $teacherLoad[$teacher] = ($teacherLoad[$teacher] ?? 0) + 1;
                }
            }
        }

        $total = $resolved + $unresolved;

        return [
            'runs' => $proxyRuns->count(),
            'approvedRuns' => $proxyRuns->where('status', 'Approved')->count(),
            'resolved' => $resolved,
            'unresolved' => $unresolved,
            'proxyClasses' => $manualOrProxy,
            'swapCount' => $swapCount,
            'resolutionRate' => $total ? round(($resolved / $total) * 100) : 0,
            'teacherLoad' => collect($teacherLoad)->sortDesc()->take(8)->map(fn ($count, $teacher) => ['teacher' => $teacher, 'count' => $count])->values()->all(),
            'absenceLoad' => collect($absences)->sortDesc()->take(8)->map(fn ($count, $teacher) => ['teacher' => $teacher, 'count' => $count])->values()->all(),
            'recentRuns' => $proxyRuns->take(6)->map(fn (ProxyRun $run) => [
                'name' => $run->name,
                'date' => $run->date?->format('d/m/y') ?? 'No date',
                'status' => $run->status,
                'resolved' => (int) ($run->metrics['resolved'] ?? 0),
                'unresolved' => (int) ($run->metrics['unresolved'] ?? 0),
            ])->values()->all(),
        ];
    }

    private function leaveAnalytics(Collection $leaves, Collection $teachers, Carbon $from, Carbon $today): array
    {
        $approved = $leaves->where('status', 'approved');
        $pending = $leaves->where('status', 'pending')->count();
        $typeBreakdown = $leaves
            ->groupBy('type')
            ->map(fn ($items, $type) => ['type' => ucfirst(str_replace('_', ' ', $type)), 'count' => $items->count()])
            ->values()
            ->all();

        $daily = collect(CarbonPeriod::create($from, $today))
            ->map(function (Carbon $date) use ($approved) {
                $count = $approved->filter(fn (LeaveRequest $leave) => $leave->start_date <= $date && $leave->end_date >= $date)->count();
                return ['day' => $date->format('d M'), 'count' => $count];
            })
            ->values()
            ->all();

        $teacherLeave = $approved
            ->groupBy('teacher_name')
            ->map(fn ($items, $teacher) => ['teacher' => $teacher, 'days' => $items->sum('days')])
            ->sortByDesc('days')
            ->take(8)
            ->values()
            ->all();

        return [
            'approvedDays' => (int) $approved->sum('days'),
            'pending' => $pending,
            'proxyRelevant' => $leaves->where('proxy_relevant', true)->count(),
            'teacherCount' => $teachers->count(),
            'daily' => $daily,
            'typeBreakdown' => $typeBreakdown,
            'teacherLeave' => $teacherLeave,
        ];
    }

    private function noticeAnalytics(Collection $notices, int $teacherCount): array
    {
        $acknowledged = $notices->sum(fn (Notice $notice) => count($notice->acknowledged_by ?? []));
        $target = max(1, $notices->count() * max(1, $teacherCount));

        return [
            'posted' => $notices->count(),
            'urgent' => $notices->filter(fn (Notice $notice) => strtolower($notice->urgency) === 'urgent')->count(),
            'reads' => $notices->sum('read_count'),
            'acknowledged' => $acknowledged,
            'ackRate' => round(($acknowledged / $target) * 100),
            'byBoard' => $notices->groupBy('board')->map(fn ($items, $board) => ['board' => ucfirst($board), 'count' => $items->count()])->values()->all(),
        ];
    }

    private function examAnalytics(Collection $schedules): array
    {
        $active = $schedules->firstWhere('status', 'Active');
        $scheduledGroups = $active ? $this->examGroupCount($active) : 0;

        return [
            'active' => $active ? [
                'name' => $active->name,
                'dateRange' => $active->start_date && $active->end_date ? $active->start_date->format('d/m/y').' - '.$active->end_date->format('d/m/y') : 'Dates not set',
                'halls' => count($active->halls ?? []),
            ] : null,
            'drafts' => $schedules->where('status', 'Draft')->count(),
            'scheduledGroups' => $scheduledGroups,
            'totalSchedules' => $schedules->count(),
        ];
    }

    private function examGroupCount(ExamSchedule $schedule): int
    {
        return collect($schedule->exam_grid ?? [])
            ->flatMap(fn ($dates) => collect($dates)->flatMap(fn ($slots) => collect($slots)->filter()))
            ->sum(fn ($cell) => count($cell['exams'] ?? (($cell['subject'] ?? null) ? [1] : [])));
    }
}
