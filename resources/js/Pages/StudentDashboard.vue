<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { useDashboardClassroomFeed } from '@/Composables/useDashboardClassroomFeed';
import {
    ArrowRight,
    Bell,
    BookOpen,
    CalendarDays,
    CalendarCheck,
    ClipboardList,
    FileCheck2,
    GraduationCap,
    MapPin,
    Sparkles,
    Timer,
} from 'lucide-vue-next';

const props = defineProps({
    studentName: { type: String, default: 'Student' },
    classLabel: { type: String, default: '' },
    dateLabel: { type: String, default: 'Today' },
    stats: { type: Object, default: () => ({}) },
    todayRoutine: { type: Array, default: () => [] },
    notices: { type: Array, default: () => [] },
    classroomUpdates: { type: Array, default: () => [] },
    exam: { type: Object, default: () => ({}) },
    classroomFeed: { type: Object, default: () => ({}) },
});

const { updates: liveClassroomUpdates, assignmentCount, upcomingAssignments, upcomingTests, loading: classroomLoading, error: classroomError } =
    useDashboardClassroomFeed(props.classroomFeed, props.classroomUpdates);

const urgencyClass = {
    Urgent: 'border-red-200 bg-red-100 text-red-800',
    Important: 'border-amber-200 bg-amber-100 text-amber-900',
    Normal: 'border-[#8BED9A]/70 bg-[#8BED9A]/25 text-[#1e2924]',
};
</script>

