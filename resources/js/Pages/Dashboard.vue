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

const colorClasses = {
    teal: { text: 'text-[#1e2924]', dot: 'bg-[#09B884]', bar: 'bg-[#09B884]', ring: 'border-[#8BED9A]/70' },
    emerald: { text: 'text-[#1e2924]', dot: 'bg-[#09B884]', bar: 'bg-[#09B884]', ring: 'border-[#8BED9A]/70' },
    rose: { text: 'text-red-700', dot: 'bg-red-600', bar: 'bg-red-600', ring: 'border-red-200' },
    amber: { text: 'text-amber-700', dot: 'bg-amber-600', bar: 'bg-amber-600', ring: 'border-amber-200' },
    sky: { text: 'text-[#04795a]', dot: 'bg-[#09B884]', bar: 'bg-[#09B884]', ring: 'border-[#8BED9A]/70' },
    violet: { text: 'text-[#1e2924]', dot: 'bg-[#1e2924]', bar: 'bg-[#1e2924]', ring: 'border-[#09B884]/30' },
    slate: { text: 'text-slate-700', dot: 'bg-slate-500', bar: 'bg-slate-500', ring: 'border-slate-200' },
};

const statusBadge = {
    Unresolved: 'bg-red-50 text-red-700 border border-red-200',
    Assigned: 'bg-[#8BED9A]/20 text-[#1e2924] border border-[#8BED9A]/70',
};

const quickActionIcons = { UserX, CheckCircle2, Megaphone, CalendarPlus };

function getSafeColor(colorKey) {
    return colorClasses[colorKey] || colorClasses.slate;
}
</script>

