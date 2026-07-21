<?php

namespace App\Http\Controllers;

use App\Models\ProxyRun;
use App\Models\Routine;
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
        $role = $request->query('role') ?? ($request->user()?->role ?? 'admin');

        if ($role === 'teacher') {
            $routine = Routine::where('status', 'Active')->latest()->first() ?? Routine::latest()->first();
            return $routine
                ? redirect()->route('routines.show', ['routine' => $routine->id, 'role' => 'teacher'])
                : redirect()->route('dashboard');
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
        $teachers = config('routine_demo.teachers', []);

        return Inertia::render('Routines/Create', [
            'classesConfig' => config('routine_demo.classes_config'),
            'classes' => config('routine_demo.classes', []),
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

    public function show(Routine $routine): Response
    {
        $days = $routine->days ?? [];
        $activeProxy = ProxyRun::where('routine_id', $routine->id)
            ->where('status', 'Approved')
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->first();
        $generatedGrid = $activeProxy?->proxy_generated_grid ?: ($routine->generated_grid ?? []);
        $teacherSchedule = $activeProxy?->proxy_teacher_schedule ?: ($routine->teacher_schedule ?? []);

        return Inertia::render('Routines/Show', [
            'routine' => [
                'id' => $routine->id,
                'name' => $routine->name,
                'term' => $routine->term_label,
                'status' => $routine->status,
            ],
            'days' => $days,
            'periods' => $routine->periods ?? [],
            'legend' => [],
            'classOptions' => collect($routine->generated_grid ?? [])->pluck('label')->values(),
            'teachers' => $teacherSchedule[$days[0] ?? ''] ?? [],
            'teacherSchedule' => $teacherSchedule,
            'classes' => $routine->classes ?? [],
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
