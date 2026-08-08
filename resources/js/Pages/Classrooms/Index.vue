<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircle2, GraduationCap, KeyRound, Pencil, Plus, Search, Trash2, UserRound, X } from 'lucide-vue-next';

const props = defineProps({
    classSections: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    canManageClasses: { type: Boolean, default: false },
});

const search = ref('');
const classEditor = ref(null);
const sectionEditor = ref(null);
const deleting = ref(null);
const notification = ref(null);

const classGroups = computed(() => {
    const groups = new Map();

    props.classSections.forEach((section) => {
        if (!groups.has(section.className)) {
            groups.set(section.className, {
                className: section.className,
                sections: [],
            });
        }

        groups.get(section.className).sections.push(section);
    });

    return Array.from(groups.values()).sort((a, b) => a.className.localeCompare(b.className, undefined, { numeric: true, sensitivity: 'base' }));
});

const filteredClassGroups = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return classGroups.value;

    return classGroups.value
        .map((group) => ({
            ...group,
            sections: group.sections.filter((section) =>
                [section.className, section.sectionName, section.label, section.joinCode, section.classTeacherName]
                    .filter(Boolean)
                    .some((value) => String(value).toLowerCase().includes(q))
            ),
        }))
        .filter((group) => group.className.toLowerCase().includes(q) || group.sections.length);
});

const sectionCount = computed(() => props.classSections.length);
const classCount = computed(() => classGroups.value.length);

function openAddClass() {
    classEditor.value = {
        className: '',
        sections: [{ sectionName: 'Section A', classTeacherId: null }],
    };
}

function closeClassEditor() {
    classEditor.value = null;
}

function addDraftSection() {
    const nextLetter = String.fromCharCode(65 + classEditor.value.sections.length);
    classEditor.value.sections.push({ sectionName: `Section ${nextLetter}`, classTeacherId: null });
}

function removeDraftSection(index) {
    if (classEditor.value.sections.length === 1) return;
    classEditor.value.sections.splice(index, 1);
}

function saveClass() {
    if (!classEditor.value?.className?.trim()) return;

    const sections = classEditor.value.sections
        .filter((section) => section.sectionName?.trim())
        .map((section) => ({
            sectionName: section.sectionName.trim(),
            classTeacherId: section.classTeacherId || null,
        }));

    if (!sections.length) return;

    router.post('/classrooms', {
        className: classEditor.value.className.trim(),
        sections,
    }, {
        preserveScroll: true,
        onSuccess: () => notifyAndClose('Class added.', closeClassEditor),
    });
}

function openAddSection(group) {
    const nextLetter = String.fromCharCode(65 + group.sections.length);
    sectionEditor.value = {
        id: null,
        className: group.className,
        sectionName: `Section ${nextLetter}`,
        classTeacherId: null,
        isNew: true,
    };
}

function openEditSection(section) {
    sectionEditor.value = {
        id: section.id,
        className: section.className,
        sectionName: section.sectionName,
        classTeacherId: section.classTeacherId,
        isNew: false,
    };
}

function closeSectionEditor() {
    sectionEditor.value = null;
}