<template>
    <AppLayout title="School Dashboard">
        <div class="space-y-6">
            <div
                v-if="alerts && alerts.length"
                class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm"
            >
                <template v-for="(alert, i) in alerts" :key="i">
                    <span>{{ alert }}</span>
                    <span v-if="i < alerts.length - 1" class="text-amber-600/50">&middot;</span>
                </template>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="surface-card flex items-center justify-between p-5 transition hover:border-[#8BED9A]/70 hover:shadow-md">
                    <div>
                        <p class="text-base font-semibold text-slate-950">Academic Schedule</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ routineSummary?.days }} days &middot; {{ routineSummary?.classes }} classes &middot;
                            {{ routineSummary?.teachers }} teachers
                        </p>
                        <span
                            class="mt-3 inline-flex rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-2.5 py-1 text-xs font-medium text-[#1e2924]"
                        >
                            {{ routineSummary?.termLabel }}
                        </span>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15">
                        <CalendarDays class="h-5 w-5 text-[#09B884]" />
                    </div>
                </div>

                <div class="surface-card flex items-center justify-between p-5 transition hover:border-[#8BED9A]/70 hover:shadow-md">
                    <div>
                        <p class="text-base font-semibold text-slate-950">Coverage Status</p>
                        <p class="mt-1 text-sm text-slate-500">
                            Today &middot; {{ proxySummary?.absentToday }} absent &middot;
                            {{ proxySummary?.assignedToday }} assigned
                        </p>
                        <span
                            class="mt-3 inline-flex rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700"
                        >
                            {{ proxySummary?.unresolvedToday }} unresolved
                        </span>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-red-100 bg-red-50">
                        <Repeat class="h-5 w-5 text-red-700" />
                    </div>
                </div>
            </div>

            <div class="surface-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-stone-200 px-5 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">This Week</p>
                    <a href="/analytics" class="flex items-center gap-1 text-sm font-semibold text-[#1e2924] transition-colors hover:text-[#09B884]">
                        See All <ArrowRight class="h-3.5 w-3.5" />
                    </a>
                </div>
                <div class="divide-y divide-stone-200">
                    <div
                        v-for="stat in weekStats"
                        :key="stat.label"
                        class="flex items-center gap-4 px-5 py-4"
                    >
                        <span class="h-2.5 w-2.5 rounded-full" :class="getSafeColor(stat.color).dot"></span>
                        <p class="flex-1 text-sm font-medium text-slate-800">{{ stat.label }}</p>
                        <p class="text-sm text-slate-500">{{ stat.sub }}</p>
                        <p class="w-10 text-right text-sm font-semibold" :class="getSafeColor(stat.color).text">
                            {{ stat.value }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="surface-card p-6 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Campus Today</p>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                            {{ today?.status }}
                        </span>
                    </div>

                    <p class="mt-4 text-5xl font-bold text-slate-950">{{ today?.absentCount }}</p>
                    <p class="mt-1 text-sm text-slate-500">teachers absent today</p>

                    <div class="mt-6 space-y-3">
                        <div class="flex items-center justify-between rounded-lg border border-stone-200 bg-stone-50 px-4 py-3">
                            <span class="text-sm text-slate-600">Proxies assigned</span>
                            <span class="text-sm font-semibold text-[#1e2924]">{{ today?.proxiesAssigned }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-stone-200 bg-stone-50 px-4 py-3">
                            <span class="text-sm text-slate-600">Unresolved periods</span>
                            <span class="text-sm font-semibold text-red-700">{{ today?.unresolvedPeriods }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-stone-200 bg-stone-50 px-4 py-3">
                            <span class="text-sm text-slate-600">Acknowledgement</span>
                            <span class="text-sm font-semibold text-amber-700">{{ today?.ackRate }}%</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div
                            v-for="stat in monthStats"
                            :key="stat.label"
                            class="surface-card p-3"
                        >
                            <p class="text-[10px] font-semibold uppercase leading-tight tracking-wider text-slate-500">
                                {{ stat.label }}
                            </p>
                            <p class="mt-2 text-2xl font-bold" :class="getSafeColor(stat.color).text">{{ stat.value }}</p>
                            <p class="mt-1 truncate text-[11px] text-slate-500">{{ stat.sub }}</p>
                            <div class="mt-2 h-1 rounded-full bg-stone-200">
                                <div class="h-1 rounded-full" :class="getSafeColor(stat.color).bar" style="width: 60%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="surface-card p-5">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-950">Live Activity</p>
                            <span class="flex items-center gap-1.5 text-xs font-semibold text-[#1e2924]">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#09B884]"></span>
                                Live
                            </span>
                        </div>
                        <div class="mt-4 space-y-4">
                            <div v-for="item in liveActivity" :key="item.id" class="flex items-start gap-3">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :class="getSafeColor(item.color).dot"></span>
                                <p class="flex-1 text-sm text-slate-700">{{ item.text }}</p>
                                <span class="shrink-0 text-xs text-slate-500">{{ item.time }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="surface-card overflow-hidden lg:col-span-2">
                    <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4">
                        <p class="text-sm font-semibold text-slate-950">Today's Absences</p>
                        <a href="/proxy-manager" class="flex items-center gap-1 text-sm font-semibold text-[#1e2924] transition-colors hover:text-[#09B884]">
                            Manage proxies <ArrowRight class="h-3.5 w-3.5" />
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-stone-100 text-xs uppercase tracking-wider text-slate-500">
                                    <th class="px-5 py-3 font-medium">Teacher</th>
                                    <th class="px-5 py-3 font-medium">Subject / Section</th>
                                    <th class="px-5 py-3 font-medium">Periods</th>
                                    <th class="px-5 py-3 font-medium">Proxy</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-200 text-slate-700">
                                <tr v-for="row in todaysAbsences" :key="row.teacher" class="transition-colors hover:bg-stone-50">
                                    <td class="px-5 py-3 font-medium text-slate-900">{{ row.teacher }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ row.subject }} &middot; {{ row.section }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ row.periods }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ row.proxy }}</td>
                                    <td class="px-5 py-3">
                                        <span class="rounded px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider" :class="statusBadge[row.status] || 'bg-stone-100 text-slate-600 border border-stone-300'">
                                            {{ row.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="surface-card p-5">
                    <p class="text-sm font-semibold text-slate-950">Quick Actions</p>
                    <div class="mt-4 space-y-2">
                        <a
                            v-for="action in quickActions"
                            :key="action.label"
                            :href="action.href"
                            class="flex items-center gap-3 rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/10 hover:text-[#1e2924]"
                        >
                            <component :is="quickActionIcons[action.icon]" class="h-4 w-4 text-[#09B884]" />
                            {{ action.label }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
