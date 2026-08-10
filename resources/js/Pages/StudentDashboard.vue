<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bell,
    BookOpen,
    CalendarDays,
    ClipboardList,
    GraduationCap,
    MapPin,
} from 'lucide-vue-next';

const props = defineProps({
    studentName: { type: String, default: 'Student' },
    classLabel: { type: String, default: '' },
    dateLabel: { type: String, default: 'Today' },
    stats: { type: Object, default: () => ({}) },
    todayRoutine: { type: Array, default: () => [] },
    notices: { type: Array, default: () => [] },
    classroomUpdates: { type: Array, default: () => [] },
});

const urgencyClass = {
    Urgent: 'border-red-200 bg-red-100 text-red-800',
    Important: 'border-amber-200 bg-amber-100 text-amber-900',
    Normal: 'border-[#8BED9A]/70 bg-[#8BED9A]/25 text-[#1e2924]',
};
</script>

<template>
    <AppLayout title="Student Portal">
        <div class="space-y-5">
            <section class="relative overflow-hidden rounded-xl border border-[#8BED9A]/45 bg-[#8BED9A]/15 p-5 shadow-sm">
                <div class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-[#8BED9A]/45 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-0 left-1/3 h-40 w-40 rounded-full bg-[#09B884]/10 blur-2xl"></div>
                <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-[#8BED9A]/70 bg-white/80 px-3 py-1 text-xs font-bold text-[#1e2924] shadow-sm">
                            <GraduationCap class="h-3.5 w-3.5 text-[#09B884]" />
                            {{ classLabel }}
                        </div>
                        <h1 class="mt-4 text-2xl font-black tracking-tight text-[#1e2924] sm:text-3xl">Good morning, {{ studentName }}</h1>
                        <p class="mt-1 text-sm font-medium text-slate-600">{{ dateLabel }}</p>
                    </div>

                    <div class="grid grid-cols-3 gap-2 sm:min-w-[26rem]">
                        <div class="rounded-lg border border-[#8BED9A]/60 bg-white/80 px-3 py-3 shadow-sm">
                            <p class="text-2xl font-black text-[#1e2924]">{{ stats.classesToday ?? 0 }}</p>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#1e2924]/55">Classes</p>
                        </div>
                        <div class="rounded-lg border border-[#8BED9A]/60 bg-white/80 px-3 py-3 shadow-sm">
                            <p class="text-2xl font-black text-[#1e2924]">{{ stats.notices ?? 0 }}</p>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#1e2924]/55">Notices</p>
                        </div>
                        <div class="rounded-lg border border-[#8BED9A]/60 bg-white/80 px-3 py-3 shadow-sm">
                            <p class="text-2xl font-black text-[#1e2924]">{{ stats.assignments ?? 0 }}</p>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#1e2924]/55">Tasks</p>
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
                        <div v-for="item in todayRoutine" :key="`${item.period}-${item.subject}`" class="grid gap-3 px-5 py-4 sm:grid-cols-[6rem_minmax(0,1fr)_9rem] sm:items-center">
                            <div>
                                <p class="text-sm font-black text-[#1e2924]">{{ item.period }}</p>
                                <p class="mt-0.5 text-xs font-medium text-slate-500">{{ item.time }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-950">{{ item.subject }}</p>
                                <p v-if="item.teacher" class="mt-0.5 text-xs font-medium text-slate-500">{{ item.teacher }}</p>
                            </div>
                            <div v-if="item.room" class="inline-flex items-center gap-1 rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-2 text-xs font-semibold text-slate-600">
                                <MapPin class="h-3.5 w-3.5" />
                                {{ item.room }}
                            </div>
                            <div v-else class="rounded-lg border border-stone-200 bg-stone-100 px-2.5 py-2 text-center text-xs font-semibold text-slate-500">
                                Break
                            </div>
                        </div>
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
                                v-for="update in classroomUpdates"
                                :key="`${update.subject}-${update.due}`"
                                href="/classrooms"
                                class="block px-4 py-3 transition hover:bg-stone-50"
                            >
                                <div class="flex items-start gap-3">
                                    <ClipboardList class="mt-0.5 h-4 w-4 shrink-0 text-[#09B884]" />
                                    <div>
                                        <p class="text-sm font-bold text-slate-950">{{ update.subject }}</p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ update.message }}</p>
                                        <p class="mt-2 text-[11px] font-bold uppercase tracking-wider text-[#1e2924]/55">{{ update.due }}</p>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
