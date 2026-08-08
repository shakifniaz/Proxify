<?php

namespace App\Http\Controllers;

use App\Models\ProxyRun;
use App\Models\Routine;
use App\Models\ClassSection;
use App\Models\TeacherProfile;
use App\Services\RoutineGenerator;
use App\Services\RoutineDocxImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoutineController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $role = strtolower($request->query('role') ?? ($request->user()?->role ?? 'admin'));

        if (in_array($role, ['teacher', 'student'], true)) {
            $routine = Routine::where('status', 'Active')->latest()->first();
            return $routine
                ? redirect()->route('routines.show', array_filter(['routine' => $routine->id, 'role' => $request->query('role')]))
                : $this->inactiveRoutineResponse($role);
        }

        return Inertia::render('Routines/Index', [
            'routines' => Routine::query()
                ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
                ->latest()
                ->get()
                ->map(fn (Routine $routine) => [
                'id' => $routine->id,
                'name' => $routine->name,
                'days' => count($routine->days ?? []),
                'classes' => count($routine->classes ?? []),
                'sections' => collect($routine->classes ?? [])->sum(fn ($class) => count($class['sections'] ?? [])),
                'teachers' => count($routine->teachers ?? []),
                'proxyClassesWeek' => $routine->metrics['unallocatedAssignments'] ?? 0,
                'status' => $routine->status,
            ])->values(),
        ]);
    }

    public function create(): Response
    {
        $institutionId = request()->user()?->institution_id;
        $teachers = TeacherProfile::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (TeacherProfile $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'phone' => $teacher->whatsapp_number ?? '',
            ])
            ->values()
            ->all();

        $sections = ClassSection::query()
            ->with('classTeacher:id,name')
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->orderBy('sort_order')
            ->orderBy('class_name')
            ->orderBy('section_name')
            ->get()
            ->groupBy('class_name')
            ->map(fn ($group, $className) => [
                'id' => 'class-'.md5((string) $className),
                'name' => $className,
                'dailyPeriods' => 7,
                'sections' => $group->map(fn (ClassSection $section) => [
                    'id' => $section->id,
                    'name' => $section->section_name,
                    'classTeacherId' => $section->class_teacher_profile_id,
                    'subjects' => ($section->subjects && count($section->subjects)) ? $section->subjects : ['Mathematics', 'English'],
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Routines/Create', [
            'classesConfig' => config('routine_demo.classes_config'),
            'classes' => $sections,
            'teachersConfig' => ['numberOfTeachers' => count($teachers)],
            'teachers' => $teachers,
        ]);
    }

    public function store(Request $request, RoutineGenerator $generator): RedirectResponse
    {
        $data = $this->validatedPayload($request);
        $generated = $generator->generate($data);

        $routine = Routine::create([
            'user_id' => $request->user()?->id,
            'institution_id' => $request->user()?->institution_id,
            'name' => $data['name'],
            'term_label' => $data['termLabel'] ?? null,
            'status' => 'Draft',
            'days' => $generated['days'],
            'periods' => $generated['periods'],
            'classes' => $generated['classes'],
            'teachers' => $generated['teachers'],
            'generation_rules' => $generated['generationRules'],
            'generated_grid' => $generated['generatedGrid'],
            'teacher_schedule' => $generated['teacherSchedule'],
            'metrics' => $generated['metrics'],
        ]);

        return redirect()->route('routines.show', $routine)->with('success', 'Routine generated and saved.');
    }


    public function import(Request $request, RoutineDocxImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $imported = $importer->import($data['file']);

        $routine = Routine::create([
            'user_id' => $request->user()?->id,
            'institution_id' => $request->user()?->institution_id,
            'name' => $imported['name'],
            'term_label' => $imported['termLabel'],
            'status' => 'Draft',
            'days' => $imported['days'],
            'periods' => $imported['periods'],
            'classes' => $imported['classes'],
            'teachers' => $imported['teachers'],
            'generation_rules' => $imported['generationRules'],
            'generated_grid' => $imported['generatedGrid'],
            'teacher_schedule' => $imported['teacherSchedule'],
            'metrics' => $imported['metrics'],
        ]);

        return redirect()->route('routines.show', $routine)->with('success', 'Routine imported from DOCX.');
    }

    public function show(Request $request, Routine $routine): Response|RedirectResponse
    {
        $role = strtolower($request->query('role') ?? ($request->user()?->role ?? 'admin'));
        if (in_array($role, ['teacher', 'student'], true) && $routine->status !== 'Active') {
            $activeRoutine = Routine::where('status', 'Active')->latest()->first();

            return $activeRoutine
                ? redirect()->route('routines.show', ['routine' => $activeRoutine->id])
                : $this->inactiveRoutineResponse($role);
        }

        $days = $routine->days ?? [];
        $activeProxy = ProxyRun::where('routine_id', $routine->id)
            ->where('status', 'Approved')
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->first();
        $generatedGrid = $activeProxy?->proxy_generated_grid ?: ($routine->generated_grid ?? []);
        $teacherSchedule = $activeProxy?->proxy_teacher_schedule ?: ($routine->teacher_schedule ?? []);
        $classes = $routine->classes ?? [];
        $requestUser = $request->user();
        $teacherNames = collect($routine->teachers ?? [])->pluck('name')->filter()->values();
        $currentTeacherName = $role === 'teacher'
            ? ($teacherNames->contains($requestUser?->name) ? $requestUser?->name : ($teacherNames->first() ?? $requestUser?->name ?? ''))
            : '';

        if ($requestUser && $role === 'student' && $requestUser->class_section_id) {
            $sectionId = (string) $requestUser->class_section_id;
            $generatedGrid = collect($generatedGrid)
                ->filter(fn ($section) => (string) ($section['sectionId'] ?? '') === $sectionId)
                ->all();

            $classes = collect($classes)
                ->map(function ($class) use ($sectionId) {
                    $class['sections'] = array_values(array_filter(
                        $class['sections'] ?? [],
                        fn ($section) => (string) ($section['id'] ?? '') === $sectionId
                    ));

                    return $class;
                })
                ->filter(fn ($class) => count($class['sections'] ?? []) > 0)
                ->values()
                ->all();
        }

        return Inertia::render('Routines/Show', [
            'accessRole' => $role,
            'readOnly' => in_array($role, ['teacher', 'student'], true),
            'noActiveRoutine' => false,
            'currentTeacherName' => $currentTeacherName,
            'currentClassLabel' => $role === 'student' ? (collect($generatedGrid)->first()['label'] ?? '') : '',
            'routine' => [
                'id' => $routine->id,
                'name' => $routine->name,
                'term' => $routine->term_label,
                'status' => $routine->status,
            ],
            'days' => $days,
            'periods' => $routine->periods ?? [],
            'legend' => [],
            'classOptions' => collect($generatedGrid)->pluck('label')->values(),
            'teachers' => $teacherSchedule[$days[0] ?? ''] ?? [],
            'teacherSchedule' => $teacherSchedule,
            'classes' => $classes,
            'teacherPool' => $routine->teachers ?? [],
            'generationRules' => $routine->generation_rules ?? [],
            'generatedGrid' => $generatedGrid,
            'metrics' => $routine->metrics ?? [],
            'activeProxyNotice' => $activeProxy ? [
                'id' => $activeProxy->id,
                'name' => $activeProxy->name,
                'date' => optional($activeProxy->date)->toDateString(),
                'day' => $activeProxy->day_label,
            ] : null,
        ]);
    }

    private function inactiveRoutineResponse(string $role): Response
    {
        return Inertia::render('Routines/Show', [
            'accessRole' => $role,
            'readOnly' => true,
            'noActiveRoutine' => true,
            'currentTeacherName' => '',
            'currentClassLabel' => '',
            'routine' => [
                'id' => null,
                'name' => 'No active routine',
                'term' => null,
                'status' => 'Inactive',
            ],
            'days' => [],
            'periods' => [],
            'legend' => [],
            'classOptions' => [],
            'teachers' => [],
            'teacherSchedule' => [],
            'classes' => [],
            'teacherPool' => [],
            'generationRules' => [],
            'generatedGrid' => [],
            'metrics' => [],
            'activeProxyNotice' => null,
        ]);
    }

    public function regenerate(Request $request, Routine $routine, RoutineGenerator $generator): RedirectResponse
    {
        $data = $this->validatedPayload($request, false);
        $generated = $generator->generate($data);

        $routine->update([
            'name' => $data['name'] ?? $routine->name,
            'term_label' => $data['termLabel'] ?? $routine->term_label,
            'days' => $generated['days'],
            'periods' => $generated['periods'],
            'classes' => $generated['classes'],
            'teachers' => $generated['teachers'],
            'generation_rules' => $generated['generationRules'],
            'generated_grid' => $generated['generatedGrid'],
            'teacher_schedule' => $generated['teacherSchedule'],
            'metrics' => $generated['metrics'],
        ]);

        return redirect()->route('routines.show', $routine)->with('success', 'Routine regenerated.');
    }

    public function activate(Request $request, Routine $routine): RedirectResponse
    {
        $this->ensureAdmin($request);

        DB::transaction(function () use ($routine): void {
            Routine::whereKeyNot($routine->id)->where('status', 'Active')->update(['status' => 'Draft']);
            $routine->update(['status' => 'Active']);
        });

        return back()->with('success', 'Active routine updated.');
    }

    public function rename(Request $request, Routine $routine): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $routine->update(['name' => $data['name']]);

        return back()->with('success', 'Routine renamed.');
    }

    public function destroy(Request $request, Routine $routine): RedirectResponse
    {
        $this->ensureAdmin($request);

        $wasActive = $routine->status === 'Active';
        $routine->delete();

        if ($wasActive) {
            Routine::latest()->first()?->update(['status' => 'Active']);
        }

        return redirect()->route('routines.index')->with('success', 'Routine deleted.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(($request->user()?->role ?? 'admin') === 'admin', 403);
    }

    private function validatedPayload(Request $request, bool $nameRequired = true): array
    {
        return $request->validate([
            'name' => [$nameRequired ? 'required' : 'sometimes', 'string', 'max:120'],
            'termLabel' => ['nullable', 'string', 'max:120'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', 'string', 'max:12'],
            'classes' => ['required', 'array', 'min:1'],
            'teachers' => ['required', 'array', 'min:1'],
            'periods' => ['required', 'array', 'min:1'],
            'generationRules' => ['nullable', 'array'],
        ]);
    }
}
