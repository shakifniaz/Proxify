<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertTriangle,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CheckCircle2,
    GripVertical,
    Layers,
    Pencil,
    Plus,
    RefreshCw,
    Repeat,
    Settings2,
    Table2,
    Trash2,
} from 'lucide-vue-next';

const props = defineProps({
    accessRole: { type: String, default: 'admin' },
    readOnly: { type: Boolean, default: false },
    noActiveRoutine: { type: Boolean, default: false },
    currentTeacherName: { type: String, default: '' },
    currentClassLabel: { type: String, default: '' },
    routine: { type: Object, default: () => ({}) },
    days: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    legend: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
    teacherSchedule: { type: Object, default: () => ({}) },
    classes: { type: Array, default: () => [] },
    teacherPool: { type: Array, default: () => [] },
    generationRules: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({}) },
    generatedGrid: { type: Object, default: () => ({}) },
    proxyContext: { type: Object, default: null },
    activeProxyNotice: { type: Object, default: null },
});

const isTeacherViewer = computed(() => props.accessRole === 'teacher');
const isStudentViewer = computed(() => props.accessRole === 'student');
const canEditRoutine = computed(() => !props.readOnly && !props.noActiveRoutine);
const viewMode = ref(isStudentViewer.value ? 'classes' : 'teachers');
const routineSource = ref(props.proxyContext ? 'proxy' : 'routine');
const selectedDay = ref(props.proxyContext?.day ?? props.days[0] ?? 'Sun');
const dayNames = { Sun: 'Sunday', Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday', Fri: 'Friday', Sat: 'Saturday' };
const routinePageTitle = computed(() => {
    if (isStudentViewer.value) return 'My Routine';
    if (isTeacherViewer.value) return 'Routine';
    if (props.noActiveRoutine) return 'Routine';
    return props.proxyContext ? `${props.proxyContext.name} - Proxy Routine` : props.routine.name;
});
const proxySourceTabs = [
    { key: 'proxy', label: 'Proxy routine', icon: Repeat },
    { key: 'original', label: 'Original routine', icon: CalendarDays },
];

function defaultDailyPeriodsByDay(value = 7) {
    return Object.fromEntries(props.days.map((day) => [day, Number(value) || 0]));
}

function applyPeriodsToAllDays(section) {
    const value = Number(section.dailyPeriods) || 0;
    section.dailyPeriodsByDay = Object.fromEntries(props.days.map((day) => [day, value]));
}
const subjectPalette = ['#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#4f46e5', '#16a34a', '#ca8a04', '#be123c', '#0f766e', '#9333ea'];

function defaultSubjectColor(name = 'Subject') {
    const key = String(name || 'Subject').toLowerCase();
    let hash = 0;
    for (let i = 0; i < key.length; i++) hash = ((hash << 5) - hash + key.charCodeAt(i)) | 0;
    return subjectPalette[Math.abs(hash) % subjectPalette.length];
}

function displaySubject(cell = {}) {
    return cell.subject && cell.subject !== 'TBA' ? cell.subject : '';
}

function hexToRgb(hex = '#2563eb') {
    const normalized = hex.replace('#', '');
    const full = normalized.length === 3 ? normalized.split('').map((part) => part + part).join('') : normalized;
    const value = Number.parseInt(full, 16);
    if (Number.isNaN(value)) return { r: 37, g: 99, b: 235 };
    return { r: (value >> 16) & 255, g: (value >> 8) & 255, b: value & 255 };
}

function subjectCellStyle(cell = {}) {
    if (!cell || cell.type !== 'class') return {};
    const color = cell.color || defaultSubjectColor(cell.subject);
    const { r, g, b } = hexToRgb(color);
    return {
        backgroundColor: `rgba(${r}, ${g}, ${b}, 0.14)`,
        borderColor: `rgba(${r}, ${g}, ${b}, 0.34)`,
    };
}

const romanNumbers = { xii: 12, xi: 11, x: 10, ix: 9, viii: 8, vii: 7, vi: 6, v: 5, iv: 4, iii: 3, ii: 2, i: 1 };

function classBaseName(label = '') {
    return String(label || '')
        .replace(/\s*\([^)]*\)\s*/g, ' ')
        .replace(/\bsection\s+[a-z0-9]+\b/gi, ' ')
        .replace(/\s+[a-z]$/i, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function classSectionName(label = '') {
    const value = String(label || '').trim();
    const parenthesized = value.match(/\(([^)]+)\)/);
    if (parenthesized) return parenthesized[1].trim();

    const sectionWord = value.match(/\bsection\s+([a-z0-9]+)\b/i);
    if (sectionWord) return sectionWord[1].trim();

    const trailingLetter = value.match(/(?:class\s*)?(?:nursery|nur|kg|\d+|xii|xi|x|ix|viii|vii|vi|v|iv|iii|ii|i)\s*([a-z])$/i);
    return trailingLetter?.[1]?.trim() ?? '';
}

function classRank(label = '') {
    const normalized = classBaseName(label).toLowerCase().replace(/\bclass\b/g, '').trim();

    if (/\b(nursery|nur)\b/.test(normalized)) return 0;
    if (/\bkg\b|kindergarten/.test(normalized)) return 1;

    const digit = normalized.match(/\b(\d{1,2})\b/);
    if (digit) return Number(digit[1]) + 1;

    const roman = normalized.match(/\b(xii|xi|x|ix|viii|vii|vi|v|iv|iii|ii|i)\b/);
    if (roman) return romanNumbers[roman[1]] + 1;

    return 1000;
}

function compareClassLabels(a, b) {
    const labelA = typeof a === 'string' ? a : a?.label;
    const labelB = typeof b === 'string' ? b : b?.label;
    const rankA = classRank(labelA);
    const rankB = classRank(labelB);

    if (rankA !== rankB) return rankA - rankB;

    const baseCompare = classBaseName(labelA).localeCompare(classBaseName(labelB), undefined, { sensitivity: 'base', numeric: true });
    if (baseCompare !== 0) return baseCompare;

    return classSectionName(labelA).localeCompare(classSectionName(labelB), undefined, { sensitivity: 'base', numeric: true });
}

const localTeacherSchedule = ref(Object.keys(props.teacherSchedule).length ? JSON.parse(JSON.stringify(props.teacherSchedule)) : { [selectedDay.value]: props.teachers.map((teacher) => ({ ...teacher, cells: { ...teacher.cells } })) });
const isProxyRoutine = computed(() => Boolean(props.proxyContext));
const isOriginalRoutineSource = computed(() => isProxyRoutine.value && routineSource.value === 'original');
const displayedTeacherSchedule = computed(() =>
    isOriginalRoutineSource.value ? (props.proxyContext?.originalTeacherSchedule ?? {}) : localTeacherSchedule.value
);
const displayedGeneratedGrid = computed(() =>
    isOriginalRoutineSource.value ? (props.proxyContext?.originalGeneratedGrid ?? {}) : (props.generatedGrid ?? {})
);
const gridTeachers = computed(() => displayedTeacherSchedule.value[selectedDay.value] ?? []);
const currentTeacherRows = computed(() => {
    if (!props.currentTeacherName) return [];
    return Object.entries(displayedTeacherSchedule.value ?? {}).flatMap(([day, teachers]) =>
        teachers
            .filter((teacher) => String(teacher.name || '').toLowerCase() === props.currentTeacherName.toLowerCase())
            .map((teacher) => ({ day, ...teacher }))
    );
});
const currentTeacherDutyGrid = computed(() =>
    props.days.map((day) => {
        const teacher = (displayedTeacherSchedule.value[day] ?? []).find((item) =>
            String(item.name || '').toLowerCase() === props.currentTeacherName.toLowerCase()
        );

        return {
            day,
            cells: teacher?.cells ?? {},
        };
    })
);
const originalTeacherCellLookup = computed(() => {
    const lookup = {};
    Object.entries(props.proxyContext?.originalTeacherSchedule ?? {}).forEach(([day, teachers]) => {
        teachers.forEach((teacher) => {
            const teacherId = String(teacher.id ?? teacher.name ?? '');
            if (!teacherId) return;
            Object.entries(teacher.cells ?? {}).forEach(([periodKey, cell]) => {
                lookup[`${day}|${teacherId}|${periodKey}`] = normalizedCell(cell, teacher);
            });
        });
    });
    return lookup;
});
const originalClassCellLookup = computed(() => {
    const lookup = {};
    Object.values(props.proxyContext?.originalGeneratedGrid ?? {}).forEach((section) => {
        Object.entries(section.days ?? {}).forEach(([day, cells]) => {
            Object.entries(cells ?? {}).forEach(([periodKey, cell]) => {
                const label = cell.classLabel ?? section.label ?? '';
                if (!label) return;
                lookup[`${day}|${label}|${periodKey}`] = normalizedCell({ ...cell, classLabel: label });
            });
        });
    });
    return lookup;
});

const teacherPool = ref(
    (props.teacherPool.length ? props.teacherPool : props.teachers).map((teacher, index) => ({
        id: teacher.id ?? index + 1,
        name: teacher.name,
        phone: teacher.phone ?? '',
        subjectHint: teacher.subjectHint ?? teacher.subject ?? (teacher.primarySubjects || []).join(', '),
        primarySubjects: teacher.primarySubjects ?? [],
    }))
);

const classBlueprint = ref(
    props.classes.length
        ? props.classes.flatMap((cls) =>
              (cls.sections || []).map((section) => ({
                  id: `${cls.id}-${section.id}`,
                  classId: cls.id,
                  sectionId: section.id,
                  className: cls.name,
                  sectionName: section.name,
                  label: `${cls.name} ${section.name}`,
                  dailyPeriods: section.dailyPeriods ?? cls.dailyPeriods ?? 7,
                  dailyPeriodsByDay: {
                      ...defaultDailyPeriodsByDay(section.dailyPeriods ?? cls.dailyPeriods ?? 7),
                      ...(cls.dailyPeriodsByDay ?? {}),
                      ...(section.dailyPeriodsByDay ?? {}),
                  },
                  classTeacherId: section.classTeacherId ?? null,
                  subjects: (section.subjects || []).map((subject) => ({ ...subject })),
              }))
          )
        : (props.classOptions.length ? props.classOptions.slice(0, 6) : ['Class 6A', 'Class 7A', 'Class 8A']).map((label, index) => ({
              id: index + 1,
              label,
              dailyPeriods: index < 2 ? 6 : 7,
              dailyPeriodsByDay: defaultDailyPeriodsByDay(index < 2 ? 6 : 7),
              classTeacherId: teacherPool.value[index % Math.max(1, teacherPool.value.length)]?.id ?? null,
              subjects: props.legend.slice(0, 4).map((item, subjectIndex) => ({
                  id: `${index + 1}-${subjectIndex + 1}`,
                  name: item.subject,
                  teacherId: teacherPool.value[subjectIndex % Math.max(1, teacherPool.value.length)]?.id ?? null,
                  weeklyPeriods: subjectIndex === 0 ? 4 : '',
                  autoBalance: subjectIndex !== 0,
                  color: item.color || defaultSubjectColor(item.subject),
              })),
          }))
);

classBlueprint.value.sort(compareClassLabels);

const classBlueprintOrder = computed(() =>
    Object.fromEntries(classBlueprint.value.map((section, index) => [classBaseName(section.label).toLowerCase(), index]))
);

function compareRoutineClassLabels(a, b) {
    const labelA = typeof a === 'string' ? a : a?.label;
    const labelB = typeof b === 'string' ? b : b?.label;
    const rankA = classRank(labelA);
    const rankB = classRank(labelB);

    if (rankA !== rankB) return rankA - rankB;

    if (rankA >= 1000) {
        const orderA = classBlueprintOrder.value[classBaseName(labelA).toLowerCase()] ?? 9999;
        const orderB = classBlueprintOrder.value[classBaseName(labelB).toLowerCase()] ?? 9999;
        if (orderA !== orderB) return orderA - orderB;
    }

    return compareClassLabels(a, b);
}

const periodTemplates = ref(
    props.periods.map((period, index) => ({
        id: index + 1,
        label: period.label,
        startTime: period.time?.split(/[^0-9:]+/)?.[0] ?? '',
        endTime: period.time?.split(/[^0-9:]+/)?.[1] ?? '',
        type: period.type === 'break' ? 'break' : 'class',
    }))
);

const generationRules = ref({
    maxConsecutivePeriods: props.generationRules.maxConsecutivePeriods ?? 3,
    preferGapBetweenPeriods: props.generationRules.preferGapBetweenPeriods ?? true,
    autoBalanceUnsetSubjectLoads: props.generationRules.autoBalanceUnsetSubjectLoads ?? true,
    keepClassTeacherFirstPeriod: props.generationRules.keepClassTeacherFirstPeriod ?? true,
    flagUnallocatedSlots: props.generationRules.flagUnallocatedSlots ?? true,
});

const gridStyle = computed(() => ({
    gridTemplateColumns: `200px repeat(${props.periods.length}, minmax(116px, 1fr))`,
}));

const classGridStyle = computed(() => ({
    gridTemplateColumns: `132px repeat(${props.periods.length}, minmax(128px, 1fr))`,
}));

const teacherLookup = computed(() =>
    Object.fromEntries(teacherPool.value.map((teacher) => [String(teacher.id), teacher.name]))
);

const classRoutineSections = computed(() => {
    const sections = new Map();

    const ensureSection = (label) => {
        const cleanLabel = String(label || '').trim();
        if (!cleanLabel) return null;
        if (!sections.has(cleanLabel)) {
            sections.set(cleanLabel, {
                id: cleanLabel.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
                label: cleanLabel,
                dailyPeriods: 0,
                dailyPeriodsByDay: {},
                days: {},
            });
        }
        return sections.get(cleanLabel);
    };

    Object.values(displayedGeneratedGrid.value ?? {}).forEach((section) => {
        const base = ensureSection(section.label);
        if (!base) return;
        base.id = section.id ?? base.id;
        base.dailyPeriods = section.dailyPeriods ?? base.dailyPeriods;
        base.dailyPeriodsByDay = { ...(base.dailyPeriodsByDay ?? {}), ...(section.dailyPeriodsByDay ?? {}) };
        base.days = isProxyRoutine.value
            ? {}
            : Object.fromEntries(Object.entries(section.days ?? {}).map(([day, cells]) => [day, { ...cells }]));
    });

    props.classOptions.forEach((label) => ensureSection(label));

    Object.entries(displayedTeacherSchedule.value ?? {}).forEach(([day, teachers]) => {
        teachers.forEach((teacher) => {
            Object.entries(teacher.cells ?? {}).forEach(([periodKey, cell]) => {
                if (!cell?.classLabel || !['class', 'proxy', 'unresolved'].includes(cell.type)) return;
                const section = ensureSection(cell.classLabel);
                if (!section) return;
                section.days[day] ??= {};
                section.days[day][periodKey] = {
                    ...cell,
                    teacherId: cell.teacherId ?? teacher.id,
                    teacherName: cell.teacherName ?? teacher.name,
                };
            });
        });
    });

    return Array.from(sections.values()).sort(compareRoutineClassLabels);
});
const visibleClassRoutineSections = computed(() => {
    if (!isStudentViewer.value) return classRoutineSections.value;
    if (props.currentClassLabel) {
        return classRoutineSections.value.filter((section) => section.label === props.currentClassLabel);
    }

    return classRoutineSections.value.slice(0, 1);
});

function classCell(section, day, periodKey) {
    return section.days?.[day]?.[periodKey] ?? { type: 'empty' };
}

function teacherNameForCell(cell = {}) {
    if (cell.teacherName) return cell.teacherName;
    if (!cell.teacherId) return '';
    return teacherLookup.value[String(cell.teacherId)] ?? 'Unassigned';
}

function normalizedCell(cell = {}, teacher = {}) {
    if (!cell || cell.type === 'empty') {
        return { type: 'empty', subject: '', classLabel: '', teacherId: '' };
    }

    return {
        type: cell.type ?? 'empty',
        subject: String(cell.subject ?? '').trim().toLowerCase(),
        classLabel: String(cell.classLabel ?? '').trim().toLowerCase(),
        teacherId: String(cell.teacherId ?? teacher.id ?? '').trim(),
    };
}

function cellsDiffer(a = {}, b = {}) {
    const left = normalizedCell(a);
    const right = normalizedCell(b);
    return ['type', 'subject', 'classLabel', 'teacherId'].some((key) => left[key] !== right[key]);
}

function teacherCellChanged(teacher = {}, periodKey) {
    if (!isProxyRoutine.value || isOriginalRoutineSource.value) return false;
    const teacherId = String(teacher.id ?? teacher.name ?? '');
    const original = originalTeacherCellLookup.value[`${selectedDay.value}|${teacherId}|${periodKey}`] ?? { type: 'empty' };
    const current = teacher.cells?.[periodKey] ?? { type: 'empty' };
    return cellsDiffer(current, original);
}

function teacherCellClasses(teacher = {}, periodKey) {
    const cell = teacher.cells?.[periodKey];
    const changed = teacherCellChanged(teacher, periodKey);
    if (!canEditRoutine.value && cell?.type === 'unresolved') {
        return 'border border-stone-200 bg-stone-100';
    }

    if ((!cell || cell.type === 'empty') && changed) {
        return 'border-[3px] border-amber-400 bg-amber-50 font-bold shadow-[inset_5px_0_0_rgba(217,119,6,0.55)] ring-2 ring-amber-100';
    }

    return cellClasses(cell, changed);
}

function readonlyCellClasses(cell = {}) {
    if (!canEditRoutine.value && cell?.type === 'unresolved') {
        return 'border border-stone-200 bg-stone-100';
    }

    return cellClasses(cell);
}

function classCellChanged(cell = {}, section = {}, day, periodKey) {
    if (!isProxyRoutine.value || isOriginalRoutineSource.value) return false;
    const label = cell.classLabel ?? section.label ?? '';
    const original = originalClassCellLookup.value[`${day}|${label}|${periodKey}`] ?? { type: 'empty' };
    return cellsDiffer(cell, original);
}

function classDisplayCellClasses(section = {}, day, period) {
    if (!isScheduledClassPeriod(section, day, period)) return 'border border-stone-200 bg-stone-50';
    const cell = classCell(section, day, period.key);
    const changed = classCellChanged(cell, section, day, period.key);
    if (!canEditRoutine.value && isClassCellUnresolved(cell)) {
        return 'border border-stone-200 bg-stone-50';
    }

    if ((!cell || cell.type === 'empty') && changed) {
        return 'border-[3px] border-amber-400 bg-amber-50 font-bold shadow-[inset_5px_0_0_rgba(217,119,6,0.55)] ring-2 ring-amber-100';
    }

    return classRoutineCellClasses(cell, changed);
}

function isClassCellUnresolved(cell = {}) {
    if (!cell || cell.type === 'empty' || cell.type === 'unresolved') return true;
    if (cell.type !== 'class' && cell.type !== 'proxy') return false;
    return !cell.teacherId && !displaySubject(cell);
}

function sectionPeriodLimit(section, day) {
    if (Object.prototype.hasOwnProperty.call(section.dailyPeriodsByDay ?? {}, day)) {
        return Math.max(0, Number(section.dailyPeriodsByDay[day]) || 0);
    }

    const configured = classBlueprint.value.find((item) => item.label === section.label);
    if (configured && Object.prototype.hasOwnProperty.call(configured.dailyPeriodsByDay ?? {}, day)) {
        return Math.max(0, Number(configured.dailyPeriodsByDay[day]) || 0);
    }

    return Math.max(0, Number(section.dailyPeriods || configured?.dailyPeriods || props.periods.filter((period) => period.type !== 'break').length) || 0);
}

function classPeriodIndex(periodKey) {
    return props.periods.filter((period) => period.type !== 'break').findIndex((period) => period.key === periodKey);
}

function isScheduledClassPeriod(section, day, period) {
    if (period.type === 'break') return false;
    const index = classPeriodIndex(period.key);
    return index >= 0 && index < sectionPeriodLimit(section, day);
}

const classUnresolvedCells = computed(() =>
    visibleClassRoutineSections.value.flatMap((section) =>
        props.days.flatMap((day) =>
            props.periods
                .filter((period) => isScheduledClassPeriod(section, day, period))
                .filter((period) => isClassCellUnresolved(classCell(section, day, period.key)))
                .map((period) => ({
                    classLabel: section.label,
                    day,
                    periodKey: period.key,
                    periodLabel: period.label,
                    periodTime: period.time,
                }))
        )
    )
);

const unresolvedCount = computed(() =>
    visibleClassRoutineSections.value.length ? classUnresolvedCells.value.length : (props.metrics.unallocatedAssignments ?? 0)
);
const teacherGapCount = computed(() =>
    gridTeachers.value.reduce(
        (sum, teacher) => sum + Object.values(teacher.cells ?? {}).filter((cell) => !cell || cell.type === 'empty').length,
        0
    )
);

const assignedSubjectRows = computed(() =>
    classBlueprint.value.reduce((sum, section) => sum + section.subjects.filter((subject) => subject.teacherId).length, 0)
);
const routineViewTabs = computed(() => [
    { key: 'teachers', label: 'Teachers routine', icon: Table2, count: gridTeachers.value.length, visible: !isStudentViewer.value },
    { key: 'mine', label: 'My classes', icon: CheckCircle2, count: currentTeacherRows.value.length, visible: isTeacherViewer.value },
    { key: 'classes', label: 'Class routine', icon: Layers, count: visibleClassRoutineSections.value.length, visible: !isStudentViewer.value },
    { key: 'settings', label: 'Generation settings', icon: Settings2, count: classBlueprint.value.length, visible: !isProxyRoutine.value && !props.readOnly },
].filter((tab) => tab.visible));
const viewTabIndex = computed(() => Math.max(0, routineViewTabs.value.findIndex((tab) => tab.key === viewMode.value)));
const viewTabIndicatorStyle = computed(() => ({
    width: `calc((100% - 0.75rem) / ${Math.max(1, routineViewTabs.value.length)})`,
    transform: `translateX(${viewTabIndex.value * 100}%)`,
}));
const proxySourceIndex = computed(() => Math.max(0, proxySourceTabs.findIndex((tab) => tab.key === routineSource.value)));
const proxySourceIndicatorStyle = computed(() => ({
    width: `calc((100% - 0.75rem) / ${proxySourceTabs.length})`,
    transform: `translateX(${proxySourceIndex.value * 100}%)`,
}));

function selectDay(day) {
    selectedDay.value = day;
}

function stepDay(direction) {
    const i = props.days.indexOf(selectedDay.value);
    const next = (i + direction + props.days.length) % props.days.length;
    selectedDay.value = props.days[next];
}

function subjectCode(name = '') {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function teacherName(id) {
    return teacherPool.value.find((teacher) => teacher.id === id)?.name ?? 'Unassigned';
}

function cellClasses(cell, changed = false) {
    if (!cell || cell.type === 'empty') return 'border border-stone-200 bg-stone-100';
    if (cell.type === 'unresolved') return 'border-2 border-red-300 bg-red-50 font-bold ring-2 ring-red-100';
    if (cell.proxyChangeKind === 'swap') return 'border-[3px] border-[#09B884] bg-[#8BED9A]/15 font-bold shadow-[inset_5px_0_0_rgba(9,184,132,0.45)] ring-2 ring-[#8BED9A]/35';
    if (cell.proxyChangeKind === 'manual' || cell.proxyChangeKind === 'moved') return 'border-[3px] border-amber-400 bg-amber-50 font-bold shadow-[inset_5px_0_0_rgba(217,119,6,0.55)] ring-2 ring-amber-100';
    if (cell.type === 'proxy') return 'border-[3px] border-[#09B884] bg-[#8BED9A]/15 font-bold shadow-[inset_5px_0_0_rgba(9,184,132,0.45)] ring-2 ring-[#8BED9A]/35';
    if (changed) return 'border-[3px] border-amber-400 bg-amber-50 font-bold shadow-[inset_5px_0_0_rgba(217,119,6,0.55)] ring-2 ring-amber-100';
    return 'border bg-white hover:brightness-[0.98]';
}

function classRoutineCellClasses(cell, changed = false) {
    if (isClassCellUnresolved(cell)) return 'border-2 border-red-300 bg-red-50 font-bold ring-2 ring-red-100';
    if (cell.proxyChangeKind === 'swap') return 'border-[3px] border-[#09B884] bg-[#8BED9A]/15 font-bold shadow-[inset_5px_0_0_rgba(9,184,132,0.45)] ring-2 ring-[#8BED9A]/35';
    if (cell.proxyChangeKind === 'manual' || cell.proxyChangeKind === 'moved') return 'border-[3px] border-amber-400 bg-amber-50 font-bold shadow-[inset_5px_0_0_rgba(217,119,6,0.55)] ring-2 ring-amber-100';
    if (cell.type === 'proxy') return 'border-[3px] border-[#09B884] bg-[#8BED9A]/15 font-bold shadow-[inset_5px_0_0_rgba(9,184,132,0.45)] ring-2 ring-[#8BED9A]/35';
    if (changed) return 'border-[3px] border-amber-400 bg-amber-50 font-bold shadow-[inset_5px_0_0_rgba(217,119,6,0.55)] ring-2 ring-amber-100';
    return 'border bg-white';
}

const dragSource = ref(null);
const dragOverKey = ref(null);
const teacherOrderDragIndex = ref(null);
const classOrderDragIndex = ref(null);

function cellKey(teacherIndex, periodKey) {
    return `${teacherIndex}-${periodKey}`;
}

function onDragStart(teacherIndex, periodKey, event) {
    if (!canEditRoutine.value) return;
    dragSource.value = { teacherIndex, periodKey };
    event.dataTransfer?.setData?.('text/plain', cellKey(teacherIndex, periodKey));
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
}

function onDragOverCell(teacherIndex, periodKey, event) {
    if (!canEditRoutine.value) return;
    event.preventDefault();
    dragOverKey.value = cellKey(teacherIndex, periodKey);
}

function onDragLeaveCell() {
    dragOverKey.value = null;
}

function onDrop(teacherIndex, periodKey, event) {
    event.preventDefault();
    if (!canEditRoutine.value || isOriginalRoutineSource.value) return;
    dragOverKey.value = null;
    const from = dragSource.value;
    dragSource.value = null;
    if (!from) return;

    const fromCell = gridTeachers.value[from.teacherIndex].cells[from.periodKey];
    const toCell = gridTeachers.value[teacherIndex].cells[periodKey];
    gridTeachers.value[from.teacherIndex].cells[from.periodKey] = toCell && toCell.type !== 'empty'
        ? { ...toCell, proxyChanged: true, proxyChangeKind: 'moved', proxyChangeLabel: 'Moved' }
        : toCell;
    gridTeachers.value[teacherIndex].cells[periodKey] = fromCell && fromCell.type !== 'empty'
        ? { ...fromCell, proxyChanged: true, proxyChangeKind: 'moved', proxyChangeLabel: 'Moved' }
        : fromCell;
}

function onDragEnd() {
    dragSource.value = null;
    dragOverKey.value = null;
}

function onTeacherOrderDragStart(index, event) {
    teacherOrderDragIndex.value = index;
    event.dataTransfer?.setData?.('text/plain', String(index));
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
}

function onTeacherOrderDrop(index, event) {
    event.preventDefault();
    const from = teacherOrderDragIndex.value;
    teacherOrderDragIndex.value = null;
    if (from === null || from === index) return;

    const [teacher] = teacherPool.value.splice(from, 1);
    teacherPool.value.splice(index, 0, teacher);
}

function onTeacherOrderDragEnd() {
    teacherOrderDragIndex.value = null;
}

function onClassOrderDragStart(index, event) {
    classOrderDragIndex.value = index;
    event.dataTransfer?.setData?.('text/plain', String(index));
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
}

function onClassOrderDrop(index, event) {
    event.preventDefault();
    const from = classOrderDragIndex.value;
    classOrderDragIndex.value = null;
    if (from === null || from === index) return;

    const [section] = classBlueprint.value.splice(from, 1);
    classBlueprint.value.splice(index, 0, section);
}

function onClassOrderDragEnd() {
    classOrderDragIndex.value = null;
}

function sortClassBlueprint() {
    classBlueprint.value.sort(compareClassLabels);
}

const editing = ref(null);

function openEditor(teacherIndex, periodKey) {
    if (!canEditRoutine.value || isOriginalRoutineSource.value) return;
    const cell = gridTeachers.value[teacherIndex].cells[periodKey] ?? { type: 'empty' };
    editing.value = {
        teacherIndex,
        periodKey,
        subject: cell.subject ?? '',
        classLabel: cell.classLabel ?? '',
        type: cell.type === 'unresolved' ? 'class' : cell.type,
        isNew: cell.type === 'empty',
    };
}

function closeEditor() {
    editing.value = null;
}

function saveEditor() {
    if (!editing.value || !editing.value.subject || !editing.value.classLabel) return;
    const { teacherIndex, periodKey, subject, classLabel } = editing.value;
    const teacher = gridTeachers.value[teacherIndex];
    gridTeachers.value[teacherIndex].cells[periodKey] = {
        type: 'class',
        subject,
        classLabel,
        teacherId: teacher.id,
        teacherName: teacher.name,
        proxyChanged: true,
        proxyChangeKind: 'manual',
        proxyChangeLabel: editing.value.isNew ? 'Added' : 'Edited',
    };
    closeEditor();
}

const editingTeacherName = computed(() => (editing.value ? gridTeachers.value[editing.value.teacherIndex].name : ''));
const editingPeriodLabel = computed(() => {
    if (!editing.value) return '';
    return props.periods.find((period) => period.key === editing.value.periodKey)?.label ?? '';
});

function addSubject(section) {
    section.subjects.push({ id: Date.now(), name: 'New Subject', teacherId: null, weeklyPeriods: '', autoBalance: true, color: defaultSubjectColor('New Subject') });
}

function removeSubject(section, index) {
    section.subjects.splice(index, 1);
}

function addTiming(type = 'class') {
    periodTemplates.value.push({ id: Date.now(), label: type === 'break' ? 'Break' : `P${periodTemplates.value.length + 1}`, startTime: '', endTime: '', type });
}

function removeTiming(index) {
    periodTemplates.value.splice(index, 1);
}
function normalizeTeacherSubjects(value) {
    if (Array.isArray(value)) return value.filter(Boolean);
    return String(value || '')
        .split(',')
        .map((subject) => subject.trim())
        .filter(Boolean);
}

function classesForRegeneration() {
    const grouped = new Map();

    classBlueprint.value.forEach((section, index) => {
        const classId = section.classId ?? `class-${index + 1}`;
        if (!grouped.has(classId)) {
            const className = section.className ?? (section.label.replace(/\s+Section\s+.*/i, '').trim() || section.label);
            grouped.set(classId, {
                id: classId,
                name: className,
                dailyPeriods: Math.max(1, ...Object.values(section.dailyPeriodsByDay ?? { default: section.dailyPeriods }).map((value) => Number(value) || 0)),
                dailyPeriodsByDay: section.dailyPeriodsByDay ?? defaultDailyPeriodsByDay(section.dailyPeriods),
                sections: [],
            });
        }

        grouped.get(classId).sections.push({
            id: section.sectionId ?? section.id,
            name: section.sectionName ?? (section.label.replace(grouped.get(classId).name, '').trim() || 'Section A'),
            dailyPeriods: Math.max(1, ...Object.values(section.dailyPeriodsByDay ?? { default: section.dailyPeriods }).map((value) => Number(value) || 0)),
            dailyPeriodsByDay: section.dailyPeriodsByDay ?? defaultDailyPeriodsByDay(section.dailyPeriods),
            classTeacherId: section.classTeacherId,
            subjects: section.subjects,
        });
    });

    return Array.from(grouped.values());
}

function regenerateRoutine() {
    if (!canEditRoutine.value) return;
    router.post(`/routines/${props.routine.id}/regenerate`, {
        name: props.routine.name,
        termLabel: props.routine.term,
        days: props.days,
        classes: classesForRegeneration(),
        teachers: teacherPool.value.map((teacher) => ({
            ...teacher,
            primarySubjects: normalizeTeacherSubjects(teacher.subjectHint),
        })),
        periods: periodTemplates.value,
        generationRules: generationRules.value,
    }, { preserveScroll: true });
}

function buildProxyGeneratedGridPayload() {
    const grid = JSON.parse(JSON.stringify(props.generatedGrid ?? {}));
    Object.values(grid).forEach((section) => {
        section.days = Object.fromEntries(props.days.map((day) => [day, {}]));
    });
    const byLabel = Object.fromEntries(Object.entries(grid).map(([key, section]) => [section.label, key]));

    Object.entries(localTeacherSchedule.value ?? {}).forEach(([day, teachers]) => {
        teachers.forEach((teacher) => {
            Object.entries(teacher.cells ?? {}).forEach(([periodKey, cell]) => {
                if (!cell?.classLabel || !['class', 'proxy', 'unresolved'].includes(cell.type)) return;
                const sectionKey = cell.sectionKey ?? byLabel[cell.classLabel];
                if (!sectionKey || !grid[sectionKey]) return;

                grid[sectionKey].days ??= {};
                grid[sectionKey].days[day] ??= {};
                grid[sectionKey].days[day][periodKey] = {
                    ...cell,
                    teacherId: cell.type === 'unresolved' ? null : (cell.teacherId ?? teacher.id),
                    teacherName: cell.type === 'unresolved' ? null : (cell.teacherName ?? teacher.name),
                    classLabel: cell.classLabel,
                };
            });
        });
    });

    return grid;
}

function saveProxyRoutine() {
    if (!props.proxyContext?.id) return;

    router.put(`/proxy-manager/${props.proxyContext.id}/routine`, {
        teacherSchedule: localTeacherSchedule.value,
        generatedGrid: buildProxyGeneratedGridPayload(),
    }, { preserveScroll: true });
}

function approveProxyRoutine() {
    if (!props.proxyContext?.id) return;
    router.put(`/proxy-manager/${props.proxyContext.id}/routine`, {
        teacherSchedule: localTeacherSchedule.value,
        generatedGrid: buildProxyGeneratedGridPayload(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            router.post(`/proxy-manager/${props.proxyContext.id}/approve`, {}, { preserveScroll: true });
        },
    });
}
</script>

<template>
    <AppLayout :title="routinePageTitle">
        <div class="space-y-5">
            <div v-if="noActiveRoutine" class="surface-card overflow-hidden">
                <div class="grid gap-px bg-stone-200/80 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="bg-white p-8">
                        <div class="flex items-start gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#8BED9A]/18 text-[#09B884]">
                                <CalendarDays class="h-7 w-7" />
                            </div>
                            <div>
                                <p class="text-xl font-black text-[#1e2924]">No routine is active right now</p>
                                <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-600">
                                    Class and teacher routines are shown here once the admin marks a routine as active.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-[#8BED9A]/12 p-6">
                        <div class="rounded-2xl border border-[#8BED9A]/60 bg-white p-5">
                            <p class="text-sm font-black text-[#1e2924]">Routine availability</p>
                            <p class="mt-2 text-sm font-semibold text-slate-600">Please check back after the active academic routine has been published.</p>
                        </div>
                    </div>
                </div>
            </div>

            <template v-else>
            <div v-if="!readOnly" class="surface-card flex flex-wrap items-center justify-between gap-4 p-4">
                <div>
                    <h2 class="page-title">
                        {{ readOnly ? routinePageTitle : (isProxyRoutine ? proxyContext.name : routine.name) }}
                    </h2>
                    <p v-if="readOnly" class="mt-1 text-sm font-semibold text-slate-500">
                        {{ isStudentViewer ? 'Your class routine' : isTeacherViewer ? 'Published teacher and class routine' : 'Published routine' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link v-if="!readOnly" :href="isProxyRoutine ? '/proxy-manager' : '/routines'" class="btn-secondary">
                        {{ isProxyRoutine ? 'Proxy manager' : 'All routines' }}
                    </Link>
                    <template v-if="isProxyRoutine && !readOnly">
                        <button type="button" class="btn-secondary" :disabled="isOriginalRoutineSource" @click="saveProxyRoutine">Save proxy draft</button>
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 text-sm font-black shadow-md transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="proxyContext.approvedAt ? 'border border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#1e2924]' : 'bg-[#1e2924] text-white shadow-[#1e2924]/20 ring-2 ring-[#8BED9A]/45 hover:-translate-y-0.5 hover:bg-[#1e2924]/90'"
                            :disabled="isOriginalRoutineSource || proxyContext.approvedAt"
                            @click="approveProxyRoutine"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                            {{ proxyContext.approvedAt ? 'Approved' : 'Approve proxy routine' }}
                        </button>
                    </template>
                    <template v-else-if="!readOnly">
                        <button type="button" class="btn-secondary">Versions</button>
                        <button type="button" class="btn-primary">Save routine</button>
                    </template>
                </div>
            </div>

            <div v-if="isProxyRoutine && !proxyContext.approvedAt && !readOnly" class="rounded-2xl border border-[#8BED9A]/70 bg-[#8BED9A]/14 p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-black text-[#1e2924]">Approve to apply this proxy routine</p>
                        <p class="mt-1 text-sm font-semibold text-slate-600">Until this is approved, the active routine will not use these proxy changes.</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#1e2924] px-5 text-sm font-black text-white shadow-md shadow-[#1e2924]/20 ring-2 ring-[#8BED9A]/45 transition hover:-translate-y-0.5 hover:bg-[#1e2924]/90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isOriginalRoutineSource"
                        @click="approveProxyRoutine"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        Approve proxy routine
                    </button>
                </div>
            </div>

            <div v-if="isProxyRoutine && !readOnly" class="surface-card p-2">
                <div class="relative grid w-full overflow-hidden rounded-2xl border border-[#8BED9A]/50 bg-[#8BED9A]/10 p-1.5 shadow-sm shadow-[#8BED9A]/20 sm:grid-cols-2">
                    <div
                        class="absolute inset-y-1.5 left-1.5 rounded-xl bg-[#1e2924] shadow-lg shadow-[#1e2924]/20 transition-transform duration-300 ease-out"
                        :style="proxySourceIndicatorStyle"
                    ></div>
                    <button
                        v-for="tab in proxySourceTabs"
                        :key="tab.key"
                        type="button"
                        class="relative z-10 flex min-h-14 items-center justify-center gap-3 rounded-xl px-3 text-sm font-black transition-colors duration-300"
                        :class="routineSource === tab.key ? 'text-white' : 'text-[#1e2924] hover:text-[#09B884]'"
                        @click="routineSource = tab.key"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition-colors duration-300"
                            :class="routineSource === tab.key ? 'border-white/15 bg-white/14 text-[#BDF8C8]' : 'border-[#8BED9A]/60 bg-white/80 text-[#09B884]'"
                        >
                            <component :is="tab.icon" class="h-4 w-4" />
                        </span>
                        <span class="truncate">{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <div v-if="activeProxyNotice && !isProxyRoutine" class="surface-card border-[#8BED9A]/70 bg-[#8BED9A]/15 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-[#1e2924]">Temporary proxy routine active</p>
                        <p class="mt-1 text-sm text-[#04795a]">
                            Showing {{ activeProxyNotice.name }} for {{ activeProxyNotice.day }}<span v-if="activeProxyNotice.date">, {{ activeProxyNotice.date }}</span>. The saved routine has not been permanently changed.
                        </p>
                    </div>
                    <Link :href="`/proxy-manager/${activeProxyNotice.id}`" class="btn-secondary bg-white">
                        View proxy/original
                    </Link>
                </div>
            </div>

            <div v-if="!isStudentViewer" class="surface-card p-2">
                <div
                    class="relative grid w-full overflow-hidden rounded-2xl border border-[#8BED9A]/50 bg-[#8BED9A]/10 p-1.5 shadow-sm shadow-[#8BED9A]/20"
                    :style="{ gridTemplateColumns: `repeat(${routineViewTabs.length}, minmax(0, 1fr))` }"
                >
                    <div
                        class="absolute inset-y-1.5 left-1.5 rounded-xl bg-[#1e2924] shadow-lg shadow-[#1e2924]/20 transition-transform duration-300 ease-out"
                        :style="viewTabIndicatorStyle"
                    ></div>
                    <button
                        v-for="tab in routineViewTabs"
                        :key="tab.key"
                        type="button"
                        class="relative z-10 flex min-h-14 items-center justify-between gap-3 rounded-xl px-3 text-left transition-colors duration-300"
                        :class="viewMode === tab.key ? 'text-white' : 'text-[#1e2924] hover:text-[#09B884]'"
                        @click="viewMode = tab.key"
                    >
                        <span class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition-colors duration-300"
                                :class="viewMode === tab.key ? 'border-white/15 bg-white/14 text-[#BDF8C8]' : 'border-[#8BED9A]/60 bg-white/80 text-[#09B884]'"
                            >
                                <component :is="tab.icon" class="h-4 w-4" />
                            </span>
                            <span class="truncate text-sm font-black">{{ tab.label }}</span>
                        </span>
                        <span
                            class="min-w-8 shrink-0 rounded-full border px-2 py-1 text-center text-xs font-black transition-colors duration-300"
                            :class="viewMode === tab.key ? 'border-white/20 bg-white/16 text-white' : 'border-[#8BED9A]/70 bg-white/80 text-[#1e2924]'"
                        >
                            {{ tab.count }}
                        </span>
                    </button>
                </div>
            </div>

            <template v-if="viewMode === 'teachers'">
                <div v-if="canEditRoutine" class="grid gap-4 md:grid-cols-3">
                    <div class="surface-card p-4">
                        <p class="text-2xl font-bold text-slate-950">{{ unresolvedCount }}</p>
                        <p class="text-sm text-slate-500">unresolved class periods needing a teacher or subject</p>
                    </div>
                    <div class="surface-card p-4">
                        <p class="text-2xl font-bold text-slate-950">{{ teacherGapCount }}</p>
                        <p class="text-sm text-slate-500">teacher gaps/free periods</p>
                    </div>
                    <div class="surface-card p-4">
                        <p class="text-2xl font-bold text-slate-950">{{ props.periods.filter((period) => period.type !== 'break').length }}</p>
                        <p class="text-sm text-slate-500">class periods per full day template</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 surface-card px-4 py-3">
                    <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-stone-300 text-slate-600 hover:bg-stone-100" @click="stepDay(-1)">
                        <ChevronLeft class="h-4 w-4" />
                    </button>

                    <p class="flex-1 text-center text-base font-bold uppercase tracking-widest text-[#1e2924]">
                        {{ dayNames[selectedDay] ?? selectedDay }}
                    </p>

                    <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-stone-300 text-slate-600 hover:bg-stone-100" @click="stepDay(1)">
                        <ChevronRight class="h-4 w-4" />
                    </button>

                    <div class="hidden items-center gap-1.5 sm:flex">
                        <button
                            v-for="day in days"
                            :key="day"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                            :class="day === selectedDay ? 'bg-[#1e2924]/95 text-white shadow-sm shadow-black/10' : 'text-[#1e2924] hover:bg-[#8BED9A]/15'"
                            @click="selectDay(day)"
                        >
                            {{ day }}
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500">Cell markers:</span>
                    <span class="flex items-center gap-1 rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-2.5 py-1 font-medium text-[#1e2924]">
                        <Repeat class="h-3 w-3" /> Proxy
                    </span>
                    <span v-if="isProxyRoutine" class="rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-2.5 py-1 font-medium text-[#1e2924]">
                        Thick green outline = swap
                    </span>
                    <span v-if="isProxyRoutine" class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 font-medium text-amber-700">
                        Thick amber outline = moved or edited
                    </span>
                    <span v-if="canEditRoutine" class="flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 font-medium text-red-700">
                        <AlertTriangle class="h-3 w-3" /> Unresolved
                    </span>
                </div>

                <div class="overflow-x-auto surface-card">
                    <div class="min-w-[980px]">
                        <div class="grid border-b border-stone-200" :style="gridStyle">
                            <div class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Teacher</div>
                            <div
                                v-for="period in periods"
                                :key="period.key"
                                class="border-l border-stone-200 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider"
                                :class="period.type === 'break' ? 'bg-stone-50 text-slate-500' : 'text-slate-500'"
                            >
                                <p>{{ period.label }}</p>
                                <p v-if="period.time" class="mt-0.5 text-[10px] font-normal normal-case text-slate-600">{{ period.time }}</p>
                            </div>
                        </div>

                        <div v-for="(teacher, teacherIndex) in gridTeachers" :key="`${selectedDay}-${teacher.id ?? teacher.name}`" class="grid border-b border-stone-200 last:border-b-0" :style="gridStyle">
                            <div class="px-4 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ teacher.name }}</p>
                                <p class="text-xs text-slate-500">{{ teacher.subject }}</p>
                            </div>

                            <div v-for="period in periods" :key="period.key" class="border-l border-stone-200 p-2">
                                <div v-if="period.type === 'break'" class="flex h-full items-center justify-center rounded-lg bg-stone-50 text-xs font-medium text-slate-600">
                                    {{ period.label }}
                                </div>

                                <button
                                    v-else
                                    type="button"
                                    :disabled="!canEditRoutine || isOriginalRoutineSource"
                                    :draggable="canEditRoutine && !isOriginalRoutineSource && period.type !== 'break' && (teacher.cells[period.key]?.type ?? 'empty') !== 'empty'"
                                    class="group relative flex min-h-16 w-full flex-col items-start justify-center gap-1 rounded-lg px-3 py-2 text-left transition-colors"
                                    :class="[
                                        teacherCellClasses(teacher, period.key),
                                        dragOverKey === cellKey(teacherIndex, period.key) ? 'ring-2 ring-[#09B884]' : '',
                                        dragSource && dragSource.teacherIndex === teacherIndex && dragSource.periodKey === period.key ? 'opacity-40' : '',
                                    ]"
                                    :style="subjectCellStyle(teacher.cells[period.key])"
                                    @click="openEditor(teacherIndex, period.key)"
                                    @dragstart="onDragStart(teacherIndex, period.key, $event)"
                                    @dragend="onDragEnd"
                                    @dragover="onDragOverCell(teacherIndex, period.key, $event)"
                                    @dragleave="onDragLeaveCell"
                                    @drop="onDrop(teacherIndex, period.key, $event)"
                                >
                                    <Pencil v-if="canEditRoutine && !isOriginalRoutineSource" class="absolute right-1.5 top-1.5 h-3 w-3 text-slate-500 opacity-0 transition-opacity group-hover:opacity-100" />

                                    <template v-if="!teacher.cells[period.key] || teacher.cells[period.key].type === 'empty'">
                                        <span class="mx-auto text-xs font-semibold text-stone-400">Gap</span>
                                    </template>

                                    <template v-else-if="teacher.cells[period.key].type === 'unresolved'">
                                        <template v-if="canEditRoutine">
                                            <span class="flex items-center gap-1 text-xs font-semibold text-red-700">
                                                <AlertTriangle class="h-3 w-3" /> Unresolved
                                            </span>
                                            <span class="text-xs text-slate-600">{{ teacher.cells[period.key].classLabel }}</span>
                                        </template>
                                        <span v-else class="mx-auto text-xs font-semibold text-stone-400">Gap</span>
                                    </template>

                                    <template v-else-if="teacher.cells[period.key].type === 'proxy'">
                                        <span class="flex items-center gap-1 text-xs font-semibold text-[#1e2924]">
                                            <Repeat class="h-3 w-3" /> {{ teacher.cells[period.key].subject }}
                                        </span>
                                        <span class="text-xs text-slate-700">{{ teacher.cells[period.key].classLabel }}</span>
                                    </template>

                                    <template v-else>
                                        <span v-if="displaySubject(teacher.cells[period.key])" class="text-xs font-semibold text-slate-950">{{ displaySubject(teacher.cells[period.key]) }}</span>
                                        <span class="text-xs text-slate-700">{{ teacher.cells[period.key].classLabel }}</span>
                                    </template>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else-if="viewMode === 'mine'">
                <div v-if="canEditRoutine" class="grid gap-4 md:grid-cols-3">
                    <div class="surface-card p-4">
                        <p class="text-2xl font-bold text-slate-950">{{ currentTeacherRows.length }}</p>
                        <p class="text-sm text-slate-500">school days in your routine</p>
                    </div>
                    <div class="surface-card p-4">
                        <p class="text-2xl font-bold text-slate-950">
                            {{ currentTeacherRows.reduce((sum, row) => sum + Object.values(row.cells ?? {}).filter((cell) => cell && cell.type !== 'empty').length, 0) }}
                        </p>
                        <p class="text-sm text-slate-500">assigned periods</p>
                    </div>
                    <div class="surface-card p-4">
                        <p class="text-2xl font-bold text-slate-950">
                            {{ currentTeacherRows.reduce((sum, row) => sum + Object.values(row.cells ?? {}).filter((cell) => cell?.type === 'proxy').length, 0) }}
                        </p>
                        <p class="text-sm text-slate-500">proxy updates</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500">Cell markers:</span>
                    <span class="flex items-center gap-1 rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-2.5 py-1 font-medium text-[#1e2924]">
                        <Repeat class="h-3 w-3" /> Proxy
                    </span>
                    <span class="rounded-full border border-stone-200 bg-stone-100 px-2.5 py-1 font-medium text-slate-600">Gap</span>
                </div>

                <div class="overflow-x-auto surface-card">
                    <div class="min-w-[980px]">
                        <div class="grid border-b border-stone-200" :style="classGridStyle">
                            <div class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Day</div>
                            <div
                                v-for="period in periods"
                                :key="period.key"
                                class="border-l border-stone-200 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider"
                                :class="period.type === 'break' ? 'bg-stone-50 text-slate-500' : 'text-slate-500'"
                            >
                                <p>{{ period.label }}</p>
                                <p v-if="period.time" class="mt-0.5 text-[10px] font-normal normal-case text-slate-600">{{ period.time }}</p>
                            </div>
                        </div>

                        <div v-for="row in currentTeacherDutyGrid" :key="`mine-${row.day}`" class="grid border-b border-stone-200 last:border-b-0" :style="classGridStyle">
                            <div class="flex items-center px-4 py-3 text-sm font-semibold text-slate-900">{{ dayNames[row.day] ?? row.day }}</div>
                            <div v-for="period in periods" :key="period.key" class="border-l border-stone-200 p-2">
                                <div v-if="period.type === 'break'" class="flex min-h-16 items-center justify-center rounded-lg bg-stone-100 text-xs font-semibold text-slate-500">
                                    {{ period.label }}
                                </div>
                                <div
                                    v-else
                                    class="relative flex min-h-16 flex-col items-start justify-center gap-1 rounded-lg px-3 py-2"
                                    :class="readonlyCellClasses(row.cells[period.key])"
                                    :style="subjectCellStyle(row.cells[period.key])"
                                >
                                    <template v-if="!row.cells[period.key] || row.cells[period.key].type === 'empty'">
                                        <span class="mx-auto text-xs font-semibold text-stone-400">Gap</span>
                                    </template>
                                    <template v-else-if="row.cells[period.key].type === 'proxy'">
                                        <span class="flex items-center gap-1 text-xs font-semibold text-[#1e2924]">
                                            <Repeat class="h-3 w-3" /> {{ row.cells[period.key].subject }}
                                        </span>
                                        <span class="text-xs text-slate-700">{{ row.cells[period.key].classLabel }}</span>
                                    </template>
                                    <template v-else>
                                        <span v-if="displaySubject(row.cells[period.key])" class="text-xs font-semibold text-slate-950">{{ displaySubject(row.cells[period.key]) }}</span>
                                        <span class="text-xs text-slate-700">{{ row.cells[period.key].classLabel }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else-if="viewMode === 'classes'">
                <div v-if="canEditRoutine" class="grid gap-4 md:grid-cols-3">
                    <div class="surface-card p-4">
                        <p class="text-2xl font-bold text-slate-950">{{ visibleClassRoutineSections.length }}</p>
                        <p class="text-sm text-slate-500">class routines generated</p>
                    </div>
                    <div class="surface-card p-4">
                        <p class="text-2xl font-bold text-slate-950">{{ unresolvedCount }}</p>
                        <p class="text-sm text-slate-500">empty class periods needing attention</p>
                    </div>
                    <div class="surface-card p-4">
                        <p class="text-2xl font-bold text-slate-950">{{ props.periods.filter((period) => period.type !== 'break').length }}</p>
                        <p class="text-sm text-slate-500">teaching periods per full day</p>
                    </div>
                </div>

                <div v-if="canEditRoutine && classUnresolvedCells.length" class="surface-card border-red-200 bg-red-50/60 p-4">
                    <div class="flex items-start gap-3">
                        <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-red-700" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-red-800">Unresolved class periods</p>
                            <p class="mt-1 text-sm text-red-700">These are class-period cells with no allocated lesson. Teacher free periods are no longer counted here.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="item in classUnresolvedCells.slice(0, 18)"
                                    :key="`${item.classLabel}-${item.day}-${item.periodKey}`"
                                    class="rounded-full border border-red-200 bg-white px-2.5 py-1 text-xs font-medium text-red-700"
                                >
                                    {{ item.classLabel }} - {{ item.day }} - {{ item.periodLabel }}<span v-if="item.periodTime">, {{ item.periodTime }}</span>
                                </span>
                                <span v-if="classUnresolvedCells.length > 18" class="rounded-full border border-red-200 bg-white px-2.5 py-1 text-xs font-medium text-red-700">
                                    +{{ classUnresolvedCells.length - 18 }} more
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!visibleClassRoutineSections.length" class="surface-card p-6 text-center text-sm text-slate-500">
                    No class routine grid is available for this routine yet. Regenerate or import a routine to build the class view.
                </div>

                <div class="space-y-5">
                    <div v-for="section in visibleClassRoutineSections" :key="section.id ?? section.label" class="surface-card overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-950">{{ section.label }}</p>
                                <p class="text-xs text-slate-500">{{ days.length }} days - {{ periods.filter((period) => period.type !== 'break').length }} teaching periods per full day</p>
                            </div>
                            <span
                                v-if="canEditRoutine && days.flatMap((day) => periods.filter((period) => isScheduledClassPeriod(section, day, period) && isClassCellUnresolved(classCell(section, day, period.key)))).length"
                                class="rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700"
                            >
                                {{ days.flatMap((day) => periods.filter((period) => isScheduledClassPeriod(section, day, period) && isClassCellUnresolved(classCell(section, day, period.key)))).length }} unresolved
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <div class="min-w-[980px]">
                                <div class="grid border-b border-stone-200 bg-stone-50" :style="classGridStyle">
                                    <div class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Day</div>
                                    <div
                                        v-for="period in periods"
                                        :key="period.key"
                                        class="border-l border-stone-200 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500"
                                    >
                                        <p>{{ period.label }}</p>
                                        <p v-if="period.time" class="mt-0.5 text-[10px] font-normal normal-case text-slate-600">{{ period.time }}</p>
                                    </div>
                                </div>

                                <div v-for="day in days" :key="`${section.label}-${day}`" class="grid border-b border-stone-200 last:border-b-0" :style="classGridStyle">
                                    <div class="flex items-center px-4 py-3 text-sm font-semibold text-slate-900">{{ dayNames[day] ?? day }}</div>
                                    <div v-for="period in periods" :key="period.key" class="border-l border-stone-200 p-2">
                                        <div v-if="period.type === 'break'" class="flex min-h-16 items-center justify-center rounded-lg bg-stone-100 text-xs font-semibold text-slate-500">
                                            {{ period.label }}
                                        </div>
                                        <div
                                            v-else
                                            class="relative flex min-h-16 flex-col items-start justify-center gap-1 rounded-lg px-3 py-2"
                                            :class="classDisplayCellClasses(section, day, period)"
                                            :style="subjectCellStyle(classCell(section, day, period.key))"
                                        >
                                            <template v-if="!isScheduledClassPeriod(section, day, period)"></template>
                                            <template v-else-if="isClassCellUnresolved(classCell(section, day, period.key))">
                                                <template v-if="canEditRoutine">
                                                    <span class="text-xs font-semibold text-red-700">Unresolved</span>
                                                    <span class="text-[11px] text-red-600">No lesson assigned</span>
                                                </template>
                                            </template>
                                            <template v-else>
                                                <span v-if="displaySubject(classCell(section, day, period.key))" class="text-xs font-semibold text-slate-950">{{ displaySubject(classCell(section, day, period.key)) }}</span>
                                                <span class="text-[11px] text-slate-700">{{ teacherNameForCell(classCell(section, day, period.key)) || 'Teacher not set' }}</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div class="space-y-4">
                        <div class="surface-card p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="section-title">Class and Subject Rules</p>
                                    <p class="mt-1 text-sm text-slate-500">Drag classes to set custom order for non-numbered names, or restore the standard Nursery, KG, 1, 2 sequence.</p>
                                </div>
                                <button type="button" class="btn-secondary" @click="sortClassBlueprint">Sort standard order</button>
                            </div>
                        </div>

                        <div
                            v-for="(section, sectionIndex) in classBlueprint"
                            :key="section.id"
                            class="surface-card p-5 transition"
                            :class="classOrderDragIndex === sectionIndex ? 'opacity-50' : ''"
                            @dragover.prevent
                            @drop="onClassOrderDrop(sectionIndex, $event)"
                        >
                            <div class="flex flex-wrap items-end gap-3">
                                <GripVertical
                                    draggable="true"
                                    class="mb-2 h-5 w-5 shrink-0 cursor-grab text-slate-400 active:cursor-grabbing"
                                    @dragstart="onClassOrderDragStart(sectionIndex, $event)"
                                    @dragend="onClassOrderDragEnd"
                                />
                                <div class="min-w-48 flex-1">
                                    <label class="section-title">Class / section</label>
                                    <input v-model="section.label" type="text" class="field-control mt-1 w-full" />
                                </div>
                                <div class="w-full xl:w-auto xl:min-w-[28rem]">
                                    <div class="max-w-xs">
                                        <label class="section-title">All days</label>
                                        <input
                                            v-model.number="section.dailyPeriods"
                                            min="0"
                                            type="number"
                                            class="field-control-sm mt-1 w-full"
                                            @change="applyPeriodsToAllDays(section)"
                                        />
                                    </div>
                                    <label class="mt-3 block section-title">Periods by day</label>
                                    <div class="mt-1 grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                                        <label v-for="day in days" :key="`${section.id}-${day}`" class="rounded-lg border border-stone-200 bg-white px-2.5 py-2">
                                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ day }}</span>
                                            <input v-model.number="section.dailyPeriodsByDay[day]" min="0" type="number" class="mt-1 w-full rounded border border-stone-200 px-2 py-1 text-sm focus:border-[#09B884] focus:outline-none" />
                                        </label>
                                    </div>
                                </div>
                                <div class="min-w-56 flex-1">
                                    <label class="section-title">Class teacher</label>
                                    <select v-model="section.classTeacherId" class="field-control mt-1 w-full">
                                        <option :value="null">Unassigned</option>
                                        <option v-for="teacher in teacherPool" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full min-w-[760px] text-left text-sm">
                                    <thead class="table-head">
                                        <tr>
                                            <th class="px-3 py-2">Color</th>
                                            <th class="px-3 py-2">Subject</th>
                                            <th class="px-3 py-2">Teacher</th>
                                            <th class="px-3 py-2">Classes / week</th>
                                            <th class="px-3 py-2">Mode</th>
                                            <th class="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-stone-200">
                                        <tr v-for="(subject, subjectIndex) in section.subjects" :key="subject.id">
                                            <td class="px-3 py-2">
                                                <input v-model="subject.color" type="color" class="h-8 w-10 cursor-pointer rounded border border-stone-300 bg-white p-1" />
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-[10px] font-bold text-white" :style="{ backgroundColor: subject.color || defaultSubjectColor(subject.name) }">{{ subjectCode(subject.name) }}</span>
                                                    <input v-model="subject.name" type="text" class="field-control-sm w-full" />
                                                </div>
                                            </td>
                                            <td class="px-3 py-2">
                                                <select v-model="subject.teacherId" class="field-control-sm w-full">
                                                    <option :value="null">Unassigned</option>
                                                    <option v-for="teacher in teacherPool" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2">
                                                <input v-model="subject.weeklyPeriods" :disabled="subject.autoBalance" type="number" min="1" class="field-control-sm w-28 disabled:bg-stone-100" />
                                            </td>
                                            <td class="px-3 py-2">
                                                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                                                    <input v-model="subject.autoBalance" type="checkbox" class="rounded border-stone-300 text-[#09B884] focus:ring-[#09B884]" />
                                                    Auto average
                                                </label>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <button type="button" class="text-slate-400 hover:text-red-700" @click="removeSubject(section, subjectIndex)">
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <button type="button" class="btn-secondary mt-3" @click="addSubject(section)">
                                <Plus class="h-4 w-4" />
                                Add subject
                            </button>
                        </div>

                        <div class="surface-card p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="section-title">Period template</p>
                                    <p class="mt-1 text-sm text-slate-500">Edit routine-specific class periods and breaks. This replaces the global settings page timing controls.</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="btn-secondary" @click="addTiming('class')">Add period</button>
                                    <button type="button" class="btn-secondary" @click="addTiming('break')">Add break</button>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div v-for="(period, index) in periodTemplates" :key="period.id" class="grid gap-2 rounded-lg border border-stone-200 bg-stone-50 p-3 sm:grid-cols-[1fr_9rem_9rem_9rem_2rem]">
                                    <input v-model="period.label" type="text" class="field-control-sm" />
                                    <input v-model="period.startTime" type="time" class="field-control-sm" />
                                    <input v-model="period.endTime" type="time" class="field-control-sm" />
                                    <select v-model="period.type" class="field-control-sm">
                                        <option value="class">Class period</option>
                                        <option value="break">Break / lunch</option>
                                    </select>
                                    <button type="button" class="text-slate-400 hover:text-red-700" @click="removeTiming(index)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="space-y-4">
                        <div class="surface-card p-5">
                            <p class="section-title">Teacher Order</p>
                            <p class="mt-1 text-xs text-slate-500">Drag teachers to control display and generation order.</p>
                            <div class="mt-4 space-y-2">
                                <div
                                    v-for="(teacher, teacherIndex) in teacherPool"
                                    :key="teacher.id"
                                    draggable="true"
                                    class="flex items-center gap-2 rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm transition"
                                    :class="teacherOrderDragIndex === teacherIndex ? 'opacity-50' : ''"
                                    @dragstart="onTeacherOrderDragStart(teacherIndex, $event)"
                                    @dragover.prevent
                                    @drop="onTeacherOrderDrop(teacherIndex, $event)"
                                    @dragend="onTeacherOrderDragEnd"
                                >
                                    <GripVertical class="h-4 w-4 shrink-0 cursor-grab text-slate-400 active:cursor-grabbing" />
                                    <span class="min-w-0 flex-1 truncate font-medium text-slate-700">{{ teacher.name }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="surface-card p-5">
                            <p class="section-title">Engine Constraints</p>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="section-title">Max consecutive periods</label>
                                    <input v-model.number="generationRules.maxConsecutivePeriods" type="number" min="1" max="5" class="field-control mt-1 w-full" />
                                </div>
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input v-model="generationRules.preferGapBetweenPeriods" type="checkbox" class="rounded border-stone-300 text-[#09B884] focus:ring-[#09B884]" />
                                    Prefer teacher gaps
                                </label>
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input v-model="generationRules.autoBalanceUnsetSubjectLoads" type="checkbox" class="rounded border-stone-300 text-[#09B884] focus:ring-[#09B884]" />
                                    Auto-balance unset subject loads
                                </label>
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input v-model="generationRules.keepClassTeacherFirstPeriod" type="checkbox" class="rounded border-stone-300 text-[#09B884] focus:ring-[#09B884]" />
                                    Class teacher first-period priority
                                </label>
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input v-model="generationRules.flagUnallocatedSlots" type="checkbox" class="rounded border-stone-300 text-[#09B884] focus:ring-[#09B884]" />
                                    Flag unallocated gaps
                                </label>
                            </div>
                        </div>

                        <div class="surface-card p-5">
                            <p class="section-title">Readiness</p>
                            <div class="mt-4 space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Configured sections</span>
                                    <span class="font-semibold text-slate-900">{{ classBlueprint.length }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Assigned subject rows</span>
                                    <span class="font-semibold text-slate-900">{{ assignedSubjectRows }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Unresolved class periods</span>
                                    <span class="font-semibold text-red-700">{{ unresolvedCount }}</span>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn-primary w-full py-3" @click="regenerateRoutine">
                            <RefreshCw class="h-4 w-4" />
                            Try generation again
                        </button>
                    </aside>
                </div>
            </template>
            </template>
        </div>

        <Teleport to="body">
            <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-100/70 p-4" @click.self="closeEditor">
                <div class="w-full max-w-sm surface-card p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-slate-950">{{ editing.isNew ? 'Allocate Teacher Gap' : 'Edit Period' }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ editingTeacherName }} - {{ editingPeriodLabel }}</p>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-xs font-medium text-slate-600">Subject</label>
                            <input v-model="editing.subject" type="text" class="field-control mt-1 w-full" placeholder="Subject name" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600">Class / section</label>
                            <select v-model="editing.classLabel" class="field-control mt-1 w-full">
                                <option value="" disabled>Select class</option>
                                <option v-for="classLabel in classOptions" :key="classLabel" :value="classLabel">{{ classLabel }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="closeEditor">Cancel</button>
                        <button type="button" class="btn-primary" :disabled="!editing.subject || !editing.classLabel" @click="saveEditor">Save</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
