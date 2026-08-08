<?php

namespace App\Http\Controllers;

use App\Models\ProxyRun;
use App\Models\ProxySubjectGroup;
use App\Models\Routine;
use App\Services\ProxyEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ProxyRunController extends Controller
{
    public function index(): Response
    {
        $routine = Routine::where('status', 'Active')->latest()->first() ?? Routine::latest()->first();
        $subjects = $routine ? $this->routineSubjects($routine) : [];
        $runs = ProxyRun::query()
            ->with('routine:id,name,status')
            ->latest()
            ->take(12)
            ->get();

        return Inertia::render('ProxyManager/Index', [
            'activeRoutine' => $routine ? $this->routinePayload($routine) : null,
            'runs' => $runs->map(fn (ProxyRun $run) => $this->runSummary($run))->values(),
            'latestRun' => $runs->first() ? $this->runPayload($runs->first()) : null,
            'defaultSubjectGroups' => $routine ? $this->subjectGroupsForRoutine($routine, $subjects) : [],
            'leaveAbsences' => $routine ? $this->approvedLeaveAbsences($routine) : [],
        ]);
    }

    public function store(Request $request, ProxyEngine $engine): RedirectResponse
    {
        $data = $request->validate([
            'routineId' => ['required', 'integer', 'exists:routines,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date'],
            'day' => ['required', 'string', 'max:20'],
            'absentTeachers' => ['required', 'array', 'min:1'],
            'absentTeachers.*.teacherId' => ['required', 'string', 'max:80'],
            'absentTeachers.*.periodKeys' => ['nullable', 'array'],
            'absentTeachers.*.periodKeys.*' => ['string', 'max:40'],
            'subjectGroups' => ['nullable', 'array'],
            'subjectGroups.*.id' => ['nullable', 'string', 'max:80'],
            'subjectGroups.*.name' => ['required_with:subjectGroups', 'string', 'max:80'],
            'subjectGroups.*.subjects' => ['nullable', 'array'],
            'subjectGroups.*.subjects.*' => ['string', 'max:120'],
            'manualAssignments' => ['nullable', 'array'],
            'manualAssignments.*.targetKey' => ['required_with:manualAssignments', 'string', 'max:220'],
            'manualAssignments.*.teacherId' => ['required_with:manualAssignments', 'string', 'max:80'],
        ]);

        $routine = Routine::findOrFail($data['routineId']);
        $generated = $engine->generate($routine, $data);
        $proxyGrid = $this->proxyGridForRun($routine, $generated['day'], $generated['assignments']);
        $proxySchedule = $this->teacherScheduleFromGrid($routine, $proxyGrid);

        ProxyRun::create([
            'routine_id' => $routine->id,
            'user_id' => $request->user()?->id,
            'name' => $data['name'] ?: 'Proxy run - '.$data['day'],
            'date' => $data['date'] ?? null,
            'day_label' => $generated['day'],
            'status' => ($generated['metrics']['unresolved'] ?? 0) > 0 ? 'Needs Review' : 'Draft',
            'absent_teachers' => $generated['absentTeachers'],
            'subject_groups' => $generated['subjectGroups'],
            'assignments' => $generated['assignments'],
            'adjustments' => $generated['adjustments'],
            'proxy_generated_grid' => $proxyGrid,
            'proxy_teacher_schedule' => $proxySchedule,
            'metrics' => $generated['metrics'],
        ]);

        return redirect()->route('proxy-manager.index')->with('success', 'Proxy run generated.');
    }

    public function show(ProxyRun $proxyRun): Response
    {
        $proxyRun->load('routine');
        $routine = $proxyRun->routine;
        abort_unless($routine, 404);

        $days = $routine->days ?? [];
        $proxyGrid = $proxyRun->proxy_generated_grid ?: $this->proxyGridForRun($routine, $proxyRun->day_label, $proxyRun->assignments ?? []);
        $proxySchedule = $proxyRun->proxy_teacher_schedule ?: $this->teacherScheduleFromGrid($routine, $proxyGrid);

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
            'teachers' => $proxySchedule[$proxyRun->day_label] ?? $proxySchedule[$days[0] ?? ''] ?? [],
            'teacherSchedule' => $proxySchedule,
            'classes' => $routine->classes ?? [],
            'teacherPool' => $routine->teachers ?? [],
            'generationRules' => $routine->generation_rules ?? [],
            'generatedGrid' => $proxyGrid,
            'metrics' => $proxyRun->metrics ?? [],
            'proxyContext' => [
                'id' => $proxyRun->id,
                'name' => $proxyRun->name,
                'date' => optional($proxyRun->date)->toDateString(),
                'day' => $proxyRun->day_label,
                'status' => $proxyRun->status,
                'approvedAt' => optional($proxyRun->approved_at)->toDateTimeString(),
                'originalGeneratedGrid' => $routine->generated_grid ?? [],
                'originalTeacherSchedule' => $routine->teacher_schedule ?? [],
            ],
        ]);
    }

    public function updateRoutine(Request $request, ProxyRun $proxyRun): RedirectResponse
    {
        $data = $request->validate([
            'generatedGrid' => ['required', 'array'],
            'teacherSchedule' => ['required', 'array'],
        ]);

        $proxyRun->update([
            'proxy_generated_grid' => $data['generatedGrid'],
            'proxy_teacher_schedule' => $data['teacherSchedule'],
            'status' => $proxyRun->approved_at ? 'Approved' : 'Draft',
        ]);

        return back()->with('success', 'Proxy routine draft saved.');
    }

    public function approve(ProxyRun $proxyRun): RedirectResponse
    {
        $proxyRun->update([
            'status' => 'Approved',
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Proxy routine approved for the selected day.');
    }

    public function saveSubjectGroups(Request $request): RedirectResponse
    {
        $this->ensureProxySubjectGroupsTable();

        $data = $request->validate([
            'routineId' => ['required', 'integer', 'exists:routines,id'],
            'subjectGroups' => ['required', 'array'],
            'subjectGroups.*.name' => ['required', 'string', 'max:80'],
            'subjectGroups.*.subjects' => ['nullable', 'array'],
            'subjectGroups.*.subjects.*' => ['string', 'max:120'],
        ]);

        $routine = Routine::findOrFail($data['routineId']);
        $validSubjects = collect($this->routineSubjects($routine))->mapWithKeys(fn ($subject) => [strtolower($subject) => $subject]);

        ProxySubjectGroup::where('routine_id', $routine->id)->delete();

        foreach (array_values($data['subjectGroups']) as $index => $group) {
            $subjects = collect($group['subjects'] ?? [])
                ->map(fn ($subject) => $validSubjects->get(strtolower(trim((string) $subject))))
                ->filter()
                ->unique(fn ($subject) => strtolower($subject))
                ->values()
                ->all();

            ProxySubjectGroup::create([
                'routine_id' => $routine->id,
                'name' => trim((string) $group['name']),
                'subjects' => $subjects,
                'sort_order' => $index + 1,
            ]);
        }

        return redirect()->route('proxy-manager.index')->with('success', 'Subject groups saved.');
    }

    private function routinePayload(Routine $routine): array
    {
        $days = $routine->days ?? [];
        $periods = array_values(array_filter($routine->periods ?? [], fn ($period) => ($period['type'] ?? 'class') === 'class'));
        $teachers = collect($routine->teachers ?? [])->map(function ($teacher) use ($routine) {
            $id = (string) ($teacher['id'] ?? '');
            $subjects = array_values($teacher['primarySubjects'] ?? $teacher['subjects'] ?? []);

            foreach ($routine->generated_grid ?? [] as $section) {
                foreach ($section['days'] ?? [] as $cells) {
                    foreach ($cells as $cell) {
                        if ((string) ($cell['teacherId'] ?? '') === $id && filled($cell['subject'] ?? null)) {
                            $subjects[] = $cell['subject'];
                        }
                    }
                }
            }

            return [
                'id' => $id,
                'name' => $teacher['name'] ?? 'Teacher',
                'subjectHint' => implode(', ', array_slice(array_values(array_unique(array_filter($subjects))), 0, 4)),
            ];
        })->values();

        return [
            'id' => $routine->id,
            'name' => $routine->name,
            'status' => $routine->status,
            'days' => $days,
            'periods' => $periods,
            'teachers' => $teachers,
            'subjects' => $this->routineSubjects($routine),
            'generatedGrid' => $routine->generated_grid ?? [],
            'summary' => [
                'classes' => count($routine->classes ?? []),
                'sections' => collect($routine->classes ?? [])->sum(fn ($class) => count($class['sections'] ?? [])),
                'teachers' => $teachers->count(),
            ],
        ];
    }

    private function routineSubjects(Routine $routine): array
    {
        $subjects = [];

        foreach ($routine->classes ?? [] as $class) {
            foreach ($class['sections'] ?? [] as $section) {
                foreach ($section['subjects'] ?? [] as $subject) {
                    if (filled($subject['name'] ?? null)) {
                        $subjects[] = trim((string) $subject['name']);
                    }
                }
            }
        }

        foreach ($routine->generated_grid ?? [] as $section) {
            foreach ($section['days'] ?? [] as $cells) {
                foreach ($cells as $cell) {
                    if (filled($cell['subject'] ?? null)) {
                        $subjects[] = trim((string) $cell['subject']);
                    }
                }
            }
        }

        return collect($subjects)
            ->filter()
            ->unique(fn ($subject) => strtolower($subject))
            ->sort(fn ($a, $b) => strnatcasecmp($a, $b))
            ->values()
            ->all();
    }

    private function runSummary(ProxyRun $run): array
    {
        return [
            'id' => $run->id,
            'name' => $run->name,
            'routineName' => $run->routine?->name,
            'day' => $run->day_label,
            'date' => optional($run->date)->toDateString(),
            'status' => $run->status,
            'affected' => $run->metrics['affectedPeriods'] ?? 0,
            'resolved' => $run->metrics['resolved'] ?? 0,
            'unresolved' => $run->metrics['unresolved'] ?? 0,
            'createdAt' => $run->created_at?->format('M j, g:i A'),
        ];
    }

    private function runPayload(ProxyRun $run): array
    {
        return array_merge($this->runSummary($run), [
            'absentTeachers' => $run->absent_teachers ?? [],
            'subjectGroups' => $run->subject_groups ?? [],
            'assignments' => $run->assignments ?? [],
            'adjustments' => $run->adjustments ?? [],
            'metrics' => $run->metrics ?? [],
        ]);
    }

    private function proxyGridForRun(Routine $routine, string $day, array $assignments): array
    {
        $grid = $routine->generated_grid ?? [];

        foreach ($this->flattenAssignments($assignments) as $assignment) {
            $sectionKey = (string) ($assignment['sectionKey'] ?? '');
            $periodKey = (string) ($assignment['periodKey'] ?? '');
            if ($sectionKey === '' || $periodKey === '' || ! isset($grid[$sectionKey]['days'][$day][$periodKey])) {
                continue;
            }

            if (($assignment['strategy'] ?? '') === 'period_swap' && isset($assignment['swapWith'])) {
                $swap = $assignment['swapWith'];
                $swapPeriod = (string) ($swap['periodKey'] ?? '');
                if ($swapPeriod !== '' && isset($grid[$sectionKey]['days'][$day][$swapPeriod])) {
                    $targetCell = $grid[$sectionKey]['days'][$day][$periodKey];
                    $swapCell = $grid[$sectionKey]['days'][$day][$swapPeriod];
                    $grid[$sectionKey]['days'][$day][$periodKey] = array_merge($swapCell, [
                        'periodKey' => $periodKey,
                        'proxyChanged' => true,
                        'proxyChangeKind' => 'swap',
                        'proxyChangeLabel' => 'Swap',
                        'proxyNote' => 'Swapped from '.$this->periodLabel($routine, $swapPeriod),
                    ]);
                    $grid[$sectionKey]['days'][$day][$swapPeriod] = array_merge($targetCell, [
                        'periodKey' => $swapPeriod,
                        'proxyChanged' => true,
                        'proxyChangeKind' => 'swap',
                        'proxyChangeLabel' => 'Swap',
                        'proxyNote' => 'Swapped from '.$this->periodLabel($routine, $periodKey),
                    ]);
                }
                continue;
            }

            if (($assignment['status'] ?? '') === 'unresolved' || empty($assignment['assignedTeacherId'])) {
                $grid[$sectionKey]['days'][$day][$periodKey] = array_merge($grid[$sectionKey]['days'][$day][$periodKey], [
                    'type' => 'unresolved',
                    'teacherId' => null,
                    'teacherName' => null,
                    'proxyForTeacherId' => $assignment['absentTeacherId'] ?? null,
                    'proxyForTeacherName' => $assignment['absentTeacher'] ?? null,
                    'proxyChanged' => true,
                    'proxyChangeKind' => 'unresolved',
                    'proxyChangeLabel' => 'Needs proxy',
                ]);
                continue;
            }

            $grid[$sectionKey]['days'][$day][$periodKey] = array_merge($grid[$sectionKey]['days'][$day][$periodKey], [
                'type' => 'proxy',
                'teacherId' => (string) $assignment['assignedTeacherId'],
                'teacherName' => $assignment['assignedTeacher'],
                'proxyForTeacherId' => $assignment['absentTeacherId'] ?? null,
                'proxyForTeacherName' => $assignment['absentTeacher'] ?? null,
                'proxyChanged' => true,
                'proxyChangeKind' => 'proxy',
                'proxyChangeLabel' => 'Proxy',
                'proxyStrategy' => $assignment['strategyLabel'] ?? $assignment['strategy'] ?? 'Proxy',
            ]);
        }

        return $grid;
    }

    private function teacherScheduleFromGrid(Routine $routine, array $grid): array
    {
        $schedule = [];
        $teachers = collect($routine->teachers ?? [])->mapWithKeys(fn ($teacher) => [
            (string) ($teacher['id'] ?? '') => [
                'id' => (string) ($teacher['id'] ?? ''),
                'name' => $teacher['name'] ?? 'Teacher',
                'subject' => implode(', ', array_slice($teacher['primarySubjects'] ?? $teacher['subjects'] ?? [], 0, 3)),
                'cells' => [],
            ],
        ])->filter(fn ($teacher, $id) => $id !== '');

        foreach ($routine->days ?? [] as $day) {
            $dayTeachers = $teachers->map(fn ($teacher) => array_merge($teacher, ['cells' => []]))->all();

            foreach ($grid as $section) {
                foreach (($section['days'][$day] ?? []) as $periodKey => $cell) {
                    if (! in_array(($cell['type'] ?? ''), ['class', 'proxy', 'unresolved'], true)) {
                        continue;
                    }

                    $teacherId = (string) ($cell['teacherId'] ?? '');
                    if ($teacherId === '' && ($cell['type'] ?? '') === 'unresolved') {
                        $teacherId = (string) ($cell['proxyForTeacherId'] ?? 'unresolved');
                    }

                    if ($teacherId === '') {
                        continue;
                    }

                    $dayTeachers[$teacherId] ??= [
                        'id' => $teacherId,
                        'name' => $cell['teacherName'] ?? $cell['proxyForTeacherName'] ?? 'Unassigned',
                        'subject' => '',
                        'cells' => [],
                    ];

                    $dayTeachers[$teacherId]['cells'][(string) $periodKey] = array_merge($cell, [
                        'teacherId' => $cell['teacherId'] ?? $teacherId,
                        'teacherName' => $cell['teacherName'] ?? $dayTeachers[$teacherId]['name'],
                        'classLabel' => $cell['classLabel'] ?? $section['label'] ?? '',
                    ]);
                }
            }

            $schedule[$day] = array_values($dayTeachers);
        }

        return $schedule;
    }

    private function flattenAssignments(array $assignments): array
    {
        if (isset($assignments[0]['items'])) {
            return collect($assignments)->flatMap(fn ($group) => $group['items'] ?? [])->values()->all();
        }

        return $assignments;
    }

    private function periodLabel(Routine $routine, string $periodKey): string
    {
        foreach ($routine->periods ?? [] as $period) {
            if (($period['key'] ?? '') === $periodKey) {
                return (string) ($period['label'] ?? $periodKey);
            }
        }

        return $periodKey;
    }

    private function subjectGroupsForRoutine(Routine $routine, array $subjects): array
    {
        $this->ensureProxySubjectGroupsTable();

        $saved = ProxySubjectGroup::where('routine_id', $routine->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($saved->isNotEmpty()) {
            return $saved->map(fn (ProxySubjectGroup $group) => [
                'id' => 'saved-'.$group->id,
                'name' => $group->name,
                'subjects' => $group->subjects ?? [],
            ])->values()->all();
        }

        return $this->defaultSubjectGroups($subjects);
    }

    private function ensureProxySubjectGroupsTable(): void
    {
        if (Schema::hasTable('proxy_subject_groups')) {
            return;
        }

        Schema::create('proxy_subject_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('subjects');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    private function defaultSubjectGroups(array $routineSubjects = []): array
    {
        $groups = [
            ['id' => 'science', 'name' => 'Science', 'subjects' => ['physics', 'chemistry', 'biology', 'science', 'sci', 'bio', 'chem', 'phy']],
            ['id' => 'math', 'name' => 'Mathematics', 'subjects' => ['math', 'mathematics', 'higher mathematics', 'h.math', 'h.math1', 'math1', 'math2']],
            ['id' => 'language', 'name' => 'Language', 'subjects' => ['english', 'eng', 'e1', 'e2', 'bangla', 'bang', 'b1', 'b2', 'lit', 'lang']],
            ['id' => 'social', 'name' => 'Social studies', 'subjects' => ['bgs', 'history', 's.std', 'social studies', 'geography']],
            ['id' => 'commerce', 'name' => 'Commerce', 'subjects' => ['accounting', 'acc', 'acc1', 'business', 'b.ent', 'bom', 'f&b', 'pmm']],
        ];

        if (empty($routineSubjects)) {
            return $groups;
        }

        $subjectsByKey = collect($routineSubjects)->mapWithKeys(fn ($subject) => [strtolower($subject) => $subject]);

        return array_map(function ($group) use ($subjectsByKey) {
            $matched = collect($group['subjects'])
                ->map(fn ($subject) => $subjectsByKey->get(strtolower($subject)))
                ->filter()
                ->unique(fn ($subject) => strtolower($subject))
                ->values()
                ->all();

            return array_merge($group, ['subjects' => $matched]);
        }, $groups);
    }

    private function approvedLeaveAbsences(Routine $routine): array
    {
        $teachers = collect($this->routinePayload($routine)['teachers'] ?? [])
            ->filter(fn ($teacher) => filled($teacher['id'] ?? null))
            ->values();
        $periods = collect($routine->periods ?? [])
            ->filter(fn ($period) => ($period['type'] ?? 'class') === 'class')
            ->pluck('key')
            ->filter()
            ->values();

        if ($teachers->isEmpty()) {
            return [];
        }

        $mockLeaves = [
            [
                'teacher' => $teachers->get(0),
                'periodKeys' => $periods->all(),
                'type' => 'Sick leave',
                'dateRange' => 'Today',
                'note' => 'Approved full-day leave',
            ],
            [
                'teacher' => $teachers->get(1),
                'periodKeys' => $periods->slice(1, 2)->values()->all(),
                'type' => 'Emergency leave',
                'dateRange' => 'Today',
                'note' => 'Approved for selected periods',
            ],
        ];

        return collect($mockLeaves)
            ->filter(fn ($leave) => filled($leave['teacher']['id'] ?? null))
            ->map(fn ($leave, $index) => [
                'id' => 'mock-approved-'.$index,
                'teacherId' => (string) $leave['teacher']['id'],
                'teacherName' => $leave['teacher']['name'] ?? 'Teacher',
                'periodKeys' => $leave['periodKeys'],
                'type' => $leave['type'],
                'dateRange' => $leave['dateRange'],
                'status' => 'approved',
                'note' => $leave['note'],
            ])
            ->values()
            ->all();
    }
}
