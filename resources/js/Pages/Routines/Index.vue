<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CalendarDays, CheckCircle2, MoreVertical, Pencil, Plus, Trash2, Upload, X } from 'lucide-vue-next';

const props = defineProps({
    routines: { type: Array, default: () => [] },
});

// Access shared authorization state from the Inertia page context
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

// Safely filter what routines get displayed based on the user's role
const displayedRoutines = computed(() => {
    if (isAdmin.value) {
        return props.routines;
    }
    // Teachers only get to see the main active routines, hiding drafts/archives
    return props.routines.filter(routine => routine.status === 'Active');
});

const statusBadge = {
    Active: 'border border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#1e2924]',
    Draft: 'border border-slate-700 bg-white text-slate-600',
};

const importInput = ref(null);
const importing = ref(false);
const openMenuId = ref(null);
const renamingRoutine = ref(null);
const renameValue = ref('');

function openImportPicker() {
    importInput.value?.click();
}

function toggleMenu(routineId) {
    openMenuId.value = openMenuId.value === routineId ? null : routineId;
}

function makeActive(routine) {
    openMenuId.value = null;
    router.post(`/routines/${routine.id}/activate`, {}, { preserveScroll: true });
}

function openRename(routine) {
    openMenuId.value = null;
    renamingRoutine.value = routine;
    renameValue.value = routine.name;
}

function closeRename() {
    renamingRoutine.value = null;
    renameValue.value = '';
}

function submitRename() {
    if (!renamingRoutine.value || !renameValue.value.trim()) return;

    router.patch(`/routines/${renamingRoutine.value.id}/rename`, { name: renameValue.value.trim() }, {
        preserveScroll: true,
        onSuccess: closeRename,
    });
}

function deleteRoutine(routine) {
    openMenuId.value = null;
    if (!window.confirm(`Delete "${routine.name}"? This cannot be undone.`)) return;

    router.delete(`/routines/${routine.id}`, { preserveScroll: true });
}

function importRoutine(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    importing.value = true;
    router.post('/routines/import', { file }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importing.value = false;
            event.target.value = '';
        },
    });
}
</script>

<template>
    <AppLayout title="Routines">
        <div class="space-y-6">
            <div class="surface-card p-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="px-3 text-lg font-bold text-slate-950">Routines</h2>
                
                <div v-if="isAdmin" class="flex items-center gap-2">
                    <input ref="importInput" type="file" accept=".docx" class="hidden" @change="importRoutine" />
                    <button
                        type="button"
                        class="flex min-h-11 items-center gap-2 rounded-lg border border-stone-300 bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition hover:border-[#09B884]/40 hover:bg-[#8BED9A]/10 hover:text-[#1e2924] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="importing"
                        @click="openImportPicker"
                    >
                        <Upload class="h-4 w-4" :class="importing ? 'animate-pulse' : ''" />
                        {{ importing ? 'Importing...' : 'Import' }}
                    </button>
                    <Link
                        href="/routines/create"
                        class="flex min-h-11 items-center gap-2 rounded-lg bg-[#1e2924]/95 px-4 text-sm font-bold text-white shadow-sm shadow-black/10 transition hover:bg-[#1e2924]"
                    >
                        <Plus class="h-4 w-4" />
                        Create routine
                    </Link>
                </div>
                </div>
            </div>

            <div class="space-y-3" @click="openMenuId = null">
                <div
                    v-for="routine in displayedRoutines"
                    :key="routine.id"
                    class="surface-card relative flex cursor-pointer items-center gap-4 p-4 transition hover:border-[#8BED9A]/70 hover:shadow-md"
                    :class="routine.status === 'Active' ? 'border-[#8BED9A]/70 bg-[#8BED9A]/10 shadow-sm shadow-[#1e2924]/5' : 'border-stone-200 bg-white'"
                    @click="router.visit(`/routines/${routine.id}`)"
                >
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border"
                        :class="routine.status === 'Active' ? 'border-[#8BED9A]/70 bg-white' : 'border-stone-200 bg-stone-50'"
                    >
                        <CalendarDays
                            class="h-5 w-5"
                            :class="routine.status === 'Active' ? 'text-[#09B884]' : 'text-slate-500'"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-950">{{ routine.name }}</p>
                            <span v-if="routine.status === 'Active'" class="rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-2 py-0.5 text-[11px] font-semibold text-[#1e2924]">Current active</span>
                        </div>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ routine.days }} Days &middot; {{ routine.classes }} Classes &middot;
                            {{ routine.sections }} Total Sections &middot; {{ routine.teachers }} Teachers
                        </p>
                        <p
                            class="mt-0.5 text-sm font-medium"
                            :class="routine.proxyClassesWeek > 0 ? 'text-red-700' : 'text-[#1e2924]'"
                        >
                            {{ routine.proxyClassesWeek }} Total Proxy Classes this Week
                        </p>
                    </div>

                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadge[routine.status]">
                        {{ routine.status }}
                    </span>

                    <div v-if="isAdmin" class="relative" @click.stop>
                        <button
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-stone-300 text-slate-500 shadow-sm transition hover:border-[#09B884]/40 hover:bg-[#8BED9A]/10 hover:text-[#1e2924]"
                            :aria-expanded="openMenuId === routine.id"
                            aria-haspopup="menu"
                            @click="toggleMenu(routine.id)"
                        >
                            <MoreVertical class="h-4 w-4" />
                        </button>

                        <div
                            v-if="openMenuId === routine.id"
                            class="absolute right-0 top-11 z-20 w-56 rounded-lg border border-stone-200 bg-white p-1.5 shadow-lg"
                            role="menu"
                        >
                            <button
                                v-if="routine.status !== 'Active'"
                                type="button"
                                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-[#8BED9A]/15 hover:text-[#1e2924]"
                                @click="makeActive(routine)"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                Make current active
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-stone-100"
                                @click="openRename(routine)"
                            >
                                <Pencil class="h-4 w-4" />
                                Rename routine
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-red-700 hover:bg-red-50"
                                @click="deleteRoutine(routine)"
                            >
                                <Trash2 class="h-4 w-4" />
                                Delete routine
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <Teleport to="body">
                <div v-if="renamingRoutine" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-100/70 p-4" @click.self="closeRename">
                    <form class="surface-card w-full max-w-sm p-5 shadow-xl" @submit.prevent="submitRename">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-950">Rename routine</h3>
                                <p class="mt-1 text-sm text-slate-500">Update the display name for this routine.</p>
                            </div>
                            <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-stone-100 hover:text-slate-700" @click="closeRename">
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <label class="mt-4 block text-xs font-medium text-slate-600">Routine name</label>
                        <input v-model="renameValue" type="text" class="field-control mt-1 w-full" autofocus />

                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" class="btn-secondary" @click="closeRename">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="!renameValue.trim()">Save name</button>
                        </div>
                    </form>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>
