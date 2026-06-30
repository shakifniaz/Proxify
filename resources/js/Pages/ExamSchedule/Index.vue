<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { AlertTriangle, Printer, Plus } from 'lucide-vue-next';

const props = defineProps({
    session: { type: Object, default: () => ({}) }, // { title, subtitle, dateLabel }
    halls: { type: Array, default: () => [] }, // [{ name, capacity }]
    timeSlots: { type: Array, default: () => [] }, // [{ key, label, startLabel }]
    subjectOptions: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
    invigilatorOptions: { type: Array, default: () => [] },
    examGrid: { type: Object, default: () => ({}) }, // { [hallName]: { [slotKey]: { subject, classLabel, invigilator } | null } }
});

const grid = ref(JSON.parse(JSON.stringify(props.examGrid)));

function cellAt(hallName, slotKey) {
    return grid.value[hallName]?.[slotKey] ?? null;
}

const gridStyle = computed(() => ({
    gridTemplateColumns: `200px repeat(${props.timeSlots.length}, minmax(160px, 1fr))`,
}));

const conflictGroups = computed(() => {
    const groups = [];
    for (const slot of props.timeSlots) {
        const byInvigilator = {};
        for (const hall of props.halls) {
            const cell = cellAt(hall.name, slot.key);
            if (cell && cell.invigilator) {
                if (!byInvigilator[cell.invigilator]) byInvigilator[cell.invigilator] = [];
                byInvigilator[cell.invigilator].push(hall.name);
            }
        }
        for (const [invigilator, halls] of Object.entries(byInvigilator)) {
            if (halls.length > 1) {
                groups.push({ invigilator, halls, slotKey: slot.key, slotStart: slot.startLabel });
            }
        }
    }
    return groups;
});

function isConflict(hallName, slotKey) {
    return conflictGroups.value.some((g) => g.slotKey === slotKey && g.halls.includes(hallName));
}

function cellClasses(hallName, slotKey) {
    const cell = cellAt(hallName, slotKey);
    if (!cell) return 'border border-dashed border-stone-300 bg-stone-50 hover:bg-stone-100';
    if (isConflict(hallName, slotKey)) return 'border-l-2 border-red-500 bg-red-50';
    return 'border-l-2 border-emerald-400 bg-blue-50';
}

const invigilatorDuties = computed(() => {
    const map = new Map();
    for (const hall of props.halls) {
        for (const slot of props.timeSlots) {
            const cell = cellAt(hall.name, slot.key);
            if (cell && cell.invigilator) {
                if (!map.has(cell.invigilator)) map.set(cell.invigilator, { duties: [], conflict: false });
                const entry = map.get(cell.invigilator);
                entry.duties.push(`${hall.name} ${slot.startLabel}`);
                if (isConflict(hall.name, slot.key)) entry.conflict = true;
            }
        }
    }
    return Array.from(map.entries()).map(([name, info]) => ({
        name,
        dutiesText: info.duties.join(', '),
        load: info.duties.length,
        conflict: info.conflict,
    }));
});


const editing = ref(null); // { hallName, slotKey, subject, classLabel, invigilator, isNew }

function openEditor(hallName, slotKey) {
    const cell = cellAt(hallName, slotKey);
    editing.value = {
        hallName,
        slotKey,
        subject: cell?.subject ?? '',
        classLabel: cell?.classLabel ?? '',
        invigilator: cell?.invigilator ?? '',
        isNew: !cell,
    };
}

function closeEditor() {
    editing.value = null;
}

function saveEditor() {
    if (!editing.value) return;
    const { hallName, slotKey, subject, classLabel, invigilator } = editing.value;
    if (!subject || !classLabel || !invigilator) return;
    if (!grid.value[hallName]) grid.value[hallName] = {};
    grid.value[hallName][slotKey] = { subject, classLabel, invigilator };
    closeEditor();
}

const editingSlotLabel = computed(() => {
    if (!editing.value) return '';
    return props.timeSlots.find((s) => s.key === editing.value.slotKey)?.label ?? '';
});

function printPage() {
    window.print();
}
</script>

