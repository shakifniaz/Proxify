<?php

namespace App\Services;

use App\Models\Routine;

class ProxyEngine
{
    private array $days = [];
    private array $periods = [];
    private array $classPeriods = [];
    private array $teachers = [];
    private array $teacherNames = [];
    private array $teacherSubjects = [];
    private array $teacherClassRanks = [];
    private array $teacherSectionKeys = [];
    private array $teacherBusy = [];
    private array $classGrid = [];
    private array $absentByTeacher = [];
    private array $assignments = [];
    private array $adjustments = [];

    public function generate(Routine $routine, array $payload): array
    {
        $this->hydrateRoutine($routine);

        $day = (string) ($payload['day'] ?? ($this->days[0] ?? 'Sun'));
        $absences = $this->normalizeAbsences($payload['absentTeachers'] ?? [], $day);
        $subjectGroups = $this->normalizeSubjectGroups($payload['subjectGroups'] ?? []);
        $manualAssignments = $this->normalizeManualAssignments($payload['manualAssignments'] ?? []);
        $affected = $this->affectedClasses($day, $absences);

        foreach ($affected as $target) {
            $this->resolveTarget($day, $target, $subjectGroups, $manualAssignments);
        }

        $resolved = count(array_filter($this->assignments, fn ($assignment) => ($assignment['status'] ?? '') === 'resolved'));
        $unresolved = count($this->assignments) - $resolved;

        return [
            'day' => $day,
            'absentTeachers' => $absences,
            'subjectGroups' => $subjectGroups,
            'assignments' => $this->groupAssignmentsByPeriod($this->assignments),
            'flatAssignments' => $this->assignments,
            'adjustments' => $this->adjustments,
            'metrics' => [
                'affectedPeriods' => count($this->assignments),
                'resolved' => $resolved,
                'unresolved' => $unresolved,
                'swapCount' => count(array_filter($this->assignments, fn ($assignment) => str_contains((string) ($assignment['strategy'] ?? ''), 'swap'))),
                'proxyCount' => count(array_filter($this->assignments, fn ($assignment) => str_contains((string) ($assignment['strategy'] ?? ''), 'proxy'))),
                'heavySuggestions' => count(array_filter($this->assignments, fn ($assignment) => ($assignment['strategy'] ?? '') === 'heavy_swap_suggestion')),
            ],
        ];
    }

