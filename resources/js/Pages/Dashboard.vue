<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    CalendarDays,
    Repeat,
    ArrowRight,
    UserX,
    CheckCircle2,
    Megaphone,
    CalendarPlus,
} from 'lucide-vue-next';

const props = defineProps({
    alerts: { type: Array, default: () => [] },
    routineSummary: { type: Object, default: () => ({}) },
    proxySummary: { type: Object, default: () => ({}) },
    weekStats: { type: Array, default: () => [] },
    today: { type: Object, default: () => ({}) },
    monthStats: { type: Array, default: () => [] },
    liveActivity: { type: Array, default: () => [] },
    todaysAbsences: { type: Array, default: () => [] },
    quickActions: { type: Array, default: () => [] },
});

// Added 'teal' to prevent the fatal crash, kept emerald for backwards compatibility
const colorClasses = {
    teal: { text: 'text-teal-400', dot: 'bg-teal-400', bar: 'bg-teal-500', ring: 'border-teal-500/30' },
    emerald: { text: 'text-emerald-400', dot: 'bg-emerald-400', bar: 'bg-emerald-500', ring: 'border-emerald-500/30' },
    rose: { text: 'text-rose-400', dot: 'bg-rose-400', bar: 'bg-rose-500', ring: 'border-rose-500/30' },
    amber: { text: 'text-amber-400', dot: 'bg-amber-400', bar: 'bg-amber-500', ring: 'border-amber-500/30' },
    sky: { text: 'text-sky-400', dot: 'bg-sky-400', bar: 'bg-sky-500', ring: 'border-sky-500/30' },
    violet: { text: 'text-violet-400', dot: 'bg-violet-400', bar: 'bg-violet-500', ring: 'border-violet-500/30' },
};

const statusBadge = {
    Unresolved: 'bg-rose-500/10 text-rose-400 border border-rose-500/30',
    Assigned: 'bg-teal-500/10 text-teal-400 border border-teal-500/30',
};

const quickActionIcons = { UserX, CheckCircle2, Megaphone, CalendarPlus };

// Safe fallback function in case web.php sends an unknown color in the future
function getSafeColor(colorKey) {
    return colorClasses[colorKey] || colorClasses['slate'] || colorClasses['teal'];
}
</script>

