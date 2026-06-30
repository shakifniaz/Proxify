<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Check } from 'lucide-vue-next';

const props = defineProps({
    general: { type: Object, default: () => ({}) }, // { schoolName, contactPhone, contactEmail, termLabel, weekStartDay, academicYear }
    notifications: { type: Array, default: () => [] }, // [{ key, label, description, enabled }]
    weekStartOptions: { type: Array, default: () => [] },
});

const tab = ref('General'); // 'General' | 'Notifications'
const tabs = ['General', 'Notifications'];

const generalForm = ref({ ...props.general });
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
function saveNotifications() {
    flashSaved();
}
</script>

<template>
    <AppLayout title="Settings">
        <div class="mx-auto max-w-3xl space-y-4">
            <!-- Page header -->
            <div>
                <h2 class="text-xl font-semibold text-slate-950">Settings</h2>
                <p class="mt-1 text-sm text-slate-500">School-wide configuration for routines, timings, and alerts.</p>
            </div>

            <!-- Tab switcher -->
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="t in tabs"
                    :key="t"
                    type="button"
                    class="rounded-lg border px-4 py-2.5 text-sm font-semibold transition-colors"
                    :class="tab === t ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-stone-300 text-slate-600 hover:bg-stone-100'"
                    @click="tab = t"
                >
                    {{ t }}
                </button>
            </div>

            <!-- General -->
            <div v-if="tab === 'General'" class="surface-card p-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">School Name</label>
                        <input
                            v-model="generalForm.schoolName"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Contact Phone</label>
                        <input
                            v-model="generalForm.contactPhone"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Contact Email</label>
                        <input
                            v-model="generalForm.contactEmail"
                            type="email"
                            class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Current Term Label</label>
                        <input
                            v-model="generalForm.termLabel"
                            type="text"
                            placeholder="e.g. Term 1 — 2025/26"
                            class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Academic Year</label>
                        <input
                            v-model="generalForm.academicYear"
                            type="text"
                            placeholder="e.g. 2025/26"
                            class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Week Starts On</label>
                        <select
                            v-model="generalForm.weekStartDay"
                            class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none"
                        >
                            <option v-for="d in weekStartOptions" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"
                        @click="saveGeneral"
                    >
                        Save changes
                    </button>
                    <span v-if="saved" class="flex items-center gap-1 text-xs font-medium text-blue-700">
                        <Check class="h-3.5 w-3.5" /> Saved
                    </span>
                </div>
            </div>

            <!-- Notifications -->
            <div v-else class="surface-card p-5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Alerts &amp; Notifications</p>

                <div class="mt-3 divide-y divide-stone-200">
                    <div v-for="n in localNotifications" :key="n.key" class="flex items-center justify-between gap-4 py-3.5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900">{{ n.label }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ n.description }}</p>
                        </div>
                        <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                            <input v-model="n.enabled" type="checkbox" class="peer sr-only" />
                            <div class="h-6 w-11 rounded-full bg-slate-700 transition-colors peer-checked:bg-blue-700"></div>
                            <div class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></div>
                        </label>
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-3 border-t border-stone-200 pt-4">
                    <button
                        type="button"
                        class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"
                        @click="saveNotifications"
                    >
                        Save changes
                    </button>
                    <span v-if="saved" class="flex items-center gap-1 text-xs font-medium text-blue-700">
                        <Check class="h-3.5 w-3.5" /> Saved
                    </span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
