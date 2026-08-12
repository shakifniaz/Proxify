<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bell,
    CalendarCheck,
    ClipboardList,
    Gauge,
    GraduationCap,
    Megaphone,
    RefreshCw,
    ShieldCheck,
    Sparkles,
    UserCheck,
    Users,
} from 'lucide-vue-next';

const props = defineProps({
    dateLabel: { type: String, default: 'Today' },
    hero: { type: Object, default: () => ({}) },
    stats: { type: Array, default: () => [] },
    routineHealth: { type: Object, default: () => ({}) },
    todaySchedule: { type: Array, default: () => [] },
    notices: { type: Array, default: () => [] },
    exam: { type: Object, default: () => ({}) },
    leaveQueue: { type: Array, default: () => [] },
    activity: { type: Array, default: () => [] },
});

const statIcons = [GraduationCap, Users, RefreshCw, UserCheck];
const toneClasses = {
    green: 'border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#1e2924]',
    mint: 'border-[#09B884]/25 bg-[#09B884]/10 text-[#04795a]',
    amber: 'border-amber-200 bg-amber-50 text-amber-800',
    dark: 'border-[#1e2924]/15 bg-[#1e2924] text-white',
};
const noticeTone = {
    Urgent: 'bg-red-100 text-red-800 border-red-200',
    Important: 'bg-amber-100 text-amber-900 border-amber-200',
    Normal: 'bg-[#8BED9A]/25 text-[#1e2924] border-[#8BED9A]/70',
};

function toneClass(tone) {
    return toneClasses[tone] || toneClasses.green;
}
</script>