<template>
    <AppLayout title="Admin Dashboard">
        <div class="space-y-6">
            <div
                v-if="alerts && alerts.length"
                class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-300"
            >
                <template v-for="(alert, i) in alerts" :key="i">
                    <span>{{ alert }}</span>
                    <span v-if="i < alerts.length - 1" class="text-amber-500/50">&middot;</span>
                </template>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                    <div>
                        <p class="text-base font-semibold text-white">Master Routine</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ routineSummary?.days }} days &middot; {{ routineSummary?.classes }} classes &middot;
                            {{ routineSummary?.teachers }} teachers
                        </p>
                        <span
                            class="mt-3 inline-flex rounded-full border border-teal-500/30 bg-teal-500/10 px-2.5 py-1 text-xs font-medium text-teal-400"
                        >
                            {{ routineSummary?.termLabel }}
                        </span>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-teal-500/10 border border-teal-500/20">
                        <CalendarDays class="h-5 w-5 text-teal-400" />
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                    <div>
                        <p class="text-base font-semibold text-white">Proxy Classes</p>
                        <p class="mt-1 text-sm text-slate-500">
                            Today &middot; {{ proxySummary?.absentToday }} absent &middot;
                            {{ proxySummary?.assignedToday }} assigned
                        </p>
                        <span
                            class="mt-3 inline-flex rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-1 text-xs font-medium text-rose-400"
                        >
                            {{ proxySummary?.unresolvedToday }} unresolved
                        </span>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-500/10 border border-rose-500/20">
                        <Repeat class="h-5 w-5 text-rose-400" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/50">
                <div class="flex items-center justify-between border-b border-slate-800 px-5 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">This Week</p>
                    <a href="/analytics" class="flex items-center gap-1 text-sm font-medium text-teal-400 hover:text-teal-300 transition-colors">
                        See All <ArrowRight class="h-3.5 w-3.5" />
                    </a>
                </div>
                <div class="divide-y divide-slate-800">
                    <div
                        v-for="stat in weekStats"
                        :key="stat.label"
                        class="flex items-center gap-4 px-5 py-4"
                    >
                        <span class="h-2.5 w-2.5 rounded-full" :class="getSafeColor(stat.color).dot"></span>
                        <p class="flex-1 text-sm font-medium text-slate-200">{{ stat.label }}</p>
                        <p class="text-sm text-slate-500">{{ stat.sub }}</p>
                        <p class="w-10 text-right text-sm font-semibold" :class="getSafeColor(stat.color).text">
                            {{ stat.value }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-6 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Today at a Glance</p>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-400"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                            {{ today?.status }}
                        </span>
                    </div>

                    <p class="mt-4 text-5xl font-bold text-white">{{ today?.absentCount }}</p>
                    <p class="mt-1 text-sm text-slate-500">teachers absent today</p>

                    <div class="mt-6 space-y-3">
                        <div class="flex items-center justify-between rounded-lg bg-slate-800/40 border border-slate-800/50 px-4 py-3">
                            <span class="text-sm text-slate-300">Proxies assigned</span>
                            <span class="text-sm font-semibold text-teal-400">{{ today?.proxiesAssigned }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-slate-800/40 border border-slate-800/50 px-4 py-3">
                            <span class="text-sm text-slate-300">Unresolved periods</span>
                            <span class="text-sm font-semibold text-rose-400">{{ today?.unresolvedPeriods }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-slate-800/40 border border-slate-800/50 px-4 py-3">
                            <span class="text-sm text-slate-300">Acknowledgement</span>
                            <span class="text-sm font-semibold text-amber-400">{{ today?.ackRate }}%</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div
                            v-for="stat in monthStats"
                            :key="stat.label"
                            class="rounded-xl border border-slate-800 bg-slate-900/50 p-3"
                        >
                            <p class="text-[10px] font-semibold uppercase leading-tight tracking-wider text-slate-500">
                                {{ stat.label }}
                            </p>
                            <p class="mt-2 text-2xl font-bold" :class="getSafeColor(stat.color).text">{{ stat.value }}</p>
                            <p class="mt-1 truncate text-[11px] text-slate-500">{{ stat.sub }}</p>
                            <div class="mt-2 h-1 rounded-full bg-slate-800">
                                <div class="h-1 rounded-full" :class="getSafeColor(stat.color).bar" style="width: 60%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-white">Live Activity</p>
                            <span class="flex items-center gap-1.5 text-xs font-medium text-teal-400">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-teal-400"></span>
                                Live
                            </span>
                        </div>
                        <div class="mt-4 space-y-4">
                            <div v-for="item in liveActivity" :key="item.id" class="flex items-start gap-3">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :class="getSafeColor(item.color).dot"></span>
                                <p class="flex-1 text-sm text-slate-300">{{ item.text }}</p>
                                <span class="shrink-0 text-xs text-slate-500">{{ item.time }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-800 bg-slate-900/50 lg:col-span-2">
                    <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                        <p class="text-sm font-semibold text-white">Today's Absences</p>
                        <a href="/proxy-manager" class="flex items-center gap-1 text-sm font-medium text-teal-400 hover:text-teal-300 transition-colors">
                            Manage proxies <ArrowRight class="h-3.5 w-3.5" />
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs uppercase tracking-wider text-slate-500 bg-slate-950/50">
                                    <th class="px-5 py-3 font-medium">Teacher</th>
                                    <th class="px-5 py-3 font-medium">Subject / Section</th>
                                    <th class="px-5 py-3 font-medium">Periods</th>
                                    <th class="px-5 py-3 font-medium">Proxy</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 text-slate-300">
                                <tr v-for="row in todaysAbsences" :key="row.teacher" class="hover:bg-slate-800/30 transition-colors">
                                    <td class="px-5 py-3 font-medium text-slate-200">{{ row.teacher }}</td>
                                    <td class="px-5 py-3 text-slate-400">{{ row.subject }} &middot; {{ row.section }}</td>
                                    <td class="px-5 py-3 text-slate-400">{{ row.periods }}</td>
                                    <td class="px-5 py-3 text-slate-400">{{ row.proxy }}</td>
                                    <td class="px-5 py-3">
                                        <span class="rounded px-2.5 py-0.5 text-[10px] uppercase tracking-wider font-bold" :class="statusBadge[row.status] || 'bg-slate-800 text-slate-400 border border-slate-700'">
                                            {{ row.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                    <p class="text-sm font-semibold text-white">Quick Actions</p>
                    <div class="mt-4 space-y-2">
                        <a
                            v-for="action in quickActions"
                            :key="action.label"
                            :href="action.href"
                            class="flex items-center gap-3 rounded-lg border border-slate-800 bg-slate-950/50 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800/80 hover:text-white transition-colors"
                        >
                            <component :is="quickActionIcons[action.icon]" class="h-4 w-4 text-teal-400" />
                            {{ action.label }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>