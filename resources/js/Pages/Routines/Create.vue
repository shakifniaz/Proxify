<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    GraduationCap,
    GripVertical,
    Layers,
    Plus,
    RefreshCw,
    Trash2,
    UserRound,
    Users,
} from 'lucide-vue-next';

const props = defineProps({
    classesConfig: { type: Object, default: () => ({ numberOfClasses: 0, maxPeriodsPerDay: 7 }) },
    classes: { type: Array, default: () => [] },
    teachersConfig: { type: Object, default: () => ({ numberOfTeachers: 0 }) },
    teachers: { type: Array, default: () => [] },
});

const steps = ['Structure', 'Subjects', 'Teachers', 'Timings', 'Generate'];
const activeStep = ref('Structure');
const isSubmitting = ref(false);
const routineMeta = ref({ name: 'New Academic Routine', termLabel: 'Term 1 - 2026' });
const availableWeekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const weekdays = ref(['Sun', 'Mon', 'Tue', 'Wed', 'Thu']);
const teacherOrderDragIndex = ref(null);
const classOrderDragIndex = ref(null);

function defaultDailyPeriodsByDay(value = 7) {
    return Object.fromEntries(weekdays.value.map((day) => [day, Number(value) || 0]));
}

function applyPeriodsToAllDays(section) {
    const value = Number(section.dailyPeriods) || 0;
    section.dailyPeriodsByDay = Object.fromEntries(weekdays.value.map((day) => [day, value]));
}

function adjustDayPeriods(section, day, amount) {
    const current = Number(section.dailyPeriodsByDay?.[day] ?? section.dailyPeriods) || 0;
    section.dailyPeriodsByDay[day] = Math.max(0, current + amount);
}


const subjectPalette = ['#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#4f46e5', '#16a34a', '#ca8a04', '#be123c', '#0f766e', '#9333ea'];

function defaultSubjectColor(name = 'Subject') {
    const key = String(name || 'Subject').toLowerCase();
    let hash = 0;
    for (let i = 0; i < key.length; i++) hash = ((hash << 5) - hash + key.charCodeAt(i)) | 0;
    return subjectPalette[Math.abs(hash) % subjectPalette.length];
}

const romanNumbers = { xii: 12, xi: 11, x: 10, ix: 9, viii: 8, vii: 7, vi: 6, v: 5, iv: 4, iii: 3, ii: 2, i: 1 };

function classRank(name = '') {
    const normalized = String(name || '').toLowerCase().replace(/\bclass\b/g, '').trim();
    if (/\b(nursery|nur)\b/.test(normalized)) return 0;
    if (/\bkg\b|kindergarten/.test(normalized)) return 1;

    const digit = normalized.match(/\b(\d{1,2})\b/);
    if (digit) return Number(digit[1]) + 1;

    const roman = normalized.match(/\b(xii|xi|x|ix|viii|vii|vi|v|iv|iii|ii|i)\b/);
    if (roman) return romanNumbers[roman[1]] + 1;

    return 1000;
}

function compareClassNames(a, b) {
    const rankA = classRank(a.name);
    const rankB = classRank(b.name);
    if (rankA !== rankB) return rankA - rankB;
    return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base', numeric: true });
}

const generationRules = ref({
    maxConsecutivePeriods: 3,
    preferGapBetweenPeriods: true,
    autoBalanceUnsetSubjectLoads: true,
    keepClassTeacherFirstPeriod: true,
    flagUnallocatedSlots: true,
});

const teacherPool = ref(
    props.teachers.map((teacher, index) => ({
        id: teacher.id ?? index + 1,
        name: teacher.name || `Teacher ${index + 1}`,
        phone: teacher.phone || '',
    }))
);