<template>
    <AppLayout title="Dashboard" live-refresh>
        <div class="space-y-5">
            <section class="group relative overflow-hidden rounded-[1.75rem] border border-[#8BED9A]/50 bg-gradient-to-br from-[#16261f] via-[#203b30] to-[#08795d] p-6 text-white shadow-xl shadow-[#1e2924]/10 sm:p-8">
                <div class="pointer-events-none absolute -right-16 -top-28 h-80 w-80 rounded-full bg-[#8BED9A]/20 blur-3xl transition duration-700 group-hover:scale-110"></div>
                <div class="pointer-events-none absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
                <div class="relative flex flex-col gap-7 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-black uppercase tracking-[0.2em] text-[#bff8c8] backdrop-blur">
                            <Sparkles class="h-3.5 w-3.5" />
                            Campus command center
                        </div>
                        <h1 class="mt-5 text-3xl font-black tracking-tight text-white sm:text-4xl">
                            {{ hero.routineName }}
                        </h1>
                        <p class="mt-2 text-sm font-semibold text-white/65">{{ dateLabel }} · {{ hero.day }} · {{ hero.status }}</p>
                    </div>

                    <div class="grid min-w-full grid-cols-2 gap-3 sm:min-w-[30rem] sm:grid-cols-4">
                        <div v-for="(stat, index) in stats" :key="stat.label" class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur transition duration-300 hover:-translate-y-1 hover:bg-white/15">
                            <div class="flex items-center justify-between gap-3">
                                <component :is="statIcons[index] || ShieldCheck" class="h-5 w-5" :class="index === 3 ? 'text-amber-300' : 'text-[#8BED9A]'" />
                                <span class="rounded-full border border-white/10 bg-white/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-white/65">
                                    {{ stat.label }}
                                </span>
                            </div>
                            <p class="mt-4 text-3xl font-black text-white">{{ stat.value }}</p>
                            <p class="mt-1 truncate text-[10px] font-black uppercase tracking-[0.12em] text-white/50">{{ stat.sub }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,0.8fr)]">
                <section class="surface-card overflow-hidden transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between gap-3 border-b border-stone-200 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#1e2924] text-[#8BED9A] shadow-lg shadow-[#1e2924]/15">
                                <Gauge class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-base font-black text-[#1e2924]">Routine health</p>
                                <p class="text-xs font-semibold text-slate-500">{{ routineHealth.assigned }} assigned · {{ routineHealth.unresolved }} unresolved</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-black text-[#1e2924]">{{ routineHealth.coverage }}%</p>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">coverage</p>
                        </div>
                    </div>
                    <div class="grid gap-5 p-5 lg:grid-cols-[16rem_minmax(0,1fr)]">
                        <div class="group relative flex min-h-52 items-center justify-center overflow-hidden rounded-2xl border border-[#8BED9A]/70 bg-gradient-to-br from-[#8BED9A]/35 to-white transition duration-300 hover:-translate-y-1">
                            <div class="absolute inset-x-8 bottom-8 h-6 rounded-full bg-[#09B884]/20 blur-xl"></div>
                            <div class="relative flex h-40 w-40 items-center justify-center rounded-full border-[14px] border-[#8BED9A] bg-white shadow-xl shadow-[#09B884]/10">
                                <div class="text-center">
                                    <p class="text-4xl font-black text-[#1e2924]">{{ routineHealth.coverage }}%</p>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">ready</p>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                            <div class="rounded-2xl border border-stone-200 bg-stone-50 p-4 transition hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/10">
                                <p class="text-2xl font-black text-[#1e2924]">{{ routineHealth.assigned }}</p>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">assigned periods</p>
                            </div>
                            <div class="rounded-2xl border border-stone-200 bg-stone-50 p-4 transition hover:border-amber-200 hover:bg-amber-50">
                                <p class="text-2xl font-black text-amber-700">{{ routineHealth.unresolved }}</p>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">needs attention</p>
                            </div>
                            <div class="rounded-2xl border border-stone-200 bg-stone-50 p-4 transition hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/10">
                                <p class="text-2xl font-black text-[#1e2924]">{{ routineHealth.periods }}</p>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">period template</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="surface-card overflow-hidden transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#09B884]">
                                <CalendarCheck class="h-5 w-5" />
                            </div>
                            <p class="text-base font-black text-[#1e2924]">Exam snapshot</p>
                        </div>
                        <Link href="/exam-schedule" class="text-xs font-black text-[#04795a] hover:text-[#1e2924]">Open</Link>
                    </div>
                    <div class="p-5">
                        <p class="text-xl font-black text-[#1e2924]">{{ exam.title }}</p>
                        <p v-if="exam.subtitle" class="mt-1 text-xs font-bold text-slate-500">{{ exam.subtitle }}</p>
                        <div class="mt-4 space-y-2">
                            <div v-for="item in exam.items" :key="`${item.date}-${item.time}-${item.hall}`" class="rounded-xl border border-stone-200 bg-stone-50 p-3 transition hover:border-[#8BED9A]/70 hover:bg-white">
                                <p class="text-sm font-black text-slate-900">{{ item.title }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ item.date }} · {{ item.time }} · {{ item.hall }}</p>
                            </div>
                            <div v-if="!exam.items?.length" class="rounded-xl border border-dashed border-stone-300 p-5 text-center text-sm font-bold text-slate-500">
                                No active exam items.
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <section class="surface-card overflow-hidden transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#09B884]">
                                <ClipboardList class="h-5 w-5" />
                            </div>
                            <p class="text-base font-black text-[#1e2924]">Today’s teaching load</p>
                        </div>
                        <Link href="/routines" class="inline-flex items-center gap-1 text-xs font-black text-[#04795a] hover:text-[#1e2924]">
                            Routine <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                    <div class="grid divide-y divide-stone-200 md:grid-cols-2 md:divide-x md:divide-y-0">
                        <div v-for="row in todaySchedule" :key="row.teacher" class="group p-4 transition hover:bg-[#8BED9A]/10">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-black text-slate-950">{{ row.teacher }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ row.subjects || 'Routine classes' }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-black text-[#1e2924]">{{ row.classes }}</p>
                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">classes</p>
                                </div>
                            </div>
                            <span v-if="row.proxy" class="mt-3 inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-black text-amber-800">
                                {{ row.proxy }} substitution change{{ row.proxy === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <div v-if="!todaySchedule.length" class="p-8 text-center text-sm font-bold text-slate-500">
                            No active teaching load found for today.
                        </div>
                    </div>
                </section>

                <aside class="space-y-5">
                    <section class="surface-card overflow-hidden transition duration-300 hover:shadow-lg">
                        <div class="flex items-center gap-3 border-b border-stone-200 px-4 py-4">
                            <Megaphone class="h-5 w-5 text-[#09B884]" />
                            <p class="text-sm font-black text-[#1e2924]">Recent notices</p>
                        </div>
                        <div class="space-y-2 p-3">
                            <Link v-for="notice in notices" :key="notice.id" href="/noticeboard" class="block rounded-xl border p-3 transition hover:-translate-y-0.5 hover:shadow-sm" :class="noticeTone[notice.urgency] || noticeTone.Normal">
                                <p class="text-sm font-black">{{ notice.title }}</p>
                                <p class="mt-1 text-xs font-semibold opacity-75">{{ notice.date }} · {{ notice.urgency }}</p>
                            </Link>
                            <p v-if="!notices.length" class="p-4 text-center text-sm font-bold text-slate-500">No notices yet.</p>
                        </div>
                    </section>

                    <section class="surface-card overflow-hidden transition duration-300 hover:shadow-lg">
                        <div class="flex items-center gap-3 border-b border-stone-200 px-4 py-4">
                            <Bell class="h-5 w-5 text-[#09B884]" />
                            <p class="text-sm font-black text-[#1e2924]">Live activity</p>
                        </div>
                        <div class="space-y-3 p-4">
                            <div v-for="item in activity" :key="item.text" class="flex gap-3 rounded-xl bg-stone-50 p-3">
                                <span class="mt-1.5 h-2 w-2 rounded-full bg-[#09B884]"></span>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">{{ item.text }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ item.time }}</p>
                                </div>
                            </div>
                            <p v-if="!activity.length" class="text-sm font-bold text-slate-500">Everything is quiet right now.</p>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
