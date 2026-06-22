<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChevronDown, ChevronUp, Layers, Users, Trash2, Plus } from 'lucide-vue-next';

const props = defineProps({
    classesConfig: { type: Object, default: () => ({ numberOfClasses: 0, maxPeriodsPerDay: 7 }) },
    classes: { type: Array, default: () => [] }, // [{ id, name, sections: [string], subjects: [string] }]
    teachersConfig: { type: Object, default: () => ({ numberOfTeachers: 0 }) },
    teachers: { type: Array, default: () => [] }, // [{ id, name, phone, subjects: [string] }]
});

const tab = ref('Classes'); // 'Classes' | 'Teachers'

const classesForm = ref({ ...props.classesConfig });
const localClasses = ref(props.classes.map((c) => ({ ...c, sections: [...c.sections], subjects: [...c.subjects] })));
const expandedClassId = ref(localClasses.value[0]?.id ?? null);

const teachersForm = ref({ ...props.teachersConfig });
const localTeachers = ref(props.teachers.map((t) => ({ ...t, subjects: [...t.subjects] })));
const expandedTeacherId = ref(null);

function classSummary(cls) {
    if (!cls.sections.length && !cls.subjects.length) return 'Click for details';
    return `${cls.sections.length} section${cls.sections.length === 1 ? '' : 's'} · ${cls.subjects.length} subject${cls.subjects.length === 1 ? '' : 's'}`;
}

function toggleClass(id) {
    expandedClassId.value = expandedClassId.value === id ? null : id;
}
function toggleTeacher(id) {
    expandedTeacherId.value = expandedTeacherId.value === id ? null : id;
}

function applyClassCount() {
    const n = Math.max(0, Number(classesForm.value.numberOfClasses) || 0);
    const next = [];
    for (let i = 0; i < n; i++) {
        next.push(localClasses.value[i] ?? { id: i + 1, name: `Class ${i + 1}`, sections: [], subjects: [] });
    }
    localClasses.value = next;
    if (!next.find((c) => c.id === expandedClassId.value)) {
        expandedClassId.value = next[0]?.id ?? null;
    }
}

function applyTeacherCount() {
    const n = Math.max(0, Number(teachersForm.value.numberOfTeachers) || 0);
    const next = [];
    for (let i = 0; i < n; i++) {
        next.push(localTeachers.value[i] ?? { id: i + 1, name: `Teacher ${i + 1}`, phone: '', subjects: [] });
    }
    localTeachers.value = next;
}

function addSection(cls) {
    cls.sections.push('');
}
function removeSection(cls, i) {
    cls.sections.splice(i, 1);
}
function addSubject(cls) {
    cls.subjects.push('');
}
function removeSubject(cls, i) {
    cls.subjects.splice(i, 1);
}
function addTeacherSubject(teacher) {
    teacher.subjects.push('');
}
function removeTeacherSubject(teacher, i) {
    teacher.subjects.splice(i, 1);
}
</script>