function makeSubject(subjectName = 'New Subject', subjectIndex = 0, classId = Date.now(), sectionIndex = 0) {
    const name = typeof subjectName === 'string' ? subjectName : (subjectName.name ?? 'New Subject');

    return {
        id: subjectName.id ?? `${classId}-${sectionIndex + 1}-${subjectIndex + 1}`,
        name,
        teacherId: subjectName.teacherId ?? teacherPool.value[subjectIndex % Math.max(1, teacherPool.value.length)]?.id ?? null,
        weeklyPeriods: subjectName.weeklyPeriods ?? '',
        autoBalance: subjectName.autoBalance ?? true,
        manualSlots: subjectName.manualSlots ?? [],
        color: subjectName.color ?? defaultSubjectColor(name),
    };
}

function makeSection(sectionInput = 'Section A', sectionIndex = 0, cls = {}) {
    const sectionName = typeof sectionInput === 'string' ? sectionInput : (sectionInput.name ?? `Section ${String.fromCharCode(65 + sectionIndex)}`);
    const sectionDailyPeriods = Number(sectionInput.dailyPeriods ?? cls.dailyPeriods ?? props.classesConfig.maxPeriodsPerDay) || 7;
    const sourceSubjects = sectionInput.subjects ?? cls.subjects ?? ['Mathematics', 'English'];

    return {
        id: sectionInput.id ?? `${cls.id ?? Date.now()}-${sectionIndex + 1}`,
        name: sectionName,
        dailyPeriods: sectionDailyPeriods,
        dailyPeriodsByDay: {
            ...defaultDailyPeriodsByDay(sectionDailyPeriods),
            ...(cls.dailyPeriodsByDay ?? {}),
            ...(sectionInput.dailyPeriodsByDay ?? {}),
        },
        classTeacherId: sectionInput.classTeacherId ?? teacherPool.value[sectionIndex % Math.max(1, teacherPool.value.length)]?.id ?? null,
        subjects: sourceSubjects.map((subject, subjectIndex) => makeSubject(subject, subjectIndex, cls.id, sectionIndex)),
    };
}

function makeClass(cls = {}, classIndex = classes.value?.length ?? 0) {
    const id = cls.id ?? Date.now();
    const dailyPeriods = Number(cls.dailyPeriods ?? props.classesConfig.maxPeriodsPerDay) || 7;
    const sections = cls.sections?.length ? cls.sections : ['Section A'];

    return {
        id,
        name: cls.name || `Class ${classIndex + 1}`,
        dailyPeriods,
        sections: sections.map((section, sectionIndex) => makeSection(section, sectionIndex, { ...cls, id, dailyPeriods })),
    };
}

const classes = ref(
    props.classes.map((cls, classIndex) => makeClass(cls, classIndex))
);

classes.value.sort(compareClassNames);

const periodTemplates = ref([
    { id: 1, label: 'P1', startTime: '08:00', endTime: '08:45', type: 'class' },
    { id: 2, label: 'P2', startTime: '08:45', endTime: '09:30', type: 'class' },
    { id: 3, label: 'Break', startTime: '09:30', endTime: '09:45', type: 'break' },
    { id: 4, label: 'P3', startTime: '09:45', endTime: '10:30', type: 'class' },
    { id: 5, label: 'P4', startTime: '10:30', endTime: '11:15', type: 'class' },
    { id: 6, label: 'P5', startTime: '11:15', endTime: '12:00', type: 'class' },
    { id: 7, label: 'Lunch', startTime: '12:00', endTime: '13:00', type: 'break' },
    { id: 8, label: 'P6', startTime: '13:00', endTime: '13:45', type: 'class' },
    { id: 9, label: 'P7', startTime: '13:45', endTime: '14:30', type: 'class' },
]);

const classPeriods = computed(() => periodTemplates.value.filter((period) => period.type === 'class'));
const manualAllocation = ref(null);

