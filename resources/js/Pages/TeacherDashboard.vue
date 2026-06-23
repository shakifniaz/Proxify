<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Clock, Calendar, AlertCircle, BookOpen, Megaphone, CheckCircle2 } from 'lucide-vue-next';

// Adding default values prevents Vue from crashing if Laravel misses a field
defineProps({
    teacherName: { type: String, default: 'Teacher' },
    dateLabel: { type: String, default: () => new Date().toLocaleDateString() },
    stats: { 
        type: Object, 
        default: () => ({ classesToday: 0, proxiesToday: 0, pendingLeaveDays: 0 }) 
    },
    todaySchedule: { type: Array, default: () => [] },
    tomorrowSchedule: { type: Array, default: () => [] },
    proxyAssignments: { type: Array, default: () => [] },
    urgentNotices: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="My Dashboard" />

    <AppLayout title="Teacher Dashboard">
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight">Welcome back, {{ teacherName }}</h1>
                    <p class="text-sm text-slate-400 mt-1">{{ dateLabel }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5 flex items-center gap-4">
                    <div class="h-12 w-12 rounded-full bg-teal-500/10 text-teal-400 flex items-center justify-center border border-teal-500/20">
                        <BookOpen class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Classes Today</p>
                        <p class="text-2xl font-bold text-white">{{ stats.classesToday }}</p>
                    </div>
                </div>
                
                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5 flex items-center gap-4">
                    <div class="h-12 w-12 rounded-full bg-amber-500/10 text-amber-400 flex items-center justify-center border border-amber-500/20">
                        <AlertCircle class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Assigned Proxies</p>
                        <p class="text-2xl font-bold text-white">{{ stats.proxiesToday }}</p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5 flex items-center gap-4">
                    <div class="h-12 w-12 rounded-full bg-slate-800 text-slate-300 flex items-center justify-center border border-slate-700">
                        <Calendar class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Pending Leave</p>
                        <p class="text-2xl font-bold text-white">{{ stats.pendingLeaveDays }} Days</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div v-if="urgentNotices && urgentNotices.length > 0" class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-5">
                        <div class="flex items-center gap-3 mb-4">
                            <Megaphone class="h-5 w-5 text-rose-400" />
                            <h2 class="text-sm font-bold text-white">Urgent Notices</h2>
                        </div>
                        <div class="space-y-3">
                            <div v-for="notice in urgentNotices" :key="notice.id" class="rounded-lg bg-slate-900 border border-slate-800 p-4">
                                <h3 class="text-sm font-semibold text-slate-200">{{ notice.title }}</h3>
                                <p class="text-xs text-slate-400 mt-1">{{ notice.message }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-800 bg-slate-900/50 overflow-hidden">
                        <div class="border-b border-slate-800 px-5 py-4">
                            <h2 class="text-base font-semibold text-white">Today's Timeline</h2>
                        </div>
                        <div class="divide-y divide-slate-800/50">
                            <div v-for="period in todaySchedule" :key="period.period" 
                                class="p-5 flex items-start gap-4 transition-colors hover:bg-slate-800/30"
                                :class="period.isProxy ? 'bg-amber-500/5' : ''"
                            >
                                <div class="w-20 shrink-0 text-right">
                                    <p class="text-sm font-bold text-slate-300">{{ period.period }}</p>
                                    <p class="text-xs text-slate-500">{{ period.time }}</p>
                                </div>
                                
                                <div v-if="period.type === 'break'" class="flex-1 flex items-center justify-center rounded-lg bg-slate-800/50 py-2 border border-slate-800">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ period.label }}</p>
                                </div>

                                <div v-else-if="period.type === 'empty'" class="flex-1">
                                    <p class="text-sm text-slate-600 font-medium italic mt-0.5">Free Period</p>
                                </div>

                                <div v-else class="flex-1 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-bold" :class="period.isProxy ? 'text-amber-400' : 'text-teal-400'">
                                                {{ period.subject }}
                                            </p>
                                            <span v-if="period.isProxy" class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-bold text-amber-400 uppercase tracking-wider border border-amber-500/20">Proxy</span>
                                        </div>
                                        <div class="flex items-center gap-3 mt-1.5">
                                            <span class="text-xs font-medium text-slate-300">{{ period.classLabel }}</span>
                                            <span class="text-slate-600 text-[10px]">&bull;</span>
                                            <span class="text-xs text-slate-400">{{ period.room }}</span>
                                        </div>
                                    </div>
                                    
                                    <button class="shrink-0 px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-xs font-medium text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                                        View Classroom
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-white">Proxy Assignments</h2>
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-500/20 text-xs font-bold text-amber-400">{{ proxyAssignments.length }}</span>
                        </div>
                        
                        <div v-if="!proxyAssignments || proxyAssignments.length === 0" class="text-sm text-slate-500">
                            No proxy classes assigned to you.
                        </div>
                        
                        <div v-else class="space-y-3">
                            <div v-for="proxy in proxyAssignments" :key="proxy.id" class="rounded-lg bg-slate-900 border border-slate-800 p-3">
                                <div class="flex justify-between items-start mb-2">
                                    <p class="text-xs font-bold text-amber-400">{{ proxy.date }} &bull; {{ proxy.period }}</p>
                                </div>
                                <p class="text-sm font-medium text-slate-200">{{ proxy.subject }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ proxy.classLabel }} &bull; Covering for {{ proxy.coveringFor }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                        <h2 class="text-sm font-semibold text-white mb-4">Tomorrow at a glance</h2>
                        <div class="space-y-3">
                            <div v-for="c in tomorrowSchedule" :key="c.period" class="flex items-center justify-between border-b border-slate-800/50 pb-2 last:border-0 last:pb-0">
                                <div>
                                    <p class="text-xs font-bold text-slate-300">{{ c.period }}</p>
                                    <p class="text-[11px] text-slate-500">{{ c.subject }}</p>
                                </div>
                                <p class="text-xs font-medium text-slate-400">{{ c.classLabel }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>