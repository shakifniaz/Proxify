<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircle2, AlertTriangle, X } from 'lucide-vue-next';

// Shape mirrors a future ProxyManagerController@index response.
const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    markDate: { type: String, default: '' },
    teacherOptions: { type: Array, default: () => [] }, // [{ id, name, subject, periodsToday, present }]
    subjectOptions: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
    periodOptions: { type: Array, default: () => [] },
    proxyGroups: { type: Array, default: () => [] }, // [{ period, label, items: [{ class, subject, absentTeacher, status, assignedTeacher }] }]
    availableTeachers: { type: Array, default: () => [] },
});

const steps = [
    { label: 'Mark absences' },
    { label: 'Review & assign' },
    { label: 'Finalize' },
    { label: 'Send WhatsApp' },
];

/* ------------------------------- Mark absences ------------------------------- */

const durations = ['Full day', 'Morning', 'Afternoon'];
const duration = ref('Full day');

const singleTeacher = ref(props.teacherOptions[0]?.name ?? '');
const singleSubject = ref(props.subjectOptions[0] ?? '');
const singleClass = ref(props.classOptions[0] ?? '');

const selectedPeriods = ref(['2nd Pd', '3rd Pd', '4th Pd']);
function togglePeriod(p) {
    const i = selectedPeriods.value.indexOf(p);
    if (i === -1) selectedPeriods.value.push(p);
    else selectedPeriods.value.splice(i, 1);
}

const selectedDates = ref(['2026-07-08', '2026-07-11', '2026-07-15']);
const newDate = ref('');
function addDate() {
    if (newDate.value && !selectedDates.value.includes(newDate.value)) {
        selectedDates.value.push(newDate.value);
        newDate.value = '';
    }
}
function removeDate(i) {
    selectedDates.value.splice(i, 1);
}
function formatDate(d) {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
}

const teacherSearch = ref('');
const markedIds = ref(new Set(props.teacherOptions.filter((t) => !t.present).map((t) => t.id)));
function toggleTeacher(id) {
    if (markedIds.value.has(id)) markedIds.value.delete(id);
    else markedIds.value.add(id);
    // re-assign so Vue's reactivity picks up the Set mutation
    markedIds.value = new Set(markedIds.value);
}
const filteredTeachers = computed(() => {
    const q = teacherSearch.value.trim().toLowerCase();
    if (!q) return props.teacherOptions;
    return props.teacherOptions.filter((t) => t.name.toLowerCase().includes(q) || t.subject.toLowerCase().includes(q));
});

/* --------------------------- Tomorrow's proxy classes --------------------------- */

// Local, mutable copy — the override flow mutates this, never props directly.
const localGroups = ref(props.proxyGroups.map((g) => ({ ...g, items: g.items.map((it) => ({ ...it })) })));

const resolvedCount = computed(() =>
    localGroups.value.reduce((sum, g) => sum + g.items.filter((it) => it.status === 'resolved').length, 0)
);
const unresolvedCount = computed(() =>
    localGroups.value.reduce((sum, g) => sum + g.items.filter((it) => it.status === 'unresolved').length, 0)
);

const overrideTarget = ref(null); // the item object currently being overridden
const overrideChoice = ref('');

function openOverride(item) {
    overrideTarget.value = item;
    overrideChoice.value = '';
}
function cancelOverride() {
    overrideTarget.value = null;
    overrideChoice.value = '';
}
function confirmOverride() {
    if (!overrideTarget.value || !overrideChoice.value) return;
    overrideTarget.value.status = 'resolved';
    overrideTarget.value.assignedTeacher = overrideChoice.value;
    cancelOverride();
}
</script>