const totalSections = computed(() => classes.value.reduce((sum, cls) => sum + cls.sections.length, 0));
const totalSubjectRows = computed(() =>
    classes.value.reduce((sum, cls) => sum + cls.sections.reduce((sectionSum, section) => sectionSum + section.subjects.length, 0), 0)
);
const unassignedSubjects = computed(() =>
    classes.value.flatMap((cls) =>
        cls.sections.flatMap((section) =>
            section.subjects
                .filter((subject) => !subject.teacherId)
                .map((subject) => `${cls.name} ${section.name} - ${subject.name}`)
        )
    )
);
const autoBalancedSubjects = computed(() =>
    classes.value.reduce(
        (sum, cls) =>
            sum + cls.sections.reduce((sectionSum, section) => sectionSum + section.subjects.filter((subject) => subject.autoBalance).length, 0),
        0
    )
);

function teacherName(id) {
    return teacherPool.value.find((teacher) => teacher.id === id)?.name ?? 'Unassigned';
}

function subjectCode(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function addClass() {
    classes.value.push(makeClass({}, classes.value.length));
}

function removeClass(index) {
    classes.value.splice(index, 1);
}

function addSection(cls) {
    cls.sections.push(makeSection({
        id: `${cls.id}-${Date.now()}`,
        name: `Section ${String.fromCharCode(65 + cls.sections.length)}`,
        classTeacherId: null,
        subjects: cls.sections[0]?.subjects.map((subject) => ({ ...subject, id: `${cls.id}-${Date.now()}-${subject.name}`, teacherId: null })) ?? [],
    }, cls.sections.length, cls));
}

function removeSection(cls, index) {
    cls.sections.splice(index, 1);
}

function addSubject(section) {
    section.subjects.push(makeSubject('New Subject', section.subjects.length, section.id, 0));
}

function removeSubject(section, index) {
    section.subjects.splice(index, 1);
}

function addTeacher() {
    teacherPool.value.push({
        id: Date.now(),
        name: `Teacher ${teacherPool.value.length + 1}`,
        phone: '',
    });
}

function removeTeacher(index) {
    const [teacher] = teacherPool.value.splice(index, 1);
    classes.value.forEach((cls) => {
        cls.sections.forEach((section) => {
            if (section.classTeacherId === teacher.id) section.classTeacherId = null;
            section.subjects.forEach((subject) => {
                if (subject.teacherId === teacher.id) subject.teacherId = null;
            });
        });
    });
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

    const [cls] = classes.value.splice(from, 1);
    classes.value.splice(index, 0, cls);
}

function onClassOrderDragEnd() {
    classOrderDragIndex.value = null;
}

function sortClasses() {
    classes.value.sort(compareClassNames);
}

function manualSlotKey(day, periodKey) {
    return `${day}::${periodKey}`;
}

function manualSlotCount(subject) {
    return subject.manualSlots?.length ?? 0;
}

function openManualAllocation(section, subject) {
    manualAllocation.value = {
        section,
        subject,
        selectedKeys: (subject.manualSlots ?? []).map((slot) => manualSlotKey(slot.day, slot.periodKey)),
    };
}

function closeManualAllocation() {
    manualAllocation.value = null;
}

function isManualSlotSelected(day, periodKey) {
    return manualAllocation.value?.selectedKeys.includes(manualSlotKey(day, periodKey)) ?? false;
}

function isManualSlotAllowed(section, day, period) {
    const index = classPeriods.value.findIndex((item) => item.label === period.label && item.startTime === period.startTime && item.endTime === period.endTime);
    const limit = Number(section.dailyPeriodsByDay?.[day] ?? section.dailyPeriods) || 0;
    return index >= 0 && index < limit;
}

function toggleManualSlot(day, period) {
    if (!manualAllocation.value || !isManualSlotAllowed(manualAllocation.value.section, day, period)) return;

    const key = manualSlotKey(day, period.label);
    manualAllocation.value.selectedKeys = isManualSlotSelected(day, period.label)
        ? manualAllocation.value.selectedKeys.filter((item) => item !== key)
        : [...manualAllocation.value.selectedKeys, key];
}

function confirmManualAllocation() {
    if (!manualAllocation.value) return;

    const slots = manualAllocation.value.selectedKeys.map((key) => {
        const [day, periodKey] = key.split('::');
        return { day, periodKey };
    });

    manualAllocation.value.subject.manualSlots = slots;
    if (slots.length) {
        manualAllocation.value.subject.autoBalance = false;
        manualAllocation.value.subject.weeklyPeriods = slots.length;
    }

    closeManualAllocation();
}

function addPeriod(type = 'class') {
    const id = Date.now();
    periodTemplates.value.push({ id, label: type === 'break' ? 'Break' : `P${periodTemplates.value.length + 1}`, startTime: '', endTime: '', type });
}

function removePeriod(index) {
    periodTemplates.value.splice(index, 1);
}

function normalizeTeacherSubjects(value) {
    if (Array.isArray(value)) return value.filter(Boolean);
    return String(value || '')
        .split(',')
        .map((subject) => subject.trim())
        .filter(Boolean);
}

function routinePayload() {
    return {
        name: routineMeta.value.name,
        termLabel: routineMeta.value.termLabel,
        days: weekdays.value,
        classes: classes.value.map((cls) => {
            const sections = cls.sections.map((section) => {
                const dailyPeriodsByDay = Object.fromEntries(weekdays.value.map((day) => [day, Number(section.dailyPeriodsByDay?.[day] ?? section.dailyPeriods ?? cls.dailyPeriods) || 0]));
                return {
                    ...section,
                    dailyPeriods: Math.max(1, ...Object.values(dailyPeriodsByDay)),
                    dailyPeriodsByDay,
                };
            });
            return {
                ...cls,
                dailyPeriods: Math.max(1, ...sections.flatMap((section) => Object.values(section.dailyPeriodsByDay))),
                sections,
            };
        }),
        teachers: teacherPool.value.map((teacher) => ({
            ...teacher,
            primarySubjects: [],
        })),
        periods: periodTemplates.value,
        generationRules: generationRules.value,
    };
}

function submitRoutine() {
    if (isSubmitting.value || unassignedSubjects.value.length) return;
    isSubmitting.value = true;
    router.post('/routines', routinePayload(), {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}
</script>

<template>
    <AppLayout title="Create Routine">
        <div class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="page-title">Create Routine</h2>
                    <p class="mt-1 text-sm text-slate-500">Build the full routine blueprint before running the automatic scheduler.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link href="/routines" class="btn-secondary">Cancel</Link>
                    <button type="button" class="btn-primary" :disabled="isSubmitting || unassignedSubjects.length > 0" @click="submitRoutine">
                        <RefreshCw class="h-4 w-4" :class="isSubmitting ? 'animate-spin' : ''" />
                        {{ isSubmitting ? 'Creating...' : 'Create routine' }}
                    </button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <aside class="surface-card p-4">
                    <div class="space-y-1">
                        <button
                            v-for="step in steps"
                            :key="step"
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors"
                            :class="activeStep === step ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-stone-50 hover:text-slate-950'"
                            @click="activeStep = step"
                        >
                            <span>{{ step }}</span>
                            <CheckCircle2 v-if="activeStep === step" class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="mt-5 space-y-3 border-t border-stone-200 pt-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Classes</span>
                            <span class="font-semibold text-slate-900">{{ classes.length }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Sections</span>
                            <span class="font-semibold text-slate-900">{{ totalSections }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Subject rows</span>
                            <span class="font-semibold text-slate-900">{{ totalSubjectRows }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Teachers</span>
                            <span class="font-semibold text-slate-900">{{ teacherPool.length }}</span>
                        </div>
                    </div>
                </aside>

                <section class="space-y-4">
                    <div v-if="activeStep === 'Structure'" class="space-y-4">                        <div class="surface-card p-5">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="section-title">Routine name</label>
                                    <input v-model="routineMeta.name" type="text" class="field-control mt-1 w-full" />
                                </div>
                                <div>
                                    <label class="section-title">Term label</label>
                                    <input v-model="routineMeta.termLabel" type="text" class="field-control mt-1 w-full" />
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="section-title">Working days</label>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <label
                                        v-for="day in availableWeekdays"
                                        :key="day"
                                        class="inline-flex items-center gap-2 rounded-lg border border-stone-200 px-3 py-2 text-sm font-medium text-slate-700"
                                        :class="weekdays.includes(day) ? 'bg-blue-50 text-blue-700' : 'bg-white'"
                                    >
                                        <input v-model="weekdays" :value="day" type="checkbox" class="rounded border-stone-300 text-blue-700 focus:ring-blue-500" />
                                        {{ day }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="surface-card p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="section-title">Classes and Sections</p>
                                    <p class="mt-1 text-sm text-slate-500">Drag classes to order custom names, or use the standard Nursery, KG, 1, 2 sequence.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="btn-secondary" @click="sortClasses">Sort standard order</button>
                                    <button type="button" class="btn-secondary" @click="addClass">
                                        <Plus class="h-4 w-4" />
                                        Add class
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-for="(cls, classIndex) in classes"
                            :key="cls.id"
                            class="surface-card p-5 transition"
                            :class="classOrderDragIndex === classIndex ? 'opacity-50' : ''"
                            @dragover.prevent
                            @drop="onClassOrderDrop(classIndex, $event)"
                        >
                            <div class="flex flex-wrap items-end gap-3">
                                <GripVertical
                                    draggable="true"
                                    class="mb-2 h-5 w-5 shrink-0 cursor-grab text-slate-400 active:cursor-grabbing"
                                    @dragstart="onClassOrderDragStart(classIndex, $event)"
                                    @dragend="onClassOrderDragEnd"
                                />
                                <div class="min-w-48 flex-1">
                                    <label class="section-title">Class name</label>
                                    <input v-model="cls.name" type="text" class="field-control mt-1 w-full" />
                                </div>
                                <button type="button" class="btn-secondary" @click="addSection(cls)">
                                    <Plus class="h-4 w-4" />
                                    Section
                                </button>
                                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 text-red-700 hover:bg-red-50" @click="removeClass(classIndex)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div v-for="(section, sectionIndex) in cls.sections" :key="section.id" class="surface-muted p-4">
                                    <div class="flex items-center gap-2">
                                        <input v-model="section.name" type="text" class="field-control-sm min-w-0 flex-1" />
                                        <button type="button" class="text-slate-400 hover:text-red-700" @click="removeSection(cls, sectionIndex)">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <div class="mt-3 overflow-hidden rounded-lg border border-stone-200 bg-white">
                                        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-stone-200 bg-stone-50 px-3 py-3">
                                            <div>
                                                <p class="section-title">Weekly period pattern</p>
                                                <p class="mt-1 text-xs text-slate-500">Set the normal day length, then adjust short days below.</p>
                                            </div>
                                            <div class="flex items-end gap-2">
                                                <label class="block">
                                                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Normal day</span>
                                                    <input
                                                        v-model.number="section.dailyPeriods"
                                                        min="0"
                                                        type="number"
                                                        class="mt-1 h-9 w-20 rounded-lg border border-stone-300 bg-white px-3 text-sm font-semibold text-slate-900 focus:border-blue-500 focus:outline-none"
                                                    />
                                                </label>
                                                <button type="button" class="h-9 rounded-lg border border-blue-200 bg-blue-50 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-100" @click="applyPeriodsToAllDays(section)">
                                                    Apply to week
                                                </button>
                                            </div>
                                        </div>

                                        <div class="grid divide-y divide-stone-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-5">
                                            <div v-for="day in weekdays" :key="`${section.id}-${day}`" class="flex items-center justify-between gap-3 px-3 py-3">
                                                <div>
                                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-600">{{ day }}</p>
                                                    <p class="text-[11px] text-slate-500">First {{ section.dailyPeriodsByDay[day] ?? 0 }} periods</p>
                                                </div>
                                                <div class="flex items-center rounded-lg border border-stone-200 bg-stone-50">
                                                    <button type="button" class="flex h-8 w-8 items-center justify-center text-slate-500 hover:text-blue-700" @click="adjustDayPeriods(section, day, -1)">-</button>
                                                    <input v-model.number="section.dailyPeriodsByDay[day]" inputmode="numeric" type="text" class="h-8 w-12 border-x border-stone-200 bg-white text-center text-sm font-semibold text-slate-900 focus:outline-none" />
                                                    <button type="button" class="flex h-8 w-8 items-center justify-center text-slate-500 hover:text-blue-700" @click="adjustDayPeriods(section, day, 1)">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <label class="mt-3 block section-title">Class teacher</label>
                                    <select v-model="section.classTeacherId" class="field-control-sm mt-1 w-full">
                                        <option :value="null">Select teacher</option>
                                        <option v-for="teacher in teacherPool" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                                    </select>
                                    <p class="mt-2 text-xs text-slate-500">First-period priority applies when this teacher has a subject in this section.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 'Subjects'" class="space-y-4">
                        <div class="surface-card p-5">
                            <p class="section-title">Subject Loads</p>
                            <p class="mt-1 text-sm text-slate-500">Set subjects per section, assign teachers, and optionally define weekly class count. Empty weekly counts will be balanced automatically.</p>
                        </div>

                        <div v-for="cls in classes" :key="cls.id" class="surface-card p-5">
                            <div class="flex items-center gap-2">
                                <Layers class="h-4 w-4 text-blue-700" />
                                <p class="font-semibold text-slate-950">{{ cls.name }}</p>
                                <span class="text-xs text-slate-500">{{ Math.max(...cls.sections.flatMap((section) => Object.values(section.dailyPeriodsByDay ?? { default: section.dailyPeriods ?? cls.dailyPeriods }))) }} max periods/day</span>
                            </div>

                            <div class="mt-4 space-y-4">
                                <div v-for="section in cls.sections" :key="section.id" class="surface-muted p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ section.name }}</p>
                                            <p class="text-xs text-slate-500">Class teacher: {{ teacherName(section.classTeacherId) }}</p>
                                        </div>
                                        <button type="button" class="btn-secondary" @click="addSubject(section)">
                                            <Plus class="h-4 w-4" />
                                            Add subject
                                        </button>
                                    </div>

                                    <div class="mt-3 overflow-x-auto">
                                        <table class="w-full min-w-[720px] text-left text-sm">
                                            <thead class="table-head">
                                                <tr>
                                                    <th class="px-3 py-2">Color</th>
                                                    <th class="px-3 py-2">Subject</th>
                                                    <th class="px-3 py-2">Teacher</th>
                                                    <th class="px-3 py-2">Classes / week</th>
                                                    <th class="px-3 py-2">Mode</th>
                                                    <th class="px-3 py-2">Manual</th>
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
                                                            <span class="flex h-7 w-7 items-center justify-center rounded text-[10px] font-bold text-white" :style="{ backgroundColor: subject.color || defaultSubjectColor(subject.name) }">{{ subjectCode(subject.name) }}</span>
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
                                                        <input v-model="subject.weeklyPeriods" :disabled="subject.autoBalance" min="1" type="number" class="field-control-sm w-28 disabled:bg-stone-100" />
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                                                            <input v-model="subject.autoBalance" type="checkbox" class="rounded border-stone-300 text-blue-700 focus:ring-blue-500" />
                                                            Auto average
                                                        </label>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <button type="button" class="rounded-lg border border-stone-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700" @click="openManualAllocation(section, subject)">
                                                            {{ manualSlotCount(subject) ? `${manualSlotCount(subject)} set` : 'Allocate' }}
                                                        </button>
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
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 'Teachers'" class="space-y-4">
                        <div class="surface-card p-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="section-title">Teacher Pool</p>
                                    <p class="mt-1 text-sm text-slate-500">Teachers can carry multiple subjects across many classes. The engine will prevent period overlap.</p>
                                </div>
                                <button type="button" class="btn-secondary" @click="addTeacher">
                                    <Plus class="h-4 w-4" />
                                    Add teacher
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(teacher, teacherIndex) in teacherPool"
                                :key="teacher.id"
                                class="surface-card p-4 transition"
                                :class="teacherOrderDragIndex === teacherIndex ? 'opacity-50' : ''"
                                @dragover.prevent
                                @drop="onTeacherOrderDrop(teacherIndex, $event)"
                            >
                                <div class="flex flex-wrap items-center gap-3">
                                    <div
                                        draggable="true"
                                        class="flex h-10 w-10 cursor-grab items-center justify-center rounded-lg border border-stone-200 bg-stone-50 text-slate-500 active:cursor-grabbing"
                                        @dragstart="onTeacherOrderDragStart(teacherIndex, $event)"
                                        @dragend="onTeacherOrderDragEnd"
                                    >
                                        <GripVertical class="h-5 w-5" />
                                    </div>
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                                        <UserRound class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <input v-model="teacher.name" type="text" class="field-control w-full" />
                                    </div>
                                    <div class="w-full sm:w-64">
                                        <input v-model="teacher.phone" type="text" placeholder="WhatsApp number" class="field-control-sm w-full" />
                                    </div>
                                    <button type="button" class="ml-auto text-slate-400 hover:text-red-700" @click="removeTeacher(teacherIndex)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 'Timings'" class="space-y-4">
                        <div class="surface-card p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="section-title">Periods and Breaks</p>
                                    <p class="mt-1 text-sm text-slate-500">Timings now belong to the routine blueprint, so different routines can carry different day structures.</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="btn-secondary" @click="addPeriod('class')">Add period</button>
                                    <button type="button" class="btn-secondary" @click="addPeriod('break')">Add break</button>
                                </div>
                            </div>
                        </div>

                        <div class="surface-card p-5">
                            <div class="space-y-2">
                                <div v-for="(period, index) in periodTemplates" :key="period.id" class="grid gap-2 rounded-lg border border-stone-200 bg-stone-50 p-3 sm:grid-cols-[1fr_9rem_9rem_9rem_2rem]">
                                    <input v-model="period.label" type="text" class="field-control-sm" />
                                    <input v-model="period.startTime" type="time" class="field-control-sm" />
                                    <input v-model="period.endTime" type="time" class="field-control-sm" />
                                    <select v-model="period.type" class="field-control-sm">
                                        <option value="class">Class period</option>
                                        <option value="break">Break / lunch</option>
                                    </select>
                                    <button type="button" class="text-slate-400 hover:text-red-700" @click="removePeriod(index)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <div class="surface-card p-5">
                            <p class="section-title">Generation Rules</p>
                            <p class="mt-1 text-sm text-slate-500">These controls describe what the engine should optimize before the user manually adjusts the generated result.</p>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="section-title">Max consecutive periods per teacher</label>
                                    <input v-model.number="generationRules.maxConsecutivePeriods" min="1" max="5" type="number" class="field-control mt-1 w-full" />
                                    <p class="mt-1 text-xs text-slate-500">Recommended: 2 to 3.</p>
                                </div>
                                <div class="space-y-3">
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input v-model="generationRules.preferGapBetweenPeriods" type="checkbox" class="rounded border-stone-300 text-blue-700 focus:ring-blue-500" />
                                        Prefer gaps between teacher periods
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input v-model="generationRules.autoBalanceUnsetSubjectLoads" type="checkbox" class="rounded border-stone-300 text-blue-700 focus:ring-blue-500" />
                                        Auto-balance unset weekly subject loads
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input v-model="generationRules.keepClassTeacherFirstPeriod" type="checkbox" class="rounded border-stone-300 text-blue-700 focus:ring-blue-500" />
                                        Prioritize class teacher in first period
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input v-model="generationRules.flagUnallocatedSlots" type="checkbox" class="rounded border-stone-300 text-blue-700 focus:ring-blue-500" />
                                        Flag unallocated routine gaps for manual edits
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="surface-card p-5">
                                <p class="text-3xl font-bold text-slate-950">{{ autoBalancedSubjects }}</p>
                                <p class="mt-1 text-sm text-slate-500">auto-balanced subjects</p>
                            </div>
                            <div class="surface-card p-5">
                                <p class="text-3xl font-bold" :class="unassignedSubjects.length ? 'text-red-700' : 'text-blue-700'">{{ unassignedSubjects.length }}</p>
                                <p class="mt-1 text-sm text-slate-500">unassigned subject rows</p>
                            </div>
                            <div class="surface-card p-5">
                                <p class="text-3xl font-bold text-slate-950">{{ periodTemplates.filter((period) => period.type === 'class').length }}</p>
                                <p class="mt-1 text-sm text-slate-500">class periods in day template</p>
                            </div>
                        </div>

                        <div v-if="unassignedSubjects.length" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            <div class="flex gap-2">
                                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                                <div>
                                    <p class="font-semibold">Assign every subject before generating.</p>
                                    <p class="mt-1">{{ unassignedSubjects.slice(0, 3).join(', ') }}{{ unassignedSubjects.length > 3 ? '...' : '' }}</p>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn-primary w-full py-3 text-base" :disabled="isSubmitting || unassignedSubjects.length > 0" @click="submitRoutine">
                            <CalendarClock class="h-5 w-5" :class="isSubmitting ? 'animate-spin' : ''" />
                            {{ isSubmitting ? 'Generating...' : 'Generate routine draft' }}
                        </button>
                    </div>
                </section>
            </div>

            <Teleport to="body">
                <div v-if="manualAllocation" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-100/70 p-4" @click.self="closeManualAllocation">
                    <div class="w-full max-w-4xl rounded-lg border border-stone-200 bg-white p-5 shadow-xl">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-950">Manual allocation</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ manualAllocation.subject.name }} - {{ manualAllocation.section.name }}</p>
                            </div>
                            <p class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ manualAllocation.selectedKeys.length }} selected</p>
                        </div>

                        <div class="mt-5 overflow-x-auto">
                            <div class="min-w-[720px]">
                                <div class="grid border-b border-stone-200" :style="{ gridTemplateColumns: `96px repeat(${classPeriods.length}, minmax(84px, 1fr))` }">
                                    <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Day</div>
                                    <div v-for="period in classPeriods" :key="period.id" class="border-l border-stone-200 px-3 py-2 text-center text-xs font-semibold text-slate-600">
                                        <p>{{ period.label }}</p>
                                    </div>
                                </div>
                                <div v-for="day in weekdays" :key="day" class="grid border-b border-stone-200 last:border-b-0" :style="{ gridTemplateColumns: `96px repeat(${classPeriods.length}, minmax(84px, 1fr))` }">
                                    <div class="flex items-center px-3 py-2 text-sm font-semibold text-slate-800">{{ day }}</div>
                                    <div v-for="period in classPeriods" :key="`${day}-${period.id}`" class="border-l border-stone-200 p-1.5">
                                        <button
                                            type="button"
                                            class="h-10 w-full rounded-md border text-xs font-semibold transition"
                                            :class="[
                                                !isManualSlotAllowed(manualAllocation.section, day, period)
                                                    ? 'cursor-not-allowed border-stone-100 bg-stone-50 text-stone-300'
                                                    : isManualSlotSelected(day, period.label)
                                                        ? 'border-blue-600 bg-blue-600 text-white'
                                                        : 'border-stone-200 bg-white text-transparent hover:border-blue-300 hover:bg-blue-50'
                                            ]"
                                            :disabled="!isManualSlotAllowed(manualAllocation.section, day, period)"
                                            @click="toggleManualSlot(day, period)"
                                        >
                                            {{ isManualSlotSelected(day, period.label) ? 'Selected' : '' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" class="btn-secondary" @click="closeManualAllocation">Cancel</button>
                            <button type="button" class="btn-primary" @click="confirmManualAllocation">Confirm</button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>