    private function hydrateRoutine(Routine $routine): void
    {
        $this->days = array_values($routine->days ?? []);
        $this->periods = array_values($routine->periods ?? []);
        $this->classPeriods = array_values(array_filter($this->periods, fn ($period) => ($period['type'] ?? 'class') === 'class'));
        $this->teachers = array_values($routine->teachers ?? []);
        $this->classGrid = $routine->generated_grid ?? [];
        $this->teacherBusy = [];
        $this->teacherNames = [];
        $this->teacherSubjects = [];
        $this->teacherClassRanks = [];
        $this->teacherSectionKeys = [];
        $this->assignments = [];
        $this->adjustments = [];

        foreach ($this->teachers as $teacher) {
            $id = (string) ($teacher['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $this->teacherNames[$id] = (string) ($teacher['name'] ?? 'Teacher '.$id);
            $this->teacherSubjects[$id] = array_values(array_filter($teacher['primarySubjects'] ?? $teacher['subjects'] ?? []));
        }

        foreach ($this->days as $day) {
            foreach ($this->teachers as $teacher) {
                $id = (string) ($teacher['id'] ?? '');
                $this->teacherBusy[$day][$id] = [];
            }
        }

        foreach ($this->classGrid as $sectionKey => $section) {
            foreach ($section['days'] ?? [] as $day => $cells) {
                foreach ($cells as $periodKey => $cell) {
                    if (($cell['type'] ?? '') !== 'class' && ($cell['type'] ?? '') !== 'proxy') {
                        continue;
                    }

                    $teacherId = (string) ($cell['teacherId'] ?? '');
                    if ($teacherId === '') {
                        continue;
                    }

                    $cell = array_merge($cell, [
                        'sectionKey' => (string) $sectionKey,
                        'classId' => (string) ($section['classId'] ?? ''),
                        'className' => (string) ($section['className'] ?? ''),
                        'sectionId' => (string) ($section['sectionId'] ?? ''),
                        'sectionName' => (string) ($section['sectionName'] ?? ''),
                        'classLabel' => (string) ($cell['classLabel'] ?? $section['label'] ?? ''),
                        'periodKey' => (string) $periodKey,
                        'day' => (string) $day,
                    ]);

                    $this->teacherBusy[$day][$teacherId][$periodKey] = $cell;
                    $this->teacherSubjects[$teacherId][] = (string) ($cell['subject'] ?? '');
                    $this->teacherClassRanks[$teacherId][] = $this->classRank($cell['classLabel'] ?? $section['label'] ?? '');
                    $this->teacherSectionKeys[$teacherId][] = (string) $sectionKey;
                }
            }
        }

        foreach ($this->teacherSubjects as $teacherId => $subjects) {
            $this->teacherSubjects[$teacherId] = array_values(array_unique(array_filter(array_map(fn ($subject) => strtolower(trim((string) $subject)), $subjects))));
        }

        foreach ($this->teacherClassRanks as $teacherId => $ranks) {
            $this->teacherClassRanks[$teacherId] = array_values(array_unique(array_filter($ranks, fn ($rank) => $rank !== null)));
        }

        foreach ($this->teacherSectionKeys as $teacherId => $sectionKeys) {
            $this->teacherSectionKeys[$teacherId] = array_values(array_unique($sectionKeys));
        }
    }

    private function normalizeAbsences(array $absences, string $day): array
    {
        $normalized = [];
        $this->absentByTeacher = [];

        foreach ($absences as $absence) {
            $teacherId = (string) ($absence['teacherId'] ?? '');
            if ($teacherId === '' || ! isset($this->teacherNames[$teacherId])) {
                continue;
            }

            $periodKeys = array_values(array_filter(array_map('strval', $absence['periodKeys'] ?? [])));
            if (empty($periodKeys)) {
                $periodKeys = array_keys($this->teacherBusy[$day][$teacherId] ?? []);
            }

            $periodKeys = array_values(array_unique($periodKeys));
            $normalized[] = [
                'teacherId' => $teacherId,
                'teacherName' => $this->teacherNames[$teacherId],
                'periodKeys' => $periodKeys,
            ];

            foreach ($periodKeys as $periodKey) {
                $this->absentByTeacher[$teacherId][$periodKey] = true;
            }
        }

        return $normalized;
    }

    private function normalizeSubjectGroups(array $groups): array
    {
        return array_values(array_filter(array_map(function ($group, $index) {
            $subjects = $group['subjects'] ?? [];
            if (is_string($subjects)) {
                $subjects = preg_split('/[,\\n]+/', $subjects);
            }

            $subjects = array_values(array_unique(array_filter(array_map(fn ($subject) => strtolower(trim((string) $subject)), $subjects))));

            if (empty($subjects)) {
                return null;
            }

            return [
                'id' => $group['id'] ?? 'group-'.($index + 1),
                'name' => trim((string) ($group['name'] ?? 'Subject group '.($index + 1))),
                'subjects' => $subjects,
            ];
        }, $groups, array_keys($groups))));
    }

    private function normalizeManualAssignments(array $assignments): array
    {
        $normalized = [];

        foreach ($assignments as $assignment) {
            $targetKey = trim((string) ($assignment['targetKey'] ?? ''));
            $teacherId = (string) ($assignment['teacherId'] ?? '');

            if ($targetKey === '' || $teacherId === '' || ! isset($this->teacherNames[$teacherId])) {
                continue;
            }

            $normalized[$targetKey] = $teacherId;
        }

        return $normalized;
    }

    private function affectedClasses(string $day, array $absences): array
    {
        $targets = [];

        foreach ($absences as $absence) {
            foreach ($absence['periodKeys'] as $periodKey) {
                $cell = $this->teacherBusy[$day][$absence['teacherId']][$periodKey] ?? null;
                if (! $cell || ($cell['type'] ?? '') !== 'class') {
                    continue;
                }

                $targets[] = array_merge($cell, [
                    'absentTeacherId' => $absence['teacherId'],
                    'absentTeacherName' => $absence['teacherName'],
                ]);
            }
        }

        usort($targets, fn ($a, $b) => $this->periodIndex($a['periodKey']) <=> $this->periodIndex($b['periodKey']));

        return $targets;
    }

    private function resolveTarget(string $day, array $target, array $subjectGroups, array $manualAssignments = []): void
    {
        $assignmentId = count($this->assignments) + 1;
        $targetKey = $this->targetKey($target);

        if (isset($manualAssignments[$targetKey])) {
            $manualTeacherId = (string) $manualAssignments[$targetKey];

            if ($manualTeacherId !== (string) ($target['teacherId'] ?? '') && $this->teacherFree($day, $manualTeacherId, (string) ($target['periodKey'] ?? ''))) {
                $this->applyProxy($day, $target, $manualTeacherId);
                $this->assignments[] = $this->assignment($assignmentId, $target, 'resolved', 'manual_proxy', $manualTeacherId, [
                    'strategyLabel' => 'Manual proxy',
                    'reason' => 'Selected manually by the admin before auto generation.',
                ]);
                return;
            }

            $this->assignments[] = $this->assignment($assignmentId, $target, 'unresolved', 'manual_proxy_unavailable', null, [
                'strategyLabel' => 'Manual selection unavailable',
                'reason' => 'The selected manual teacher was no longer free for this period.',
            ]);
            return;
        }

        if ($swap = $this->findDirectSwap($day, $target)) {
            $this->applySwap($day, $target, $swap);
            $this->assignments[] = $this->assignment($assignmentId, $target, 'resolved', 'period_swap', $swap['teacherId'], [
                'strategyLabel' => 'Period swap',
                'reason' => 'Swapped with another period in the same class so both teachers keep their own subjects.',
                'swapWith' => $swap,
            ]);
            return;
        }

        if ($chain = $this->findChainSwapSuggestion($day, $target)) {
            $this->assignments[] = $this->assignment($assignmentId, $target, 'review', 'heavy_swap_suggestion', $chain['teacherId'], [
                'strategyLabel' => 'Heavy swap suggestion',
                'reason' => 'A swap path exists, but it affects multiple teachers/classes and should be reviewed before applying.',
                'swapPath' => $chain['path'],
            ]);
            return;
        }

        if ($sameClass = $this->bestFreeTeacher($day, $target, fn ($teacherId) => $this->teachesSameSectionOrClass($teacherId, $target), 'same-class')) {
            $this->applyProxy($day, $target, $sameClass['teacherId']);
            $this->assignments[] = $this->assignment($assignmentId, $target, 'resolved', 'same_class_proxy', $sameClass['teacherId'], [
                'strategyLabel' => 'Same class proxy',
                'reason' => 'No clean swap found. Assigned a free teacher already connected to this class.',
                'score' => $sameClass['score'],
            ]);
            return;
        }

        if ($groupTeacher = $this->bestSubjectGroupTeacher($day, $target, $subjectGroups)) {
            $this->applyProxy($day, $target, $groupTeacher['teacherId']);
            $this->assignments[] = $this->assignment($assignmentId, $target, 'resolved', 'subject_group_proxy', $groupTeacher['teacherId'], [
                'strategyLabel' => 'Similar subject proxy',
                'reason' => 'Assigned a free teacher from a matching subject group.',
                'subjectGroup' => $groupTeacher['group'],
                'score' => $groupTeacher['score'],
            ]);
            return;
        }

        if ($nearby = $this->bestFreeTeacher($day, $target, fn () => true, 'nearby-class')) {
            $this->applyProxy($day, $target, $nearby['teacherId']);
            $this->assignments[] = $this->assignment($assignmentId, $target, 'resolved', 'nearby_class_proxy', $nearby['teacherId'], [
                'strategyLabel' => 'Nearby class proxy',
                'reason' => 'Assigned the least-loaded free teacher with the closest class-level experience.',
                'score' => $nearby['score'],
            ]);
            return;
        }

        $this->assignments[] = $this->assignment($assignmentId, $target, 'unresolved', 'unresolved', null, [
            'strategyLabel' => 'Unresolved',
            'reason' => 'No teacher is free for this period.',
        ]);
    }

    private function findDirectSwap(string $day, array $target): ?array
    {
        $section = $this->classGrid[$target['sectionKey']] ?? null;
        $cells = $section['days'][$day] ?? [];

        foreach ($cells as $periodKey => $candidate) {
            if ($periodKey === $target['periodKey'] || ($candidate['type'] ?? '') !== 'class') {
                continue;
            }

            $candidateTeacherId = (string) ($candidate['teacherId'] ?? '');
            if ($candidateTeacherId === '' || $candidateTeacherId === (string) $target['teacherId']) {
                continue;
            }

            if (! $this->teacherFree($day, $candidateTeacherId, $target['periodKey']) || ! $this->teacherFree($day, (string) $target['teacherId'], $periodKey)) {
                continue;
            }

            return array_merge($candidate, [
                'teacherId' => $candidateTeacherId,
                'teacherName' => $this->teacherNames[$candidateTeacherId] ?? 'Teacher',
                'periodKey' => (string) $periodKey,
                'periodLabel' => $this->periodLabel((string) $periodKey),
            ]);
        }

        return null;
    }

    private function findChainSwapSuggestion(string $day, array $target): ?array
    {
        $section = $this->classGrid[$target['sectionKey']] ?? null;
        $cells = $section['days'][$day] ?? [];

        foreach ($cells as $periodKey => $candidate) {
            if ($periodKey === $target['periodKey'] || ($candidate['type'] ?? '') !== 'class') {
                continue;
            }

            $candidateTeacherId = (string) ($candidate['teacherId'] ?? '');
            if ($candidateTeacherId === '' || $candidateTeacherId === (string) $target['teacherId']) {
                continue;
            }

            if (! $this->teacherFree($day, $candidateTeacherId, $target['periodKey'])) {
                continue;
            }

            $blocker = $this->teacherBusy[$day][(string) $target['teacherId']][$periodKey] ?? null;
            if (! $blocker) {
                continue;
            }

            $freePeriod = $this->findFreePeriodForTeacher($day, (string) ($blocker['teacherId'] ?? ''), $blocker['periodKey'] ?? '');
            if (! $freePeriod) {
                continue;
            }

            return [
                'teacherId' => $candidateTeacherId,
                'path' => [
                    [
                        'teacher' => $this->teacherNames[$candidateTeacherId] ?? 'Teacher',
                        'from' => $this->periodLabel((string) $periodKey),
                        'to' => $this->periodLabel((string) $target['periodKey']),
                    ],
                    [
                        'teacher' => $target['absentTeacherName'],
                        'from' => $this->periodLabel((string) $target['periodKey']),
                        'to' => $this->periodLabel((string) $periodKey),
                    ],
                    [
                        'teacher' => $this->teacherNames[(string) ($blocker['teacherId'] ?? '')] ?? 'Teacher',
                        'from' => $this->periodLabel((string) ($blocker['periodKey'] ?? '')),
                        'to' => $this->periodLabel($freePeriod),
                    ],
                ],
            ];
        }

        return null;
    }

    private function bestSubjectGroupTeacher(string $day, array $target, array $subjectGroups): ?array
    {
        $targetSubject = strtolower(trim((string) ($target['subject'] ?? '')));
        $matchingGroup = null;

        foreach ($subjectGroups as $group) {
            if (in_array($targetSubject, $group['subjects'], true)) {
                $matchingGroup = $group;
                break;
            }
        }

        if (! $matchingGroup) {
            return null;
        }

        return $this->bestFreeTeacher($day, $target, function ($teacherId) use ($matchingGroup) {
            return count(array_intersect($this->teacherSubjects[$teacherId] ?? [], $matchingGroup['subjects'])) > 0;
        }, 'subject-group', ['group' => $matchingGroup['name']]);
    }

    private function bestFreeTeacher(string $day, array $target, callable $filter, string $mode, array $extra = []): ?array
    {
        $candidates = [];

        foreach (array_keys($this->teacherNames) as $teacherId) {
            if ((string) $teacherId === (string) $target['teacherId']) {
                continue;
            }

            if (! $this->teacherFree($day, $teacherId, (string) $target['periodKey']) || ! $filter($teacherId)) {
                continue;
            }

            $dailyLoad = count($this->teacherBusy[$day][$teacherId] ?? []);
            $distance = $this->classDistance($teacherId, $target['classLabel'] ?? '');
            $sameSectionBonus = in_array((string) ($target['sectionKey'] ?? ''), $this->teacherSectionKeys[$teacherId] ?? [], true) ? -20 : 0;

            $score = match ($mode) {
                'same-class' => $sameSectionBonus + ($distance * 4) + ($dailyLoad * 8),
                'subject-group' => ($distance * 3) + ($dailyLoad * 8),
                default => ($distance * 10) + ($dailyLoad * 8),
            };

            $candidates[] = array_merge($extra, [
                'teacherId' => (string) $teacherId,
                'teacherName' => $this->teacherNames[$teacherId],
                'score' => $score,
                'dailyLoad' => $dailyLoad,
                'classDistance' => $distance,
            ]);
        }

        usort($candidates, fn ($a, $b) => $a['score'] <=> $b['score'] ?: $a['dailyLoad'] <=> $b['dailyLoad']);

        return $candidates[0] ?? null;
    }

    private function applySwap(string $day, array $target, array $swap): void
    {
        $targetTeacher = (string) $target['teacherId'];
        $swapTeacher = (string) $swap['teacherId'];
        $targetPeriod = (string) $target['periodKey'];
        $swapPeriod = (string) $swap['periodKey'];

        $targetCell = $this->teacherBusy[$day][$targetTeacher][$targetPeriod] ?? $target;
        $swapCell = $this->teacherBusy[$day][$swapTeacher][$swapPeriod] ?? $swap;

        unset($this->teacherBusy[$day][$targetTeacher][$targetPeriod], $this->teacherBusy[$day][$swapTeacher][$swapPeriod]);

        $targetCell['periodKey'] = $swapPeriod;
        $swapCell['periodKey'] = $targetPeriod;
        $this->teacherBusy[$day][$targetTeacher][$swapPeriod] = $targetCell;
        $this->teacherBusy[$day][$swapTeacher][$targetPeriod] = $swapCell;

        $this->adjustments[] = [
            'type' => 'swap',
            'day' => $day,
            'classLabel' => $target['classLabel'] ?? '',
            'from' => $this->periodLabel($targetPeriod),
            'to' => $this->periodLabel($swapPeriod),
            'absentTeacher' => $target['absentTeacherName'] ?? $this->teacherNames[$targetTeacher] ?? '',
            'coverTeacher' => $this->teacherNames[$swapTeacher] ?? '',
        ];
    }

    private function applyProxy(string $day, array $target, string $proxyTeacherId): void
    {
        $this->teacherBusy[$day][$proxyTeacherId][(string) $target['periodKey']] = array_merge($target, [
            'type' => 'proxy',
            'proxyForTeacherId' => (string) $target['teacherId'],
            'teacherId' => $proxyTeacherId,
        ]);
    }

    private function assignment(int $id, array $target, string $status, string $strategy, ?string $assignedTeacherId, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'periodKey' => (string) ($target['periodKey'] ?? ''),
            'periodLabel' => $this->periodLabel((string) ($target['periodKey'] ?? '')),
            'classLabel' => (string) ($target['classLabel'] ?? ''),
            'sectionKey' => (string) ($target['sectionKey'] ?? ''),
            'subject' => (string) ($target['subject'] ?? ''),
            'absentTeacherId' => (string) ($target['teacherId'] ?? ''),
            'absentTeacher' => (string) ($target['absentTeacherName'] ?? $this->teacherNames[(string) ($target['teacherId'] ?? '')] ?? ''),
            'assignedTeacherId' => $assignedTeacherId,
            'assignedTeacher' => $assignedTeacherId ? ($this->teacherNames[$assignedTeacherId] ?? 'Teacher') : null,
            'status' => $status,
            'strategy' => $strategy,
        ], $extra);
    }

