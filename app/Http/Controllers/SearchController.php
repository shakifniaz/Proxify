<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\Routine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->classroomItems($request),
        ]);
    }

    private function classroomItems(Request $request): array
    {
        $user = $request->user();
        $role = strtolower($user?->role ?? 'admin');
        $institutionId = $user?->institution_id;
        $routine = $this->activeRoutine($institutionId);
        $teacherId = (string) ($user?->teacher_profile_id ?? '');
        $teacherName = $user?->teacherProfile?->name ?? $user?->name ?? '';
        $teacherNames = collect($routine?->teachers ?? [])
            ->mapWithKeys(fn ($teacher) => [
                (string) ($teacher['id'] ?? $teacher['name'] ?? '') => $teacher['name'] ?? 'Teacher',
            ])
            ->filter();

        $directorySections = ClassSection::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->with('classTeacher:id,name')
            ->orderBy('class_name')
            ->orderBy('section_name')
            ->get()
            ->keyBy(fn (ClassSection $section) => (string) $section->id);

        $gridBySectionId = collect($routine?->generated_grid ?? [])
            ->keyBy(fn ($section) => (string) ($section['sectionId'] ?? $section['id'] ?? ''));

        $routineSections = collect($routine?->classes ?? [])
            ->flatMap(function ($class) use ($gridBySectionId, $teacherNames) {
                return collect($class['sections'] ?? [])->map(function ($section) use ($class) {
                    return [
                        ...$section,
                        'className' => $class['name'] ?? $section['className'] ?? 'Class',
                    ];
                })->map(function ($section) use ($gridBySectionId, $teacherNames) {
                    $sectionId = (string) ($section['id'] ?? '');
                    $grid = $gridBySectionId->get($sectionId, []);
                    $configuredSubjects = collect($section['subjects'] ?? []);
                    $gridSubjects = collect($grid['days'] ?? [])
                        ->flatMap(fn ($dayCells) => collect($dayCells)->filter(fn ($cell) => ($cell['type'] ?? 'empty') === 'class'))
                        ->map(function ($cell) use ($teacherNames) {
                            $subjectTeacherId = (string) ($cell['teacherId'] ?? '');

                            return [
                                'id' => md5(($cell['subject'] ?? 'Subject').'-'.$subjectTeacherId),
                                'name' => trim((string) ($cell['subject'] ?? 'Subject')),
                                'teacherId' => $subjectTeacherId,
                                'teacherName' => $cell['teacherName'] ?? $teacherNames->get($subjectTeacherId, 'Assigned teacher'),
                            ];
                        });

                    return [
                        ...$section,
                        'subjects' => $configuredSubjects
                            ->merge($gridSubjects)
                            ->filter()
                            ->values()
                            ->all(),
                    ];
                });
            });

        $sections = $directorySections
            ->map(fn (ClassSection $section) => [
                'id' => (string) $section->id,
                'className' => $section->class_name,
                'sectionName' => $section->section_name,
                'classTeacherId' => (string) ($section->class_teacher_profile_id ?? ''),
                'classTeacherName' => $section->classTeacher?->name,
                'subjects' => $section->subjects ?? [],
            ])
            ->merge($routineSections->map(fn ($section) => [
                'id' => (string) ($section['id'] ?? ''),
                'className' => $section['className'] ?? 'Class',
                'sectionName' => $section['name'] ?? $section['sectionName'] ?? 'Section',
                'classTeacherId' => (string) ($section['classTeacherId'] ?? ''),
                'classTeacherName' => $section['classTeacherName'] ?? null,
                'subjects' => $section['subjects'] ?? [],
            ]))
            ->filter(fn ($section) => $section['id'] !== '')
            ->groupBy('id')
            ->map(function ($group) {
                $base = $group->first();

                return [
                    ...$base,
                    'classTeacherId' => $group->pluck('classTeacherId')->filter()->first() ?? '',
                    'classTeacherName' => $group->pluck('classTeacherName')->filter()->first(),
                    'subjects' => $group
                        ->flatMap(fn ($section) => $section['subjects'] ?? [])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->filter(function ($section) use ($role, $user, $teacherId, $teacherName) {
                if ($role === 'admin') {
                    return true;
                }

                if ($role === 'student') {
                    return (string) ($user?->class_section_id ?? '') === (string) $section['id'];
                }

                if ((string) ($section['classTeacherId'] ?? '') === $teacherId) {
                    return true;
                }

                return collect($section['subjects'] ?? [])->contains(function ($subject) use ($teacherId, $teacherName) {
                    if (! is_array($subject)) {
                        return false;
                    }

                    return (string) ($subject['teacherId'] ?? $subject['teacher_id'] ?? '') === $teacherId
                        || ($teacherName !== '' && strcasecmp((string) ($subject['teacherName'] ?? $subject['teacher'] ?? ''), $teacherName) === 0);
                });
            });

        return $sections
            ->flatMap(function ($section) use ($role, $teacherId, $teacherName) {
                $classroomLabel = trim($section['className'].' '.$section['sectionName']);
                $subjects = collect($section['subjects'] ?? [])
                    ->map(function ($subject, int $index) {
                        $name = is_array($subject) ? ($subject['name'] ?? $subject['subject'] ?? null) : $subject;
                        if (! $name) {
                            return null;
                        }

                        return [
                            'id' => is_array($subject)
                                ? (string) ($subject['id'] ?? md5($name.'-'.$index))
                                : md5($name.'-'.$index),
                            'name' => trim((string) $name),
                            'teacherId' => is_array($subject) ? (string) ($subject['teacherId'] ?? $subject['teacher_id'] ?? '') : '',
                            'teacherName' => is_array($subject) ? (string) ($subject['teacherName'] ?? $subject['teacher'] ?? '') : '',
                        ];
                    })
                    ->filter()
                    ->filter(function ($subject) use ($role, $teacherId, $teacherName, $section) {
                        if ($role !== 'teacher') {
                            return true;
                        }

                        return (string) ($section['classTeacherId'] ?? '') === $teacherId
                            || (string) ($subject['teacherId'] ?? '') === $teacherId
                            || ($teacherName !== '' && strcasecmp((string) ($subject['teacherName'] ?? ''), $teacherName) === 0);
                    })
                    ->unique(fn ($subject) => strtolower($subject['name']))
                    ->values();

                $items = collect([[
                    'key' => 'classroom:'.$section['id'],
                    'name' => $classroomLabel,
                    'href' => '/classroom?classroom='.urlencode($section['id']),
                    'description' => $role === 'student' ? 'Your classroom' : 'Classroom stream',
                    'keywords' => $classroomLabel.' classroom class section '.$section['classTeacherName'],
                    'type' => 'classroom',
                ]]);

                return $items->merge($subjects->map(fn ($subject) => [
                    'key' => 'classroom:'.$section['id'].':subject:'.$subject['id'],
                    'name' => $classroomLabel.' - '.$subject['name'],
                    'href' => '/classroom?classroom='.urlencode($section['id']).'&subject='.urlencode($subject['id']),
                    'description' => $subject['teacherName'] ? $subject['name'].' with '.$subject['teacherName'] : $subject['name'].' classroom',
                    'keywords' => $classroomLabel.' '.$subject['name'].' '.$subject['teacherName'].' subject classroom assignment homework',
                    'type' => 'subject',
                ]));
            })
            ->values()
            ->all();
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
}
