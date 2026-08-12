<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\ExamSchedule;
use App\Models\LeaveRequest;
use App\Models\Notice;
use App\Models\ProxyRun;
use App\Models\Routine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationCenter
{
    public function payload(Request $request): array
    {
        $items = $this->items($request)
            ->sortByDesc(fn (array $item) => $item['timestamp'])
            ->take(24)
            ->values();
        $readKeys = $this->readKeys($request, $items->pluck('key')->all());

        $items = $items
            ->map(fn (array $item) => [
                ...$item,
                'read' => in_array($item['key'], $readKeys, true),
            ])
            ->values();

        return [
            'items' => $items,
            'unreadCount' => $items->where('read', false)->count(),
            'classroomContext' => $this->classroomContext($request),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    public function markRead(Request $request, array $keys): void
    {
        $userId = $request->user()?->id;
        if (! $userId) {
            return;
        }

        collect($keys)
            ->filter(fn ($key) => is_string($key) && trim($key) !== '')
            ->unique()
            ->take(80)
            ->each(fn (string $key) => DB::table('notification_reads')->updateOrInsert(
                ['user_id' => $userId, 'notification_key' => $key],
                ['read_at' => now(), 'created_at' => now(), 'updated_at' => now()]
            ));
    }

    private function items(Request $request): Collection
    {
        $user = $request->user();
        $role = strtolower($user?->role ?? 'admin');
        $institutionId = $user?->institution_id;
        $routine = $this->activeRoutine($institutionId);

        return collect()
            ->merge($this->noticeItems($role, $institutionId))
            ->merge($this->leaveItems($request))
            ->merge($this->proxyItems($request, $routine))
            ->merge($this->examItems($request))
            ->merge($this->adminDirectoryItems($request));
    }

    private function noticeItems(string $role, ?int $institutionId): Collection
    {
        $visibility = match ($role) {
            'student' => ['All'],
            'teacher' => ['All', 'Teachers'],
            default => ['All', 'Teachers', 'Admins'],
        };

        return Notice::query()
            ->with('user:id,name')
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
            ->map(fn (Notice $notice) => $this->item(
                key: "notice:{$notice->id}:{$notice->updated_at?->timestamp}",
                type: 'notice',
                title: $notice->title,
                message: str($notice->message)->limit(120)->toString(),
                href: '/noticeboard',
                at: $notice->created_at,
                tone: $notice->urgency === 'Urgent' ? 'red' : ($notice->urgency === 'Important' ? 'amber' : 'green'),
                eyebrow: $this->titleText($notice->board).' notice',
            ));
    }

    private function leaveItems(Request $request): Collection
    {
        $user = $request->user();
        $role = strtolower($user?->role ?? 'admin');
        $institutionId = $user?->institution_id;

        if ($role === 'admin') {
            return LeaveRequest::query()
                ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
                ->where('status', 'pending')
                ->latest()
                ->take(8)
                ->get()
                ->map(fn (LeaveRequest $leave) => $this->item(
                    key: "leave:pending:{$leave->id}:{$leave->updated_at?->timestamp}",
                    type: 'leave',
                    title: $leave->teacher_name.' requested leave',
                    message: $leave->type.' for '.$leave->start_date?->format('M j').' - '.$leave->end_date?->format('M j'),
                    href: '/leave-requests',
                    at: $leave->created_at,
                    tone: 'amber',
                    eyebrow: 'Needs approval',
                ));
        }

        if ($role !== 'teacher') {
            return collect();
        }

        $teacherId = (string) ($user?->teacher_profile_id ?? '');

        return LeaveRequest::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('teacher_id', $teacherId)
            ->whereIn('status', ['approved', 'rejected'])
            ->whereNotNull('reviewed_at')
            ->latest('reviewed_at')
            ->take(6)
            ->get()
            ->map(fn (LeaveRequest $leave) => $this->item(
                key: "leave:reviewed:{$leave->id}:{$leave->updated_at?->timestamp}",
                type: 'leave',
                title: 'Leave request '.$this->titleText($leave->status),
                message: $leave->type.' for '.$leave->start_date?->format('M j').' - '.$leave->end_date?->format('M j'),
                href: '/leave-requests',
                at: $leave->reviewed_at,
                tone: $leave->status === 'approved' ? 'green' : 'red',
                eyebrow: 'Leave update',
            ));
    }

    private function proxyItems(Request $request, ?Routine $routine): Collection
    {
        $user = $request->user();
        $role = strtolower($user?->role ?? 'admin');
        $institutionId = $user?->institution_id;

        if ($role === 'admin') {
            return ProxyRun::query()
                ->whereHas('routine', fn ($query) => $query->when($institutionId, fn ($inner) => $inner->where('institution_id', $institutionId)))
                ->whereIn('status', ['Draft', 'Approved'])
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (ProxyRun $run) => $this->item(
                    key: "proxy:admin:{$run->id}:{$run->updated_at?->timestamp}",
                    type: 'proxy',
                    title: $run->status === 'Approved' ? 'Substitution plan approved' : 'Substitution plan ready for review',
                    message: $run->name.' for '.$run->day_label,
                    href: '/proxy-manager',
                    at: $run->approved_at ?? $run->updated_at,
                    tone: $run->status === 'Approved' ? 'green' : 'amber',
                    eyebrow: 'Class Coverage',
                ));
        }

        if ($role !== 'teacher' || ! $routine) {
            return collect();
        }

        $teacherId = (string) ($user?->teacher_profile_id ?? '');
        $teacherName = $user?->teacherProfile?->name ?? $user?->name ?? '';

        return ProxyRun::query()
            ->where('routine_id', $routine->id)
            ->where('status', 'Approved')
            ->latest('approved_at')
            ->take(8)
            ->get()
            ->flatMap(function (ProxyRun $run) use ($teacherId, $teacherName) {
                return collect($this->flattenAssignments($run->assignments ?? []))
                    ->filter(function ($assignment) use ($teacherId, $teacherName) {
                        return ($teacherId !== '' && (string) ($assignment['assignedTeacherId'] ?? '') === $teacherId)
                            || ($teacherName !== '' && strcasecmp((string) ($assignment['assignedTeacher'] ?? ''), $teacherName) === 0);
                    })
                    ->take(4)
                    ->map(fn ($assignment, int $index) => $this->item(
                        key: "proxy:teacher:{$run->id}:{$index}:{$run->updated_at?->timestamp}",
                        type: 'proxy',
                        title: 'New substitution assignment',
                        message: trim(($assignment['subject'] ?? 'Class').' - '.($assignment['sectionLabel'] ?? $assignment['classLabel'] ?? '').' '.$run->day_label),
                        href: '/proxy-manager',
                        at: $run->approved_at ?? $run->updated_at,
                        tone: 'green',
                        eyebrow: 'Substitution duty',
                    ));
            });
    }

    private function examItems(Request $request): Collection
    {
        $user = $request->user();
        $role = strtolower($user?->role ?? 'admin');
        $institutionId = $user?->institution_id;

        return ExamSchedule::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('status', 'Active')
            ->latest()
            ->take(2)
            ->get()
            ->map(fn (ExamSchedule $exam) => $this->item(
                key: "exam:{$exam->id}:{$exam->updated_at?->timestamp}:{$role}",
                type: 'exam',
                title: 'Active exam schedule',
                message: trim($exam->name.' '.($exam->start_date ? 'starts '.$exam->start_date->format('M j') : '')),
                href: '/exam-schedule',
                at: $exam->updated_at,
                tone: 'blue',
                eyebrow: 'Exam schedule',
            ));
    }

    private function adminDirectoryItems(Request $request): Collection
    {
        $user = $request->user();
        if (strtolower($user?->role ?? 'admin') !== 'admin') {
            return collect();
        }

        $institutionId = $user?->institution_id;
        $recentStudents = \App\Models\User::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('role', 'student')
            ->latest()
            ->take(3)
            ->get()
            ->map(fn ($student) => $this->item(
                key: "student:new:{$student->id}:{$student->created_at?->timestamp}",
                type: 'directory',
                title: 'Student joined',
                message: $student->name.' registered for classroom access.',
                href: '/classrooms',
                at: $student->created_at,
                tone: 'blue',
                eyebrow: 'Directory',
            ));

        return $recentStudents;
    }

    private function classroomContext(Request $request): array
    {
        $user = $request->user();
        $role = strtolower($user?->role ?? 'admin');
        $institutionId = $user?->institution_id;
        $routine = $this->activeRoutine($institutionId);
        $sectionIds = collect();
        $teacherId = (string) ($user?->teacher_profile_id ?? '');
        $teacherName = $user?->teacherProfile?->name ?? $user?->name ?? null;

        if ($role === 'admin') {
            $sectionIds = ClassSection::query()
                ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
                ->pluck('id')
                ->map(fn ($id) => (string) $id);
        } elseif ($role === 'teacher' && $routine) {
            $sectionIds = collect($routine->generated_grid ?? [])
                ->filter(function ($section) use ($teacherId, $teacherName) {
                    return collect($section['days'] ?? [])->flatten(1)->contains(function ($cell) use ($teacherId, $teacherName) {
                        return ($teacherId !== '' && (string) ($cell['teacherId'] ?? '') === $teacherId)
                            || ($teacherName && strcasecmp((string) ($cell['teacherName'] ?? ''), $teacherName) === 0);
                    });
                })
                ->map(fn ($section) => (string) ($section['sectionId'] ?? $section['id'] ?? ''))
                ->merge(ClassSection::query()
                    ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
                    ->where('class_teacher_profile_id', $teacherId)
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id));
        } elseif ($role === 'student' && $user?->class_section_id) {
            $sectionIds = collect([(string) $user->class_section_id]);
        }

        return [
            'enabled' => true,
            'role' => $role,
            'institutionId' => $institutionId ? (string) $institutionId : 'global',
            'sectionIds' => $sectionIds->filter()->unique()->values()->all(),
            'userKey' => $role.'-'.($user?->id ?? 'guest'),
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

    private function flattenAssignments(array $assignments): array
    {
        if (isset($assignments[0]['items'])) {
            return collect($assignments)->flatMap(fn ($group) => $group['items'] ?? [])->values()->all();
        }

        return $assignments;
    }

    private function readKeys(Request $request, array $keys): array
    {
        $userId = $request->user()?->id;
        if (! $userId || ! $keys) {
            return [];
        }

        return DB::table('notification_reads')
            ->where('user_id', $userId)
            ->whereIn('notification_key', $keys)
            ->pluck('notification_key')
            ->all();
    }

    private function item(string $key, string $type, string $title, string $message, string $href, ?Carbon $at, string $tone, string $eyebrow): array
    {
        $at ??= now();

        return [
            'key' => $key,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'href' => $href,
            'time' => $at->diffForHumans(),
            'timestamp' => $at->timestamp,
            'tone' => $tone,
            'eyebrow' => $eyebrow,
        ];
    }

    private function titleText(?string $value): string
    {
        return str_replace('_', ' ', ucfirst((string) $value));
    }
}