function saveSection() {
    if (!sectionEditor.value?.className?.trim() || !sectionEditor.value?.sectionName?.trim()) return;

    const payload = {
        className: sectionEditor.value.className.trim(),
        sectionName: sectionEditor.value.sectionName.trim(),
        classTeacherId: sectionEditor.value.classTeacherId || null,
    };

    if (sectionEditor.value.isNew) {
        router.post('/classrooms', payload, {
            preserveScroll: true,
            onSuccess: () => notifyAndClose('Section added.', closeSectionEditor),
        });
    } else {
        router.patch(`/classrooms/${sectionEditor.value.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => notifyAndClose('Section updated.', closeSectionEditor),
        });
    }
}

function confirmDeleteClass(group) {
    deleting.value = {
        type: 'class',
        title: `Delete ${group.className}?`,
        message: `This will delete ${group.sections.length} section${group.sections.length === 1 ? '' : 's'} and their student signup codes.`,
        className: group.className,
    };
}

function confirmDeleteSection(section) {
    deleting.value = {
        type: 'section',
        title: `Delete ${section.label}?`,
        message: 'This will remove this section and its student signup code.',
        section,
    };
}

function deleteConfirmed() {
    if (!deleting.value) return;

    if (deleting.value.type === 'class') {
        const className = deleting.value.className;
        router.delete('/classrooms/class-group', {
            data: { className },
            preserveScroll: true,
            onSuccess: () => notifyAndClose(`${className} deleted.`, () => {
                deleting.value = null;
            }),
        });
        return;
    }

    const label = deleting.value.section.label;
    router.delete(`/classrooms/${deleting.value.section.id}`, {
        preserveScroll: true,
        onSuccess: () => notifyAndClose(`${label} deleted.`, () => {
            deleting.value = null;
        }),
    });
}

function notifyAndClose(message, callback) {
    callback?.();
    notification.value = message;
    window.setTimeout(() => {
        if (notification.value === message) notification.value = null;
    }, 3200);
}
</script>

<template>
    <AppLayout :title="canManageClasses ? 'Classes' : 'Classrooms'">
        <div class="space-y-5">
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="-translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <div
                    v-if="notification"
                    class="flex items-center gap-3 rounded-xl border border-[#8BED9A]/70 bg-white px-4 py-3 text-sm font-bold text-[#1e2924] shadow-[0_14px_35px_rgba(30,41,36,0.10)] ring-1 ring-[#8BED9A]/20"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#8BED9A]/25 text-[#09B884]">
                        <CheckCircle2 class="h-4 w-4" />
                    </span>
                    <span>{{ notification }}</span>
                </div>
            </Transition>

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
                            {{ canManageClasses ? 'Add each class once, then manage sections inside it.' : 'Your class is linked from your student signup code.' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="search" type="text" placeholder="Search class, section, or code" class="field-control w-72 pl-9" />
                        </div>
                        <button v-if="canManageClasses" type="button" class="btn-primary min-h-10" @click="openAddClass">
                            <Plus class="h-4 w-4" />
                            Add class
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <div v-for="group in filteredClassGroups" :key="group.className" class="surface-card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 bg-stone-50/70 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#8BED9A]/60 bg-[#8BED9A]/15 text-[#09B884]">
                                <GraduationCap class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-base font-black text-slate-950">{{ group.className }}</p>
                                <p class="text-xs font-semibold text-slate-500">{{ group.sections.length }} section{{ group.sections.length === 1 ? '' : 's' }}</p>
                            </div>
                        </div>
                        <div v-if="canManageClasses" class="flex flex-wrap gap-2">
                            <button type="button" class="btn-secondary min-h-9 px-3 py-1.5 text-xs" @click="openAddSection(group)">
                                <Plus class="h-3.5 w-3.5" />
                                Add section
                            </button>
                            <button type="button" class="btn-danger-soft min-h-9 px-3 py-1.5 text-xs" @click="confirmDeleteClass(group)">
                                <Trash2 class="h-3.5 w-3.5" />
                                Delete class
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="table-head">
                                <tr>
                                    <th class="px-5 py-3">Section</th>
                                    <th class="px-3 py-3">Class teacher</th>
                                    <th class="px-3 py-3">Student signup code</th>
                                    <th class="px-3 py-3">Subjects</th>
                                    <th v-if="canManageClasses" class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-200">
                                <tr v-for="section in group.sections" :key="section.id" class="hover:bg-stone-50/70">
                                    <td class="px-5 py-4">
                                        <p class="font-bold text-slate-950">{{ section.sectionName }}</p>
                                        <p class="text-xs text-slate-500">{{ section.label }}</p>
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
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="btn-secondary min-h-9 px-3 py-1.5 text-xs" @click="openEditSection(section)">
                                                <Pencil class="h-3.5 w-3.5" />
                                                Edit
                                            </button>
                                            <button type="button" class="btn-danger-soft min-h-9 px-3 py-1.5 text-xs" @click="confirmDeleteSection(section)">
                                                <Trash2 class="h-3.5 w-3.5" />
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <p v-if="!filteredClassGroups.length" class="surface-card px-5 py-10 text-center text-sm text-slate-500">
                No classes found.
            </p>
        </div>

        <Teleport to="body">
            <div v-if="classEditor" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-100/70 p-4" @click.self="closeClassEditor">
                <div class="surface-card w-full max-w-2xl p-5 shadow-xl">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-base font-black text-[#1e2924]">Add class</h3>
                        <button type="button" class="text-slate-400 hover:text-slate-700" @click="closeClassEditor">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="section-title">Class name</label>
                            <input v-model="classEditor.className" type="text" placeholder="Class 1, KG, Nursery" class="field-control mt-1 w-full" />
                        </div>

                        <div class="rounded-lg border border-stone-200 bg-stone-50/70 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="section-title">Sections</p>
                                <button type="button" class="btn-secondary min-h-8 px-2.5 py-1 text-xs" @click="addDraftSection">
                                    <Plus class="h-3.5 w-3.5" />
                                    Add section
                                </button>
                            </div>

                            <div class="mt-3 space-y-2">
                                <div v-for="(section, index) in classEditor.sections" :key="index" class="grid gap-2 rounded-lg border border-stone-200 bg-white p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_2rem]">
                                    <input v-model="section.sectionName" type="text" placeholder="Section A" class="field-control-sm w-full" />
                                    <select v-model="section.classTeacherId" class="field-control-sm w-full">
                                        <option :value="null">Unassigned class teacher</option>
                                        <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                                    </select>
                                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-30" :disabled="classEditor.sections.length === 1" @click="removeDraftSection(index)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="closeClassEditor">Cancel</button>
                        <button type="button" class="btn-primary" :disabled="!classEditor.className?.trim() || !classEditor.sections.some((section) => section.sectionName?.trim())" @click="saveClass">Save class</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="sectionEditor" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-100/70 p-4" @click.self="closeSectionEditor">
                <div class="surface-card w-full max-w-md p-5 shadow-xl">
                    <h3 class="text-base font-black text-[#1e2924]">{{ sectionEditor.isNew ? `Add section to ${sectionEditor.className}` : 'Edit section' }}</h3>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="section-title">Class name</label>
                            <input v-model="sectionEditor.className" type="text" class="field-control mt-1 w-full bg-stone-50" :readonly="sectionEditor.isNew" />
                        </div>
                        <div>
                            <label class="section-title">Section name</label>
                            <input v-model="sectionEditor.sectionName" type="text" placeholder="Section A" class="field-control mt-1 w-full" />
                        </div>
                        <div>
                            <label class="section-title">Class teacher</label>
                            <select v-model="sectionEditor.classTeacherId" class="field-control mt-1 w-full">
                                <option :value="null">Unassigned</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="closeSectionEditor">Cancel</button>
                        <button type="button" class="btn-primary" :disabled="!sectionEditor.className?.trim() || !sectionEditor.sectionName?.trim()" @click="saveSection">Save</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="deleting" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-100/70 p-4" @click.self="deleting = null">
                <div class="surface-card w-full max-w-md p-5 shadow-xl">
                    <h3 class="text-base font-black text-[#1e2924]">{{ deleting.title }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ deleting.message }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="deleting = null">Cancel</button>
                        <button type="button" class="btn-danger-soft min-h-10 px-4 text-sm font-bold" @click="deleteConfirmed">
                            <Trash2 class="h-4 w-4" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
