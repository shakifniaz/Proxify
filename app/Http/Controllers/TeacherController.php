<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\ExamSchedule;
use App\Models\LeaveRequest;
use App\Models\ProxyRun;
use App\Models\Routine;
use App\Models\TeacherProfile;
use App\Models\TeacherLeaveAllowance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(Request $request): Response
    {
        $institutionId = $this->institutionId($request);

        return Inertia::render('Teachers/Index', [
            'teachers' => TeacherProfile::query()
                ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (TeacherProfile $teacher) => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'phone' => $teacher->whatsapp_number ?? '',
                    'initials' => $this->initials($teacher->name),
                    'avatarColor' => 'emerald',
                    'subject' => 'Set in routines',
                    'joinCode' => $teacher->join_code,
                    'linked' => (bool) $teacher->user_id,
                    'proxyLoadThisMonth' => 0,
                    'leaveUsedDays' => 0,
                    'status' => $teacher->status,
                    'role' => 'Teacher',
                ])
                ->values(),
            'subjectOptions' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        TeacherProfile::create([
            'institution_id' => $this->institutionId($request),
            'name' => $data['name'],
            'whatsapp_number' => $data['phone'] ?? null,
            'join_code' => $this->uniqueCode(TeacherProfile::class, 'TCH'),
            'sort_order' => TeacherProfile::where('institution_id', $this->institutionId($request))->count(),
        ]);

        return back()->with('success', 'Teacher added with signup code.');
    }

    public function update(Request $request, TeacherProfile $teacher): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($teacher->institution_id === $this->institutionId($request), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $oldName = $teacher->name;
        $newName = $data['name'];
        $phone = $data['phone'] ?? null;

        DB::transaction(function () use ($teacher, $data, $oldName, $newName, $phone): void {
            $teacher->update([
                'name' => $newName,
                'whatsapp_number' => $phone,
                'status' => $data['status'] ?? $teacher->status,
            ]);

            if ($teacher->user) {
                $teacher->user->update([
                    'name' => $newName,
                    'phone' => $phone,
                ]);
            }

            $this->syncTeacherNameAcrossSnapshots($teacher, $oldName, $newName);
        });

        return back()->with('success', 'Teacher updated.');
    }

    public function destroy(Request $request, TeacherProfile $teacher): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($teacher->institution_id === $this->institutionId($request), 403);

        $name = $teacher->name;
        $teacher->delete();

        return back()->with('success', $name.' deleted.');
    }

    private function institutionId(Request $request): int
    {
        $user = $request->user();
        $id = $user?->institution_id ?? Institution::query()->value('id');

        if (! $id && ($user?->role ?? 'admin') === 'admin') {
            $institution = Institution::create([
                'owner_user_id' => $user->id,
                'name' => 'My Institution',
                'email' => $user->email,
            ]);

            $user->update(['institution_id' => $institution->id]);
            $id = $institution->id;
        }

        abort_unless($id, 422, 'Create an institution before adding teachers.');

        return (int) $id;
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(($request->user()?->role ?? 'admin') === 'admin', 403);
    }

    private function uniqueCode(string $model, string $prefix): string
    {
        do {
            $code = $prefix.'-'.Str::upper(Str::random(6));
        } while ($model::where('join_code', $code)->exists());

        return $code;
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');
    }

    private function syncTeacherNameAcrossSnapshots(TeacherProfile $teacher, string $oldName, string $newName): void
    {
        $teacherId = (string) $teacher->id;

        Routine::query()
            ->where('institution_id', $teacher->institution_id)
            ->get()
            ->each(function (Routine $routine) use ($teacherId, $oldName, $newName): void {
                $fields = [
                    'classes',
                    'teachers',
                    'generated_grid',
                    'teacher_schedule',
                    'generation_rules',
                ];

                $updates = [];

                foreach ($fields as $field) {
                    $value = $routine->{$field};

                    if (! is_array($value)) {
                        continue;
                    }

                    if ($this->replaceTeacherNameInSnapshot($value, $teacherId, $oldName, $newName)) {
                        $updates[$field] = $value;
                    }
                }

                if ($updates) {
                    $routine->update($updates);
                }
            });

        LeaveRequest::query()
            ->where('teacher_id', $teacher->id)
            ->update(['teacher_name' => $newName]);

        TeacherLeaveAllowance::query()
            ->where('teacher_id', $teacher->id)
            ->update(['teacher_name' => $newName]);

        ProxyRun::query()
            ->whereHas('routine', fn ($query) => $query->where('institution_id', $teacher->institution_id))
            ->get()
            ->each(function (ProxyRun $proxyRun) use ($teacherId, $oldName, $newName): void {
                $fields = [
                    'absent_teachers',
                    'subject_groups',
                    'assignments',
                    'adjustments',
                    'proxy_generated_grid',
                    'proxy_teacher_schedule',
                    'metrics',
                ];

                $updates = [];

                foreach ($fields as $field) {
                    $value = $proxyRun->{$field};

                    if (! is_array($value)) {
                        continue;
                    }

                    if ($this->replaceTeacherNameInSnapshot($value, $teacherId, $oldName, $newName)) {
                        $updates[$field] = $value;
                    }
                }

                if ($updates) {
                    $proxyRun->update($updates);
                }
            });

        ExamSchedule::query()
            ->where('institution_id', $teacher->institution_id)
            ->get()
            ->each(function (ExamSchedule $schedule) use ($oldName, $newName): void {
                $fields = [
                    'invigilator_options',
                    'exam_grid',
                ];

                $updates = [];

                foreach ($fields as $field) {
                    $value = $schedule->{$field};

                    if (! is_array($value)) {
                        continue;
                    }

                    if ($this->replaceExactNameInSnapshot($value, $oldName, $newName)) {
                        $updates[$field] = $value;
                    }
                }

                if ($updates) {
                    $schedule->update($updates);
                }
            });
    }

    private function replaceTeacherNameInSnapshot(array &$value, string $teacherId, string $oldName, string $newName): bool
    {
        $changed = false;

        if (array_is_list($value)) {
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $changed = $this->replaceTeacherNameInSnapshot($item, $teacherId, $oldName, $newName) || $changed;
                }
            }

            return $changed;
        }

        $idMatches = isset($value['id']) && (string) $value['id'] === $teacherId;
        $teacherIdMatches = isset($value['teacherId']) && (string) $value['teacherId'] === $teacherId;
        $classTeacherIdMatches = isset($value['classTeacherId']) && (string) $value['classTeacherId'] === $teacherId;
        $proxyForTeacherIdMatches = isset($value['proxyForTeacherId']) && (string) $value['proxyForTeacherId'] === $teacherId;

        if ($idMatches && isset($value['name']) && $value['name'] !== $newName) {
            $value['name'] = $newName;
            $changed = true;
        }

        if ($teacherIdMatches && isset($value['teacherName']) && $value['teacherName'] !== $newName) {
            $value['teacherName'] = $newName;
            $changed = true;
        }

        if ($classTeacherIdMatches && isset($value['classTeacherName']) && $value['classTeacherName'] !== $newName) {
            $value['classTeacherName'] = $newName;
            $changed = true;
        }

        if ($proxyForTeacherIdMatches && isset($value['proxyForTeacherName']) && $value['proxyForTeacherName'] !== $newName) {
            $value['proxyForTeacherName'] = $newName;
            $changed = true;
        }

        if (isset($value['teacher_id']) && (string) $value['teacher_id'] === $teacherId && isset($value['teacher_name']) && $value['teacher_name'] !== $newName) {
            $value['teacher_name'] = $newName;
            $changed = true;
        }

        if (isset($value['coTeacherIds'], $value['coTeacherNames']) && is_array($value['coTeacherIds']) && is_array($value['coTeacherNames'])) {
            foreach ($value['coTeacherIds'] as $index => $id) {
                if ((string) $id === $teacherId && ($value['coTeacherNames'][$index] ?? null) !== $newName) {
                    $value['coTeacherNames'][$index] = $newName;
                    $changed = true;
                }
            }
        }

        foreach ($value as $key => &$item) {
            if (is_array($item)) {
                $changed = $this->replaceTeacherNameInSnapshot($item, $teacherId, $oldName, $newName) || $changed;
            } elseif (is_string($item) && $item === $oldName && $this->looksLikeTeacherNameField((string) $key)) {
                $item = $newName;
                $changed = true;
            }
        }

        return $changed;
    }

    private function looksLikeTeacherNameField(string $key): bool
    {
        return in_array($key, [
            'name',
            'teacher',
            'teacherName',
            'teacher_name',
            'classTeacherName',
            'proxyForTeacherName',
        ], true);
    }

    private function replaceExactNameInSnapshot(array &$value, string $oldName, string $newName): bool
    {
        $changed = false;

        foreach ($value as &$item) {
            if (is_array($item)) {
                $changed = $this->replaceExactNameInSnapshot($item, $oldName, $newName) || $changed;
            } elseif (is_string($item) && $item === $oldName) {
                $item = $newName;
                $changed = true;
            }
        }

        return $changed;
    }
}
