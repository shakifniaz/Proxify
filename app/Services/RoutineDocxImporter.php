<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class RoutineDocxImporter
{
    private const DAY_LABELS = [
        'sunday' => 'Sun',
        'monday' => 'Mon',
        'tuesday' => 'Tue',
        'wednesday' => 'Wed',
        'thursday' => 'Thu',
        'friday' => 'Fri',
        'saturday' => 'Sat',
    ];

    private const CLASS_LABELS = [
        'NUR' => ['name' => 'Nursery', 'sort' => 0],
        'NURSERY' => ['name' => 'Nursery', 'sort' => 0],
        'KG' => ['name' => 'KG', 'sort' => 0.5],
        'I' => ['name' => 'Class 1', 'sort' => 1],
        'II' => ['name' => 'Class 2', 'sort' => 2],
        'III' => ['name' => 'Class 3', 'sort' => 3],
        'IV' => ['name' => 'Class 4', 'sort' => 4],
        'V' => ['name' => 'Class 5', 'sort' => 5],
        'VI' => ['name' => 'Class 6', 'sort' => 6],
        'VII' => ['name' => 'Class 7', 'sort' => 7],
        'VIII' => ['name' => 'Class 8', 'sort' => 8],
        'IX' => ['name' => 'Class 9', 'sort' => 9],
        'X' => ['name' => 'Class 10', 'sort' => 10],
        'XI' => ['name' => 'Class 11', 'sort' => 11],
        'XII' => ['name' => 'Class 12', 'sort' => 12],
    ];

    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if ($extension !== 'docx') {
            throw new RuntimeException('Only DOCX routine imports are supported right now.');
        }

        $documentXml = $this->extractDocumentXml($file->getRealPath());
        $tablesByDay = $this->readRoutineTables($documentXml);

        if (empty($tablesByDay)) {
            throw new RuntimeException('No day-wise routine tables were found in the DOCX file.');
        }

        $firstTable = reset($tablesByDay);
        $periodMeta = $this->periodsFromHeader($firstTable[0] ?? []);
        $periods = $periodMeta['periods'];
        $periodColumns = $periodMeta['columns'];
        $classPeriodCount = count($periodColumns);

        $teachers = [];
        $teacherSubjects = [];
        $teacherSchedule = [];
        $classMap = [];
        $grid = [];
        $metrics = [
            'assignedSlots' => 0,
            'emptySlots' => 0,
            'unallocatedAssignments' => 0,
            'unallocated' => [],
            'importedConflicts' => 0,
        ];

        foreach ($tablesByDay as $day => $rows) {
            $teacherSchedule[$day] = [];

            foreach (array_slice($rows, 1) as $rowIndex => $row) {
                $teacherName = trim($row[1] ?? '');
                if ($teacherName === '') {
                    continue;
                }

                $teacherId = $this->teacherId($teacherName, $teachers);
                $teacherSchedule[$day][$teacherId] ??= [
                    'id' => $teacherId,
                    'name' => $teacherName,
                    'subject' => 'Imported',
                    'cells' => $this->emptyCells($periodColumns),
                ];

                foreach ($periodColumns as $periodKey => $columnIndex) {
                    $entry = $this->parseRoutineCell($row[$columnIndex] ?? '');
                    if (! $entry) {
                        continue;
                    }

                    $subjectColor = $this->subjectColor($entry['subject']);
                    $teacherSubjects[$teacherId][$entry['subject']] = true;
                    $classId = $this->classId($entry['className']);
                    $sectionId = $this->sectionId($entry['section']);
                    $sectionKey = $classId.'-'.$sectionId;
                    $classLabel = $entry['className'].' Section '.$entry['section'];

                    $classMap[$classId] ??= [
                        'id' => $classId,
                        'name' => $entry['className'],
                        'sort' => $entry['sort'],
                        'dailyPeriods' => $classPeriodCount,
                        'sections' => [],
                    ];

                    $classMap[$classId]['sections'][$sectionId] ??= [
                        'id' => $sectionId,
                        'name' => 'Section '.$entry['section'],
                        'classTeacherId' => null,
                        'subjects' => [],
                    ];

                    $subjectId = $this->subjectId($entry['subject'], $teacherId);
                    $classMap[$classId]['sections'][$sectionId]['subjects'][$subjectId] ??= [
                        'id' => $subjectId,
                        'name' => $entry['subject'],
                        'teacherId' => $teacherId,
                        'weeklyPeriods' => 0,
                        'autoBalance' => false,
                        'color' => $subjectColor,
                    ];
                    $classMap[$classId]['sections'][$sectionId]['subjects'][$subjectId]['weeklyPeriods']++;

                    $grid[$sectionKey] ??= [
                        'classId' => $classId,
                        'className' => $entry['className'],
                        'sectionId' => $sectionId,
                        'sectionName' => 'Section '.$entry['section'],
                        'label' => $classLabel,
                        'days' => [],
                    ];
                    $grid[$sectionKey]['days'][$day] ??= $this->emptyCells($periodColumns);

                    $cell = [
                        'type' => 'class',
                        'subject' => $entry['subject'],
                        'subjectCode' => $this->subjectCode($entry['subject']),
                        'color' => $subjectColor,
                        'classLabel' => $classLabel,
                        'teacherId' => $teacherId,
                        'periodKey' => $periodKey,
                        'day' => $day,
                        'sourceLabel' => $entry['sourceLabel'],
                    ];

                    if (($grid[$sectionKey]['days'][$day][$periodKey]['type'] ?? 'empty') !== 'empty') {
                        $metrics['importedConflicts']++;
                        $metrics['unallocated'][] = [
                            'classLabel' => $classLabel,
                            'subject' => $entry['subject'],
                            'teacherName' => $teacherName,
                            'periodKey' => $periodKey,
                            'day' => $day,
                            'reason' => 'Another teacher was already assigned to this class section in this period.',
                        ];
                    } else {
                        $grid[$sectionKey]['days'][$day][$periodKey] = $cell;
                    }

                    $teacherSchedule[$day][$teacherId]['cells'][$periodKey] = $cell;
                    $metrics['assignedSlots']++;
                }
            }
        }

        $days = array_keys($tablesByDay);
        $this->fillMissingGridDays($grid, $days, $periodColumns);
        $this->fillMissingTeacherDays($teacherSchedule, $teachers, $days, $periodColumns);

        foreach ($teachers as &$teacher) {
            $subjects = array_keys($teacherSubjects[$teacher['id']] ?? []);
            sort($subjects);
            $teacher['primarySubjects'] = $subjects;
            $teacher['subjectHint'] = implode(', ', $subjects);
        }
        unset($teacher);

        $classes = array_values($classMap);
        usort($classes, fn ($a, $b) => $a['sort'] <=> $b['sort']);
        foreach ($classes as &$class) {
            unset($class['sort']);
            $class['sections'] = array_values($class['sections']);
            usort($class['sections'], fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
            foreach ($class['sections'] as &$section) {
                $section['subjects'] = array_values($section['subjects']);
                usort($section['subjects'], fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
            }
            unset($section);
        }
        unset($class);

        foreach ($teacherSchedule as &$dayTeachers) {
            $dayTeachers = array_values($dayTeachers);
        }
        unset($dayTeachers);

        $metrics['emptySlots'] = $this->countEmptySlots($grid);
        $metrics['unallocatedAssignments'] = count($metrics['unallocated']);
        $metrics['teacherCount'] = count($teachers);

        return [
            'name' => $this->routineName($file),
            'termLabel' => 'Imported routine',
            'days' => $days,
            'periods' => $periods,
            'classes' => $classes,
            'teachers' => array_values($teachers),
            'generationRules' => [
                'source' => 'docx-import',
                'romanClassLabelsConverted' => true,
            ],
            'generatedGrid' => $grid,
            'teacherSchedule' => $teacherSchedule,
            'metrics' => $metrics,
        ];
    }

    private function extractDocumentXml(string $path): string
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('Could not read the uploaded DOCX file.');
        }

        $entry = $this->extractZipEntry($data, 'word/document.xml');
        if ($entry === null) {
            throw new RuntimeException('The DOCX file does not contain word/document.xml.');
        }

        return $entry;
    }

    private function extractZipEntry(string $zipData, string $entryName): ?string
    {
        $eocdPosition = strrpos($zipData, "PK\x05\x06");
        if ($eocdPosition === false) {
            throw new RuntimeException('The uploaded file is not a readable DOCX archive.');
        }

        $centralDirectorySize = $this->u32(substr($zipData, $eocdPosition + 12, 4));
        $centralDirectoryOffset = $this->u32(substr($zipData, $eocdPosition + 16, 4));
        $position = $centralDirectoryOffset;
        $end = $centralDirectoryOffset + $centralDirectorySize;

        while ($position < $end && substr($zipData, $position, 4) === "PK\x01\x02") {
            $method = $this->u16(substr($zipData, $position + 10, 2));
            $compressedSize = $this->u32(substr($zipData, $position + 20, 4));
            $nameLength = $this->u16(substr($zipData, $position + 28, 2));
            $extraLength = $this->u16(substr($zipData, $position + 30, 2));
            $commentLength = $this->u16(substr($zipData, $position + 32, 2));
            $localHeaderOffset = $this->u32(substr($zipData, $position + 42, 4));
            $name = substr($zipData, $position + 46, $nameLength);

            if ($name === $entryName) {
                $localNameLength = $this->u16(substr($zipData, $localHeaderOffset + 26, 2));
                $localExtraLength = $this->u16(substr($zipData, $localHeaderOffset + 28, 2));
                $dataOffset = $localHeaderOffset + 30 + $localNameLength + $localExtraLength;
                $compressed = substr($zipData, $dataOffset, $compressedSize);

                return match ($method) {
                    0 => $compressed,
                    8 => gzinflate($compressed),
                    default => throw new RuntimeException('Unsupported DOCX compression method: '.$method),
                };
            }

            $position += 46 + $nameLength + $extraLength + $commentLength;
        }

        return null;
    }

    private function readRoutineTables(string $documentXml): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($documentXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $tablesByDay = [];
        $currentDay = null;
        $bodyNodes = $xpath->query('/w:document/w:body/*');

        foreach ($bodyNodes as $node) {
            if ($node->localName === 'p') {
                $text = $this->nodeText($xpath, $node);
                $day = self::DAY_LABELS[strtolower(trim($text))] ?? null;
                if ($day) {
                    $currentDay = $day;
                }
                continue;
            }

            if ($node->localName === 'tbl' && $currentDay) {
                $rows = [];
                foreach ($xpath->query('./w:tr', $node) as $rowNode) {
                    $row = [];
                    foreach ($xpath->query('./w:tc', $rowNode) as $cellNode) {
                        $row[] = $this->cellText($xpath, $cellNode);
                    }
                    $rows[] = $row;
                }
                if ($rows) {
                    $tablesByDay[$currentDay] = $rows;
                }
                $currentDay = null;
            }
        }

        return $tablesByDay;
    }

    private function periodsFromHeader(array $header): array
    {
        $periods = [];
        $columns = [];
        $lastClassPeriod = null;

        foreach ($header as $index => $text) {
            $normalized = preg_replace('/\s+/', ' ', trim($text));
            if (! preg_match('/^(\d+)(st|nd|rd|th)/i', $normalized, $matches)) {
                continue;
            }

            $number = (int) $matches[1];
            $key = 'P'.$number;
            $time = $this->periodTime($text);

            if ($lastClassPeriod === 'P3' && $key === 'P4') {
                $periods[] = [
                    'id' => 'break-after-p3',
                    'key' => 'BREAK',
                    'label' => 'Break',
                    'startTime' => '',
                    'endTime' => '',
                    'time' => '',
                    'type' => 'break',
                ];
            }

            $periods[] = [
                'id' => $number,
                'key' => $key,
                'label' => 'P'.$number,
                'startTime' => $time['start'],
                'endTime' => $time['end'],
                'time' => $time['label'],
                'type' => 'class',
            ];
            $columns[$key] = $index;
            $lastClassPeriod = $key;
        }

        if (empty($columns)) {
            throw new RuntimeException('Could not find period columns in the DOCX routine table.');
        }

        return ['periods' => $periods, 'columns' => $columns];
    }

    private function parseRoutineCell(string $text): ?array
    {
        $lines = array_values(array_filter(array_map(fn ($line) => trim($line), preg_split('/\R+/', $text))));
        if (empty($lines)) {
            return null;
        }

        $first = ltrim(array_shift($lines), "* \t");
        $first = preg_replace('/\s+/', ' ', $first);
        if (! preg_match('/^(Nursery|Nur|KG|XII|XI|VIII|VII|VI|IV|IX|V|III|II|I|X)\s*\(\s*([^)]*)\s*\)\s*(.*)$/i', $first, $matches)) {
            return null;
        }

        $classToken = strtoupper(trim($matches[1]));
        $section = strtoupper(trim($matches[2]));
        if ($section === '') {
            $section = 'A';
        }

        $classInfo = self::CLASS_LABELS[$classToken] ?? null;
        if (! $classInfo) {
            return null;
        }

        $tail = trim($matches[3] ?? '');
        $subjectParts = array_values(array_filter(array_merge([$tail], $lines)));
        $subject = trim(preg_replace('/\s+/', ' ', implode(' ', $subjectParts)));

        if ($subject === '') {
            $subject = 'TBA';
        }

        return [
            'classToken' => $classToken,
            'className' => $classInfo['name'],
            'sort' => $classInfo['sort'],
            'section' => $section,
            'subject' => $subject,
            'sourceLabel' => $classToken.'('.$section.')',
        ];
    }

    private function fillMissingGridDays(array &$grid, array $days, array $periodColumns): void
    {
        foreach ($grid as &$section) {
            foreach ($days as $day) {
                $section['days'][$day] ??= $this->emptyCells($periodColumns);
            }
            uksort($section['days'], fn ($a, $b) => array_search($a, $days, true) <=> array_search($b, $days, true));
        }
        unset($section);
    }

    private function fillMissingTeacherDays(array &$teacherSchedule, array $teachers, array $days, array $periodColumns): void
    {
        foreach ($days as $day) {
            foreach ($teachers as $teacher) {
                $teacherSchedule[$day][$teacher['id']] ??= [
                    'id' => $teacher['id'],
                    'name' => $teacher['name'],
                    'subject' => 'Imported',
                    'cells' => $this->emptyCells($periodColumns),
                ];
            }
        }
    }

    private function emptyCells(array $periodColumns): array
    {
        $cells = [];
        foreach (array_keys($periodColumns) as $periodKey) {
            $cells[$periodKey] = ['type' => 'empty'];
        }
        return $cells;
    }

    private function teacherId(string $teacherName, array &$teachers): string
    {
        $id = 'teacher-'.strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($teacherName)));
        $id = trim($id, '-');
        $base = $id ?: 'teacher';
        $suffix = 2;

        while (isset($teachers[$id]) && strcasecmp($teachers[$id]['name'], $teacherName) !== 0) {
            $id = $base.'-'.$suffix++;
        }

        $teachers[$id] ??= [
            'id' => $id,
            'name' => $teacherName,
            'phone' => '',
            'primarySubjects' => [],
            'subjectHint' => '',
        ];

        return $id;
    }

    private function classId(string $className): string
    {
        return 'class-'.trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $className)), '-');
    }

    private function subjectId(string $subject, string $teacherId): string
    {
        return 'subject-'.trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $subject.'-'.$teacherId)), '-');
    }

    private function sectionId(string $section): string
    {
        return 'section-'.trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $section)), '-');
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

    private function subjectCode(string $name): string
    {
        $letters = preg_replace('/[^A-Za-z0-9]/', '', $name);
        return strtoupper(substr($letters ?: 'S', 0, 4));
    }

    private function periodTime(string $text): array
    {
        $normalized = str_replace('.', ':', $text);
        if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $normalized, $matches)) {
            return [
                'start' => $matches[1],
                'end' => $matches[2],
                'label' => $matches[1].' - '.$matches[2],
            ];
        }

        return ['start' => '', 'end' => '', 'label' => ''];
    }

    private function routineName(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Imported Routine';
        return trim($name) ?: 'Imported Routine';
    }

    private function countEmptySlots(array $grid): int
    {
        $count = 0;
        foreach ($grid as $section) {
            foreach ($section['days'] as $cells) {
                foreach ($cells as $cell) {
                    if (($cell['type'] ?? 'empty') === 'empty') {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    private function nodeText(\DOMXPath $xpath, \DOMNode $node): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t', $node) as $textNode) {
            $parts[] = $textNode->textContent;
        }
        return trim(implode('', $parts));
    }

    private function cellText(\DOMXPath $xpath, \DOMNode $cellNode): string
    {
        $paragraphs = [];
        foreach ($xpath->query('.//w:p', $cellNode) as $paragraphNode) {
            $paragraphs[] = $this->nodeText($xpath, $paragraphNode);
        }

        $text = trim(implode("\n", array_filter($paragraphs, fn ($part) => trim($part) !== '')));
        if ($text !== '') {
            return $text;
        }

        return $this->nodeText($xpath, $cellNode);
    }

    private function u16(string $bytes): int
    {
        return unpack('v', $bytes)[1];
    }

    private function u32(string $bytes): int
    {
        return unpack('V', $bytes)[1];
    }
}
