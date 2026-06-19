<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChevronLeft, ChevronRight, AlertTriangle, Repeat } from 'lucide-vue-next';

const props = defineProps({
    routine: { type: Object, default: () => ({}) },
    days: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    legend: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
});


const cellColors = {
    blue: { bg: 'bg-blue-500/15', border: 'border-blue-400', text: 'text-blue-300', pill: 'bg-blue-500/15 text-blue-300 border border-blue-500/30' },
    amber: { bg: 'bg-amber-500/15', border: 'border-amber-400', text: 'text-amber-300', pill: 'bg-amber-500/15 text-amber-300 border border-amber-500/30' },
    emerald: { bg: 'bg-emerald-500/15', border: 'border-emerald-400', text: 'text-emerald-300', pill: 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' },
    indigo: { bg: 'bg-indigo-500/15', border: 'border-indigo-400', text: 'text-indigo-300', pill: 'bg-indigo-500/15 text-indigo-300 border border-indigo-500/30' },
    cyan: { bg: 'bg-cyan-500/15', border: 'border-cyan-400', text: 'text-cyan-300', pill: 'bg-cyan-500/15 text-cyan-300 border border-cyan-500/30' },
    rose: { bg: 'bg-rose-500/15', border: 'border-rose-400', text: 'text-rose-300', pill: 'bg-rose-500/15 text-rose-300 border border-rose-500/30' },
};

const grid = ref(JSON.parse(JSON.stringify(props.teachers)));

watch(
    () => props.teachers,
    (next) => {
        grid.value = JSON.parse(JSON.stringify(next));
    }
);

const selectedDay = ref(props.days[0] ?? 'Sun');

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

const dragSource = ref(null);
const dragOverTarget = ref(null);

function onDragStart(tIndex, key, event) {
    const cell = grid.value[tIndex].cells[key];
    if (!cell || cell.type === 'empty') {
        event.preventDefault();
        return;
    }
    dragSource.value = { tIndex, key };
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', `${tIndex}:${key}`);
}

function onDragEnter(tIndex, key) {
    if (!dragSource.value) return;
    dragOverTarget.value = { tIndex, key };
}

function onDragLeave(tIndex, key) {
    if (dragOverTarget.value && dragOverTarget.value.tIndex === tIndex && dragOverTarget.value.key === key) {
        dragOverTarget.value = null;
    }
}

function onDrop(tIndex, key, event) {
    event.preventDefault();
    dragOverTarget.value = null;
    const source = dragSource.value;
    dragSource.value = null;
    if (!source) return;
    if (source.tIndex === tIndex && source.key === key) return;

    const sourceCell = grid.value[source.tIndex].cells[source.key];
    const targetCell = grid.value[tIndex].cells[key];
    grid.value[tIndex].cells[key] = sourceCell;
    grid.value[source.tIndex].cells[source.key] = targetCell;
}

function onDragEnd() {
    dragSource.value = null;
    dragOverTarget.value = null;
}

function isDropTarget(tIndex, key) {
    return !!dragOverTarget.value && dragOverTarget.value.tIndex === tIndex && dragOverTarget.value.key === key;
}

function isDragging(tIndex, key) {
    return !!dragSource.value && dragSource.value.tIndex === tIndex && dragSource.value.key === key;
}

function cellWrapperClasses(tIndex, key) {
    const cell = grid.value[tIndex].cells[key];
    const classes = [cellClasses(cell)];
    if (cell && cell.type !== 'empty') classes.push('cursor-grab active:cursor-grabbing');
    if (isDragging(tIndex, key)) classes.push('opacity-40');
    if (isDropTarget(tIndex, key)) classes.push('ring-2 ring-emerald-400');
    return classes.join(' ');
}

/* ---------------- Edit popup: add or edit a period ---------------- */

const editing = ref(null);
const editSubject = ref('');
const editClass = ref('');

const classOptions = [
    '6A', '6B', '7A', '7B', '7C', '8A', '8B', '8C',
    '9A', '9B', '9C', '10A', '10B', 'XI A', 'XI B',
];

const subjectColorMap = computed(() => {
    const map = {};
    props.legend.forEach((item) => {
        map[item.subject] = item.color;
    });
    return map;
});

const editingCell = computed(() => {
    if (!editing.value) return null;
    return grid.value[editing.value.tIndex].cells[editing.value.key];
});

const editIsEmpty = computed(() => !editingCell.value || editingCell.value.type === 'empty');

const editingTeacherName = computed(() => (editing.value ? grid.value[editing.value.tIndex].name : ''));

const editingPeriodLabel = computed(() => {
    if (!editing.value) return '';
    const period = props.periods.find((p) => p.key === editing.value.key);
    return period ? `${period.label}${period.time ? ' · ' + period.time : ''}` : '';
});

function openEdit(tIndex, key) {
    const cell = grid.value[tIndex].cells[key];
    editing.value = { tIndex, key };
    if (cell && cell.type !== 'empty') {
        editSubject.value = cell.subject ?? '';
        editClass.value = (cell.classLabel ?? '').replace('Class ', '');
    } else {
        editSubject.value = '';
        editClass.value = '';
    }
}

function closeEdit() {
    editing.value = null;
}

function saveEdit() {
    if (!editing.value || !editSubject.value || !editClass.value) return;
    const { tIndex, key } = editing.value;
    grid.value[tIndex].cells[key] = {
        type: 'class',
        subject: editSubject.value,
        classLabel: `Class ${editClass.value}`,
        color: subjectColorMap.value[editSubject.value] ?? 'blue',
    };
    closeEdit();
}
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
                    {{ selectedDay === 'Sun' ? 'Sunday' : selectedDay === 'Mon' ? 'Monday' : selectedDay === 'Tue' ? 'Tuesday' : selectedDay === 'Wed' ? 'Wednesday' : 'Thursday' }}
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
                        v-for="(teacher, tIndex) in grid"
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
                                class="flex h-full w-full flex-col items-start justify-center gap-0.5 rounded-lg px-3 py-2 text-left transition-colors"
                                :class="cellWrapperClasses(tIndex, p.key)"
                                :draggable="teacher.cells[p.key]?.type !== 'empty'"
                                @click="openEdit(tIndex, p.key)"
                                @dragstart="onDragStart(tIndex, p.key, $event)"
                                @dragend="onDragEnd"
                                @dragover.prevent
                                @dragenter.prevent="onDragEnter(tIndex, p.key)"
                                @dragleave="onDragLeave(tIndex, p.key)"
                                @drop="onDrop(tIndex, p.key, $event)"
                            >
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
                @click.self="closeEdit"
            >
                <div class="w-full max-w-sm rounded-xl border border-slate-800 bg-slate-900 p-5 shadow-xl">
                    <p class="text-sm font-semibold text-white">{{ editIsEmpty ? 'Add Period' : 'Edit Period' }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ editingTeacherName }} &middot; {{ editingPeriodLabel }}</p>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-400">Subject</label>
                            <select
                                v-model="editSubject"
                                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                            >
                                <option value="" disabled>Select subject</option>
                                <option v-for="item in legend" :key="item.subject" :value="item.subject">
                                    {{ item.subject }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-400">Class</label>
                            <select
                                v-model="editClass"
                                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                            >
                                <option value="" disabled>Select class</option>
                                <option v-for="c in classOptions" :key="c" :value="c">Class {{ c }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/50"
                            @click="closeEdit"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!editSubject || !editClass"
                            @click="saveEdit"
                        >
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
VUEEOF
echo "written"