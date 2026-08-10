<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\Routine;
use App\Models\TeacherLeaveAllowance;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) now()->format('Y');
        $institutionId = $this->institutionId($request);
        $routine = $this->activeRoutine($institutionId);
        $query = LeaveRequest::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->latest();

        if (strtolower($request->user()?->role ?? 'admin') === 'teacher') {
            $teacherId = (string) ($request->user()?->teacher_profile_id ?? '');
            $query->where('teacher_id', $teacherId);
        }

        $requests = $query->get();
        $requestsByTeacher = $requests->groupBy('teacher_id');
        $allowances = collect($routine?->teachers ?? [])->map(function (array $teacher, int $index) use ($routine, $year, $requestsByTeacher) {
            $teacherId = (string) ($teacher['id'] ?? $index + 1);
            $teacherName = trim((string) ($teacher['name'] ?? 'Teacher '.($index + 1)));
            $subjects = array_values($teacher['primarySubjects'] ?? $teacher['subjects'] ?? []);
            $record = TeacherLeaveAllowance::firstOrCreate(
                ['routine_id' => $routine->id, 'teacher_id' => $teacherId, 'year' => $year],
                ['teacher_name' => $teacherName, 'max_leaves' => 12]
            );
            $teacherRequests = $requestsByTeacher->get($teacherId, collect());

            return [
                'id' => $teacherId,
                'routineId' => $routine->id,
                'teacher' => $teacherName,
                'subject' => $teacher['subjectHint'] ?? implode(', ', array_slice($subjects, 0, 3)),
                'maxLeaves' => $record->max_leaves,
                'used' => $teacherRequests->where('status', 'approved')->sum('days'),
                'pending' => $teacherRequests->where('status', 'pending')->count(),
                'paid' => $teacherRequests->where('status', 'approved')->where('type', 'Paid leave')->sum('days'),
                'casual' => $teacherRequests->where('status', 'approved')->where('type', 'Casual leave')->sum('days'),
                'unpaid' => $teacherRequests->where('status', 'approved')->where('type', 'Unpaid leave')->sum('days'),
                'discretionary' => $teacherRequests->where('status', 'approved')->where('type', 'Discretionary leave')->sum('days'),
            ];
        })->values();

        return Inertia::render('LeaveRequests/Index', [
            'requests' => $requests->map(fn (LeaveRequest $leave) => $this->payload($leave, $requestsByTeacher->get($leave->teacher_id, collect())))->values(),
            'leaveBalances' => $allowances,
            'typeOptions' => ['Paid leave', 'Casual leave', 'Unpaid leave', 'Discretionary leave'],
            'year' => $year,
            'activeRoutineName' => $routine?->name,
            'routinePeriods' => $routine?->periods ?? [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'routineId' => ['nullable', 'integer', 'exists:routines,id'],
            'teacherId' => ['nullable', 'string', 'max:80'],
            'teacherName' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:180'],
            'type' => ['required', 'string', 'max:80'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'duration' => ['required', 'string', 'max:80'],
            'periods' => ['required', 'array', 'min:1'],
            'periods.*' => ['string', 'max:40'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $institutionId = $this->institutionId($request);
        $routine = ! empty($data['routineId'])
            ? Routine::find($data['routineId'])
            : $this->activeRoutine($institutionId);
        $isAdmin = strtolower($request->user()?->role ?? 'admin') === 'admin';
        $teacherId = $isAdmin ? (string) ($data['teacherId'] ?? '') : (string) ($request->user()?->teacher_profile_id ?? '');
        $teacherName = $isAdmin ? (string) ($data['teacherName'] ?? '') : (string) ($request->user()?->name ?? '');

        abort_unless($teacherId !== '' && $teacherName !== '', 422, 'Select a teacher before creating a leave request.');

        $start = Carbon::parse($data['startDate']);
        $end = Carbon::parse($data['endDate']);

        LeaveRequest::create([
            'institution_id' => $institutionId,
            'routine_id' => $routine?->id,
            'user_id' => $request->user()?->id,
            'teacher_id' => $teacherId,
            'teacher_name' => $teacherName,
            'subject' => $data['subject'] ?? null,
            'type' => $data['type'],
            'start_date' => $start,
            'end_date' => $end,
            'days' => $start->diffInDays($end) + 1,
            'duration' => $data['duration'],
            'periods' => array_values($data['periods']),
            'reason' => $data['reason'],
            'status' => 'pending',
            'proxy_relevant' => false,
        ]);

        return back()->with('success', 'Leave request submitted.');
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless(strtolower($request->user()?->role ?? 'admin') === 'admin', 403);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
        ]);

        $leaveRequest->update([
            'status' => $data['status'],
            'proxy_relevant' => $data['status'] === 'approved',
            'reviewed_at' => $data['status'] === 'pending' ? null : now(),
            'reviewed_by' => $data['status'] === 'pending' ? null : $request->user()?->id,
        ]);

        return back()->with('success', 'Leave request updated.');
    }

    public function updateAllowance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'routineId' => ['required', 'integer', 'exists:routines,id'],
            'teacherId' => ['required', 'string', 'max:80'],
            'teacherName' => ['required', 'string', 'max:120'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'maxLeaves' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        TeacherLeaveAllowance::updateOrCreate(
            ['routine_id' => $data['routineId'], 'teacher_id' => $data['teacherId'], 'year' => $data['year']],
            ['teacher_name' => $data['teacherName'], 'max_leaves' => $data['maxLeaves']]
        );

        return back()->with('success', 'Leave allowance updated.');
    }

    private function payload(LeaveRequest $leave, $teacherRequests): array
    {
        $used = $teacherRequests->where('status', 'approved')->sum('days');
        $pending = $teacherRequests->where('status', 'pending')->count();
        $maxLeaves = optional(TeacherLeaveAllowance::where('routine_id', $leave->routine_id)->where('teacher_id', $leave->teacher_id)->where('year', (int) $leave->start_date->format('Y'))->first())->max_leaves ?? 12;

        return [
            'id' => $leave->id,
            'teacherId' => $leave->teacher_id,
            'teacherName' => $leave->teacher_name,
            'initials' => $this->initials($leave->teacher_name),
            'subject' => $leave->subject ?? '',
            'type' => $leave->type,
            'dateRange' => $leave->start_date->format('d/m/y').' - '.$leave->end_date->format('d/m/y'),
            'days' => $leave->days,
            'duration' => $leave->duration,
            'status' => $leave->status,
            'reason' => $leave->reason,
            'submittedAt' => $leave->created_at?->diffForHumans() ?? '',
            'proxyRelevant' => $leave->proxy_relevant,
            'periods' => $leave->periods ?? [],
            'leaveStats' => [
                'maxLeaves' => $maxLeaves,
                'used' => $used,
                'pending' => $pending,
                'remaining' => max(0, $maxLeaves - $used),
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

    private function institutionId(Request $request): ?int
    {
        return $request->user()?->institution_id;
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
