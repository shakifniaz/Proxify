<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CalendarDays, Upload, Plus, MoreVertical } from 'lucide-vue-next';

// Shape mirrors a future RoutineController@index response.
const props = defineProps({
    routines: { type: Array, default: () => [] },
});

const statusBadge = {
    Active: 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-400',
    Draft: 'border border-slate-700 bg-slate-800/60 text-slate-400',
};
</script>

<template>
    <AppLayout title="Routines">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-white">Routines</h2>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg border border-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/50"
                    >
                        <Upload class="h-4 w-4" />
                        Import Excel
                    </button>
                    <Link
                        href="/routines/create"
                        class="flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                    >
                        <Plus class="h-4 w-4" />
                        Create routine
                    </Link>
                </div>
            </div>

            <div class="space-y-3">
                <Link
                    v-for="routine in routines"
                    :key="routine.id"
                    :href="`/routines/${routine.id}`"
                    class="flex items-center gap-4 rounded-xl border bg-slate-900/50 p-4 transition-colors hover:bg-slate-900"
                    :class="routine.status === 'Active' ? 'border-emerald-500/40' : 'border-slate-800'"
                >
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
                        :class="routine.status === 'Active' ? 'bg-emerald-500/15' : 'bg-slate-800/60'"
                    >
                        <CalendarDays
                            class="h-5 w-5"
                            :class="routine.status === 'Active' ? 'text-emerald-400' : 'text-slate-500'"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-white">{{ routine.name }}</p>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ routine.days }} Days &middot; {{ routine.classes }} Classes &middot;
                            {{ routine.sections }} Total Sections &middot; {{ routine.teachers }} Teachers
                        </p>
                        <p
                            class="mt-0.5 text-sm font-medium"
                            :class="routine.proxyClassesWeek > 0 ? 'text-rose-400' : 'text-emerald-400'"
                        >
                            {{ routine.proxyClassesWeek }} Total Proxy Classes this Week
                        </p>
                    </div>

                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadge[routine.status]">
                        {{ routine.status }}
                    </span>

                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-800 text-slate-500 hover:bg-slate-800/70 hover:text-slate-300"
                        @click.stop.prevent
                    >
                        <MoreVertical class="h-4 w-4" />
                    </button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>