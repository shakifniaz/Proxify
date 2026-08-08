<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CalendarClock,
    CheckCircle2,
    Clock3,
    GraduationCap,
    GripVertical,
    Layers,
    Plus,
    RefreshCw,
    Settings2,
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
const stepDetails = computed(() => [
    { name: 'Structure', icon: GraduationCap, hint: `${classes.value.length} classes, ${totalSections.value} sections` },
    { name: 'Subjects', icon: BookOpen, hint: `${totalSubjectRows.value} subject rows` },
    { name: 'Teachers', icon: Users, hint: `${teacherPool.value.length} teachers` },
    { name: 'Timings', icon: Clock3, hint: `${classPeriods.value.length} teaching periods` },
    { name: 'Generate', icon: Settings2, hint: unassignedSubjects.value.length ? `${unassignedSubjects.value.length} needs attention` : 'Ready check' },
]);
const activeStepIndex = computed(() => steps.indexOf(activeStep.value));
const setupProgress = computed(() => Math.round(((activeStepIndex.value + 1) / steps.length) * 100));

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
const missingSetup = computed(() => {
    if (!classes.value.length) return 'Add classes in the Classrooms tab before generating a routine.';
    if (!teacherPool.value.length) return 'Add teachers in the Teachers tab before generating a routine.';
    return '';
});
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

function goToStep(offset) {
    const nextIndex = Math.min(steps.length - 1, Math.max(0, activeStepIndex.value + offset));
    activeStep.value = steps[nextIndex];
}
</script>

