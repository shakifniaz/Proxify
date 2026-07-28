<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertTriangle,
    ArrowRightLeft,
    CalendarDays,
    Clock3,
    Layers3,
    Plus,
    RefreshCw,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Trash2,
    UserX,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    activeRoutine: { type: Object, default: null },
    runs: { type: Array, default: () => [] },
    latestRun: { type: Object, default: null },
    defaultSubjectGroups: { type: Array, default: () => [] },
    leaveAbsences: { type: Array, default: () => [] },
});

const isSubmitting = ref(false);
const isSavingGroups = ref(false);
const groupsDirty = ref(false);
const subjectPicker = ref({ groupId: null, query: '', draft: [] });
const activeTab = ref('plan');
const showApprovedLeaves = ref(false);
const query = ref('');
const defaultRunTarget = nextRoutineTarget(props.activeRoutine?.days ?? []);
const selectedDay = ref(defaultRunTarget.day);
const runName = ref(`Proxy run - ${selectedDay.value}`);
const runDate = ref(defaultRunTarget.date);
const classPeriodKeys = (props.activeRoutine?.periods ?? [])
    .filter((period) => (period.type ?? 'class') === 'class')
    .map((period) => period.key)
    .filter(Boolean);
const selectedTeacherIds = ref(new Set(props.leaveAbsences.map((absence) => String(absence.teacherId))));
const teacherPeriods = ref(
    Object.fromEntries(props.leaveAbsences.map((absence) => [
        String(absence.teacherId),
        (absence.periodKeys ?? []).length ? [...absence.periodKeys] : [...classPeriodKeys],
    ]))
);
const subjectGroups = ref(props.defaultSubjectGroups.map((group) => ({
    id: group.id,
    name: group.name,
    subjects: [...(group.subjects ?? [])],
})));

const tabs = [
    { key: 'plan', label: 'Proxy plan' },
    { key: 'groups', label: 'Subject groups' },
];

const teachers = computed(() => props.activeRoutine?.teachers ?? []);
const periods = computed(() => props.activeRoutine?.periods ?? []);
const subjectOptions = computed(() => props.activeRoutine?.subjects ?? []);
const filteredPickerSubjects = computed(() => {
    const needle = subjectPicker.value.query.trim().toLowerCase();
    if (!needle) return subjectOptions.value;
    return subjectOptions.value.filter((subject) => subject.toLowerCase().includes(needle));
});

const filteredTeachers = computed(() => {
    const needle = query.value.trim().toLowerCase();
    if (!needle) return teachers.value;

    return teachers.value.filter((teacher) =>
        `${teacher.name} ${teacher.subjectHint ?? ''}`.toLowerCase().includes(needle)
    );
});

const selectedTeachers = computed(() =>
    teachers.value.filter((teacher) => selectedTeacherIds.value.has(String(teacher.id)))
);
const approvedLeaveAbsences = computed(() => props.leaveAbsences.filter((absence) => absence.status === 'approved'));
const approvedLeaveTeacherIds = computed(() => new Set(approvedLeaveAbsences.value.map((absence) => String(absence.teacherId))));

const canGenerate = computed(() => Boolean(props.activeRoutine?.id) && selectedTeacherIds.value.size > 0 && !isSubmitting.value);
const selectedPeriodCount = computed(() =>
    selectedTeachers.value.reduce((sum, teacher) => sum + (teacherPeriods.value[String(teacher.id)]?.length ?? 0), 0)
);
const runDateDisplay = computed({
    get() {
        return formatDisplayDate(runDate.value);
    },
    set(value) {
        const parsed = parseDisplayDate(value);
        if (parsed) runDate.value = parsed;
    },
});
const selectedDayBadge = computed(() => `${selectedDay.value} - ${runDateDisplay.value}`);
const approvedLeavePreview = computed(() => approvedLeaveAbsences.value.slice(0, 2));
const latestResolvedRate = computed(() => {
    const affected = props.latestRun?.metrics?.affectedPeriods ?? 0;
    if (!affected) return 0;
    return Math.round(((props.latestRun?.metrics?.resolved ?? 0) / affected) * 100);
});

