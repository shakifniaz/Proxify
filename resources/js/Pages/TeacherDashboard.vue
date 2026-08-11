<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { useDashboardClassroomFeed } from '@/Composables/useDashboardClassroomFeed';
import {
    ArrowRight,
    BookOpen,
    CalendarCheck,
    CalendarDays,
    CalendarOff,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    FileCheck2,
    GraduationCap,
    MapPin,
    Megaphone,
    Repeat2,
    Sparkles,
    Timer,
} from 'lucide-vue-next';

const props = defineProps({
    teacherName: { type: String, default: 'Teacher' },
    dateLabel: { type: String, default: 'Today' },
    nextDayLabel: { type: String, default: 'Tomorrow' },
    routineName: { type: String, default: 'No active routine' },
    stats: { type: Object, default: () => ({}) },
    todaySchedule: { type: Array, default: () => [] },
    tomorrowSchedule: { type: Array, default: () => [] },
    proxyAssignments: { type: Array, default: () => [] },
    urgentNotices: { type: Array, default: () => [] },
    exam: { type: Object, default: () => ({}) },
    leaveStats: { type: Object, default: () => ({}) },
    classroomUpdates: { type: Array, default: () => [] },
    classroomFeed: { type: Object, default: () => ({}) },
});

const {
    createdAssignments,
    upcomingTests,
    loading: classroomLoading,
    error: classroomError,
} = useDashboardClassroomFeed(props.classroomFeed, props.classroomUpdates);

const todayClasses = computed(() => props.todaySchedule.filter((period) => period.type === 'class'));
const nextDayClasses = computed(() => props.tomorrowSchedule.filter((period) => period.type === 'class'));

const noticeTone = {
    Urgent: 'border-red-200 bg-red-50 text-red-800',
    Important: 'border-amber-200 bg-amber-50 text-amber-900',
    Normal: 'border-[#8BED9A]/60 bg-[#8BED9A]/15 text-[#1e2924]',
};
</script>