<template>
    <AppLayout title="Exam Schedule">
        <div class="space-y-6">
            <!-- Page header -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950">{{ session.title }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ session.subtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-stone-100"
                        @click="printPage"
                    >
                        <Printer class="h-4 w-4" />
                        Print
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"
                    >
                        <Plus class="h-4 w-4" />
                        New session
                    </button>
                </div>
            </div>

            <!-- Conflict banner -->
            <div
                v-if="conflictGroups.length"
                class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                <AlertTriangle class="h-4 w-4 shrink-0" />
                <span>
                    {{ conflictGroups.length }} conflict{{ conflictGroups.length > 1 ? 's' : '' }} detected &mdash;
                    {{ conflictGroups[0].invigilator }} is double-booked in {{ conflictGroups[0].halls.join(' & ') }}
                    at {{ conflictGroups[0].slotStart }}
                </span>
            </div>

            <!-- Exam grid -->
            <div class="surface-card">
                <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4">
                    <p class="text-sm font-semibold text-slate-950">{{ session.dateLabel }} &mdash; Exam Grid</p>
                    <span
                        v-if="conflictGroups.length"
                        class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700"
                    >
                        {{ conflictGroups.length }} conflict{{ conflictGroups.length > 1 ? 's' : '' }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <div class="min-w-[820px]">
                        <!-- Header row -->
                        <div class="grid border-b border-stone-200" :style="gridStyle">
                            <div class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Hall</div>
                            <div
                                v-for="slot in timeSlots"
                                :key="slot.key"
                                class="border-l border-stone-200 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500"
                            >
                                {{ slot.label }}
                            </div>
                        </div>

                        <!-- Hall rows -->
                        <div
                            v-for="hall in halls"
                            :key="hall.name"
                            class="grid border-b border-stone-200 last:border-b-0"
                            :style="gridStyle"
                        >
                            <div class="px-4 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ hall.name }}</p>
                                <p class="text-xs text-slate-500">Capacity: {{ hall.capacity }}</p>
                            </div>

                            <div v-for="slot in timeSlots" :key="slot.key" class="border-l border-stone-200 p-2">
                                <button
                                    type="button"
                                    class="flex h-full w-full flex-col items-start justify-center gap-0.5 rounded-lg px-3 py-2 text-left transition-colors"
                                    :class="cellClasses(hall.name, slot.key)"
                                    @click="openEditor(hall.name, slot.key)"
                                >
                                    <template v-if="!cellAt(hall.name, slot.key)">
                                        <span class="text-xs text-slate-600">+ Add</span>
                                    </template>
                                    <template v-else>
                                        <span
                                            v-if="isConflict(hall.name, slot.key)"
                                            class="flex items-center gap-1 text-xs font-semibold text-red-700"
                                        >
                                            <AlertTriangle class="h-3 w-3" /> Conflict
                                        </span>
                                        <span
                                            class="text-xs font-semibold"
                                            :class="isConflict(hall.name, slot.key) ? 'text-red-700' : 'text-blue-700'"
                                        >
                                            {{ cellAt(hall.name, slot.key).subject }}
                                        </span>
                                        <span class="text-xs text-slate-600">
                                            {{ cellAt(hall.name, slot.key).classLabel }} &middot; {{ cellAt(hall.name, slot.key).invigilator }}
                                        </span>
                                    </template>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invigilator duty list -->
            <div class="surface-card">
                <div class="border-b border-stone-200 px-5 py-4">
                    <p class="text-sm font-semibold text-slate-950">Invigilator duty list</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-xs uppercase tracking-wider text-slate-500">
                                <th class="px-5 py-3 font-medium">Invigilator</th>
                                <th class="px-5 py-3 font-medium">Duties</th>
                                <th class="px-5 py-3 font-medium">Load</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200">
                            <tr v-for="d in invigilatorDuties" :key="d.name">
                                <td class="px-5 py-3 font-medium text-slate-800">{{ d.name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ d.dutiesText }}</td>
                                <td class="px-5 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="d.conflict ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-700'"
                                    >
                                        {{ d.load }}{{ d.conflict ? ' — conflict!' : '' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit / Add exam slot popup -->
        <Teleport to="body">
            <div
                v-if="editing"
                class="fixed inset-0 z-50 flex items-center justify-center bg-stone-100/70 p-4"
                @click.self="closeEditor"
            >
                <div class="w-full max-w-sm surface-card p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-slate-950">{{ editing.isNew ? 'Add Exam Slot' : 'Edit Exam Slot' }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ editing.hallName }} &middot; {{ editingSlotLabel }}</p>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-xs font-medium text-slate-600">Subject</label>
                            <select
                                v-model="editing.subject"
                                class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="" disabled>Select subject</option>
                                <option v-for="s in subjectOptions" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600">Class</label>
                            <select
                                v-model="editing.classLabel"
                                class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="" disabled>Select class</option>
                                <option v-for="c in classOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600">Invigilator</label>
                            <select
                                v-model="editing.invigilator"
                                class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="" disabled>Select invigilator</option>
                                <option v-for="i in invigilatorOptions" :key="i" :value="i">{{ i }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-stone-100"
                            @click="closeEditor"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!editing.subject || !editing.classLabel || !editing.invigilator"
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