function nextRoutineTarget(routineDays = []) {
    const normalizedDays = routineDays
        .map((day) => ({ label: day, index: dayIndex(day) }))
        .filter((day) => day.index !== null);
    const today = new Date();

    for (let offset = 1; offset <= 14; offset += 1) {
        const candidate = new Date(today);
        candidate.setDate(today.getDate() + offset);
        const match = normalizedDays.find((day) => day.index === candidate.getDay());

        if (match) {
            return {
                day: match.label,
                date: toLocalIsoDate(candidate),
            };
        }
    }

    const fallback = new Date(today);
    fallback.setDate(today.getDate() + 1);

    return {
        day: routineDays[0] ?? 'Sun',
        date: toLocalIsoDate(fallback),
    };
}

function dayIndex(label) {
    const key = String(label || '').trim().slice(0, 3).toLowerCase();
    return {
        sun: 0,
        mon: 1,
        tue: 2,
        wed: 3,
        thu: 4,
        fri: 5,
        sat: 6,
    }[key] ?? null;
}

function toLocalIsoDate(date) {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

function formatDisplayDate(value) {
    const [year, month, day] = String(value || '').split('-');
    if (!year || !month || !day) return '';
    return `${day}/${month}/${year.slice(-2)}`;
}

function parseDisplayDate(value) {
    const match = String(value || '').trim().match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2}|\d{4})$/);
    if (!match) return null;

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3].length === 2 ? `20${match[3]}` : match[3]);
    const date = new Date(year, month - 1, day);

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function initials(name) {
    return String(name || '')
        .split(/\s+/)
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function toggleTeacher(teacherId) {
    const id = String(teacherId);
    if (selectedTeacherIds.value.has(id)) {
        selectedTeacherIds.value.delete(id);
        delete teacherPeriods.value[id];
    } else {
        selectedTeacherIds.value.add(id);
        teacherPeriods.value[id] = [...classPeriodKeys];
    }
    selectedTeacherIds.value = new Set(selectedTeacherIds.value);
}

function togglePeriod(teacherId, periodKey) {
    const id = String(teacherId);
    const current = teacherPeriods.value[id] ?? [];
    if (current.includes(periodKey) && current.length === 1) return;

    teacherPeriods.value[id] = current.includes(periodKey)
        ? current.filter((key) => key !== periodKey)
        : [...current, periodKey];
}

function markFullDay(teacherId) {
    teacherPeriods.value[String(teacherId)] = [...classPeriodKeys];
}

function periodSummary(periodKeys = []) {
    if (periodKeys.length === periods.value.length && periods.value.length > 0) {
        return `All ${periodKeys.length} periods`;
    }

    const labels = periods.value
        .filter((period) => periodKeys.includes(period.key))
        .map((period) => period.label);
    return labels.length ? labels.join(', ') : `${periodKeys.length} periods`;
}

function addSubjectGroup() {
    activeTab.value = 'groups';
    subjectGroups.value.push({
        id: `custom-${Date.now()}`,
        name: `New group ${subjectGroups.value.length + 1}`,
        subjects: [],
    });
    groupsDirty.value = true;
}

function removeSubjectGroup(index) {
    subjectGroups.value.splice(index, 1);
    groupsDirty.value = true;
}

function openSubjectPicker(group) {
    subjectPicker.value = {
        groupId: group.id,
        query: '',
        draft: [...(group.subjects ?? [])],
    };
}

function closeSubjectPicker() {
    subjectPicker.value = { groupId: null, query: '', draft: [] };
}

function draftHasSubject(subject) {
    return subjectPicker.value.draft.includes(subject);
}

function toggleDraftSubject(subject) {
    const draft = subjectPicker.value.draft;
    subjectPicker.value.draft = draft.includes(subject)
        ? draft.filter((item) => item !== subject)
        : [...draft, subject];
}

function confirmSubjectPicker(group) {
    group.subjects = [...subjectPicker.value.draft];
    groupsDirty.value = true;
    closeSubjectPicker();
}

function removeGroupSubject(group, subject) {
    group.subjects = (group.subjects ?? []).filter((item) => item !== subject);
    groupsDirty.value = true;
}

function markGroupsDirty() {
    groupsDirty.value = true;
}

function saveSubjectGroups() {
    if (!props.activeRoutine?.id || isSavingGroups.value) return;
    isSavingGroups.value = true;

    router.put('/proxy-manager/subject-groups', {
        routineId: props.activeRoutine.id,
        subjectGroups: subjectGroups.value.map((group) => ({
            name: group.name,
            subjects: group.subjects ?? [],
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            groupsDirty.value = false;
        },
        onFinish: () => {
            isSavingGroups.value = false;
        },
    });
}

function generateProxyRun() {
    if (!canGenerate.value) return;
    isSubmitting.value = true;

    router.post('/proxy-manager', {
        routineId: props.activeRoutine.id,
        name: runName.value || `Proxy run - ${selectedDay.value}`,
        date: runDate.value,
        day: selectedDay.value,
        absentTeachers: Array.from(selectedTeacherIds.value).map((teacherId) => ({
            teacherId,
            periodKeys: teacherPeriods.value[teacherId] ?? [],
        })),
        subjectGroups: subjectGroups.value.map((group) => ({
            id: group.id,
            name: group.name,
            subjects: group.subjects ?? [],
        })),
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}

function statusClass(status) {
    if (status === 'Needs Review') return 'border-amber-200 bg-amber-50 text-amber-800';
    if (status === 'Finalized' || status === 'Approved') return 'border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#1e2924]';
    return 'border-[#09B884]/30 bg-[#8BED9A]/15 text-[#1e2924]';
}

function assignmentClass(item) {
    if (item.status === 'unresolved') return 'border-red-200 bg-red-50';
    if (item.status === 'review') return 'border-amber-200 bg-amber-50';
    if (item.strategy === 'period_swap') return 'border-[#09B884]/30 bg-[#8BED9A]/15';
    return 'border-stone-200 bg-white';
}

function strategyTone(item) {
    if (item.status === 'unresolved') return 'text-red-700';
    if (item.status === 'review') return 'text-amber-800';
    if (item.strategy === 'period_swap') return 'text-[#1e2924]';
    return 'text-[#09B884]';
}
</script>

<template>
    <AppLayout title="Proxy Manager">
        <div class="space-y-6">
            <div v-if="!activeRoutine" class="surface-card p-8 text-center">
                <AlertTriangle class="mx-auto h-8 w-8 text-amber-600" />
                <p class="mt-3 text-base font-semibold text-slate-950">No active routine found</p>
            </div>

            <template v-else>
                <div class="surface-card p-2">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div class="inline-flex rounded-xl border border-stone-200 bg-stone-100 p-1 shadow-inner shadow-stone-200/60">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                type="button"
                                class="min-h-11 rounded-lg px-5 text-sm font-bold transition-all focus:outline-none focus:ring-2 focus:ring-[#09B884]/35"
                                :class="activeTab === tab.key ? 'bg-[#1e2924]/95 text-white shadow-sm shadow-black/10 ring-1 ring-white/10' : 'text-[#1e2924] hover:bg-white hover:text-[#1e2924] hover:shadow-sm'"
                                @click="activeTab = tab.key"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <div class="grid min-w-0 grid-cols-2 gap-2 sm:grid-cols-[minmax(16rem,1fr)_6.5rem_6.5rem] xl:w-[38rem]">
                            <div class="col-span-2 rounded-md bg-[#8BED9A]/10 px-3 py-2 sm:col-span-1">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-[#1e2924]/55">Active routine</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-950" :title="activeRoutine.name">{{ activeRoutine.name }}</p>
                            </div>
                            <div class="rounded-md bg-[#8BED9A]/10 px-3 py-2 text-center">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-[#1e2924]/55">Sections</p>
                                <p class="mt-1 text-sm font-bold text-slate-950">{{ activeRoutine.summary.sections }}</p>
                            </div>
                            <div class="rounded-md bg-[#8BED9A]/10 px-3 py-2 text-center">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-[#1e2924]/55">Teachers</p>
                                <p class="mt-1 text-sm font-bold text-slate-950">{{ activeRoutine.summary.teachers }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'plan'" class="space-y-5">
                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_26rem] xl:items-stretch">
                                <div class="surface-card bg-white p-4 shadow-sm">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-white text-[#09B884] shadow-sm">
                                                <CalendarDays class="h-4 w-4" />
                                            </div>
                                            <p class="text-sm font-semibold text-slate-950">Plan setup</p>
                                        </div>
                                        <span class="inline-flex items-center justify-center rounded-lg border border-[#09B884]/30 bg-white px-3 py-2 text-xs font-bold text-[#1e2924] shadow-sm">
                                            {{ selectedDayBadge }}
                                        </span>
                                    </div>

                                    <div class="mt-4">
                                        <div>
                                            <label class="section-title">Proxy run name</label>
                                            <input v-model="runName" type="text" class="field-control mt-1 w-full bg-white" />
                                        </div>
                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="section-title">Date</label>
                                                <input
                                                    v-model="runDateDisplay"
                                                    type="text"
                                                    inputmode="numeric"
                                                    placeholder="dd/mm/yy"
                                                    class="field-control mt-1 w-full bg-white"
                                                />
                                            </div>
                                            <div>
                                                <label class="section-title">Routine day</label>
                                                <select v-model="selectedDay" class="field-control mt-1 w-full bg-white">
                                                    <option v-for="day in activeRoutine.days" :key="day" :value="day">{{ day }}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="surface-card flex flex-col border-[#8BED9A]/60 bg-[#8BED9A]/10 p-5 shadow-sm shadow-[#1e2924]/5 xl:min-w-[26rem]">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-950">Readiness</p>
                                            <p class="mt-1 text-xs font-semibold uppercase tracking-wider text-[#1e2924]/55">Engine inputs</p>
                                        </div>
                                        <span class="rounded-full border border-[#8BED9A]/70 bg-white px-3 py-1.5 text-xs font-bold text-[#1e2924] shadow-sm">Swap first</span>
                                    </div>

                                    <div class="mt-4 grid grid-cols-3 gap-3">
                                        <div class="rounded-lg border border-[#8BED9A]/70 bg-white p-3 shadow-sm">
                                            <div class="mb-3 flex h-8 w-8 items-center justify-center rounded-md bg-[#8BED9A]/20 text-[#09B884]">
                                                <UserX class="h-4 w-4" />
                                            </div>
                                            <p class="text-2xl font-bold leading-none text-[#1e2924]">{{ selectedTeacherIds.size }}</p>
                                            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-[#1e2924]/55">Teachers</p>
                                        </div>
                                        <div class="rounded-lg border border-[#8BED9A]/70 bg-white p-3 shadow-sm">
                                            <div class="mb-3 flex h-8 w-8 items-center justify-center rounded-md bg-[#8BED9A]/20 text-[#09B884]">
                                                <SlidersHorizontal class="h-4 w-4" />
                                            </div>
                                            <p class="text-2xl font-bold leading-none text-[#1e2924]">{{ subjectGroups.length }}</p>
                                            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-[#1e2924]/55">Groups</p>
                                        </div>
                                        <div class="rounded-lg border border-[#8BED9A]/70 bg-white p-3 shadow-sm">
                                            <div class="mb-3 flex h-8 w-8 items-center justify-center rounded-md bg-[#8BED9A]/20 text-[#09B884]">
                                                <Clock3 class="h-4 w-4" />
                                            </div>
                                            <p class="text-2xl font-bold leading-none text-[#1e2924]">{{ selectedPeriodCount }}</p>
                                            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-[#1e2924]/55">Periods</p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="mt-4 flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-[#1e2924]/95 px-5 text-sm font-bold text-white shadow-sm shadow-black/10 transition hover:bg-[#1e2924] disabled:cursor-not-allowed disabled:bg-slate-300"
                                        :disabled="!canGenerate"
                                        @click="generateProxyRun"
                                    >
                                        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isSubmitting }" />
                                        {{ isSubmitting ? 'Generating...' : 'Generate proxy plan' }}
                                    </button>
                                </div>
                            </div>

                        <div v-if="approvedLeaveAbsences.length" class="surface-card overflow-hidden">
                            <div class="flex flex-col gap-3 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 text-[#09B884]">
                                        <ShieldCheck class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-slate-950">Approved leaves imported</p>
                                            <span class="rounded-full bg-[#8BED9A]/20 px-2.5 py-1 text-xs font-bold text-[#1e2924]">
                                                {{ approvedLeaveAbsences.length }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                                    <span
                                        v-for="absence in approvedLeavePreview"
                                        :key="`preview-${absence.id}`"
                                        class="inline-flex max-w-[15rem] items-center gap-2 rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-3 py-2 text-xs font-semibold text-[#1e2924]"
                                    >
                                        <span class="truncate">{{ absence.teacherName }}</span>
                                        <span class="shrink-0 text-[#09B884]">{{ periodSummary(absence.periodKeys) }}</span>
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-9 items-center justify-center rounded-lg border border-stone-300 bg-white px-3 text-xs font-bold text-slate-800 shadow-sm transition hover:border-[#09B884]/40 hover:bg-[#8BED9A]/10 hover:text-[#1e2924]"
                                        @click="showApprovedLeaves = !showApprovedLeaves"
                                    >
                                        {{ showApprovedLeaves ? 'Hide' : approvedLeaveAbsences.length > 2 ? `View ${approvedLeaveAbsences.length}` : 'View' }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="showApprovedLeaves" class="border-t border-stone-200 bg-stone-50/70 p-3">
                                <div class="grid max-h-52 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
                                    <div
                                        v-for="absence in approvedLeaveAbsences"
                                        :key="absence.id"
                                        class="rounded-lg border border-stone-200 bg-white px-3 py-2 shadow-sm"
                                    >
                                        <p class="truncate text-sm font-semibold text-slate-950">{{ absence.teacherName }}</p>
                                        <p class="mt-1 text-xs font-semibold text-[#09B884]">{{ periodSummary(absence.periodKeys) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="surface-card grid gap-px overflow-hidden bg-stone-200 xl:grid-cols-[21rem_minmax(0,1fr)]">
                            <div class="bg-white p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950">Unavailable teachers</p>
                                    </div>
                                    <span class="rounded-full border border-[#09B884]/30 bg-[#8BED9A]/15 px-2.5 py-1 text-xs font-semibold text-[#1e2924]">
                                        {{ selectedTeacherIds.size }} selected
                                    </span>
                                </div>

                                <div class="relative mt-4">
                                    <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                    <input v-model="query" type="text" placeholder="Search teacher or subject" class="field-control w-full pl-9" />
                                </div>

                                <div class="mt-3 max-h-[34rem] space-y-2 overflow-y-auto pr-1">
                                    <button
                                        v-for="teacher in filteredTeachers"
                                        :key="teacher.id"
                                        type="button"
                                        class="group w-full rounded-lg border px-3 py-3 text-left transition"
                                        :class="selectedTeacherIds.has(String(teacher.id)) ? 'border-[#09B884]/50 bg-[#8BED9A]/15 shadow-sm' : 'border-stone-200 bg-white hover:border-[#8BED9A] hover:bg-[#8BED9A]/10'"
                                        @click="toggleTeacher(teacher.id)"
                                    >
                                        <span class="flex items-center gap-3">
                                            <span
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border text-xs font-bold"
                                                :class="selectedTeacherIds.has(String(teacher.id)) ? 'border-[#8BED9A]/70 bg-white text-[#1e2924]' : 'border-stone-200 bg-stone-50 text-slate-500 group-hover:text-[#1e2924]'"
                                            >
                                                {{ initials(teacher.name) }}
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-center gap-2">
                                                    <span class="block truncate text-sm font-semibold text-slate-950">{{ teacher.name }}</span>
                                                    <span v-if="approvedLeaveTeacherIds.has(String(teacher.id))" class="shrink-0 rounded-full bg-[#8BED9A]/25 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#1e2924]">
                                                        Leave
                                                    </span>
                                                </span>
                                                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ teacher.subjectHint || 'No subject history yet' }}</span>
                                            </span>
                                            <UserX
                                                class="h-4 w-4 shrink-0"
                                                :class="selectedTeacherIds.has(String(teacher.id)) ? 'text-[#09B884]' : 'text-slate-300'"
                                            />
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div class="bg-white p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950">Availability window</p>
                                    </div>
                                    <span class="rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-2.5 py-1 text-xs font-semibold text-[#1e2924]">
                                        {{ selectedPeriodCount }} period marks
                                    </span>
                                </div>

                                <div v-if="!selectedTeachers.length" class="mt-4 rounded-lg border border-dashed border-stone-300 bg-stone-50 p-8 text-center">
                                    <CalendarDays class="mx-auto h-7 w-7 text-slate-300" />
                                    <p class="mt-2 text-sm font-medium text-slate-600">No teacher selected</p>
                                    <p class="mt-1 text-xs text-slate-500">Choose a teacher or approve a leave request to expose period controls.</p>
                                </div>

                                <div v-else class="mt-4 max-h-[34rem] space-y-3 overflow-y-auto pr-1">
                                    <div v-for="teacher in selectedTeachers" :key="teacher.id" class="rounded-lg border border-stone-200 bg-stone-50/70 p-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="truncate text-sm font-semibold text-slate-950">{{ teacher.name }}</p>
                                                    <span v-if="approvedLeaveTeacherIds.has(String(teacher.id))" class="rounded-full bg-[#8BED9A]/25 px-2 py-0.5 text-[11px] font-bold text-[#1e2924]">
                                                        Approved leave
                                                    </span>
                                                </div>
                                                <p class="mt-0.5 text-xs text-slate-500">
                                                    {{ periodSummary(teacherPeriods[String(teacher.id)] ?? []) }}
                                                </p>
                                            </div>
                                            <button type="button" class="rounded-md border border-[#8BED9A]/70 bg-white px-2.5 py-1.5 text-xs font-semibold text-[#1e2924] hover:bg-[#8BED9A]/15" @click="markFullDay(teacher.id)">
                                                Mark full day
                                            </button>
                                        </div>
                                        <div class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-7">
                                            <button
                                                v-for="period in periods"
                                                :key="period.key"
                                                type="button"
                                                class="rounded-md border px-2 py-2 text-xs font-semibold transition"
                                                :class="(teacherPeriods[String(teacher.id)] ?? []).includes(period.key) ? 'border-[#09B884] bg-[#8BED9A]/15 text-[#1e2924] shadow-sm' : 'border-stone-200 bg-white text-slate-600 hover:bg-[#8BED9A]/10'"
                                                @click="togglePeriod(teacher.id, period.key)"
                                            >
                                                {{ period.label }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[0.75fr_1.25fr]">
                        <div class="surface-card p-5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-950">Recent proxy runs</p>
                                </div>
                                <Layers3 class="h-4 w-4 text-slate-400" />
                            </div>

                            <div v-if="!runs.length" class="mt-4 rounded-lg border border-dashed border-stone-300 p-6 text-center text-sm text-slate-500">
                                Generated proxy plans will appear here.
                            </div>

                            <div v-else class="mt-4 space-y-3">
                                <div v-for="run in runs" :key="run.id" class="rounded-lg border border-stone-200 bg-white p-4 shadow-sm transition hover:border-slate-300">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-950">{{ run.name }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ run.routineName }} &middot; {{ run.day }} &middot; {{ run.createdAt }}</p>
                                        </div>
                                        <span class="shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(run.status)">
                                            {{ run.status }}
                                        </span>
                                    </div>
                                    <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                                        <div class="rounded-md bg-stone-50 px-2 py-2">
                                            <p class="text-base font-bold text-slate-950">{{ run.affected }}</p>
                                            <p class="text-[11px] text-slate-500">affected</p>
                                        </div>
                                        <div class="rounded-md bg-[#8BED9A]/20 px-2 py-2">
                                            <p class="text-base font-bold text-[#1e2924]">{{ run.resolved }}</p>
                                            <p class="text-[11px] text-[#1e2924]">resolved</p>
                                        </div>
                                        <div class="rounded-md bg-red-50 px-2 py-2">
                                            <p class="text-base font-bold text-red-700">{{ run.unresolved }}</p>
                                            <p class="text-[11px] text-red-700">open</p>
                                        </div>
                                    </div>
                                    <Link
                                        :href="`/proxy-manager/${run.id}`"
                                        class="mt-3 flex w-full items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 transition hover:bg-stone-50"
                                    >
                                        Open routine view
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div class="surface-card p-5">
                            <div class="flex flex-col gap-3 border-b border-stone-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-950">Latest generated plan</p>
                                </div>
                                <div v-if="latestRun" class="grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-md border border-[#09B884]/30 bg-[#8BED9A]/15 px-3 py-2">
                                        <p class="text-sm font-bold text-[#1e2924]">{{ latestRun.metrics.swapCount ?? 0 }}</p>
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[#1e2924]">Swaps</p>
                                    </div>
                                    <div class="rounded-md border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-3 py-2">
                                        <p class="text-sm font-bold text-[#1e2924]">{{ latestRun.metrics.proxyCount ?? 0 }}</p>
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[#1e2924]">Proxies</p>
                                    </div>
                                    <div class="rounded-md border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-sm font-bold text-slate-950">{{ latestResolvedRate }}%</p>
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Resolved</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="latestRun" class="mt-4 flex flex-wrap items-center justify-end gap-2">
                            <Link :href="`/proxy-manager/${latestRun.id}`" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1e2924]/95 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-black/10 transition-colors hover:bg-[#1e2924]">
                                    Open proxy routine
                                </Link>
                            </div>

                            <div v-if="!latestRun" class="py-12 text-center">
                                <ShieldCheck class="mx-auto h-9 w-9 text-slate-300" />
                                <p class="mt-3 text-sm font-semibold text-slate-950">No proxy plan generated yet</p>
                            </div>

                            <div v-else class="mt-5 space-y-5">
                                <div v-if="latestRun.adjustments?.length" class="rounded-lg border border-[#09B884]/30 bg-[#8BED9A]/15 p-4">
                                    <div class="flex items-center gap-2 text-sm font-semibold text-[#1e2924]">
                                        <ArrowRightLeft class="h-4 w-4" />
                                        Period swaps applied
                                    </div>
                                    <div class="mt-3 space-y-2">
                                        <div v-for="adjustment in latestRun.adjustments" :key="`${adjustment.classLabel}-${adjustment.from}-${adjustment.to}`" class="text-sm text-[#1e2924]">
                                            {{ adjustment.classLabel }}: {{ adjustment.coverTeacher }} covers {{ adjustment.from }}, {{ adjustment.absentTeacher }} moves to {{ adjustment.to }}.
                                        </div>
                                    </div>
                                </div>

                                <div v-for="group in latestRun.assignments" :key="group.period">
                                    <div class="mb-2 flex items-center gap-2">
                                        <Clock3 class="h-4 w-4 text-slate-400" />
                                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ group.label }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <div v-for="item in group.items" :key="item.id" class="rounded-lg border p-3 shadow-sm" :class="assignmentClass(item)">
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="font-semibold text-slate-950">{{ item.classLabel }}</p>
                                                        <span class="rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-semibold" :class="strategyTone(item)">
                                                            {{ item.strategyLabel }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-1 text-sm text-slate-700">{{ item.subject }}</p>
                                                    <p class="mt-1 text-xs text-slate-500">Unavailable: {{ item.absentTeacher }}</p>
                                                </div>

                                                <div class="shrink-0 text-left sm:text-right">
                                                    <p v-if="item.assignedTeacher" class="text-sm font-semibold text-slate-950">{{ item.assignedTeacher }}</p>
                                                    <p v-else class="text-sm font-semibold text-red-700">No teacher assigned</p>
                                                    <p class="mt-1 max-w-xs text-xs text-slate-500">{{ item.reason }}</p>
                                                </div>
                                            </div>

                                            <div v-if="item.swapPath?.length" class="mt-3 rounded-md border border-amber-200 bg-white/70 p-2">
                                                <div v-for="step in item.swapPath" :key="`${step.teacher}-${step.from}-${step.to}`" class="flex items-center justify-between gap-3 text-xs text-amber-900">
                                                    <span class="font-medium">{{ step.teacher }}</span>
                                                    <span>{{ step.from }} - {{ step.to }}</span>
                                                </div>
                                            </div>

                                            <div v-if="item.subjectGroup" class="mt-3 inline-flex items-center gap-1 rounded-full bg-white/70 px-2 py-1 text-xs font-semibold text-slate-600">
                                                <SlidersHorizontal class="h-3 w-3" />
                                                {{ item.subjectGroup }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="surface-card overflow-visible">
                    <div class="border-b border-stone-200 bg-white p-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="max-w-2xl">
                                <p class="text-sm font-semibold text-slate-950">Subject grouping</p>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="flex h-12 min-w-28 items-center gap-3 rounded-lg border border-stone-200 bg-white px-3 shadow-sm">
                                        <p class="text-lg font-bold leading-none text-slate-950">{{ subjectGroups.length }}</p>
                                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Groups</p>
                                    </div>
                                    <div class="flex h-12 min-w-28 items-center gap-3 rounded-lg border border-stone-200 bg-white px-3 shadow-sm">
                                        <p class="text-lg font-bold leading-none text-slate-950">{{ subjectOptions.length }}</p>
                                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Subjects</p>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <button type="button" class="inline-flex h-12 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-[#1e2924]/95 px-5 text-sm font-bold text-white shadow-sm shadow-black/10 transition-colors hover:bg-[#1e2924]" @click="addSubjectGroup">
                                        <Plus class="h-4 w-4" />
                                        Add group
                                    </button>
                                    <button
                                        type="button"
                                        class="flex h-12 min-w-28 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 text-sm font-bold shadow-sm transition disabled:cursor-default"
                                        :class="groupsDirty ? 'bg-[#1e2924]/95 text-white shadow-sm shadow-black/10 hover:bg-[#1e2924] disabled:opacity-70' : 'border border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#1e2924]'"
                                        :disabled="!groupsDirty || isSavingGroups"
                                        @click="saveSubjectGroups"
                                    >
                                        <RefreshCw v-if="groupsDirty || isSavingGroups" class="h-4 w-4" :class="{ 'animate-spin': isSavingGroups }" />
                                        {{ isSavingGroups ? 'Saving...' : groupsDirty ? 'Save changes' : 'Saved' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5">
                        <div v-if="!subjectOptions.length" class="rounded-lg border border-dashed border-stone-300 bg-stone-50 p-8 text-center">
                            <SlidersHorizontal class="mx-auto h-7 w-7 text-slate-300" />
                            <p class="mt-2 text-sm font-semibold text-slate-950">No subjects found in the active routine</p>
                        </div>

                        <div v-else-if="!subjectGroups.length" class="rounded-lg border border-dashed border-stone-300 bg-stone-50 p-8 text-center">
                            <Plus class="mx-auto h-7 w-7 text-slate-300" />
                            <p class="mt-2 text-sm font-semibold text-slate-950">No subject groups yet</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div v-for="(group, index) in subjectGroups" :key="group.id" class="rounded-lg border border-stone-200 bg-white p-4 shadow-sm">
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <input v-model="group.name" type="text" class="field-control w-full" @input="markGroupsDirty" />
                                            <p class="mt-2 text-xs text-slate-500">{{ group.subjects.length }} selected subjects</p>
                                        </div>
                                        <button type="button" class="rounded-md p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-700" @click="removeSubjectGroup(index)">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <div class="mt-4 min-h-16 rounded-lg border border-stone-200 bg-stone-50 p-3">
                                        <div v-if="group.subjects.length" class="flex flex-wrap gap-2">
                                            <span
                                                v-for="subject in group.subjects"
                                                :key="`${group.id}-selected-${subject}`"
                                                class="inline-flex items-center gap-1.5 rounded-md border border-[#8BED9A]/70 bg-white px-2.5 py-1 text-xs font-semibold text-[#1e2924] shadow-sm"
                                            >
                                                {{ subject }}
                                                <button type="button" class="rounded text-slate-400 hover:text-rose-700" @click="removeGroupSubject(group, subject)">
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </span>
                                        </div>
                                        <p v-else class="text-sm text-slate-500">No subjects selected yet.</p>
                                    </div>

                                    <div class="relative mt-3">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-stone-50"
                                            @click="openSubjectPicker(group)"
                                        >
                                            <Plus class="h-4 w-4" />
                                            Add subject
                                        </button>

                                        <div
                                            v-if="subjectPicker.groupId === group.id"
                                            class="absolute left-0 top-12 z-30 w-full max-w-xl rounded-lg border border-stone-200 bg-white p-3 shadow-xl"
                                        >
                                            <div class="flex items-center justify-between gap-3 border-b border-stone-200 pb-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-950">Select subjects</p>
                                                    <p class="text-xs text-slate-500">{{ subjectPicker.draft.length }} selected for {{ group.name }}</p>
                                                </div>
                                                <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-stone-100 hover:text-slate-700" @click="closeSubjectPicker">
                                                    <X class="h-4 w-4" />
                                                </button>
                                            </div>

                                            <div class="relative mt-3">
                                                <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                                <input
                                                    v-model="subjectPicker.query"
                                                    type="text"
                                                    class="field-control w-full pl-9"
                                                    placeholder="Search subjects..."
                                                />
                                            </div>

                                            <div class="mt-3 max-h-56 space-y-1 overflow-y-auto pr-1">
                                                <button
                                                    v-for="subject in filteredPickerSubjects"
                                                    :key="`${group.id}-picker-${subject}`"
                                                    type="button"
                                                    class="flex w-full items-center justify-between gap-3 rounded-md border px-3 py-2 text-left text-sm transition"
                                                    :class="draftHasSubject(subject) ? 'border-[#09B884]/50 bg-[#8BED9A]/15 text-[#1e2924]' : 'border-stone-200 bg-white text-slate-700 hover:bg-stone-50'"
                                                    @click="toggleDraftSubject(subject)"
                                                >
                                                    <span class="font-medium">{{ subject }}</span>
                                                    <span
                                                        class="flex h-4 w-4 items-center justify-center rounded border"
                                                        :class="draftHasSubject(subject) ? 'border-[#09B884] bg-[#09B884]' : 'border-stone-300 bg-white'"
                                                    >
                                                        <span v-if="draftHasSubject(subject)" class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                                    </span>
                                                </button>
                                                <p v-if="!filteredPickerSubjects.length" class="px-3 py-6 text-center text-sm text-slate-500">No matching subjects.</p>
                                            </div>

                                            <div class="mt-3 flex justify-end gap-2 border-t border-stone-200 pt-3">
                                                <button type="button" class="rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-stone-50" @click="closeSubjectPicker">
                                                    Cancel
                                                </button>
                                                <button type="button" class="rounded-md bg-[#1e2924]/95 px-3 py-2 text-sm font-semibold text-white shadow-sm shadow-black/10 hover:bg-[#1e2924]" @click="confirmSubjectPicker(group)">
                                                    Done
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
