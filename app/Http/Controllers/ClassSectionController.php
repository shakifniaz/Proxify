<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\TeacherProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClassSectionController extends Controller
{
    public function index(Request $request): Response
    {
        $institutionId = $this->institutionId($request);
        $role = $request->user()?->role ?? 'admin';
        $classSectionId = $request->user()?->class_section_id;

        $sections = ClassSection::query()
            ->with('classTeacher:id,name')
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->when($role === 'student' && $classSectionId, fn ($query) => $query->whereKey($classSectionId))
            ->orderBy('sort_order')
            ->orderBy('class_name')
            ->orderBy('section_name')
            ->get();

        return Inertia::render('Classrooms/Index', [
            'classSections' => $sections->map(fn (ClassSection $section) => [
                'id' => $section->id,
                'className' => $section->class_name,
                'sectionName' => $section->section_name,
                'label' => $section->class_name.' ('.$this->sectionShortName($section->section_name).')',
                'joinCode' => $section->join_code,
                'classTeacherId' => $section->class_teacher_profile_id,
                'classTeacherName' => $section->classTeacher?->name ?? 'Unassigned',
                'subjects' => $section->subjects ?? [],
            ])->values(),
            'teachers' => TeacherProfile::query()
                ->where('institution_id', $institutionId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values(),
            'canManageClasses' => $role === 'admin',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $institutionId = $this->institutionId($request);

        $data = $request->validate([
            'className' => ['required', 'string', 'max:80'],
            'sectionName' => ['required', 'string', 'max:80'],
            'classTeacherId' => ['nullable', 'integer', 'exists:teacher_profiles,id'],
        ]);

        if (! empty($data['classTeacherId'])) {
            abort_unless(
                TeacherProfile::where('institution_id', $institutionId)->whereKey($data['classTeacherId'])->exists(),
                403
            );
        }

        ClassSection::create([
            'institution_id' => $institutionId,
            'class_teacher_profile_id' => $data['classTeacherId'] ?? null,
            'class_name' => $data['className'],
            'section_name' => $data['sectionName'],
            'join_code' => $this->uniqueCode(),
            'sort_order' => ClassSection::where('institution_id', $institutionId)->count(),
            'subjects' => [],
        ]);

        return back()->with('success', 'Class section added with student signup code.');
    }

    public function update(Request $request, ClassSection $classSection): RedirectResponse
    {
        $this->ensureAdmin($request);
        $institutionId = $this->institutionId($request);
        abort_unless($classSection->institution_id === $institutionId, 403);

        $data = $request->validate([
            'className' => ['required', 'string', 'max:80'],
            'sectionName' => ['required', 'string', 'max:80'],
            'classTeacherId' => ['nullable', 'integer', 'exists:teacher_profiles,id'],
        ]);

        if (! empty($data['classTeacherId'])) {
            abort_unless(
                TeacherProfile::where('institution_id', $institutionId)->whereKey($data['classTeacherId'])->exists(),
                403
            );
        }

        $classSection->update([
            'class_teacher_profile_id' => $data['classTeacherId'] ?? null,
            'class_name' => $data['className'],
            'section_name' => $data['sectionName'],
        ]);

        return back()->with('success', 'Class section updated.');
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

        abort_unless($id, 422, 'Create an institution before adding classes.');

        return (int) $id;
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(($request->user()?->role ?? 'admin') === 'admin', 403);
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'CLS-'.Str::upper(Str::random(6));
        } while (ClassSection::where('join_code', $code)->exists());

        return $code;
    }

    private function sectionShortName(string $name): string
    {
        return trim(str_ireplace('section', '', $name)) ?: $name;
    }
}