<template>
    <AppLayout title="My Dashboard" live-refresh>
        <div class="space-y-6">
            <section class="group relative overflow-hidden rounded-[1.75rem] border border-[#8BED9A]/50 bg-gradient-to-br from-[#16261f] via-[#203b30] to-[#08795d] p-6 text-white shadow-xl shadow-[#1e2924]/10 sm:p-8">
                <div class="pointer-events-none absolute -right-16 -top-28 h-80 w-80 rounded-full bg-[#8BED9A]/20 blur-3xl transition duration-700 group-hover:scale-110"></div>
                <div class="pointer-events-none absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
                <div class="relative flex flex-col gap-7 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-black uppercase tracking-[0.2em] text-[#bff8c8] backdrop-blur">
                            <Sparkles class="h-3.5 w-3.5" />
                            Teaching overview
                        </div>
                        <h1 class="mt-5 text-3xl font-black tracking-tight sm:text-4xl">Good day, {{ teacherName }}</h1>
                        <p class="mt-2 text-sm font-semibold text-white/65">{{ dateLabel }} · {{ routineName }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:min-w-[38rem] sm:grid-cols-4">
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur transition duration-300 hover:-translate-y-1 hover:bg-white/15">
                            <BookOpen class="h-5 w-5 text-[#8BED9A]" />
                            <p class="mt-3 text-3xl font-black">{{ todayClasses.length }}</p>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-white/55">Classes today</p>
                        </div>
                        <div class="rounded-2xl border border-amber-300/25 bg-amber-300/10 p-4 backdrop-blur transition duration-300 hover:-translate-y-1 hover:bg-amber-300/15">
                            <Repeat2 class="h-5 w-5 text-amber-300" />
                            <p class="mt-3 text-3xl font-black">{{ stats.proxiesToday ?? 0 }}</p>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-white/55">Proxy classes</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur transition duration-300 hover:-translate-y-1 hover:bg-white/15">
                            <CalendarDays class="h-5 w-5 text-[#8BED9A]" />
                            <p class="mt-3 text-3xl font-black">{{ nextDayClasses.length }}</p>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-white/55">Next day</p>
                        </div>
                        <Link href="/leave-requests" class="rounded-2xl border border-sky-300/20 bg-sky-300/10 p-4 backdrop-blur transition duration-300 hover:-translate-y-1 hover:bg-sky-300/15">
                            <CalendarOff class="h-5 w-5 text-sky-300" />
                            <div class="mt-3 flex items-end gap-1.5">
                                <p class="text-3xl font-black">{{ leaveStats.remaining ?? 0 }}</p>
                                <p class="pb-1 text-[10px] font-black uppercase text-white/45">left</p>
                            </div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-white/55">{{ leaveStats.used ?? 0 }} taken · {{ leaveStats.maximum ?? 12 }} total</p>
                        </Link>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
                <section class="surface-card overflow-hidden shadow-sm transition duration-300 hover:shadow-lg">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#1e2924] text-[#8BED9A] shadow-lg shadow-[#1e2924]/15">
                                <Clock3 class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-black text-[#1e2924]">Today’s teaching schedule</h2>
                                <p class="text-xs font-semibold text-slate-500">Only scheduled classes are shown.</p>
                            </div>
                        </div>
                        <Link href="/routines" class="inline-flex items-center gap-1 text-xs font-black text-[#04795a] transition hover:gap-2 hover:text-[#1e2924]">
                            Full routine <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div class="divide-y divide-stone-200">
                        <div
                            v-for="(period, index) in todayClasses"
                            :key="period.id || `${period.period}-${period.subject}`"
                            class="group relative grid gap-4 px-5 py-5 transition duration-200 hover:bg-[#8BED9A]/10 sm:grid-cols-[5rem_minmax(0,1fr)_8rem] sm:items-center"
                            :class="period.isProxy ? 'bg-amber-50/80' : ''"
                        >
                            <div class="absolute bottom-0 left-0 top-0 w-1 transition group-hover:w-1.5" :class="period.isProxy ? 'bg-amber-400' : 'bg-[#09B884]' "></div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Class {{ index + 1 }}</p>
                                <p class="mt-1 text-sm font-black text-[#1e2924]">{{ period.period }}</p>
                                <p class="text-xs font-semibold text-slate-500">{{ period.time }}</p>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-base font-black text-slate-950">{{ period.subject }}</p>
                                    <span v-if="period.isProxy" class="rounded-full border border-amber-300 bg-amber-100 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-amber-900">Proxy class</span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-3 text-xs font-bold text-slate-500">
                                    <span class="inline-flex items-center gap-1"><GraduationCap class="h-3.5 w-3.5" />{{ period.classLabel || 'Class' }}</span>
                                    <span v-if="period.room" class="inline-flex items-center gap-1"><MapPin class="h-3.5 w-3.5" />{{ period.room }}</span>
                                    <span v-if="period.coveringFor" class="text-amber-700">Covering for {{ period.coveringFor }}</span>
                                </div>
                            </div>
                            <Link href="/classroom" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-stone-200 bg-white px-3 text-xs font-black text-[#1e2924] shadow-sm transition hover:-translate-y-0.5 hover:border-[#8BED9A] hover:shadow-md">
                                Classroom
                            </Link>
                        </div>
                        <div v-if="!todayClasses.length" class="flex flex-col items-center px-6 py-12 text-center">
                            <CheckCircle2 class="h-9 w-9 text-[#09B884]" />
                            <p class="mt-3 text-base font-black text-[#1e2924]">No classes scheduled today</p>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Your free periods have been hidden from this view.</p>
                        </div>
                    </div>
                </section>

                <aside class="space-y-6">
                    <section class="surface-card overflow-hidden shadow-sm transition duration-300 hover:shadow-lg">
                        <div class="flex items-center justify-between border-b border-stone-200 px-4 py-4">
                            <div class="flex items-center gap-3">
                                <CalendarDays class="h-5 w-5 text-[#09B884]" />
                                <div>
                                    <p class="text-sm font-black text-[#1e2924]">Next teaching day</p>
                                    <p class="text-[11px] font-bold text-slate-500">{{ nextDayLabel }}</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-[#8BED9A]/20 px-2.5 py-1 text-[10px] font-black text-[#04795a]">{{ nextDayClasses.length }} classes</span>
                        </div>
                        <div class="divide-y divide-stone-200">
                            <div v-for="item in nextDayClasses" :key="item.id || `${item.period}-${item.subject}`" class="px-4 py-3.5 transition hover:bg-[#8BED9A]/10" :class="item.isProxy ? 'bg-amber-50' : ''">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ item.period }} · {{ item.subject }}</p>
                                        <p class="mt-1 text-xs font-bold text-slate-500">{{ item.time }} · {{ item.classLabel }}</p>
                                    </div>
                                    <span v-if="item.isProxy" class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">Proxy</span>
                                </div>
                            </div>
                            <p v-if="!nextDayClasses.length" class="p-6 text-center text-sm font-bold text-slate-500">No classes scheduled.</p>
                        </div>
                    </section>

                    <section v-if="proxyAssignments.length" class="overflow-hidden rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white shadow-sm">
                        <div class="flex items-center gap-3 border-b border-amber-200 px-4 py-4">
                            <Repeat2 class="h-5 w-5 text-amber-700" />
                            <p class="text-sm font-black text-amber-950">Today’s proxy focus</p>
                        </div>
                        <div class="space-y-2 p-3">
                            <div v-for="proxy in proxyAssignments" :key="proxy.id" class="rounded-xl border border-amber-200 bg-white/80 p-3 transition hover:-translate-y-0.5 hover:shadow-sm">
                                <p class="text-sm font-black text-amber-950">{{ proxy.period }} · {{ proxy.subject }}</p>
                                <p class="mt-1 text-xs font-bold text-amber-800">{{ proxy.classLabel }}<span v-if="proxy.coveringFor"> · for {{ proxy.coveringFor }}</span></p>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="surface-card overflow-hidden shadow-sm transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#8BED9A]/20 text-[#04795a]"><FileCheck2 class="h-5 w-5" /></div>
                            <div>
                                <h2 class="text-sm font-black text-[#1e2924]">Assignments you created</h2>
                                <p class="text-xs font-semibold text-slate-500">Latest classroom assignments and deadlines</p>
                            </div>
                        </div>
                        <Link href="/classroom" class="text-xs font-black text-[#04795a] hover:text-[#1e2924]">Manage</Link>
                    </div>
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        <Link v-for="assignment in createdAssignments" :key="assignment.id" href="/classroom" class="group rounded-2xl border border-stone-200 bg-stone-50 p-4 transition duration-200 hover:-translate-y-1 hover:border-[#8BED9A] hover:bg-white hover:shadow-md">
                            <div class="flex items-start justify-between gap-3">
                                <span class="rounded-full bg-[#1e2924] px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-[#8BED9A]">{{ assignment.subject }}</span>
                                <span v-if="assignment.marks" class="text-[10px] font-black text-slate-400">{{ assignment.marks }} marks</span>
                            </div>
                            <p class="mt-3 text-sm font-black text-slate-950 group-hover:text-[#04795a]">{{ assignment.title }}</p>
                            <p class="mt-1 line-clamp-2 text-xs font-semibold text-slate-500">{{ assignment.message || assignment.classroom }}</p>
                            <p class="mt-3 text-[10px] font-black uppercase tracking-wider text-amber-700">{{ assignment.due }}</p>
                        </Link>
                        <div v-if="classroomLoading" class="col-span-full p-8 text-center text-sm font-bold text-slate-500">Loading assignments…</div>
                        <div v-else-if="!createdAssignments.length" class="col-span-full rounded-2xl border border-dashed border-stone-300 p-8 text-center">
                            <ClipboardCheck class="mx-auto h-8 w-8 text-slate-300" />
                            <p class="mt-3 text-sm font-black text-[#1e2924]">No assignments created yet</p>
                            <Link href="/classroom" class="mt-2 inline-flex items-center gap-1 text-xs font-black text-[#04795a]">Create in Classroom <ArrowRight class="h-3.5 w-3.5" /></Link>
                        </div>
                    </div>
                </section>

                <section class="surface-card overflow-hidden shadow-sm transition duration-300 hover:shadow-lg">
                    <div class="flex items-center gap-3 border-b border-stone-200 px-5 py-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700"><Timer class="h-5 w-5" /></div>
                        <div>
                            <h2 class="text-sm font-black text-[#1e2924]">Upcoming class tests</h2>
                            <p class="text-xs font-semibold text-slate-500">Tests announced across your classrooms</p>
                        </div>
                    </div>
                    <div class="divide-y divide-stone-200">
                        <Link v-for="test in upcomingTests" :key="test.id" href="/classroom" class="flex items-center gap-4 px-5 py-4 transition hover:bg-amber-50/70">
                            <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-800">
                                <CalendarCheck class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-black text-slate-950">{{ test.title }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">{{ test.subject }} · {{ test.classroom }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black text-amber-800">{{ test.due }}</span>
                        </Link>
                        <p v-if="!classroomLoading && !upcomingTests.length" class="p-8 text-center text-sm font-bold text-slate-500">No upcoming class tests.</p>
                    </div>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="surface-card overflow-hidden">
                    <div class="flex items-center gap-3 border-b border-stone-200 px-4 py-4"><Megaphone class="h-5 w-5 text-[#09B884]" /><p class="text-sm font-black text-[#1e2924]">Notices</p></div>
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        <Link v-for="notice in urgentNotices" :key="notice.id" href="/noticeboard" class="rounded-xl border p-3 transition hover:-translate-y-0.5 hover:shadow-sm" :class="noticeTone[notice.urgency] || noticeTone.Normal">
                            <p class="text-sm font-black">{{ notice.title }}</p><p class="mt-1 text-xs font-semibold opacity-70">{{ notice.date }} · {{ notice.urgency }}</p>
                        </Link>
                        <p v-if="!urgentNotices.length" class="col-span-full p-5 text-center text-sm font-bold text-slate-500">No new notices.</p>
                    </div>
                </section>

                <section class="surface-card overflow-hidden">
                    <div class="flex items-center gap-3 border-b border-stone-200 px-4 py-4"><ClipboardCheck class="h-5 w-5 text-[#09B884]" /><p class="text-sm font-black text-[#1e2924]">Exam duties</p></div>
                    <div class="space-y-2 p-4">
                        <p class="text-base font-black text-[#1e2924]">{{ exam.title || 'No active exam schedule' }}</p>
                        <div v-for="item in exam.items" :key="`${item.date}-${item.time}`" class="rounded-xl border border-stone-200 bg-stone-50 p-3 transition hover:border-[#8BED9A] hover:bg-white">
                            <p class="text-sm font-black text-slate-900">{{ item.title }}</p><p class="mt-1 text-xs font-bold text-slate-500">{{ item.date }} · {{ item.time }} · {{ item.hall }}</p>
                        </div>
                        <p v-if="!exam.items?.length" class="py-4 text-sm font-bold text-slate-500">No upcoming exam duties.</p>
                    </div>
                </section>
            </div>

            <p v-if="classroomError" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-700">{{ classroomError }}</p>
        </div>
    </AppLayout>
</template>