    private function targetKey(array $target): string
    {
        return implode('|', [
            (string) ($target['sectionKey'] ?? ''),
            (string) ($target['periodKey'] ?? ''),
            (string) ($target['teacherId'] ?? ''),
        ]);
    }

    private function groupAssignmentsByPeriod(array $assignments): array
    {
        $groups = [];
        foreach ($assignments as $assignment) {
            $key = $assignment['periodKey'];
            $groups[$key] ??= [
                'period' => $key,
                'label' => $assignment['periodLabel'],
                'items' => [],
            ];
            $groups[$key]['items'][] = $assignment;
        }

        uasort($groups, fn ($a, $b) => $this->periodIndex($a['period']) <=> $this->periodIndex($b['period']));

        return array_values($groups);
    }

    private function teacherFree(string $day, string $teacherId, string $periodKey): bool
    {
        if (isset($this->absentByTeacher[$teacherId][$periodKey])) {
            return false;
        }

        return ! isset($this->teacherBusy[$day][$teacherId][$periodKey]);
    }

    private function teachesSameSectionOrClass(string $teacherId, array $target): bool
    {
        if (in_array((string) ($target['sectionKey'] ?? ''), $this->teacherSectionKeys[$teacherId] ?? [], true)) {
            return true;
        }

        return $this->classDistance($teacherId, $target['classLabel'] ?? '') === 0;
    }