<template>
    <AppLayout title="Create Routine">
        <div class="mx-auto max-w-3xl space-y-4">
            <!-- Page header -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-white">Create Routine</h2>
                    <p class="mt-1 text-sm text-slate-500">Set up classes and teachers</p>
                </div>
                <Link
                    href="/routines"
                    class="rounded-lg border border-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/50"
                >
                    Cancel
                </Link>
            </div>

            <!-- Tab switcher -->
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="rounded-lg border px-4 py-2.5 text-sm font-semibold transition-colors"
                    :class="tab === 'Classes' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-400' : 'border-slate-800 text-slate-400 hover:bg-slate-800/50'"
                    @click="tab = 'Classes'"
                >
                    Classes
                </button>
                <button
                    type="button"
                    class="rounded-lg border px-4 py-2.5 text-sm font-semibold transition-colors"
                    :class="tab === 'Teachers' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-400' : 'border-slate-800 text-slate-400 hover:bg-slate-800/50'"
                    @click="tab = 'Teachers'"
                >
                    Teachers
                </button>
            </div>

            <!-- Classes tab -->
            <template v-if="tab === 'Classes'">
                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Number of Classes</label>
                            <input
                                v-model="classesForm.numberOfClasses"
                                type="number"
                                min="0"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Maximum Periods per Day</label>
                            <input
                                v-model="classesForm.maxPeriodsPerDay"
                                type="number"
                                min="0"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            />
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-lg bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                        @click="applyClassCount"
                    >
                        Confirm
                    </button>
                </div>

                <p class="px-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Class Details</p>

                <div class="space-y-2">
                    <div
                        v-for="cls in localClasses"
                        :key="cls.id"
                        class="rounded-xl border bg-slate-900/50 transition-colors"
                        :class="expandedClassId === cls.id ? 'border-emerald-500/40' : 'border-slate-800'"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-5 py-4 text-left"
                            @click="toggleClass(cls.id)"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15">
                                <Layers class="h-5 w-5 text-emerald-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-white">{{ cls.name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ classSummary(cls) }}</p>
                            </div>
                            <ChevronUp v-if="expandedClassId === cls.id" class="h-4 w-4 shrink-0 text-slate-500" />
                            <ChevronDown v-else class="h-4 w-4 shrink-0 text-slate-500" />
                        </button>

                        <div v-if="expandedClassId === cls.id" class="space-y-4 border-t border-slate-800 px-5 py-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Sections</p>
                                <div class="mt-2 space-y-2">
                                    <div v-for="(section, i) in cls.sections" :key="i" class="flex items-center gap-2">
                                        <input
                                            v-model="cls.sections[i]"
                                            type="text"
                                            placeholder="e.g. Section A"
                                            class="w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-800 text-slate-500 hover:bg-slate-800/50 hover:text-rose-400"
                                            @click="removeSection(cls, i)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="mt-2 flex items-center gap-1.5 rounded-lg border border-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800/50"
                                    @click="addSection(cls)"
                                >
                                    <Plus class="h-3.5 w-3.5" /> Add Section
                                </button>
                            </div>

                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Subjects</p>
                                <div class="mt-2 space-y-2">
                                    <div v-for="(subject, i) in cls.subjects" :key="i" class="flex items-center gap-2">
                                        <input
                                            v-model="cls.subjects[i]"
                                            type="text"
                                            placeholder="e.g. Mathematics"
                                            class="w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-800 text-slate-500 hover:bg-slate-800/50 hover:text-rose-400"
                                            @click="removeSubject(cls, i)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="mt-2 flex items-center gap-1.5 rounded-lg border border-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800/50"
                                    @click="addSubject(cls)"
                                >
                                    <Plus class="h-3.5 w-3.5" /> Add Subject
                                </button>
                            </div>
                        </div>
                    </div>

                    <p v-if="!localClasses.length" class="rounded-xl border border-dashed border-slate-800 px-5 py-8 text-center text-sm text-slate-500">
                        Set a class count above and hit Confirm to get started.
                    </p>
                </div>
            </template>

            <!-- Teachers tab -->
            <template v-else>
                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                    <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Number of Teachers</label>
                    <input
                        v-model="teachersForm.numberOfTeachers"
                        type="number"
                        min="0"
                        class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                    />
                    <button
                        type="button"
                        class="mt-4 w-full rounded-lg bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                        @click="applyTeacherCount"
                    >
                        Confirm
                    </button>
                </div>

                <p class="px-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Teacher Details</p>

                <div class="space-y-2">
                    <div
                        v-for="teacher in localTeachers"
                        :key="teacher.id"
                        class="rounded-xl border bg-slate-900/50 transition-colors"
                        :class="expandedTeacherId === teacher.id ? 'border-emerald-500/40' : 'border-slate-800'"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-5 py-4 text-left"
                            @click="toggleTeacher(teacher.id)"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/15">
                                <Users class="h-5 w-5 text-sky-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-white">{{ teacher.name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ teacher.phone || 'No phone yet' }} &middot; {{ teacher.subjects.length }} subject{{ teacher.subjects.length === 1 ? '' : 's' }}
                                </p>
                            </div>
                            <ChevronUp v-if="expandedTeacherId === teacher.id" class="h-4 w-4 shrink-0 text-slate-500" />
                            <ChevronDown v-else class="h-4 w-4 shrink-0 text-slate-500" />
                        </button>

                        <div v-if="expandedTeacherId === teacher.id" class="space-y-4 border-t border-slate-800 px-5 py-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Name</label>
                                    <input
                                        v-model="teacher.name"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Phone (WhatsApp)</label>
                                    <input
                                        v-model="teacher.phone"
                                        type="text"
                                        placeholder="+8801XXXXXXXXX"
                                        class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Subjects</p>
                                <div class="mt-2 space-y-2">
                                    <div v-for="(subject, i) in teacher.subjects" :key="i" class="flex items-center gap-2">
                                        <input
                                            v-model="teacher.subjects[i]"
                                            type="text"
                                            placeholder="e.g. Mathematics"
                                            class="w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-800 text-slate-500 hover:bg-slate-800/50 hover:text-rose-400"
                                            @click="removeTeacherSubject(teacher, i)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="mt-2 flex items-center gap-1.5 rounded-lg border border-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800/50"
                                    @click="addTeacherSubject(teacher)"
                                >
                                    <Plus class="h-3.5 w-3.5" /> Add Subject
                                </button>
                            </div>
                        </div>
                    </div>

                    <p v-if="!localTeachers.length" class="rounded-xl border border-dashed border-slate-800 px-5 py-8 text-center text-sm text-slate-500">
                        Set a teacher count above and hit Confirm to get started.
                    </p>
                </div>
            </template>
        </div>
    </AppLayout>
</template>