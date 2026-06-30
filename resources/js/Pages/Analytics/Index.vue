<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FileDown } from 'lucide-vue-next';

const props = defineProps({
    stats: { type: Object, default: () => ({}) }, // { totalAbsences, proxyClasses, unresolved, ackRate } each { value, delta }
    rangeOptions: { type: Array, default: () => [] },
    chartLabel: { type: String, default: '' },
    dailyAbsences: { type: Array, default: () => [] }, // [{ day, count }]
    proxyLoad: { type: Array, default: () => [] }, // [{ teacher, count }]
    heatmapDays: { type: Array, default: () => [] },
    heatmap: { type: Array, default: () => [] }, // [{ teacher, values: [0-3,...] }]
});

const range = ref(props.rangeOptions[0] ?? '');

const maxDaily = computed(() => Math.max(1, ...props.dailyAbsences.map((d) => d.count)));
const maxProxyLoad = computed(() => Math.max(1, ...props.proxyLoad.map((p) => p.count)));

function proxyBarColor(count) {
    if (count >= 12) return 'bg-rose-500';
    if (count >= 8) return 'bg-amber-500';
    return 'bg-blue-700';
}
function proxyLabelColor(count) {
    if (count >= 12) return 'text-red-700';
    if (count >= 8) return 'text-amber-700';
    return 'text-blue-700';
}

const heatClasses = ['bg-white', 'bg-indigo-500/30', 'bg-indigo-500/60', 'bg-rose-500/70'];
function heatClass(value) {
    return heatClasses[Math.min(value, heatClasses.length - 1)];
}

function exportPdf() {
    window.print();
}
</script>

<template>
    <AppLayout title="Analytics">
        <div class="space-y-6">
            <!-- Page header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-slate-950">Analytics &amp; Reports</h2>
                <div class="flex items-center gap-2">
                    <select
                        v-model="range"
                        class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-blue-500 focus:outline-none"
                    >
                        <option v-for="r in rangeOptions" :key="r" :value="r">{{ r }}</option>
                    </select>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-stone-100"
                        @click="exportPdf"
                    >
                        <FileDown class="h-4 w-4" />
                        Export PDF
                    </button>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="surface-card p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Absences</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950">{{ stats.totalAbsences?.value }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ stats.totalAbsences?.delta }}</p>
                </div>
                <div class="surface-card p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Proxy Classes</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950">{{ stats.proxyClasses?.value }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ stats.proxyClasses?.delta }}</p>
                </div>
                <div class="surface-card p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Unresolved</p>
                    <p class="mt-2 text-3xl font-bold text-red-700">{{ stats.unresolved?.value }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ stats.unresolved?.delta }}</p>
                </div>
                <div class="surface-card p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Ack. Rate</p>
                    <p class="mt-2 text-3xl font-bold text-blue-700">{{ stats.ackRate?.value }}%</p>
                    <p class="mt-1 text-xs text-slate-500">{{ stats.ackRate?.delta }}</p>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Daily absences bar chart -->
                <div class="surface-card p-5">
                    <p class="text-sm font-semibold text-slate-950">{{ chartLabel }}</p>
                    <div class="mt-6 flex h-40 items-end gap-3">
                        <div v-for="(d, i) in dailyAbsences" :key="i" class="flex flex-1 flex-col items-center gap-2">
                            <span class="text-xs font-semibold text-slate-700">{{ d.count }}</span>
                            <div
                                class="w-full rounded-t-md bg-blue-700/70 transition-all"
                                :style="{ height: `${(d.count / maxDaily) * 100}%` }"
                                :title="`${d.day}: ${d.count} absences`"
                            ></div>
                            <span class="text-[11px] text-slate-500">{{ d.day }}</span>
                        </div>
                    </div>
                </div>

                <!-- Proxy load per teacher -->
                <div class="surface-card p-5">
                    <p class="text-sm font-semibold text-slate-950">Proxy load per teacher</p>
                    <div class="mt-5 space-y-4">
                        <div v-for="p in proxyLoad" :key="p.teacher">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-700">{{ p.teacher }}</span>
                                <span class="font-semibold" :class="proxyLabelColor(p.count)">{{ p.count }} proxies</span>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-stone-100">
                                <div
                                    class="h-2 rounded-full transition-all"
                                    :class="proxyBarColor(p.count)"
                                    :style="{ width: `${(p.count / maxProxyLoad) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Absence heatmap -->
            <div class="surface-card p-5">
                <p class="text-sm font-semibold text-slate-950">Absence heatmap &mdash; teachers</p>
                <div class="mt-5 space-y-3">
                    <div v-for="row in heatmap" :key="row.teacher" class="flex items-center gap-4">
                        <span class="w-24 shrink-0 text-xs font-medium text-slate-600">{{ row.teacher }}</span>
                        <div class="flex gap-1.5">
                            <div
                                v-for="(value, i) in row.values"
                                :key="i"
                                class="h-6 w-6 rounded"
                                :class="heatClass(value)"
                                :title="`${row.teacher} — ${heatmapDays[i]}: ${value} absence${value === 1 ? '' : 's'}`"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>