<template>
    <AppLayout title="Create Routine">
        <div class="routine-create space-y-5">
            <section class="relative overflow-hidden rounded-2xl border border-[#8BED9A]/45 bg-white p-5 shadow-sm">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(139,237,154,0.28),transparent_34%),linear-gradient(135deg,rgba(9,184,132,0.08),transparent_48%)]"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-[0.24em] text-[#09B884]">Routine builder</p>
                        <h2 class="mt-1 text-2xl font-black text-[#1e2924]">{{ routineMeta.name || 'New routine' }}</h2>
                        <p class="mt-1 text-sm font-medium text-slate-500">{{ routineMeta.termLabel || 'Term label not set' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link href="/routines" class="btn-secondary">Cancel</Link>
                        <button type="button" class="btn-primary min-h-11" :disabled="isSubmitting || unassignedSubjects.length > 0 || Boolean(missingSetup)" @click="submitRoutine">
                            <RefreshCw class="h-4 w-4" :class="isSubmitting ? 'animate-spin' : ''" />
                            {{ isSubmitting ? 'Creating...' : 'Create routine' }}
                        </button>
                    </div>
                </div>

                <div class="relative mt-5 overflow-hidden rounded-full bg-stone-100">
                    <div class="h-2 rounded-full bg-gradient-to-r from-[#09B884] to-[#8BED9A] transition-all duration-500" :style="{ width: `${setupProgress}%` }"></div>
                </div>
            </section>

            <section class="surface-card p-2">
                <div class="grid gap-2 md:grid-cols-5">
                    <button
                        v-for="(step, index) in stepDetails"
                        :key="step.name"
                        type="button"
                        class="group rounded-xl border p-3 text-left transition-all duration-300 hover:-translate-y-0.5"
                        :class="activeStep === step.name ? 'border-[#1e2924] bg-[#1e2924] text-white shadow-lg shadow-[#1e2924]/15' : 'border-transparent bg-stone-50 text-[#1e2924] hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/15'"
                        @click="activeStep = step.name"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg" :class="activeStep === step.name ? 'bg-[#8BED9A]/20 text-[#8BED9A]' : 'bg-white text-[#09B884]'">
                                <component :is="step.icon" class="h-4 w-4" />
                            </span>
                            <span class="text-xs font-black opacity-60">0{{ index + 1 }}</span>
                        </div>
                        <p class="mt-3 text-sm font-black">{{ step.name }}</p>
                        <p class="mt-1 truncate text-xs font-semibold" :class="activeStep === step.name ? 'text-white/60' : 'text-slate-500'">{{ step.hint }}</p>
                    </button>
                </div>
            </section>

            <Transition name="routine-step" mode="out-in">
                <section :key="activeStep" class="space-y-4">
                    <div v-if="activeStep === 'Structure'" class="space-y-4">
                        <div class="surface-card p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-lg font-black text-[#1e2924]">Start with the school week</p>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-xl bg-[#8BED9A]/16 px-3 py-2">
                                        <p class="text-lg font-black text-[#1e2924]">{{ classes.length }}</p>
                                        <p class="text-[10px] font-black uppercase text-[#1e2924]/55">Classes</p>
                                    </div>
                                    <div class="rounded-xl bg-[#8BED9A]/16 px-3 py-2">
                                        <p class="text-lg font-black text-[#1e2924]">{{ totalSections }}</p>
                                        <p class="text-[10px] font-black uppercase text-[#1e2924]/55">Sections</p>
                                    </div>
                                    <div class="rounded-xl bg-[#8BED9A]/16 px-3 py-2">
                                        <p class="text-lg font-black text-[#1e2924]">{{ weekdays.length }}</p>
                                        <p class="text-[10px] font-black uppercase text-[#1e2924]/55">Days</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                <label>
                                    <span class="section-title">Routine name</span>
                                    <input v-model="routineMeta.name" type="text" class="field-control mt-1 w-full" />
                                </label>
                                <label>
                                    <span class="section-title">Term label</span>
                                    <input v-model="routineMeta.termLabel" type="text" class="field-control mt-1 w-full" />
                                </label>
                            </div>

                            <div class="mt-5">
                                <p class="section-title">Working days</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                                    <label
                                        v-for="day in availableWeekdays"
                                        :key="day"
                                        class="flex min-h-12 cursor-pointer items-center justify-center rounded-xl border text-sm font-black transition"
                                        :class="weekdays.includes(day) ? 'border-[#09B884]/60 bg-[#8BED9A]/22 text-[#1e2924] shadow-sm' : 'border-stone-200 bg-white text-slate-500 hover:border-[#8BED9A]/60'"
                                    >
                                        <input v-model="weekdays" :value="day" type="checkbox" class="sr-only" />
                                        {{ day }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="surface-card p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-lg font-black text-[#1e2924]">Classes and sections</p>
                                    <p class="mt-1 text-sm text-slate-500">Drag classes into order and adjust each section only where needed.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="btn-secondary" @click="sortClasses">Sort standard order</button>
                                    <Link href="/classrooms" class="btn-secondary">
                                        <Plus class="h-4 w-4" />
                                        Manage classes
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div v-if="!classes.length" class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-900">
                            Add classes and sections in the Classrooms tab first.
                        </div>

                        <div
                            v-for="(cls, classIndex) in classes"
                            :key="cls.id"
                            class="surface-card overflow-hidden transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                            :class="classOrderDragIndex === classIndex ? 'opacity-50' : ''"
                            @dragover.prevent
                            @drop="onClassOrderDrop(classIndex, $event)"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 bg-stone-50/70 p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        draggable="true"
                                        class="flex h-10 w-10 cursor-grab items-center justify-center rounded-xl border border-stone-200 bg-white text-slate-400 active:cursor-grabbing"
                                        @dragstart="onClassOrderDragStart(classIndex, $event)"
                                        @dragend="onClassOrderDragEnd"
                                    >
                                        <GripVertical class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-base font-black text-[#1e2924]">{{ cls.name }}</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ cls.sections.length }} section{{ cls.sections.length === 1 ? '' : 's' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3 p-4 xl:grid-cols-2">
                                <div v-for="section in cls.sections" :key="section.id" class="rounded-xl border border-stone-200 bg-white p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="font-black text-slate-950">{{ section.name }}</p>
                                            <p class="text-xs font-semibold text-slate-500">Class teacher: {{ teacherName(section.classTeacherId) }}</p>
                                        </div>
                                        <select v-model="section.classTeacherId" class="field-control-sm w-52">
                                            <option :value="null">Select teacher</option>
                                            <option v-for="teacher in teacherPool" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                                        </select>
                                    </div>

                                    <div class="mt-4 rounded-xl border border-[#8BED9A]/45 bg-[#8BED9A]/10 p-3">
                                        <div class="flex flex-wrap items-end justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-black text-[#1e2924]">Daily period pattern</p>
                                                <p class="text-xs font-semibold text-slate-500">Set a normal day, then fine tune.</p>
                                            </div>
                                            <div class="flex items-end gap-2">
                                                <label>
                                                    <span class="block text-[10px] font-black uppercase tracking-wider text-[#1e2924]/55">Normal</span>
                                                    <input v-model.number="section.dailyPeriods" min="0" type="number" class="mt-1 h-9 w-20 rounded-lg border border-stone-300 bg-white px-3 text-sm font-black focus:border-[#09B884] focus:outline-none" />
                                                </label>
                                                <button type="button" class="btn-secondary min-h-9 px-3 py-1.5 text-xs" @click="applyPeriodsToAllDays(section)">Apply</button>
                                            </div>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                                            <div v-for="day in weekdays" :key="`${section.id}-${day}`" class="rounded-lg bg-white p-2 text-center shadow-sm">
                                                <p class="text-[10px] font-black uppercase text-slate-500">{{ day }}</p>
                                                <div class="mt-1 flex items-center justify-center rounded-lg border border-stone-200">
                                                    <button type="button" class="h-7 w-7 text-slate-500 hover:text-[#09B884]" @click="adjustDayPeriods(section, day, -1)">-</button>
                                                    <input v-model.number="section.dailyPeriodsByDay[day]" inputmode="numeric" type="text" class="h-7 w-8 border-x border-stone-200 text-center text-sm font-black focus:outline-none" />
                                                    <button type="button" class="h-7 w-7 text-slate-500 hover:text-[#09B884]" @click="adjustDayPeriods(section, day, 1)">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 'Subjects'" class="space-y-4">
                        <div class="surface-card p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-lg font-black text-[#1e2924]">Subjects and teacher loads</p>
                                    <p class="mt-1 text-sm text-slate-500">Cards keep each subject readable while preserving weekly load, color, and manual allocation controls.</p>
                                </div>
                                <span class="rounded-full bg-[#8BED9A]/20 px-3 py-1 text-xs font-black text-[#1e2924]">{{ autoBalancedSubjects }} auto-balanced</span>
                            </div>
                        </div>

                        <div v-for="cls in classes" :key="cls.id" class="surface-card overflow-hidden">
                            <div class="flex items-center gap-2 border-b border-stone-200 bg-stone-50/70 p-4">
                                <Layers class="h-4 w-4 text-[#09B884]" />
                                <p class="font-black text-slate-950">{{ cls.name }}</p>
                            </div>
                            <div class="space-y-4 p-4">
                                <div v-for="section in cls.sections" :key="section.id" class="rounded-xl border border-stone-200 bg-white p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="font-black text-slate-950">{{ section.name }}</p>
                                            <p class="text-xs font-semibold text-slate-500">Class teacher: {{ teacherName(section.classTeacherId) }}</p>
                                        </div>
                                        <button type="button" class="btn-secondary min-h-9 px-3 py-1.5 text-xs" @click="addSubject(section)">
                                            <Plus class="h-3.5 w-3.5" />
                                            Add subject
                                        </button>
                                    </div>

                                    <div class="mt-4 grid gap-3 xl:grid-cols-2">
                                        <div v-for="(subject, subjectIndex) in section.subjects" :key="subject.id" class="group rounded-xl border border-stone-200 bg-stone-50/70 p-3 transition hover:-translate-y-0.5 hover:border-[#8BED9A]/70 hover:bg-white hover:shadow-sm">
                                            <div class="flex items-start gap-3">
                                                <input v-model="subject.color" type="color" class="mt-1 h-10 w-10 shrink-0 cursor-pointer rounded-xl border border-stone-300 bg-white p-1" />
                                                <div class="min-w-0 flex-1 space-y-3">
                                                    <input v-model="subject.name" type="text" class="field-control-sm w-full bg-white font-black" />
                                                    <select v-model="subject.teacherId" class="field-control-sm w-full bg-white">
                                                        <option :value="null">Unassigned teacher</option>
                                                        <option v-for="teacher in teacherPool" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                                                    </select>
                                                    <div class="grid gap-2 sm:grid-cols-[8rem_minmax(0,1fr)_7rem]">
                                                        <input v-model="subject.weeklyPeriods" :disabled="subject.autoBalance" min="1" type="number" class="field-control-sm w-full disabled:bg-stone-100" placeholder="Per week" />
                                                        <label class="flex min-h-9 items-center gap-2 rounded-lg border border-stone-200 bg-white px-3 text-xs font-bold text-slate-600">
                                                            <input v-model="subject.autoBalance" type="checkbox" class="rounded border-stone-300 text-[#09B884] focus:ring-[#09B884]" />
                                                            Auto average
                                                        </label>
                                                        <button type="button" class="rounded-lg border border-[#8BED9A]/55 bg-white px-2 text-xs font-black text-[#1e2924] transition hover:bg-[#8BED9A]/15" @click="openManualAllocation(section, subject)">
                                                            {{ manualSlotCount(subject) ? `${manualSlotCount(subject)} set` : 'Allocate' }}
                                                        </button>
                                                    </div>
                                                </div>
                                                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-700" @click="removeSubject(section, subjectIndex)">
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 'Teachers'" class="space-y-4">
                        <div class="surface-card p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-lg font-black text-[#1e2924]">Teacher order</p>
                                    <p class="mt-1 text-sm text-slate-500">Drag teachers into the order you want the engine to consider.</p>
                                </div>
                                <Link href="/teachers" class="btn-secondary">
                                    <Plus class="h-4 w-4" />
                                    Manage teachers
                                </Link>
                            </div>
                        </div>

                        <div v-if="!teacherPool.length" class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-900">
                            Add teachers in the Teachers tab first.
                        </div>

                        <div class="grid gap-3 xl:grid-cols-2">
                            <div
                                v-for="(teacher, teacherIndex) in teacherPool"
                                :key="teacher.id"
                                class="surface-card p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                                :class="teacherOrderDragIndex === teacherIndex ? 'opacity-50' : ''"
                                @dragover.prevent
                                @drop="onTeacherOrderDrop(teacherIndex, $event)"
                            >
                                <div class="flex items-center gap-3">
                                    <div draggable="true" class="flex h-11 w-11 cursor-grab items-center justify-center rounded-xl border border-stone-200 bg-stone-50 text-slate-500 active:cursor-grabbing" @dragstart="onTeacherOrderDragStart(teacherIndex, $event)" @dragend="onTeacherOrderDragEnd">
                                        <GripVertical class="h-5 w-5" />
                                    </div>
                                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#8BED9A]/18 text-[#09B884]">
                                        <UserRound class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-black text-slate-950">{{ teacher.name }}</p>
                                        <p class="truncate text-xs font-semibold text-slate-500">{{ teacher.phone || 'WhatsApp not set' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 'Timings'" class="space-y-4">
                        <div class="surface-card p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-lg font-black text-[#1e2924]">Periods and breaks</p>
                                    <p class="mt-1 text-sm text-slate-500">Build the day once. Class-specific shorter days still use the first periods from this order.</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="btn-secondary" @click="addPeriod('class')">Add period</button>
                                    <button type="button" class="btn-secondary" @click="addPeriod('break')">Add break</button>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <div v-for="(period, index) in periodTemplates" :key="period.id" class="surface-card grid gap-3 p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md sm:grid-cols-[3rem_minmax(0,1fr)_9rem_9rem_10rem_2rem] sm:items-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl" :class="period.type === 'break' ? 'bg-amber-100 text-amber-700' : 'bg-[#8BED9A]/18 text-[#09B884]'">
                                    <Clock3 class="h-4 w-4" />
                                </div>
                                <input v-model="period.label" type="text" class="field-control-sm" />
                                <input v-model="period.startTime" type="time" class="field-control-sm" />
                                <input v-model="period.endTime" type="time" class="field-control-sm" />
                                <select v-model="period.type" class="field-control-sm">
                                    <option value="class">Class period</option>
                                    <option value="break">Break / lunch</option>
                                </select>
                                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-700" @click="removePeriod(index)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <div class="surface-card p-5">
                            <p class="text-lg font-black text-[#1e2924]">Final check</p>
                            <div class="mt-4 grid gap-3 md:grid-cols-3">
                                <div class="rounded-xl bg-[#8BED9A]/16 p-4">
                                    <p class="text-3xl font-black text-[#1e2924]">{{ autoBalancedSubjects }}</p>
                                    <p class="text-xs font-black uppercase tracking-wider text-[#1e2924]/55">Auto balanced</p>
                                </div>
                                <div class="rounded-xl p-4" :class="unassignedSubjects.length ? 'bg-red-50 text-red-800' : 'bg-[#8BED9A]/16 text-[#1e2924]'">
                                    <p class="text-3xl font-black">{{ unassignedSubjects.length }}</p>
                                    <p class="text-xs font-black uppercase tracking-wider opacity-70">Unassigned</p>
                                </div>
                                <div class="rounded-xl bg-[#8BED9A]/16 p-4">
                                    <p class="text-3xl font-black text-[#1e2924]">{{ classPeriods.length }}</p>
                                    <p class="text-xs font-black uppercase tracking-wider text-[#1e2924]/55">Teaching periods</p>
                                </div>
                            </div>
                        </div>

                        <div class="surface-card p-5">
                            <div class="grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
                                <label>
                                    <span class="section-title">Max consecutive periods</span>
                                    <input v-model.number="generationRules.maxConsecutivePeriods" min="1" max="5" type="number" class="field-control mt-1 w-full" />
                                </label>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label v-for="item in [
                                        ['preferGapBetweenPeriods', 'Prefer teacher gaps'],
                                        ['autoBalanceUnsetSubjectLoads', 'Auto-balance empty loads'],
                                        ['keepClassTeacherFirstPeriod', 'Class teacher first period'],
                                        ['flagUnallocatedSlots', 'Flag unresolved class cells']
                                    ]" :key="item[0]" class="flex min-h-12 items-center gap-3 rounded-xl border border-stone-200 bg-stone-50/70 px-3 text-sm font-bold text-slate-700">
                                        <input v-model="generationRules[item[0]]" type="checkbox" class="rounded border-stone-300 text-[#09B884] focus:ring-[#09B884]" />
                                        {{ item[1] }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div v-if="unassignedSubjects.length" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            <div class="flex gap-2">
                                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                                <div>
                                    <p class="font-black">Assign every subject before generating.</p>
                                    <p class="mt-1">{{ unassignedSubjects.slice(0, 3).join(', ') }}{{ unassignedSubjects.length > 3 ? '...' : '' }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="missingSetup" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-900">
                            {{ missingSetup }}
                        </div>

                        <button type="button" class="btn-primary w-full py-3 text-base" :disabled="isSubmitting || unassignedSubjects.length > 0 || Boolean(missingSetup)" @click="submitRoutine">
                            <CalendarClock class="h-5 w-5" :class="isSubmitting ? 'animate-spin' : ''" />
                            {{ isSubmitting ? 'Generating...' : 'Generate routine draft' }}
                        </button>
                    </div>
                </section>
            </Transition>

            <div class="surface-card flex flex-wrap items-center justify-between gap-3 p-3">
                <button type="button" class="btn-secondary" :disabled="activeStepIndex === 0" @click="goToStep(-1)">
                    <ArrowLeft class="h-4 w-4" />
                    Back
                </button>
                <div class="flex items-center justify-center gap-1.5">
                    <span
                        v-for="step in steps"
                        :key="`dot-${step}`"
                        class="h-2.5 rounded-full transition-all duration-300"
                        :class="steps.indexOf(step) <= activeStepIndex ? 'w-8 bg-[#09B884]' : 'w-2.5 bg-stone-300'"
                    ></span>
                </div>
                <button v-if="activeStepIndex < steps.length - 1" type="button" class="btn-primary" @click="goToStep(1)">
                    Next
                    <ArrowRight class="h-4 w-4" />
                </button>
                <button v-else type="button" class="btn-primary" :disabled="isSubmitting || unassignedSubjects.length > 0 || Boolean(missingSetup)" @click="submitRoutine">
                    <RefreshCw class="h-4 w-4" :class="isSubmitting ? 'animate-spin' : ''" />
                    Create routine
                </button>
            </div>

            <Teleport to="body">
                <div v-if="manualAllocation" class="fixed inset-0 z-50 flex items-center justify-center bg-[#1e2924]/30 p-4 backdrop-blur-sm" @click.self="closeManualAllocation">
                    <div class="w-full max-w-4xl rounded-2xl border border-[#8BED9A]/50 bg-white p-5 shadow-2xl">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-black text-[#1e2924]">Manual allocation</h3>
                                <p class="mt-1 text-sm font-semibold text-slate-500">{{ manualAllocation.subject.name }} - {{ manualAllocation.section.name }}</p>
                            </div>
                            <p class="rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-3 py-1 text-xs font-black text-[#1e2924]">{{ manualAllocation.selectedKeys.length }} selected</p>
                        </div>

                        <div class="mt-5 overflow-x-auto">
                            <div class="min-w-[720px] overflow-hidden rounded-xl border border-stone-200">
                                <div class="grid border-b border-stone-200 bg-stone-50" :style="{ gridTemplateColumns: `96px repeat(${classPeriods.length}, minmax(84px, 1fr))` }">
                                    <div class="px-3 py-2 text-xs font-black uppercase tracking-wide text-slate-500">Day</div>
                                    <div v-for="period in classPeriods" :key="period.id" class="border-l border-stone-200 px-3 py-2 text-center text-xs font-black text-slate-600">{{ period.label }}</div>
                                </div>
                                <div v-for="day in weekdays" :key="day" class="grid border-b border-stone-200 last:border-b-0" :style="{ gridTemplateColumns: `96px repeat(${classPeriods.length}, minmax(84px, 1fr))` }">
                                    <div class="flex items-center bg-stone-50 px-3 py-2 text-sm font-black text-slate-800">{{ day }}</div>
                                    <div v-for="period in classPeriods" :key="`${day}-${period.id}`" class="border-l border-stone-200 p-1.5">
                                        <button
                                            type="button"
                                            class="h-10 w-full rounded-lg border text-xs font-black transition"
                                            :class="[
                                                !isManualSlotAllowed(manualAllocation.section, day, period)
                                                    ? 'cursor-not-allowed border-stone-100 bg-stone-50 text-stone-300'
                                                    : isManualSlotSelected(day, period.label)
                                                        ? 'border-[#09B884] bg-[#09B884] text-white shadow-sm'
                                                        : 'border-stone-200 bg-white text-transparent hover:border-[#8BED9A] hover:bg-[#8BED9A]/15'
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

<style scoped>
.routine-create {
    animation: routine-create-rise 420ms ease-out both;
}

.routine-step-enter-active,
.routine-step-leave-active {
    transition: all 220ms ease;
}

.routine-step-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.routine-step-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@keyframes routine-create-rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
