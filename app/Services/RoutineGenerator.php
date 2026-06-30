<?php

namespace App\Services;

class RoutineGenerator
{
    public function generate(array $payload): array
    {
        $days = array_values($payload['days'] ?? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu']);
        $periods = $this->normalizePeriods($payload['periods'] ?? []);
        $teachers = $this->normalizeTeachers($payload['teachers'] ?? []);
        $classes = $this->normalizeClasses($payload['classes'] ?? []);
        $rules = array_merge([
            'maxConsecutivePeriods' => 3,
            'preferGapBetweenPeriods' => true,
            'autoBalanceUnsetSubjectLoads' => true,
            'keepClassTeacherFirstPeriod' => true,
            'flagUnallocatedSlots' => true,
        ], $payload['generationRules'] ?? []);

        $classPeriods = array_values(array_filter($periods, fn ($period) => ($period['type'] ?? 'class') === 'class'));
        $teacherBusy = [];
        $sectionBusy = [];
        $subjectDays = [];
        $grid = [];
        $unallocated = [];

        foreach ($days as $day) {
            foreach ($teachers as $teacher) {
                $teacherBusy[$day][$teacher['id']] = [];
            }
        }

        foreach ($classes as $classIndex => $class) {
            foreach ($class['sections'] as $sectionIndex => $section) {
                $sectionKey = $this->sectionKey($class['id'], $section['id']);
                $grid[$sectionKey] = [
                    'classId' => $class['id'],
                    'className' => $class['name'],
                    'sectionId' => $section['id'],
                    'sectionName' => $section['name'],
                    'label' => trim($class['name'].' '.$section['name']),
                    'dailyPeriods' => $section['dailyPeriods'] ?? $class['dailyPeriods'],
                    'dailyPeriodsByDay' => $section['dailyPeriodsByDay'] ?? $class['dailyPeriodsByDay'] ?? [],
                    'days' => [],
                ];

                foreach ($days as $day) {
                    $grid[$sectionKey]['days'][$day] = [];
                    $allowedPeriods = $this->dailyPeriodsForDay($section, $day);
                    foreach ($classPeriods as $slotIndex => $period) {
                        if ($slotIndex < $allowedPeriods) {
                            $grid[$sectionKey]['days'][$day][$period['key']] = ['type' => 'empty'];
                        }
                    }
                }

                $assignments = $this->buildAssignments($class, $section, count($days));

                $this->placeManualAssignments(
                    $assignments,
                    $section,
                    $class,
                    $days,
                    $classPeriods,
                    $grid,
                    $teacherBusy,
                    $sectionBusy,
                    $subjectDays,
                    $unallocated,
                    $teachers
                );

                if ($rules['keepClassTeacherFirstPeriod'] ?? true) {
                    $this->placeClassTeacherFirstPeriods(
                        $assignments,
                        $section,
                        $class,
                        $days,
                        $classPeriods,
                        $grid,
                        $teacherBusy,
                        $sectionBusy,
                        $subjectDays
                    );
                }

                foreach ($assignments as &$assignment) {
                    while ($assignment['remaining'] > 0) {
                        $placement = $this->findBestPlacement(
                            $assignment,
                            $class,
                            $section,
                            $days,
                            $classPeriods,
                            $grid,
                            $teacherBusy,
                            $sectionBusy,
                            $subjectDays,
                            $rules
                        );

                        if (! $placement) {
                            $unallocated[] = [
                                'classLabel' => trim($class['name'].' '.$section['name']),
                                'subject' => $assignment['name'],
                                'teacherId' => $assignment['teacherId'],
                                'teacherName' => $this->teacherName($teachers, $assignment['teacherId']),
                                'remaining' => $assignment['remaining'],
                            ];
                            break;
                        }

                        $this->placeAssignment($assignment, $class, $section, $placement['day'], $placement['period'], $grid, $teacherBusy, $sectionBusy, $subjectDays);
                        $assignment['remaining']--;
                    }
                }
                unset($assignment);
            }
        }

        $teacherSchedule = $this->buildTeacherSchedule($teachers, $days, $periods, $teacherBusy);
        $metrics = $this->buildMetrics($grid, $teacherSchedule, $unallocated);

        return [
            'days' => $days,
            'periods' => $periods,
            'classes' => $classes,
            'teachers' => $teachers,
            'generationRules' => $rules,
            'generatedGrid' => $grid,
            'teacherSchedule' => $teacherSchedule,
            'metrics' => $metrics,
        ];
    }

    private function normalizePeriods(array $periods): array
    {
        if (empty($periods)) {
            $periods = [
                ['label' => 'P1', 'startTime' => '08:00', 'endTime' => '08:45', 'type' => 'class'],
                ['label' => 'P2', 'startTime' => '08:45', 'endTime' => '09:30', 'type' => 'class'],
                ['label' => 'Break', 'startTime' => '09:30', 'endTime' => '09:45', 'type' => 'break'],
                ['label' => 'P3', 'startTime' => '09:45', 'endTime' => '10:30', 'type' => 'class'],
                ['label' => 'P4', 'startTime' => '10:30', 'endTime' => '11:15', 'type' => 'class'],
                ['label' => 'P5', 'startTime' => '11:15', 'endTime' => '12:00', 'type' => 'class'],
                ['label' => 'Lunch', 'startTime' => '12:00', 'endTime' => '13:00', 'type' => 'break'],
                ['label' => 'P6', 'startTime' => '13:00', 'endTime' => '13:45', 'type' => 'class'],
                ['label' => 'P7', 'startTime' => '13:45', 'endTime' => '14:30', 'type' => 'class'],
            ];
        }

        $classNumber = 1;

        return array_values(array_map(function ($period, $index) use (&$classNumber) {
            $type = ($period['type'] ?? 'class') === 'break' ? 'break' : 'class';
            $label = trim((string) ($period['label'] ?? ($type === 'class' ? 'P'.$classNumber : 'Break')));
            $key = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $label));

            if ($type === 'class' && ($key === '' || $key === 'P')) {
                $key = 'P'.$classNumber;
            }

            if ($type === 'class') {
                $classNumber++;
            }

            return [
                'id' => $period['id'] ?? $index + 1,
                'key' => $key ?: 'PERIOD_'.($index + 1),
                'label' => $label ?: ($type === 'class' ? 'P'.($index + 1) : 'Break'),
                'startTime' => $period['startTime'] ?? '',
                'endTime' => $period['endTime'] ?? '',
                'time' => trim(($period['startTime'] ?? '').' - '.($period['endTime'] ?? ''), ' -'),
                'type' => $type,
            ];
        }, $periods, array_keys($periods)));
    }

    private function normalizeTeachers(array $teachers): array
    {
        return array_values(array_map(fn ($teacher, $index) => [
            'id' => (string) ($teacher['id'] ?? $index + 1),
            'name' => trim((string) ($teacher['name'] ?? 'Teacher '.($index + 1))),
            'phone' => $teacher['phone'] ?? '',
            'primarySubjects' => array_values($teacher['primarySubjects'] ?? $teacher['subjects'] ?? []),
            'subjectHint' => implode(', ', $teacher['primarySubjects'] ?? $teacher['subjects'] ?? []),
        ], $teachers, array_keys($teachers)));
    }

    private function normalizeClasses(array $classes): array
    {
        return array_values(array_map(function ($class, $classIndex) {
            return [
                'id' => (string) ($class['id'] ?? $classIndex + 1),
                'name' => trim((string) ($class['name'] ?? 'Class '.($classIndex + 1))),
                'dailyPeriods' => max(1, (int) ($class['dailyPeriods'] ?? 7)),
                'dailyPeriodsByDay' => array_map(
                    fn ($value) => max(0, (int) $value),
                    $class['dailyPeriodsByDay'] ?? []
                ),
                'sections' => array_values(array_map(function ($section, $sectionIndex) use ($class, $classIndex) {
                    $sectionDailyPeriods = max(1, (int) ($section['dailyPeriods'] ?? $class['dailyPeriods'] ?? 7));

                    return [
                        'id' => (string) ($section['id'] ?? (($class['id'] ?? $classIndex + 1).'-'.($sectionIndex + 1))),
                        'name' => trim((string) ($section['name'] ?? 'Section '.chr(65 + $sectionIndex))),
                        'dailyPeriods' => $sectionDailyPeriods,
                        'dailyPeriodsByDay' => array_map(
                            fn ($value) => max(0, (int) $value),
                            $section['dailyPeriodsByDay'] ?? $class['dailyPeriodsByDay'] ?? []
                        ),
                        'classTeacherId' => isset($section['classTeacherId']) ? (string) $section['classTeacherId'] : null,
                        'subjects' => array_values(array_map(fn ($subject, $subjectIndex) => [
                            'id' => (string) ($subject['id'] ?? (($class['id'] ?? $classIndex + 1).'-'.($sectionIndex + 1).'-'.($subjectIndex + 1))),
                            'name' => trim((string) ($subject['name'] ?? 'Subject '.($subjectIndex + 1))),
                            'teacherId' => isset($subject['teacherId']) ? (string) $subject['teacherId'] : null,
                            'weeklyPeriods' => $subject['weeklyPeriods'] ?? null,
                            'autoBalance' => (bool) ($subject['autoBalance'] ?? empty($subject['weeklyPeriods'])),
                            'manualSlots' => array_values(array_filter(array_map(fn ($slot) => [
                                'day' => (string) ($slot['day'] ?? ''),
                                'periodKey' => (string) ($slot['periodKey'] ?? ''),
                            ], $subject['manualSlots'] ?? []), fn ($slot) => filled($slot['day']) && filled($slot['periodKey']))),
                            'color' => $subject['color'] ?? $this->subjectColor((string) ($subject['name'] ?? 'Subject')),
                        ], $section['subjects'] ?? [], array_keys($section['subjects'] ?? []))),
                    ];
                }, $class['sections'] ?? [], array_keys($class['sections'] ?? []))),
            ];
        }, $classes, array_keys($classes)));
    }

    private function dailyPeriodsForDay(array $class, string $day): int
    {
        if (array_key_exists($day, $class['dailyPeriodsByDay'] ?? [])) {
            return max(0, (int) $class['dailyPeriodsByDay'][$day]);
        }

        return max(1, (int) ($class['dailyPeriods'] ?? 7));
    }

    private function buildAssignments(array $class, array $section, int $dayCount): array
    {
        $subjects = array_values(array_filter($section['subjects'], fn ($subject) => filled($subject['name']) && filled($subject['teacherId'])));
        $slots = array_sum($section['dailyPeriodsByDay'] ?? []) ?: (($section['dailyPeriods'] ?? $class['dailyPeriods']) * $dayCount);
        $fixed = 0;
        $autoIndexes = [];

        foreach ($subjects as $index => $subject) {
            $manualCount = count($subject['manualSlots'] ?? []);
            $weekly = $manualCount > 0
                ? $manualCount
                : (is_numeric($subject['weeklyPeriods']) && (int) $subject['weeklyPeriods'] > 0 ? min((int) $subject['weeklyPeriods'], $dayCount) : null);
            if ($weekly) {
                $subjects[$index]['targetWeeklyPeriods'] = $weekly;
                $fixed += $weekly;
            } else {
                $autoIndexes[] = $index;
            }
        }

        $remaining = max(0, $slots - $fixed);
        $base = count($autoIndexes) ? max(1, intdiv($remaining, count($autoIndexes))) : 0;
        $extra = count($autoIndexes) ? $remaining % count($autoIndexes) : 0;

        foreach ($autoIndexes as $position => $index) {
            $subjects[$index]['targetWeeklyPeriods'] = min($dayCount, $base + ($position < $extra ? 1 : 0));
        }

        return array_map(fn ($subject) => [
            'subjectId' => $subject['id'],
            'name' => $subject['name'],
            'teacherId' => (string) $subject['teacherId'],
            'color' => $subject['color'] ?? $this->subjectColor((string) $subject['name']),
            'target' => (int) ($subject['targetWeeklyPeriods'] ?? 0),
            'remaining' => (int) ($subject['targetWeeklyPeriods'] ?? 0),
            'manualSlots' => $subject['manualSlots'] ?? [],
        ], $subjects);
    }

    private function placeManualAssignments(array &$assignments, array $section, array $class, array $days, array $classPeriods, array &$grid, array &$teacherBusy, array &$sectionBusy, array &$subjectDays, array &$unallocated, array $teachers): void
    {
        $periodsByKey = collect($classPeriods)->flatMap(fn ($period) => [
            $period['key'] => [$period],
            $period['label'] => [$period],
        ])->map(fn ($matches) => $matches[0]);

        foreach ($assignments as &$assignment) {
            foreach ($assignment['manualSlots'] ?? [] as $slot) {
                if ($assignment['remaining'] <= 0) {
                    break;
                }

                $day = $slot['day'];
                $period = $periodsByKey->get($slot['periodKey']);

                if (! in_array($day, $days, true) || ! $period) {
                    $unallocated[] = [
                        'classLabel' => trim($class['name'].' '.$section['name']),
                        'subject' => $assignment['name'],
                        'teacherId' => $assignment['teacherId'],
                        'teacherName' => $this->teacherName($teachers, $assignment['teacherId']),
                        'remaining' => 1,
                        'reason' => 'Manual slot is outside the routine days or periods.',
                    ];
                    continue;
                }

                if (! $this->canPlace($assignment, $class, $section, $day, $period, $grid, $teacherBusy, $sectionBusy, $subjectDays, true)) {
                    $unallocated[] = [
                        'classLabel' => trim($class['name'].' '.$section['name']),
                        'subject' => $assignment['name'],
                        'teacherId' => $assignment['teacherId'],
                        'teacherName' => $this->teacherName($teachers, $assignment['teacherId']),
                        'remaining' => 1,
                        'reason' => 'Manual slot conflicts with another assignment.',
                    ];
                    continue;
                }

                $this->placeAssignment($assignment, $class, $section, $day, $period, $grid, $teacherBusy, $sectionBusy, $subjectDays);
                $assignment['remaining']--;
            }
        }
        unset($assignment);
    }

    private function placeClassTeacherFirstPeriods(array &$assignments, array $section, array $class, array $days, array $classPeriods, array &$grid, array &$teacherBusy, array &$sectionBusy, array &$subjectDays): void
    {
        if (empty($section['classTeacherId']) || empty($classPeriods)) {
            return;
        }

        foreach ($assignments as &$assignment) {
            if ((string) $assignment['teacherId'] !== (string) $section['classTeacherId']) {
                continue;
            }

            foreach ($days as $day) {
                if ($assignment['remaining'] <= 0) {
                    break 2;
                }

                if ($this->dailyPeriodsForDay($section, $day) < 1) {
                    continue;
                }

                $period = $classPeriods[0];
                if ($this->canPlace($assignment, $class, $section, $day, $period, $grid, $teacherBusy, $sectionBusy, $subjectDays, false)) {
                    $this->placeAssignment($assignment, $class, $section, $day, $period, $grid, $teacherBusy, $sectionBusy, $subjectDays);
                    $assignment['remaining']--;
                }
            }
        }
        unset($assignment);
    }

    private function findBestPlacement(array $assignment, array $class, array $section, array $days, array $classPeriods, array $grid, array $teacherBusy, array $sectionBusy, array $subjectDays, array $rules): ?array
    {
        $candidates = [];
        $maxConsecutive = max(1, (int) ($rules['maxConsecutivePeriods'] ?? 3));

        foreach ($days as $dayIndex => $day) {
            foreach ($classPeriods as $periodIndex => $period) {
                $dailyPeriods = $this->dailyPeriodsForDay($section, $day);
                if ($periodIndex >= $dailyPeriods) {
                    continue;
                }

                if (! $this->canPlace($assignment, $class, $section, $day, $period, $grid, $teacherBusy, $sectionBusy, $subjectDays, true)) {
                    continue;
                }

                $consecutive = $this->consecutiveCount($teacherBusy[$day][$assignment['teacherId']] ?? [], $periodIndex);
                $gapPenalty = $this->adjacentCount($teacherBusy[$day][$assignment['teacherId']] ?? [], $periodIndex) * 8;
                $overLimitPenalty = $consecutive > $maxConsecutive ? 100 : 0;
                $placedCount = max(0, $assignment['target'] - $assignment['remaining']);
                $subjectSeed = abs(crc32($this->sectionKey($class['id'], $section['id']).$assignment['subjectId']));
                $targetPeriodIndex = ($subjectSeed + $placedCount + $dayIndex) % max(1, $dailyPeriods);
                $rotationPenalty = abs($periodIndex - $targetPeriodIndex) * 5;
                $dayLoadPenalty = $this->sectionDayLoad($grid, $class, $section, $day) * 2;
                $sameTeacherDayLoadPenalty = count($teacherBusy[$day][$assignment['teacherId']] ?? []) * 3;

                $candidates[] = [
                    'day' => $day,
                    'period' => $period,
                    'score' => $overLimitPenalty + $gapPenalty + $rotationPenalty + $dayLoadPenalty + $sameTeacherDayLoadPenalty,
                ];
            }
        }

        usort($candidates, fn ($a, $b) => $a['score'] <=> $b['score']);
        return $candidates[0] ?? null;
    }

    private function canPlace(array $assignment, array $class, array $section, string $day, array $period, array $grid, array $teacherBusy, array $sectionBusy, array $subjectDays, bool $enforceSubjectOncePerDay): bool
    {
        $sectionKey = $this->sectionKey($class['id'], $section['id']);
        $periodKey = $period['key'];

        if (! array_key_exists($periodKey, $grid[$sectionKey]['days'][$day] ?? [])) {
            return false;
        }

        if (($grid[$sectionKey]['days'][$day][$periodKey]['type'] ?? 'empty') !== 'empty') {
            return false;
        }

        if (isset($teacherBusy[$day][$assignment['teacherId']][$periodKey])) {
            return false;
        }

        if (isset($sectionBusy[$sectionKey][$day][$periodKey])) {
            return false;
        }

        if ($enforceSubjectOncePerDay && isset($subjectDays[$sectionKey][$assignment['subjectId']][$day])) {
            return false;
        }

        return true;
    }

    private function placeAssignment(array $assignment, array $class, array $section, string $day, array $period, array &$grid, array &$teacherBusy, array &$sectionBusy, array &$subjectDays): void
    {
        $sectionKey = $this->sectionKey($class['id'], $section['id']);
        $cell = [
            'type' => 'class',
            'subject' => $assignment['name'],
            'subjectCode' => $this->subjectCode($assignment['name']),
            'color' => $assignment['color'] ?? $this->subjectColor($assignment['name']),
            'classLabel' => trim($class['name'].' '.$section['name']),
            'teacherId' => $assignment['teacherId'],
            'periodKey' => $period['key'],
            'day' => $day,
        ];

        $grid[$sectionKey]['days'][$day][$period['key']] = $cell;
        $teacherBusy[$day][$assignment['teacherId']][$period['key']] = $cell;
        $sectionBusy[$sectionKey][$day][$period['key']] = true;
        $subjectDays[$sectionKey][$assignment['subjectId']][$day] = true;
    }

    private function buildTeacherSchedule(array $teachers, array $days, array $periods, array $teacherBusy): array
    {
        $schedule = [];

        foreach ($days as $day) {
            $schedule[$day] = array_map(function ($teacher) use ($day, $periods, $teacherBusy) {
                $cells = [];
                foreach ($periods as $period) {
                    if (($period['type'] ?? 'class') !== 'class') {
                        continue;
                    }
                    $cells[$period['key']] = $teacherBusy[$day][$teacher['id']][$period['key']] ?? ['type' => 'empty'];
                }

                return [
                    'id' => $teacher['id'],
                    'name' => $teacher['name'],
                    'subject' => $teacher['subjectHint'] ?: 'Not assigned',
                    'cells' => $cells,
                ];
            }, $teachers);
        }

        return $schedule;
    }

    private function buildMetrics(array $grid, array $teacherSchedule, array $unallocated): array
    {
        $emptySlots = 0;
        $assignedSlots = 0;

        foreach ($grid as $section) {
            foreach ($section['days'] as $cells) {
                foreach ($cells as $cell) {
                    if (($cell['type'] ?? 'empty') === 'empty') {
                        $emptySlots++;
                    } else {
                        $assignedSlots++;
                    }
                }
            }
        }

        return [
            'assignedSlots' => $assignedSlots,
            'emptySlots' => $emptySlots,
            'unallocatedAssignments' => count($unallocated),
            'unallocated' => $unallocated,
            'teacherCount' => count(reset($teacherSchedule) ?: []),
        ];
    }

    private function consecutiveCount(array $busyCells, int $periodIndex): int
    {
        $busyIndexes = array_map(fn ($cell) => $this->periodNumber($cell['periodKey'] ?? ''), $busyCells);
        $busyIndexes[] = $periodIndex + 1;
        sort($busyIndexes);

        $best = $current = 1;
        for ($i = 1; $i < count($busyIndexes); $i++) {
            $current = $busyIndexes[$i] === $busyIndexes[$i - 1] + 1 ? $current + 1 : 1;
            $best = max($best, $current);
        }

        return $best;
    }

    private function adjacentCount(array $busyCells, int $periodIndex): int
    {
        $target = $periodIndex + 1;
        $count = 0;
        foreach ($busyCells as $cell) {
            $number = $this->periodNumber($cell['periodKey'] ?? '');
            if (abs($number - $target) === 1) {
                $count++;
            }
        }
        return $count;
    }

    private function sectionDayLoad(array $grid, array $class, array $section, string $day): int
    {
        $sectionKey = $this->sectionKey($class['id'], $section['id']);
        $cells = $grid[$sectionKey]['days'][$day] ?? [];

        return count(array_filter($cells, fn ($cell) => ($cell['type'] ?? 'empty') !== 'empty'));
    }

    private function periodNumber(string $periodKey): int
    {
        if (preg_match('/(\d+)/', $periodKey, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    private function sectionKey(string $classId, string $sectionId): string
    {
        return $classId.'-'.$sectionId;
    }

    private function subjectCode(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $code = '';
        foreach (array_slice(array_filter($parts), 0, 2) as $part) {
            $code .= strtoupper(substr($part, 0, 1));
        }
        return $code ?: 'S';
    }


    private function subjectColor(string $name): string
    {
        $palette = [
            '#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2',
            '#4f46e5', '#16a34a', '#ca8a04', '#be123c', '#0f766e', '#9333ea',
        ];
        $normalized = strtolower(trim($name));
        return $palette[abs(crc32($normalized ?: 'subject')) % count($palette)];
    }

    private function teacherName(array $teachers, ?string $teacherId): string
    {
        foreach ($teachers as $teacher) {
            if ((string) $teacher['id'] === (string) $teacherId) {
                return $teacher['name'];
            }
        }
        return 'Unassigned';
    }
}
