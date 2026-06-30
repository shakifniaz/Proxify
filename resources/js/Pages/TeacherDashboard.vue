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
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-950">Welcome back, {{ teacherName }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ dateLabel }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="flex items-center gap-4 rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-blue-100 bg-blue-50 text-blue-700">
                        <BookOpen class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Classes Today</p>
                        <p class="text-2xl font-bold text-slate-950">{{ stats.classesToday }}</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-4 rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-amber-100 bg-amber-50 text-amber-700">
                        <AlertCircle class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Assigned Proxies</p>
                        <p class="text-2xl font-bold text-slate-950">{{ stats.proxiesToday }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-stone-200 bg-stone-100 text-slate-700">
                        <Calendar class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Pending Leave</p>
                        <p class="text-2xl font-bold text-slate-950">{{ stats.pendingLeaveDays }} Days</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <div v-if="urgentNotices && urgentNotices.length > 0" class="rounded-lg border border-red-200 bg-red-50 p-5 shadow-sm">
                        <div class="flex items-center gap-3 mb-4">
                            <Megaphone class="h-5 w-5 text-red-700" />
                            <h2 class="text-sm font-bold text-red-950">Urgent Notices</h2>
                        </div>
                        <div class="space-y-3">
                            <div v-for="notice in urgentNotices" :key="notice.id" class="rounded-lg border border-red-100 bg-white p-4">
                                <h3 class="text-sm font-semibold text-slate-950">{{ notice.title }}</h3>
                                <p class="mt-1 text-xs text-slate-600">{{ notice.message }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm">
                        <div class="border-b border-stone-200 px-5 py-4">
                            <h2 class="text-base font-semibold text-slate-950">Today's Timeline</h2>
                        </div>
                        <div class="divide-y divide-stone-200">
                            <div v-for="period in todaySchedule" :key="period.period" 
                                class="flex items-start gap-4 p-5 transition-colors hover:bg-stone-50"
                                :class="period.isProxy ? 'bg-amber-50' : ''"
                            >
                                <div class="w-20 shrink-0 text-right">
                                    <p class="text-sm font-bold text-slate-700">{{ period.period }}</p>
                                    <p class="text-xs text-slate-500">{{ period.time }}</p>
                                </div>
                                
                                <div v-if="period.type === 'break'" class="flex flex-1 items-center justify-center rounded-lg border border-stone-200 bg-stone-100 py-2">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ period.label }}</p>
                                </div>

                                <div v-else-if="period.type === 'empty'" class="flex-1">
                                    <p class="mt-0.5 text-sm font-medium italic text-slate-400">Free Period</p>
                                </div>

                                <div v-else class="flex flex-1 flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-bold" :class="period.isProxy ? 'text-amber-700' : 'text-blue-700'">
                                                {{ period.subject }}
                                            </p>
                                            <span v-if="period.isProxy" class="rounded border border-amber-200 bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800">Proxy</span>
                                        </div>
                                        <div class="flex items-center gap-3 mt-1.5">
                                            <span class="text-xs font-medium text-slate-700">{{ period.classLabel }}</span>
                                            <span class="text-[10px] text-slate-300">&bull;</span>
                                            <span class="text-xs text-slate-500">{{ period.room }}</span>
                                        </div>
                                    </div>
                                    
                                    <button class="shrink-0 rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-stone-400 hover:text-slate-950">
                                        View Classroom
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-amber-950">Proxy Assignments</h2>
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-200 text-xs font-bold text-amber-900">{{ proxyAssignments.length }}</span>
                        </div>
                        
                        <div v-if="!proxyAssignments || proxyAssignments.length === 0" class="text-sm text-slate-500">
                            No proxy classes assigned to you.
                        </div>
                        
                        <div v-else class="space-y-3">
                            <div v-for="proxy in proxyAssignments" :key="proxy.id" class="rounded-lg border border-amber-100 bg-white p-3">
                                <div class="flex justify-between items-start mb-2">
                                    <p class="text-xs font-bold text-amber-800">{{ proxy.date }} &bull; {{ proxy.period }}</p>
                                </div>
                                <p class="text-sm font-medium text-slate-950">{{ proxy.subject }}</p>
                                <p class="mt-0.5 text-xs text-slate-600">{{ proxy.classLabel }} &bull; Covering for {{ proxy.coveringFor }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-4 text-sm font-semibold text-slate-950">Tomorrow at a glance</h2>
                        <div class="space-y-3">
                            <div v-for="c in tomorrowSchedule" :key="c.period" class="flex items-center justify-between border-b border-stone-200 pb-2 last:border-0 last:pb-0">
                                <div>
                                    <p class="text-xs font-bold text-slate-700">{{ c.period }}</p>
                                    <p class="text-[11px] text-slate-500">{{ c.subject }}</p>
                                </div>
                                <p class="text-xs font-medium text-slate-600">{{ c.classLabel }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
