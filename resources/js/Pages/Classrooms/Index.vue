<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { GraduationCap, KeyRound, Pencil, Plus, Search, UserRound } from 'lucide-vue-next';

const props = defineProps({
    classSections: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    canManageClasses: { type: Boolean, default: false },
});

const search = ref('');
const editing = ref(null);

const filteredSections = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.classSections;

    return props.classSections.filter((section) =>
        [section.className, section.sectionName, section.label, section.joinCode, section.classTeacherName]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(q))
    );
});

const sectionCount = computed(() => props.classSections.length);
const classCount = computed(() => new Set(props.classSections.map((section) => section.className)).size);

function openAdd() {
    editing.value = {
        id: null,
        className: '',
        sectionName: 'Section A',
        classTeacherId: null,
        isNew: true,
    };
}

function openEdit(section) {
    editing.value = {
        id: section.id,
        className: section.className,
        sectionName: section.sectionName,
        classTeacherId: section.classTeacherId,
        isNew: false,
    };
}

function closeEditor() {
    editing.value = null;
}

function saveEditor() {
    if (!editing.value?.className?.trim() || !editing.value?.sectionName?.trim()) return;

    const payload = {
        className: editing.value.className.trim(),
        sectionName: editing.value.sectionName.trim(),
        classTeacherId: editing.value.classTeacherId || null,
    };

    if (editing.value.isNew) {
        router.post('/classrooms', payload, { preserveScroll: true, onSuccess: closeEditor });
    } else {
        router.patch(`/classrooms/${editing.value.id}`, payload, { preserveScroll: true, onSuccess: closeEditor });
    }
}
</script>

<template>
    <AppLayout :title="canManageClasses ? 'Classes' : 'Classrooms'">
        <div class="space-y-5">
            <div class="surface-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <h2 class="text-lg font-black text-[#1e2924]">{{ canManageClasses ? 'Class directory' : 'My classroom' }}</h2>
                            <div class="flex items-center gap-3 text-xs font-bold uppercase tracking-wider text-[#1e2924]/50">
                                <span><strong class="text-sm text-[#1e2924]">{{ classCount }}</strong> classes</span>
                                <span class="h-1 w-1 rounded-full bg-[#09B884]/60"></span>
                                <span><strong class="text-sm text-[#1e2924]">{{ sectionCount }}</strong> sections</span>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ canManageClasses ? 'Add class sections and generate student signup codes.' : 'Your class is linked from your student signup code.' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="search" type="text" placeholder="Search class or code" class="field-control w-64 pl-9" />
                        </div>
                        <button v-if="canManageClasses" type="button" class="btn-primary min-h-10" @click="openAdd">
                            <Plus class="h-4 w-4" />
                            Add class
                        </button>
                    </div>
                </div>
            </div>

            <div class="surface-card overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="table-head">
                        <tr>
                            <th class="px-5 py-3">Class section</th>
                            <th class="px-3 py-3">Class teacher</th>
                            <th class="px-3 py-3">Student signup code</th>
                            <th class="px-3 py-3">Subjects</th>
                            <th v-if="canManageClasses" class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        <tr v-for="section in filteredSections" :key="section.id" class="hover:bg-stone-50/70">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#8BED9A]/60 bg-[#8BED9A]/15 text-[#09B884]">
                                        <GraduationCap class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-950">{{ section.label }}</p>
                                        <p class="text-xs text-slate-500">{{ section.className }} · {{ section.sectionName }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold" :class="section.classTeacherId ? 'bg-[#8BED9A]/20 text-[#1e2924]' : 'bg-amber-50 text-amber-800'">
                                    <UserRound class="h-3.5 w-3.5" />
                                    {{ section.classTeacherName }}
                                </span>
                            </td>
                            <td class="px-3 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-2.5 py-1 text-xs font-black text-[#1e2924]">
                                    <KeyRound class="h-3.5 w-3.5 text-[#09B884]" />
                                    {{ section.joinCode }}
                                </span>
                            </td>
                            <td class="px-3 py-4 text-slate-600">
                                {{ section.subjects?.length || 0 }} configured
                            </td>
                            <td v-if="canManageClasses" class="px-5 py-4 text-right">
                                <button type="button" class="btn-secondary min-h-9 px-3 py-1.5 text-xs" @click="openEdit(section)">
                                    <Pencil class="h-3.5 w-3.5" />
                                    Edit
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!filteredSections.length" class="px-5 py-10 text-center text-sm text-slate-500">
                    No class sections found.
                </p>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-100/70 p-4" @click.self="closeEditor">
                <div class="surface-card w-full max-w-md p-5 shadow-xl">
                    <h3 class="text-base font-black text-[#1e2924]">{{ editing.isNew ? 'Add class section' : 'Edit class section' }}</h3>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="section-title">Class name</label>
                            <input v-model="editing.className" type="text" placeholder="Class 1, KG, Nursery" class="field-control mt-1 w-full" />
                        </div>
                        <div>
                            <label class="section-title">Section name</label>
                            <input v-model="editing.sectionName" type="text" placeholder="Section A" class="field-control mt-1 w-full" />
                        </div>
                        <div>
                            <label class="section-title">Class teacher</label>
                            <select v-model="editing.classTeacherId" class="field-control mt-1 w-full">
                                <option :value="null">Unassigned</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="closeEditor">Cancel</button>
                        <button type="button" class="btn-primary" :disabled="!editing.className?.trim() || !editing.sectionName?.trim()" @click="saveEditor">Save</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
