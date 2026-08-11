<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\ExamSchedule;
use App\Models\Institution;
use App\Models\LeaveRequest;
use App\Models\Notice;
use App\Models\ProxyMessageLog;
use App\Models\ProxyRun;
use App\Models\ProxySubjectGroup;
use App\Models\Routine;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = strtolower($user?->role ?? 'admin');
        $institution = $this->institution($request, $role === 'admin');
        $institutionId = $institution?->id;
        $institutionSettings = $institution?->settings ?? [];

        return Inertia::render('Settings/Index', [
            'role' => $role,
            'general' => [
                'schoolName' => $institution?->name ?? '',
                'shortName' => $institution?->short_name ?? '',
                'contactPhone' => $institution?->phone ?? '',
                'contactEmail' => $institution?->email ?? '',
                'address' => $institution?->address ?? '',
                'academicYear' => $institution?->academic_year ?? '',
                'defaultNoticeVisibility' => $institutionSettings['defaultNoticeVisibility'] ?? 'Teachers',
            ],
            'profile' => [
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
                'phone' => $user?->phone ?? '',
            ],
            'notificationSettings' => [
                'whatsappRoutineUpdates' => (bool) (($user?->settings ?? [])['whatsappRoutineUpdates'] ?? true),
            ],
            'noticeVisibilityOptions' => ['All', 'Teachers', 'Admins'],
            'cleanup' => $role === 'admin' ? $this->cleanupPayload($institutionId) : null,
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $institution = $this->institution($request, true);

        $data = $request->validate([
            'schoolName' => ['required', 'string', 'max:255'],
            'shortName' => ['nullable', 'string', 'max:80'],
            'contactPhone' => ['nullable', 'string', 'max:30'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'academicYear' => ['nullable', 'string', 'max:40'],
            'defaultNoticeVisibility' => ['required', Rule::in(['All', 'Teachers', 'Admins'])],
        ]);

        $settings = array_merge($institution->settings ?? [], [
            'defaultNoticeVisibility' => $data['defaultNoticeVisibility'],
        ]);

        $institution->update([
            'name' => $data['schoolName'],
            'short_name' => $data['shortName'] ?? null,
            'phone' => $data['contactPhone'] ?? null,
            'email' => $data['contactEmail'] ?? null,
            'address' => $data['address'] ?? null,
            'academic_year' => $data['academicYear'] ?? null,
            'settings' => $settings,
        ]);

        return back()->with('success', 'Administration settings saved.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsappRoutineUpdates' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->update([
            'settings' => array_merge($user->settings ?? [], [
                'whatsappRoutineUpdates' => (bool) $data['whatsappRoutineUpdates'],
            ]),
        ]);

        return back()->with('success', 'Notification settings saved.');
    }

    public function clearData(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $institutionId = $this->institution($request, true)->id;

        $data = $request->validate([
            'target' => ['required', Rule::in(array_keys($this->cleanupTargets()))],
            'confirmation' => ['required', 'string'],
        ]);

        if ($data['confirmation'] !== 'CLEAR') {
            throw ValidationException::withMessages([
                'confirmation' => 'Type CLEAR to confirm this cleanup.',
            ]);
        }

        $target = $this->cleanupTargets()[$data['target']];
        $deleted = DB::transaction(fn () => $target['delete']($institutionId));

        return back()->with('success', $target['label'].' cleared. '.$deleted.' record'.($deleted === 1 ? '' : 's').' removed.');
    }

    private function cleanupPayload(?int $institutionId): array
    {
        return collect($this->cleanupTargets())
            ->map(fn (array $target, string $key) => [
                'key' => $key,
                'label' => $target['label'],
                'description' => $target['description'],
                'count' => $institutionId ? $target['count']($institutionId) : 0,
            ])
            ->values()
            ->all();
    }

    private function cleanupTargets(): array
    {
        return [
            'classrooms' => [
                'label' => 'Classrooms',
                'description' => 'Deletes class sections, join codes, and subject lists. Student class links are unset by the database.',
                'count' => fn (int $institutionId): int => ClassSection::where('institution_id', $institutionId)->count(),
                'delete' => fn (int $institutionId): int => ClassSection::where('institution_id', $institutionId)->delete(),
            ],
            'students' => [
                'label' => 'Student accounts',
                'description' => 'Deletes student user accounts for this institution. Authored records keep their history without the deleted user link.',
                'count' => fn (int $institutionId): int => User::where('institution_id', $institutionId)->where('role', 'student')->count(),
                'delete' => fn (int $institutionId): int => User::where('institution_id', $institutionId)->where('role', 'student')->delete(),
            ],
            'teachers' => [
                'label' => 'Teachers',
                'description' => 'Deletes teacher profiles and join codes. Linked user accounts stay in place without a teacher profile.',
                'count' => fn (int $institutionId): int => TeacherProfile::where('institution_id', $institutionId)->count(),
                'delete' => fn (int $institutionId): int => TeacherProfile::where('institution_id', $institutionId)->delete(),
            ],
            'routines' => [
                'label' => 'Routines',
                'description' => 'Deletes routines, proxy runs, proxy message logs, and subject grouping attached to those routines.',
                'count' => fn (int $institutionId): int => Routine::where('institution_id', $institutionId)->count(),
                'delete' => function (int $institutionId): int {
                    $routineIds = Routine::where('institution_id', $institutionId)->pluck('id');
                    ProxySubjectGroup::whereIn('routine_id', $routineIds)->delete();

                    return Routine::whereIn('id', $routineIds)->delete();
                },
            ],
            'proxy' => [
                'label' => 'Proxy runs',
                'description' => 'Deletes generated proxy plans and WhatsApp send logs while keeping routines.',
                'count' => fn (int $institutionId): int => ProxyRun::whereHas('routine', fn ($query) => $query->where('institution_id', $institutionId))->count(),
                'delete' => function (int $institutionId): int {
                    $runIds = ProxyRun::whereHas('routine', fn ($query) => $query->where('institution_id', $institutionId))->pluck('id');
                    ProxyMessageLog::whereIn('proxy_run_id', $runIds)->delete();

                    return ProxyRun::whereIn('id', $runIds)->delete();
                },
            ],
            'notices' => [
                'label' => 'Notices',
                'description' => 'Deletes institutional and staff noticeboard posts.',
                'count' => fn (int $institutionId): int => Notice::where('institution_id', $institutionId)->count(),
                'delete' => fn (int $institutionId): int => Notice::where('institution_id', $institutionId)->delete(),
            ],
            'leave_requests' => [
                'label' => 'Leave requests',
                'description' => 'Deletes leave request history and review state.',
                'count' => fn (int $institutionId): int => LeaveRequest::where('institution_id', $institutionId)->count(),
                'delete' => fn (int $institutionId): int => LeaveRequest::where('institution_id', $institutionId)->delete(),
            ],
            'exam_schedules' => [
                'label' => 'Exam schedules',
                'description' => 'Deletes exam plans, halls, invigilators, and exam grid data.',
                'count' => fn (int $institutionId): int => ExamSchedule::where('institution_id', $institutionId)->count(),
                'delete' => fn (int $institutionId): int => ExamSchedule::where('institution_id', $institutionId)->delete(),
            ],
        ];
    }

    private function institution(Request $request, bool $createForAdmin = false): ?Institution
    {
        $user = $request->user();
        $id = $user?->institution_id;

        if ($id) {
            return Institution::find($id);
        }

        if (strtolower($user?->role ?? '') !== 'admin') {
            return null;
        }

        $id = Institution::query()->value('id');

        if (! $id && $createForAdmin) {
            $institution = Institution::create([
                'owner_user_id' => $user->id,
                'name' => 'My Institution',
                'email' => $user->email,
            ]);

            $user->update(['institution_id' => $institution->id]);

            return $institution;
        }

        return $id ? Institution::find($id) : null;
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(strtolower($request->user()?->role ?? 'admin') === 'admin', 403);
    }
}