    private function findFreePeriodForTeacher(string $day, string $teacherId, string $exceptPeriodKey): ?string
    {
        foreach ($this->classPeriods as $period) {
            $periodKey = (string) ($period['key'] ?? '');
            if ($periodKey === '' || $periodKey === $exceptPeriodKey) {
                continue;
            }

            if ($this->teacherFree($day, $teacherId, $periodKey)) {
                return $periodKey;
            }
        }

        return null;
    }

    private function classDistance(string $teacherId, string $classLabel): int
    {
        $targetRank = $this->classRank($classLabel);
        if ($targetRank === null) {
            return 99;
        }

        $ranks = $this->teacherClassRanks[$teacherId] ?? [];
        if (empty($ranks)) {
            return 99;
        }

        return min(array_map(fn ($rank) => abs($rank - $targetRank), $ranks));
    }

    private function classRank(string $label): ?int
    {
        $normalized = strtolower($label);
        if (preg_match('/\\b(nursery|nur)\\b/', $normalized)) {
            return 0;
        }
        if (preg_match('/\\bkg\\b|kindergarten/', $normalized)) {
            return 1;
        }
        if (preg_match('/\\b(\\d{1,2})\\b/', $normalized, $matches)) {
            return (int) $matches[1] + 1;
        }

        $roman = [
            'xii' => 13, 'xi' => 12, 'x' => 11, 'ix' => 10, 'viii' => 9, 'vii' => 8,
            'vi' => 7, 'v' => 6, 'iv' => 5, 'iii' => 4, 'ii' => 3, 'i' => 2,
        ];

        if (preg_match('/\\b(xii|xi|x|ix|viii|vii|vi|v|iv|iii|ii|i)\\b/', $normalized, $matches)) {
            return $roman[$matches[1]];
        }

        return null;
    }

    private function periodLabel(string $periodKey): string
    {
        foreach ($this->periods as $period) {
            if (($period['key'] ?? '') === $periodKey) {
                return (string) ($period['label'] ?? $periodKey);
            }
        }

        return $periodKey;
    }

    private function periodIndex(string $periodKey): int
    {
        foreach ($this->classPeriods as $index => $period) {
            if (($period['key'] ?? '') === $periodKey) {
                return $index;
            }
        }

        return 999;
    }
}
