<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChevronLeft, ChevronRight, AlertTriangle, Repeat, Pencil } from 'lucide-vue-next';

// Shape mirrors a future RoutineController@show response.
const props = defineProps({
    routine: { type: Object, default: () => ({}) },
    days: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    legend: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
});

// Literal Tailwind class strings per subject color — required so the JIT
// scanner can find them; never interpolate color names into class names.
const cellColors = {
    blue: { bg: 'bg-blue-500/15', border: 'border-blue-400', text: 'text-blue-300', pill: 'bg-blue-500/15 text-blue-300 border border-blue-500/30' },
    amber: { bg: 'bg-amber-500/15', border: 'border-amber-400', text: 'text-amber-300', pill: 'bg-amber-500/15 text-amber-300 border border-amber-500/30' },
    emerald: { bg: 'bg-emerald-500/15', border: 'border-emerald-400', text: 'text-emerald-300', pill: 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' },
    indigo: { bg: 'bg-indigo-500/15', border: 'border-indigo-400', text: 'text-indigo-300', pill: 'bg-indigo-500/15 text-indigo-300 border border-indigo-500/30' },
    cyan: { bg: 'bg-cyan-500/15', border: 'border-cyan-400', text: 'text-cyan-300', pill: 'bg-cyan-500/15 text-cyan-300 border border-cyan-500/30' },
    rose: { bg: 'bg-rose-500/15', border: 'border-rose-400', text: 'text-rose-300', pill: 'bg-rose-500/15 text-rose-300 border border-rose-500/30' },
};

// Local, mutable copy of the grid — drag/drop and the edit popup operate on
// this, never on props directly. Once the backend exists, each mutation
// below is exactly where a PATCH request to persist the change would go.
const gridTeachers = ref(props.teachers.map((t) => ({ ...t, cells: { ...t.cells } })));

const selectedDay = ref(props.days[0] ?? 'Sun');
const dayNames = { Sun: 'Sunday', Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday', Fri: 'Friday', Sat: 'Saturday' };

function selectDay(day) {
    selectedDay.value = day;
}

function stepDay(direction) {
    const i = props.days.indexOf(selectedDay.value);
    const next = (i + direction + props.days.length) % props.days.length;
    selectedDay.value = props.days[next];
}

const gridStyle = computed(() => ({
    gridTemplateColumns: `200px repeat(${props.periods.length}, minmax(110px, 1fr))`,
}));

function cellClasses(cell) {
    if (!cell || cell.type === 'empty') {
        return 'border border-dashed border-slate-800 bg-slate-800/20 hover:bg-slate-800/50';
    }
    if (cell.type === 'unresolved') {
        return 'border-l-2 border-rose-500 bg-rose-500/10';
    }
    if (cell.type === 'proxy') {
        return 'border-l-2 border-emerald-500 bg-emerald-500/10';
    }
    const c = cellColors[cell.color];
    return `border-l-2 ${c.border} ${c.bg}`;
}

/* ---------------- Drag and drop: move into empty slot, or swap ---------------- */

const dragSource = ref(null); // { teacherIndex, periodKey }
const dragOverKey = ref(null);

function cellKey(teacherIndex, periodKey) {
    return `${teacherIndex}-${periodKey}`;
}

function onDragStart(teacherIndex, periodKey, event) {
    dragSource.value = { teacherIndex, periodKey };
    event.dataTransfer?.setData?.('text/plain', cellKey(teacherIndex, periodKey));
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
}

function onDragOverCell(teacherIndex, periodKey, event) {
    event.preventDefault();
    dragOverKey.value = cellKey(teacherIndex, periodKey);
}

function onDragLeaveCell() {
    dragOverKey.value = null;
}

function onDrop(teacherIndex, periodKey, event) {
    event.preventDefault();
    dragOverKey.value = null;

    const from = dragSource.value;
    dragSource.value = null;
    if (!from) return;

    const to = { teacherIndex, periodKey };
    if (from.teacherIndex === to.teacherIndex && from.periodKey === to.periodKey) return;

    const fromCell = gridTeachers.value[from.teacherIndex].cells[from.periodKey];
    const toCell = gridTeachers.value[to.teacherIndex].cells[to.periodKey];

    // Same swap covers both cases: dropping on an empty slot just moves the
    // period there (the source becomes empty); dropping on a filled slot
    // exchanges the two periods.
    gridTeachers.value[from.teacherIndex].cells[from.periodKey] = toCell;
    gridTeachers.value[to.teacherIndex].cells[to.periodKey] = fromCell;
}

function onDragEnd() {
    dragSource.value = null;
    dragOverKey.value = null;
}

/* ---------------------------- Edit / Add popup ---------------------------- */

const editing = ref(null); // { teacherIndex, periodKey, subject, classLabel, isNew }

function subjectColor(subject) {
    return props.legend.find((item) => item.subject === subject)?.color ?? 'blue';
}

function openEditor(teacherIndex, periodKey) {
    const cell = gridTeachers.value[teacherIndex].cells[periodKey];
    editing.value = {
        teacherIndex,
        periodKey,
        subject: cell.subject ?? '',
        classLabel: cell.classLabel ?? '',
        isNew: cell.type === 'empty',
    };
}

function closeEditor() {
    editing.value = null;
}

function saveEditor() {
    if (!editing.value || !editing.value.subject || !editing.value.classLabel) return;
    const { teacherIndex, periodKey, subject, classLabel } = editing.value;
    gridTeachers.value[teacherIndex].cells[periodKey] = {
        type: 'class',
        subject,
        classLabel,
        color: subjectColor(subject),
    };
    closeEditor();
}

const editingTeacherName = computed(() => (editing.value ? gridTeachers.value[editing.value.teacherIndex].name : ''));
const editingPeriodLabel = computed(() => {
    if (!editing.value) return '';
    return props.periods.find((p) => p.key === editing.value.periodKey)?.label ?? '';
});
</script>

<template>
    <AppLayout :title="`${routine.name} — Edit`">
        <div class="space-y-4">
            <!-- Page header -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-white">{{ routine.name }} — {{ routine.term }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Click any slot to edit &middot; Drag to rearrange</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        href="/routines"
                        class="rounded-lg border border-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/50"
                    >
                        All routines
                    </Link>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/50"
                    >
                        Versions
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                    >
                        Save routine
                    </button>
                </div>
            </div>

            <!-- Day switcher -->
            <div class="flex items-center gap-4 rounded-xl border border-slate-800 bg-slate-900/50 px-4 py-3">
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-800 text-slate-400 hover:bg-slate-800/70"
                    @click="stepDay(-1)"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>

                <p class="flex-1 text-center text-base font-bold uppercase tracking-widest text-emerald-400">
                    {{ dayNames[selectedDay] ?? selectedDay }}
                </p>

                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-800 text-slate-400 hover:bg-slate-800/70"
                    @click="stepDay(1)"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>

                <div class="hidden items-center gap-1.5 sm:flex">
                    <button
                        v-for="day in days"
                        :key="day"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                        :class="
                            day === selectedDay
                                ? 'bg-emerald-500 text-slate-950'
                                : 'text-slate-400 hover:bg-slate-800/70 hover:text-slate-200'
                        "
                        @click="selectDay(day)"
                    >
                        {{ day }}
                    </button>
                </div>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="text-slate-500">Legend:</span>
                <span
                    v-for="item in legend"
                    :key="item.subject"
                    class="rounded-full px-2.5 py-1 font-medium"
                    :class="cellColors[item.color].pill"
                >
                    {{ item.subject }}
                </span>
                <span class="flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 font-medium text-emerald-400">
                    <Repeat class="h-3 w-3" /> Proxy
                </span>
                <span class="flex items-center gap-1 rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-1 font-medium text-rose-400">
                    <AlertTriangle class="h-3 w-3" /> Unresolved
                </span>
            </div>

            <!-- Grid -->
            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/50">
                <div class="min-w-[980px]">
                    <!-- Header row -->
                    <div class="grid border-b border-slate-800" :style="gridStyle">
                        <div class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Teacher</div>
                        <div
                            v-for="p in periods"
                            :key="p.key"
                            class="border-l border-slate-800 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider"
                            :class="p.type ? 'bg-slate-800/40 text-slate-500' : 'text-slate-500'"
                        >
                            <p>{{ p.label }}</p>
                            <p v-if="p.time" class="mt-0.5 text-[10px] font-normal normal-case text-slate-600">{{ p.time }}</p>
                        </div>
                    </div>

                    <!-- Teacher rows -->
                    <div
                        v-for="(teacher, ti) in gridTeachers"
                        :key="teacher.name"
                        class="grid border-b border-slate-800 last:border-b-0"
                        :style="gridStyle"
                    >
                        <div class="px-4 py-4">
                            <p class="text-sm font-semibold text-slate-100">{{ teacher.name }}</p>
                            <p class="text-xs text-slate-500">{{ teacher.subject }}</p>
                        </div>

                        <div v-for="p in periods" :key="p.key" class="border-l border-slate-800 p-2">
                            <div
                                v-if="p.type"
                                class="flex h-full items-center justify-center rounded-lg bg-slate-800/20 text-xs font-medium text-slate-600"
                            >
                                {{ p.label }}
                            </div>

                            <button
                                v-else
                                type="button"
                                :draggable="teacher.cells[p.key].type !== 'empty'"
                                class="group relative flex h-full w-full flex-col items-start justify-center gap-0.5 rounded-lg px-3 py-2 text-left transition-colors"
                                :class="[
                                    cellClasses(teacher.cells[p.key]),
                                    dragOverKey === cellKey(ti, p.key) ? 'ring-2 ring-emerald-400' : '',
                                    dragSource && dragSource.teacherIndex === ti && dragSource.periodKey === p.key ? 'opacity-40' : '',
                                ]"
                                @click="openEditor(ti, p.key)"
                                @dragstart="onDragStart(ti, p.key, $event)"
                                @dragend="onDragEnd"
                                @dragover="onDragOverCell(ti, p.key, $event)"
                                @dragleave="onDragLeaveCell"
                                @drop="onDrop(ti, p.key, $event)"
                            >
                                <Pencil class="absolute right-1.5 top-1.5 h-3 w-3 text-slate-500 opacity-0 transition-opacity group-hover:opacity-100" />

                                <template v-if="teacher.cells[p.key].type === 'empty'">
                                    <span class="text-xs text-slate-600">+ Add</span>
                                </template>

                                <template v-else-if="teacher.cells[p.key].type === 'unresolved'">
                                    <span class="flex items-center gap-1 text-xs font-semibold text-rose-400">
                                        <AlertTriangle class="h-3 w-3" /> Unresolved
                                    </span>
                                    <span class="text-xs text-slate-400">{{ teacher.cells[p.key].classLabel }}</span>
                                </template>

                                <template v-else-if="teacher.cells[p.key].type === 'proxy'">
                                    <span class="flex items-center gap-1 text-xs font-semibold text-emerald-400">
                                        <Repeat class="h-3 w-3" /> {{ teacher.cells[p.key].subject }}
                                    </span>
                                    <span class="text-xs text-slate-400">Proxy &mdash; {{ teacher.cells[p.key].classLabel }}</span>
                                </template>

                                <template v-else>
                                    <span class="text-xs font-semibold" :class="cellColors[teacher.cells[p.key].color].text">
                                        {{ teacher.cells[p.key].subject }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ teacher.cells[p.key].classLabel }}</span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit / Add period popup -->
        <Teleport to="body">
            <div
                v-if="editing"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4"
                @click.self="closeEditor"
            >
                <div class="w-full max-w-sm rounded-xl border border-slate-800 bg-slate-900 p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-white">{{ editing.isNew ? 'Add Period' : 'Edit Period' }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ editingTeacherName }} &middot; {{ editingPeriodLabel }}</p>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-xs font-medium text-slate-400">Subject</label>
                            <select
                                v-model="editing.subject"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            >
                                <option value="" disabled>Select subject</option>
                                <option v-for="item in legend" :key="item.subject" :value="item.subject">{{ item.subject }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-400">Class</label>
                            <select
                                v-model="editing.classLabel"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            >
                                <option value="" disabled>Select class</option>
                                <option v-for="c in classOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/50"
                            @click="closeEditor"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!editing.subject || !editing.classLabel"
                            @click="saveEditor"
                        >
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>