<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Check, Trash2, Plus } from 'lucide-vue-next';

const props = defineProps({
    general: { type: Object, default: () => ({}) }, // { schoolName, contactPhone, contactEmail, termLabel, weekStartDay, academicYear }
    periods: { type: Array, default: () => [] }, // [{ id, label, startTime, endTime, locked }]
    notifications: { type: Array, default: () => [] }, // [{ key, label, description, enabled }]
    weekStartOptions: { type: Array, default: () => [] },
});

const tab = ref('General'); // 'General' | 'Period Timings' | 'Notifications'
const tabs = ['General', 'Period Timings', 'Notifications'];

const generalForm = ref({ ...props.general });
const localPeriods = ref(props.periods.map((p) => ({ ...p })));
const localNotifications = ref(props.notifications.map((n) => ({ ...n })));

const saved = ref(false);
let savedTimeout = null;

function flashSaved() {
    saved.value = true;
    if (savedTimeout) clearTimeout(savedTimeout);
    savedTimeout = setTimeout(() => {
        saved.value = false;
    }, 2000);
}

function saveGeneral() {
    flashSaved();
}
function savePeriods() {
    flashSaved();
}
function saveNotifications() {
    flashSaved();
}

function addPeriod() {
    const nextId = Math.max(0, ...localPeriods.value.map((p) => p.id)) + 1;
    localPeriods.value.push({ id: nextId, label: `P${localPeriods.value.length + 1}`, startTime: '', endTime: '', locked: false });
}
function removePeriod(i) {
    localPeriods.value.splice(i, 1);
}
</script>

<template>
    <AppLayout title="Settings">
        <div class="mx-auto max-w-3xl space-y-4">
            <!-- Page header -->
            <div>
                <h2 class="text-xl font-semibold text-white">Settings</h2>
                <p class="mt-1 text-sm text-slate-500">School-wide configuration for routines, timings, and alerts.</p>
            </div>

            <!-- Tab switcher -->
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="t in tabs"
                    :key="t"
                    type="button"
                    class="rounded-lg border px-4 py-2.5 text-sm font-semibold transition-colors"
                    :class="tab === t ? 'border-emerald-500 bg-emerald-500/10 text-emerald-400' : 'border-slate-800 text-slate-400 hover:bg-slate-800/50'"
                    @click="tab = t"
                >
                    {{ t }}
                </button>
            </div>

            <!-- General -->
            <div v-if="tab === 'General'" class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">School Name</label>
                        <input
                            v-model="generalForm.schoolName"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Contact Phone</label>
                        <input
                            v-model="generalForm.contactPhone"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Contact Email</label>
                        <input
                            v-model="generalForm.contactEmail"
                            type="email"
                            class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Current Term Label</label>
                        <input
                            v-model="generalForm.termLabel"
                            type="text"
                            placeholder="e.g. Term 1 — 2025/26"
                            class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Academic Year</label>
                        <input
                            v-model="generalForm.academicYear"
                            type="text"
                            placeholder="e.g. 2025/26"
                            class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Week Starts On</label>
                        <select
                            v-model="generalForm.weekStartDay"
                            class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                        >
                            <option v-for="d in weekStartOptions" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                        @click="saveGeneral"
                    >
                        Save changes
                    </button>
                    <span v-if="saved" class="flex items-center gap-1 text-xs font-medium text-emerald-400">
                        <Check class="h-3.5 w-3.5" /> Saved
                    </span>
                </div>
            </div>

            <!-- Period Timings -->
            <div v-else-if="tab === 'Period Timings'" class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Daily Periods</p>

                <div class="mt-3 space-y-2">
                    <div
                        v-for="(period, i) in localPeriods"
                        :key="period.id"
                        class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-800 bg-slate-800/40 p-3"
                    >
                        <input
                            v-model="period.label"
                            type="text"
                            placeholder="Label"
                            class="w-20 rounded-lg border border-slate-800 bg-slate-900/60 px-2.5 py-1.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                        />
                        <input
                            v-model="period.startTime"
                            type="time"
                            class="rounded-lg border border-slate-800 bg-slate-900/60 px-2.5 py-1.5 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                        />
                        <span class="text-xs text-slate-500">to</span>
                        <input
                            v-model="period.endTime"
                            type="time"
                            class="rounded-lg border border-slate-800 bg-slate-900/60 px-2.5 py-1.5 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                        />

                        <label class="ml-1 flex items-center gap-2 text-xs text-slate-400">
                            <input
                                v-model="period.locked"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-700 bg-slate-800 text-emerald-500 focus:ring-emerald-500"
                            />
                            Break / Lunch (excluded from proxies)
                        </label>

                        <button
                            type="button"
                            class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-800 text-slate-500 hover:bg-slate-800/50 hover:text-rose-400"
                            @click="removePeriod(i)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="mt-3 flex items-center gap-1.5 rounded-lg border border-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800/50"
                    @click="addPeriod"
                >
                    <Plus class="h-3.5 w-3.5" /> Add Period
                </button>

                <div class="mt-5 flex items-center gap-3 border-t border-slate-800 pt-4">
                    <button
                        type="button"
                        class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                        @click="savePeriods"
                    >
                        Save changes
                    </button>
                    <span v-if="saved" class="flex items-center gap-1 text-xs font-medium text-emerald-400">
                        <Check class="h-3.5 w-3.5" /> Saved
                    </span>
                </div>
            </div>

            <!-- Notifications -->
            <div v-else class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Alerts &amp; Notifications</p>

                <div class="mt-3 divide-y divide-slate-800">
                    <div v-for="n in localNotifications" :key="n.key" class="flex items-center justify-between gap-4 py-3.5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-100">{{ n.label }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ n.description }}</p>
                        </div>
                        <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                            <input v-model="n.enabled" type="checkbox" class="peer sr-only" />
                            <div class="h-6 w-11 rounded-full bg-slate-700 transition-colors peer-checked:bg-emerald-500"></div>
                            <div class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></div>
                        </label>
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-3 border-t border-slate-800 pt-4">
                    <button
                        type="button"
                        class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                        @click="saveNotifications"
                    >
                        Save changes
                    </button>
                    <span v-if="saved" class="flex items-center gap-1 text-xs font-medium text-emerald-400">
                        <Check class="h-3.5 w-3.5" /> Saved
                    </span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>