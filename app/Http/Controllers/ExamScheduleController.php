<?php

namespace App\Http\Controllers;

use App\Models\ExamSchedule;
use App\Models\Routine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $role = $this->role($request);
        $routine = $this->activeRoutine($request);
        $active = $this->scheduleQuery($request)->where('status', 'Active')->latest()->first();

        if ($role !== 'admin') {
            return Inertia::render('ExamSchedule/Index', [
                ...$this->basePayload($routine, $active),
                'pageMode' => 'viewer',
                'role' => $role,
                'currentTeacherName' => $request->user()?->teacherProfile?->name ?? $request->user()?->name ?? '',
                'viewerClassLabel' => $this->studentClassLabel($request),
            ]);
        }

        return Inertia::render('ExamSchedule/Index', [
            ...$this->basePayload($routine, $active),
            'schedules' => $this->scheduleQuery($request)->latest()->get()->map(fn (ExamSchedule $schedule) => $this->summary($schedule))->values(),
            'pageMode' => 'list',
            'role' => 'admin',
            'currentTeacherName' => $request->user()?->name ?? '',
            'viewerClassLabel' => '',
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($this->role($request) === 'admin', 403);
        $routine = $this->activeRoutine($request);

        return Inertia::render('ExamSchedule/Index', [
            ...$this->basePayload($routine),
            'pageMode' => 'editor',
            'role' => 'admin',
            'currentTeacherName' => $request->user()?->name ?? '',
            'viewerClassLabel' => '',
        ]);
    }

    public function show(Request $request, ExamSchedule $examSchedule): Response
    {
        $role = $this->role($request);
        $routine = $this->activeRoutine($request);

        return Inertia::render('ExamSchedule/Index', [
            ...$this->basePayload($routine, $examSchedule),
            'schedules' => $role === 'admin' ? $this->scheduleQuery($request)->latest()->get()->map(fn (ExamSchedule $schedule) => $this->summary($schedule))->values() : [],
            'pageMode' => $role === 'admin' ? 'editor' : 'viewer',
            'role' => $role,
            'currentTeacherName' => $request->user()?->teacherProfile?->name ?? $request->user()?->name ?? '',
            'viewerClassLabel' => $this->studentClassLabel($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->role($request) === 'admin', 403);
        $data = $this->validatedPayload($request);
        $routine = $this->activeRoutine($request);

        $schedule = ExamSchedule::create([
            ...$data,
            'institution_id' => $request->user()?->institution_id,
            'routine_id' => $routine?->id,
            'user_id' => $request->user()?->id,
            'status' => 'Draft',
        ]);

        return redirect()->route('exam-schedule.show', $schedule)->with('success', 'Exam schedule saved.');
    }

    public function update(Request $request, ExamSchedule $examSchedule): RedirectResponse
    {
        abort_unless($this->role($request) === 'admin', 403);
        $examSchedule->update($this->validatedPayload($request));

        return back()->with('success', 'Exam schedule saved.');
    }

    public function activate(Request $request, string $examSchedule): RedirectResponse
    {
        abort_unless($this->role($request) === 'admin', 403);
        $query = $this->scheduleQuery($request);
        $query->update(['status' => 'Draft']);

        if ($examSchedule !== 'none') {
            $schedule = $query->whereKey($examSchedule)->firstOrFail();
            $schedule->update(['status' => 'Active']);
        }

        return redirect()->route('exam-schedule.index');
    }

    public function destroy(Request $request, ExamSchedule $examSchedule): RedirectResponse
    {
        abort_unless($this->role($request) === 'admin', 403);
        $examSchedule->delete();

        return redirect()->route('exam-schedule.index')->with('success', 'Exam schedule deleted.');
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:180'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'halls' => ['nullable', 'array'],
            'time_slots' => ['nullable', 'array'],
            'class_options' => ['nullable', 'array'],
            'subject_options' => ['nullable', 'array'],
            'invigilator_options' => ['nullable', 'array'],
            'exam_grid' => ['nullable', 'array'],
        ]);
    }

    private function basePayload(?Routine $routine, ?ExamSchedule $schedule = null): array
    {
        $options = $this->routineOptions($routine);

        return [
            'halls' => $schedule?->halls ?? [],
            'timeSlots' => $schedule?->time_slots ?? [],
            'subjectOptions' => $schedule?->subject_options ?: $options['subjects'],
            'classOptions' => $schedule?->class_options ?: $options['classes'],
            'classSubjectOptions' => $options['classSubjects'],
            'invigilatorOptions' => $schedule?->invigilator_options ?: $options['teachers'],
            'schedules' => [],
            'hasActiveSchedule' => $schedule?->status === 'Active',
            'session' => [
                'id' => $schedule?->id,
                'title' => $schedule?->name ?? '',
                'subtitle' => $schedule?->subtitle ?? '',
                'startDate' => $schedule?->start_date?->toDateString(),
                'endDate' => $schedule?->end_date?->toDateString(),
                'status' => $schedule?->status ?? 'Inactive',
            ],
            'examGrid' => $schedule?->exam_grid ?? [],
        ];
    }

    private function routineOptions(?Routine $routine): array
    {
        $sections = collect($routine?->generated_grid ?? []);
        $configuredClasses = collect($routine?->classes ?? []);
        $classSubjects = $configuredClasses
            ->flatMap(function ($class) {
                return collect($class['sections'] ?? [])->mapWithKeys(function ($section) use ($class) {
                    $label = trim(($class['name'] ?? 'Class').' '.($section['name'] ?? ''));
                    $subjects = collect($section['subjects'] ?? [])
                        ->pluck('name')
                        ->filter()
                        ->unique(fn ($subject) => strtolower((string) $subject))
                        ->values()
                        ->all();

                    return $label ? [$label => $subjects] : [];
                });
            })
            ->all();
        $allSubjects = collect($classSubjects)
            ->flatMap(fn ($subjects) => $subjects)
            ->unique(fn ($subject) => strtolower((string) $subject))
            ->values()
            ->all();

        return [
            'classes' => $sections
                ->map(fn ($section) => $section['label'] ?? trim(($section['className'] ?? 'Class').' '.($section['sectionName'] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'classSubjects' => filled($classSubjects) ? $classSubjects : $sections
                ->mapWithKeys(function ($section) {
                    $label = $section['label'] ?? trim(($section['className'] ?? 'Class').' '.($section['sectionName'] ?? ''));
                    $subjects = collect($section['days'] ?? [])
                        ->flatMap(fn ($day) => collect($day))
                        ->filter(fn ($cell) => ($cell['type'] ?? '') === 'class' && filled($cell['subject'] ?? null))
                        ->pluck('subject')
                        ->unique(fn ($subject) => strtolower((string) $subject))
                        ->values()
                        ->all();

                    return $label ? [$label => $subjects] : [];
                })
                ->all(),
            'subjects' => filled($allSubjects) ? $allSubjects : $sections
                ->flatMap(fn ($section) => collect($section['days'] ?? [])->flatMap(fn ($day) => collect($day)))
                ->filter(fn ($cell) => ($cell['type'] ?? '') === 'class' && filled($cell['subject'] ?? null))
                ->pluck('subject')
                ->unique(fn ($subject) => strtolower((string) $subject))
                ->values()
                ->all(),
            'teachers' => collect($routine?->teachers ?? [])
                ->pluck('name')
                ->filter()
                ->unique(fn ($name) => strtolower((string) $name))
                ->values()
                ->all(),
        ];
    }

    private function summary(ExamSchedule $schedule): array
    {
        $examCount = collect($schedule->exam_grid ?? [])
            ->flatMap(fn ($dates) => collect($dates)->flatMap(fn ($slots) => collect($slots)->filter()))
            ->sum(fn ($cell) => count($cell['exams'] ?? (($cell['subject'] ?? null) ? [1] : [])));

        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'subtitle' => $schedule->subtitle,
            'startDate' => $schedule->start_date?->toDateString(),
            'endDate' => $schedule->end_date?->toDateString(),
            'dateRange' => $schedule->start_date && $schedule->end_date ? $schedule->start_date->format('d/m/y').' - '.$schedule->end_date->format('d/m/y') : 'Dates not set',
            'classes' => count($schedule->class_options ?? []),
            'halls' => count($schedule->halls ?? []),
            'exams' => $examCount,
            'status' => $schedule->status,
        ];
    }

    private function activeRoutine(Request $request): ?Routine
    {
        $institutionId = $request->user()?->institution_id;

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

    private function scheduleQuery(Request $request)
    {
        $institutionId = $request->user()?->institution_id;

        return ExamSchedule::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId));
    }

    private function role(Request $request): string
    {
        $actualRole = strtolower($request->user()?->role ?? 'admin');
        $previewRole = strtolower((string) $request->query('previewRole', ''));

        return $actualRole === 'admin' && in_array($previewRole, ['teacher', 'student'], true) ? $previewRole : $actualRole;
    }

    private function studentClassLabel(Request $request): string
    {
        $section = $request->user()?->classSection;
        return $section ? trim($section->class_name.' '.$section->section_name) : '';
    }
}
