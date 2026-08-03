<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircle2, KeyRound, Pencil, Plus, Search, UserRound } from 'lucide-vue-next';

const props = defineProps({
    teachers: { type: Array, default: () => [] },
});

const localTeachers = computed(() => props.teachers);
const search = ref('');
const editing = ref(null);

const filteredTeachers = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return localTeachers.value;

    return localTeachers.value.filter((teacher) =>
        [teacher.name, teacher.phone, teacher.joinCode]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(q))
    );
});

const statusBadge = {
    Active: 'bg-[#8BED9A]/20 text-[#1e2924] border border-[#8BED9A]/70',
    'On leave': 'bg-amber-50 text-amber-800 border border-amber-200',
    Inactive: 'bg-stone-100 text-slate-600 border border-stone-200',
};
const statusOptions = ['Active', 'On leave', 'Inactive'];

function openAdd() {
    editing.value = { id: null, name: '', phone: '', status: 'Active', isNew: true };
}

function openEdit(teacher) {
    editing.value = {
        id: teacher.id,
        name: teacher.name,
        phone: teacher.phone ?? '',
        status: teacher.status ?? 'Active',
        isNew: false,
    };
}

function closeEditor() {
    editing.value = null;
}

function saveEditor() {
    if (!editing.value?.name?.trim()) return;

    const payload = {
        name: editing.value.name.trim(),
        phone: editing.value.phone?.trim() || null,
        status: editing.value.status,
    };

    if (editing.value.isNew) {
        router.post('/teachers', payload, { preserveScroll: true, onSuccess: closeEditor });
    } else {
        router.patch(`/teachers/${editing.value.id}`, payload, { preserveScroll: true, onSuccess: closeEditor });
    }
}
</script>

<template>
    <AppLayout title="Teachers">
        <div class="space-y-5">
            <div class="surface-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-[#1e2924]">Teacher directory</h2>
                        <p class="mt-1 text-sm text-slate-500">Add teachers here before assigning them inside routines.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="search" type="text" placeholder="Search teacher or code" class="field-control w-64 pl-9" />
                        </div>
                        <button type="button" class="btn-primary min-h-10" @click="openAdd">
                            <Plus class="h-4 w-4" />
                            Add teacher
                        </button>
                    </div>
                </div>
            </div>

            <div class="surface-card overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="table-head">
                        <tr>
                            <th class="px-5 py-3">Teacher</th>
                            <th class="px-3 py-3">Signup code</th>
                            <th class="px-3 py-3">Account</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        <tr v-for="teacher in filteredTeachers" :key="teacher.id" class="hover:bg-stone-50/70">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#8BED9A]/60 bg-[#8BED9A]/15 text-[#09B884]">
                                        <UserRound class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-950">{{ teacher.name }}</p>
                                        <p class="text-xs text-slate-500">{{ teacher.phone || 'WhatsApp not set' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-2.5 py-1 text-xs font-black text-[#1e2924]">
                                    <KeyRound class="h-3.5 w-3.5 text-[#09B884]" />
                                    {{ teacher.joinCode }}
                                </span>
                            </td>
                            <td class="px-3 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold" :class="teacher.linked ? 'bg-[#8BED9A]/20 text-[#1e2924]' : 'bg-amber-50 text-amber-800'">
                                    <CheckCircle2 v-if="teacher.linked" class="h-3.5 w-3.5" />
                                    {{ teacher.linked ? 'Linked' : 'Waiting for signup' }}
                                </span>
                            </td>
                            <td class="px-3 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadge[teacher.status] ?? statusBadge.Active">
                                    {{ teacher.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button type="button" class="btn-secondary min-h-9 px-3 py-1.5 text-xs" @click="openEdit(teacher)">
                                    <Pencil class="h-3.5 w-3.5" />
                                    Edit
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!filteredTeachers.length" class="px-5 py-10 text-center text-sm text-slate-500">
                    No teachers found.
                </p>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-100/70 p-4" @click.self="closeEditor">
                <div class="surface-card w-full max-w-md p-5 shadow-xl">
                    <h3 class="text-base font-black text-[#1e2924]">{{ editing.isNew ? 'Add teacher' : 'Edit teacher' }}</h3>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="section-title">Name</label>
                            <input v-model="editing.name" type="text" placeholder="Teacher name" class="field-control mt-1 w-full" />
                        </div>
                        <div>
                            <label class="section-title">WhatsApp number</label>
                            <input v-model="editing.phone" type="text" placeholder="Optional" class="field-control mt-1 w-full" />
                        </div>
                        <div v-if="!editing.isNew">
                            <label class="section-title">Status</label>
                            <select v-model="editing.status" class="field-control mt-1 w-full">
                                <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="closeEditor">Cancel</button>
                        <button type="button" class="btn-primary" :disabled="!editing.name?.trim()" @click="saveEditor">Save</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