<template>
    <AppLayout title="Proxy Manager">
        <div class="space-y-6">
            <!-- Stepper -->
            <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-800 sm:grid-cols-4">
                <div
                    v-for="(step, i) in steps"
                    :key="step.label"
                    class="px-4 py-3"
                    :class="i < 2 ? 'bg-emerald-500/15' : 'bg-slate-900/60'"
                >
                    <p class="text-[10px] font-semibold uppercase tracking-wider" :class="i < 2 ? 'text-emerald-400' : 'text-slate-500'">
                        Step {{ i + 1 }}
                    </p>
                    <p class="text-sm font-semibold" :class="i < 2 ? 'text-emerald-300' : 'text-slate-500'">{{ step.label }}</p>
                </div>
            </div>

            <!-- Summary banner -->
            <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                <div>
                    <p class="text-base font-semibold text-white">Send Tomorrow's Routine Updates</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ summary.routineName }} &middot; {{ summary.absentTeachers }} Absent Teachers &middot;
                        {{ summary.availableTeachers }} Available Teachers
                    </p>
                    <p class="mt-1 text-sm font-medium text-rose-400">{{ summary.proxyClassesTomorrow }} Total Proxy Classes Tomorrow</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-500">
                    <CheckCircle2 class="h-6 w-6 text-slate-950" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Mark absences -->
                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-white">Mark absences</p>
                        <span class="rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-medium text-amber-400">{{ markDate }}</span>
                    </div>

                    <p class="mt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Duration</p>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <button
                            v-for="d in durations"
                            :key="d"
                            type="button"
                            class="rounded-lg border px-3 py-2 text-sm font-medium"
                            :class="duration === d ? 'border-emerald-500 bg-emerald-500/10 text-emerald-400' : 'border-slate-800 text-slate-400 hover:bg-slate-800/50'"
                            @click="duration = d"
                        >
                            {{ d }}
                        </button>
                    </div>

                    <p class="mt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Select Teacher</p>
                    <select
                        v-model="singleTeacher"
                        class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                    >
                        <option v-for="t in teacherOptions" :key="t.id" :value="t.name">{{ t.name }}</option>
                    </select>

                    <p class="mt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Select Subject</p>
                    <select
                        v-model="singleSubject"
                        class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                    >
                        <option v-for="s in subjectOptions" :key="s" :value="s">{{ s }}</option>
                    </select>

                    <p class="mt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Select Class</p>
                    <select
                        v-model="singleClass"
                        class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                    >
                        <option v-for="c in classOptions" :key="c" :value="c">{{ c }}</option>
                    </select>

                    <p class="mt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Select Absent Periods</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="p in periodOptions"
                            :key="p"
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-xs font-semibold"
                            :class="selectedPeriods.includes(p) ? 'border-rose-500 bg-rose-500/15 text-rose-300' : 'border-slate-800 text-slate-500 hover:bg-slate-800/50'"
                            @click="togglePeriod(p)"
                        >
                            {{ p }}
                        </button>
                    </div>

                    <p class="mt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Select Date</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span
                            v-for="(d, i) in selectedDates"
                            :key="d"
                            class="flex items-center gap-1.5 rounded-full border border-slate-800 bg-slate-800/60 px-2.5 py-1 text-xs text-slate-300"
                        >
                            {{ formatDate(d) }}
                            <button type="button" class="text-slate-500 hover:text-rose-400" @click="removeDate(i)">
                                <X class="h-3 w-3" />
                            </button>
                        </span>
                        <div class="flex items-center gap-1.5">
                            <input
                                v-model="newDate"
                                type="date"
                                class="rounded-lg border border-slate-800 bg-slate-800/60 px-2 py-1 text-xs text-slate-200 focus:border-emerald-500 focus:outline-none"
                            />
                            <button
                                type="button"
                                class="rounded-lg border border-slate-800 px-2.5 py-1 text-xs font-medium text-emerald-400 hover:bg-slate-800/50"
                                @click="addDate"
                            >
                                + Add
                            </button>
                        </div>
                    </div>

                    <p class="mt-6 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Or Select Multiple Teachers</p>
                    <input
                        v-model="teacherSearch"
                        type="text"
                        placeholder="Search teachers..."
                        class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                    />
                    <div class="mt-2 max-h-64 space-y-2 overflow-y-auto pr-1">
                        <label
                            v-for="t in filteredTeachers"
                            :key="t.id"
                            class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border px-3 py-2.5 transition-colors"
                            :class="markedIds.has(t.id) ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-slate-800 hover:bg-slate-800/40'"
                        >
                            <span class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    :checked="markedIds.has(t.id)"
                                    class="h-4 w-4 rounded border-slate-700 bg-slate-800 text-emerald-500 focus:ring-emerald-500"
                                    @change="toggleTeacher(t.id)"
                                />
                                <span>
                                    <span class="block text-sm font-medium text-slate-100">{{ t.name }}</span>
                                    <span class="block text-xs text-slate-500">{{ t.subject }} &middot; {{ t.periodsToday }} periods today</span>
                                </span>
                            </span>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="markedIds.has(t.id) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-800 text-slate-500'"
                            >
                                {{ markedIds.has(t.id) ? t.periodsToday : 'present' }}
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Tomorrow's Proxy Classes -->
                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-white">Tomorrow's Proxy Classes</p>
                        <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-medium text-emerald-400">
                            {{ resolvedCount }} resolved
                        </span>
                    </div>

                    <div class="mt-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-300">
                        Priority: same subject &rarr; same class &rarr; least load today
                    </div>

                    <div class="mt-4 max-h-[28rem] space-y-5 overflow-y-auto pr-1">
                        <div v-for="group in localGroups" :key="group.period">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ group.label }}</p>
                            <div class="mt-2 space-y-2">
                                <div
                                    v-for="item in group.items"
                                    :key="item.class + item.subject"
                                    class="rounded-lg px-3 py-2.5"
                                    :class="item.status === 'unresolved' ? 'border border-rose-500/30 bg-rose-500/10' : 'bg-slate-800/40'"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p
                                                class="flex items-center gap-1 text-sm font-semibold"
                                                :class="item.status === 'unresolved' ? 'text-rose-300' : 'text-slate-100'"
                                            >
                                                <AlertTriangle v-if="item.status === 'unresolved'" class="h-3.5 w-3.5 shrink-0" />
                                                {{ item.class }} &middot; {{ item.subject }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                {{ item.status === 'unresolved' ? 'No available teacher found' : `Absent: ${item.absentTeacher}` }}
                                            </p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p v-if="item.status === 'resolved'" class="text-sm font-medium text-emerald-400">
                                                &rarr; {{ item.assignedTeacher }}
                                            </p>
                                            <button
                                                v-else
                                                type="button"
                                                class="rounded-full border border-rose-500/40 bg-rose-500/15 px-3 py-1 text-xs font-semibold text-rose-300 hover:bg-rose-500/25"
                                                @click="openOverride(item)"
                                            >
                                                Override
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="overrideTarget === item" class="mt-3 flex items-center gap-2">
                                        <select
                                            v-model="overrideChoice"
                                            class="flex-1 rounded-lg border border-slate-800 bg-slate-800/60 px-2 py-1.5 text-xs text-slate-100 focus:border-emerald-500 focus:outline-none"
                                        >
                                            <option value="" disabled>Choose teacher</option>
                                            <option v-for="t in availableTeachers" :key="t" :value="t">{{ t }}</option>
                                        </select>
                                        <button
                                            type="button"
                                            class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-slate-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="!overrideChoice"
                                            @click="confirmOverride"
                                        >
                                            Assign
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-800 px-2.5 py-1.5 text-xs text-slate-400 hover:bg-slate-800/50"
                                            @click="cancelOverride"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-4">
                        <p class="text-xs text-slate-500">
                            <span class="font-semibold text-emerald-400">{{ resolvedCount }} resolved</span>
                            &middot;
                            <span class="font-semibold text-rose-400">{{ unresolvedCount }} unresolved</span>
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/50"
                            >
                                Edit draft
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="unresolvedCount > 0"
                                :title="unresolvedCount > 0 ? 'Resolve all periods before finalizing' : ''"
                            >
                                Finalize &amp; Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>