<template>
    <AppLayout title="Student Portal">
        <div class="space-y-5">
            <section class="group relative overflow-hidden rounded-[1.75rem] border border-[#8BED9A]/50 bg-gradient-to-br from-[#16261f] via-[#203b30] to-[#08795d] p-6 text-white shadow-xl shadow-[#1e2924]/10 sm:p-8">
                <div class="pointer-events-none absolute -right-16 -top-28 h-80 w-80 rounded-full bg-[#8BED9A]/20 blur-3xl transition duration-700 group-hover:scale-110"></div>
                <div class="pointer-events-none absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
                <div class="relative flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-black uppercase tracking-[0.2em] text-[#bff8c8] backdrop-blur">
                            <Sparkles class="h-3.5 w-3.5" />
                            Student daybook
                        </div>
                        <h1 class="mt-5 text-3xl font-black tracking-tight text-white sm:text-4xl">Good day, {{ studentName }}</h1>
                        <p class="mt-2 flex flex-wrap items-center gap-2 text-sm font-semibold text-white/65">
                            <span>{{ dateLabel }}</span><span class="text-white/30">·</span><span class="inline-flex items-center gap-1 text-[#bff8c8]"><GraduationCap class="h-4 w-4" />{{ classLabel }}</span>
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-3 sm:min-w-[28rem]">
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur transition duration-300 hover:-translate-y-1 hover:bg-white/15">
                            <BookOpen class="h-5 w-5 text-[#8BED9A]" />
                            <p class="mt-3 text-3xl font-black text-white">{{ stats.classesToday ?? 0 }}</p>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-white/55">Classes today</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur transition duration-300 hover:-translate-y-1 hover:bg-white/15">
                            <Bell class="h-5 w-5 text-[#8BED9A]" />
                            <p class="mt-3 text-3xl font-black text-white">{{ stats.notices ?? 0 }}</p>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-white/55">Notices</p>
                        </div>
                        <div class="rounded-2xl border border-amber-300/25 bg-amber-300/10 p-4 backdrop-blur transition duration-300 hover:-translate-y-1 hover:bg-amber-300/15">
                            <ClipboardList class="h-5 w-5 text-amber-300" />
                            <p class="mt-3 text-3xl font-black text-white">{{ assignmentCount || stats.assignments || 0 }}</p>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-white/55">Active tasks</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <section class="surface-card overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-stone-200 bg-white px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 text-[#09B884]">
                                <CalendarDays class="h-4 w-4" />
                            </div>
                            <p class="text-sm font-bold text-slate-950">Today's routine</p>
                        </div>
                        <Link href="/routines" class="inline-flex items-center gap-1 text-xs font-bold text-[#1e2924] hover:text-[#09B884]">
                            Full routine
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div class="divide-y divide-stone-200">
                        <div v-for="item in todayRoutine" :key="`${item.period}-${item.subject}`" class="group grid gap-3 px-5 py-4 transition hover:bg-[#8BED9A]/10 sm:grid-cols-[6rem_minmax(0,1fr)_9rem] sm:items-center" :class="item.isProxy ? 'bg-amber-50 ring-1 ring-inset ring-amber-300' : ''">
                            <div>
                                <p class="text-sm font-black text-[#1e2924]">{{ item.period }}</p>
                                <p class="mt-0.5 text-xs font-medium text-slate-500">{{ item.time }}</p>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-bold text-slate-950">{{ item.subject }}</p>
                                    <span v-if="item.isProxy" class="rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-900">Substitute teacher</span>
                                </div>
                                <p v-if="item.teacher" class="mt-0.5 text-xs font-medium text-slate-500">{{ item.teacher }}<span v-if="item.coveringFor"> · covering for {{ item.coveringFor }}</span></p>
                            </div>
                            <div v-if="item.room" class="inline-flex items-center gap-1 rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-2 text-xs font-semibold text-slate-600">
                                <MapPin class="h-3.5 w-3.5" />
                                {{ item.room }}
                            </div>
                            <div v-else class="rounded-lg border border-stone-200 bg-stone-100 px-2.5 py-2 text-center text-xs font-semibold text-slate-500">
                                Break
                            </div>
                        </div>
                        <div v-if="!todayRoutine.length" class="p-10 text-center text-sm font-bold text-slate-500">No routine is active for your class today.</div>
                    </div>
                </section>

                <aside class="space-y-5">
                    <section class="surface-card overflow-hidden">
                        <div class="flex items-center gap-3 border-b border-stone-200 px-4 py-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 text-[#09B884]">
                                <Bell class="h-4 w-4" />
                            </div>
                            <p class="text-sm font-bold text-slate-950">Notices</p>
                        </div>
                        <div class="space-y-2 p-3">
                            <Link
                                v-for="notice in notices"
                                :key="notice.id"
                                href="/noticeboard"
                                class="block rounded-lg border p-3 transition hover:-translate-y-0.5 hover:shadow-sm"
                                :class="urgencyClass[notice.urgency] ?? urgencyClass.Normal"
                            >
                                <p class="text-sm font-bold">{{ notice.title }}</p>
                                <p class="mt-1 text-xs font-semibold opacity-70">{{ notice.date }} - {{ notice.urgency }}</p>
                            </Link>
                            <p v-if="!notices.length" class="p-5 text-center text-sm font-bold text-slate-500">No new notices.</p>
                        </div>
                    </section>

                    <section class="surface-card overflow-hidden transition duration-300 hover:-translate-y-0.5 hover:shadow-lg">
                        <div class="flex items-center gap-3 border-b border-stone-200 px-4 py-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 text-[#09B884]">
                                <CalendarCheck class="h-4 w-4" />
                            </div>
                            <p class="text-sm font-bold text-slate-950">Upcoming exams</p>
                        </div>
                        <div class="space-y-2 p-3">
                            <p class="text-sm font-black text-[#1e2924]">{{ exam.title || 'No active exam schedule' }}</p>
                            <div v-for="item in exam.items" :key="`${item.date}-${item.time}-${item.title}`" class="rounded-xl border border-stone-200 bg-stone-50 p-3 transition hover:border-[#8BED9A]/70 hover:bg-white">
                                <p class="text-sm font-black text-slate-900">{{ item.title }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">{{ item.date }} · {{ item.time }} · {{ item.hall }}</p>
                            </div>
                            <p v-if="!exam.items?.length" class="py-3 text-xs font-semibold text-slate-500">Nothing scheduled for your class.</p>
                        </div>
                    </section>

                    <section class="surface-card overflow-hidden">
                        <div class="flex items-center gap-3 border-b border-stone-200 px-4 py-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 text-[#09B884]">
                                <BookOpen class="h-4 w-4" />
                            </div>
                            <p class="text-sm font-bold text-slate-950">Classroom updates</p>
                        </div>
                        <div class="divide-y divide-stone-200">
                            <Link
                                v-for="update in liveClassroomUpdates"
                                :key="update.id || `${update.subject}-${update.due}`"
                                href="/classroom"
                                class="block px-4 py-3 transition hover:bg-stone-50"
                            >
                                <div class="flex items-start gap-3">
                                    <ClipboardList class="mt-0.5 h-4 w-4 shrink-0 text-[#09B884]" />
                                    <div>
                                        <p class="text-sm font-bold text-slate-950">{{ update.subject }}</p>
                                        <p v-if="update.title" class="mt-1 text-xs font-bold text-slate-700">{{ update.title }}</p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ update.message }}</p>
                                        <p class="mt-2 text-[11px] font-bold uppercase tracking-wider text-[#1e2924]/55">{{ update.due }}</p>
                                    </div>
                                </div>
                            </Link>
                            <p v-if="classroomLoading" class="p-4 text-center text-xs font-bold text-slate-500">Loading classroom activity…</p>
                            <p v-if="classroomError" class="px-4 pb-3 text-xs font-semibold text-amber-700">{{ classroomError }}</p>
                            <p v-if="!classroomLoading && !liveClassroomUpdates.length" class="p-5 text-center text-sm font-bold text-slate-500">No classroom activity yet.</p>
                        </div>
                    </section>
                </aside>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
                <section class="surface-card overflow-hidden shadow-sm transition duration-300 hover:shadow-lg">
                    <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#8BED9A]/20 text-[#04795a]"><FileCheck2 class="h-5 w-5" /></div>
                            <div><p class="text-sm font-black text-[#1e2924]">Upcoming assignments</p><p class="text-xs font-semibold text-slate-500">Deadlines from your classroom</p></div>
                        </div>
                        <Link href="/classroom" class="text-xs font-black text-[#04795a] hover:text-[#1e2924]">Open classroom</Link>
                    </div>
                    <div class="divide-y divide-stone-200">
                        <Link v-for="assignment in upcomingAssignments" :key="assignment.id" href="/classroom" class="flex items-start gap-4 px-5 py-4 transition hover:bg-[#8BED9A]/10">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2"><p class="text-sm font-black text-slate-950">{{ assignment.title }}</p><span class="rounded-full bg-[#8BED9A]/20 px-2 py-0.5 text-[9px] font-black uppercase text-[#04795a]">{{ assignment.subject }}</span></div>
                                <p class="mt-1 line-clamp-2 text-xs font-semibold text-slate-500">{{ assignment.message || assignment.classroom }}</p>
                                <p class="mt-2 text-[10px] font-black uppercase tracking-wider text-amber-700">{{ assignment.due }}<span v-if="assignment.marks"> · {{ assignment.marks }} marks</span></p>
                            </div>
                            <ArrowRight class="mt-1 h-4 w-4 shrink-0 text-slate-300" />
                        </Link>
                        <p v-if="classroomLoading" class="p-8 text-center text-sm font-bold text-slate-500">Loading assignments…</p>
                        <p v-else-if="!upcomingAssignments.length" class="p-8 text-center text-sm font-bold text-slate-500">No upcoming assignments.</p>
                    </div>
                </section>

                <section class="surface-card overflow-hidden shadow-sm transition duration-300 hover:shadow-lg">
                    <div class="flex items-center gap-3 border-b border-stone-200 px-5 py-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700"><Timer class="h-5 w-5" /></div>
                        <div><p class="text-sm font-black text-[#1e2924]">Upcoming class tests</p><p class="text-xs font-semibold text-slate-500">Tests announced by your teachers</p></div>
                    </div>
                    <div class="divide-y divide-stone-200">
                        <Link v-for="test in upcomingTests" :key="test.id" href="/classroom" class="flex items-center gap-4 px-5 py-4 transition hover:bg-amber-50/70">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-700"><CalendarCheck class="h-5 w-5" /></div>
                            <div class="min-w-0 flex-1"><p class="truncate text-sm font-black text-slate-950">{{ test.title }}</p><p class="mt-1 text-xs font-bold text-slate-500">{{ test.subject }} · {{ test.classroom }}</p></div>
                            <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black text-amber-800">{{ test.due }}</span>
                        </Link>
                        <p v-if="!classroomLoading && !upcomingTests.length" class="p-8 text-center text-sm font-bold text-slate-500">No upcoming class tests.</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
