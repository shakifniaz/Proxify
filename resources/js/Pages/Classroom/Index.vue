<script setup>
import { computed, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    BookOpenCheck,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    FileUp,
    Megaphone,
    MessageSquareText,
    Plus,
    Search,
    Send,
    Users,
} from 'lucide-vue-next';

const props = defineProps({
    classrooms: { type: Array, default: () => [] },
    role: { type: String, default: 'admin' },
    activeRoutineName: { type: String, default: null },
    currentUserName: { type: String, default: 'User' },
});

const classroomPosts = ref({});
const selectedClassId = ref(props.classrooms[0]?.id ?? null);
const selectedSubjectId = ref(props.classrooms[0]?.subjects?.[0]?.id ?? null);
const search = ref('');
const postForm = ref({
    kind: 'Update',
    title: '',
    body: '',
    due: '',
    marks: '',
});

props.classrooms.forEach((classroom) => {
    classroom.subjects?.forEach((subject) => {
        classroomPosts.value[subjectKey(classroom.id, subject.id)] = [...(subject.posts ?? [])];
    });
});

const canPost = computed(() => ['admin', 'teacher'].includes(props.role));

const filteredClassrooms = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (!needle) return props.classrooms;

    return props.classrooms.filter((classroom) =>
        [classroom.name, classroom.classTeacherName, ...(classroom.subjects ?? []).map((subject) => subject.name)]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(needle))
    );
});

const selectedClassroom = computed(() =>
    props.classrooms.find((classroom) => String(classroom.id) === String(selectedClassId.value)) ?? props.classrooms[0] ?? null
);

const selectedSubject = computed(() =>
    selectedClassroom.value?.subjects?.find((subject) => String(subject.id) === String(selectedSubjectId.value))
    ?? selectedClassroom.value?.subjects?.[0]
    ?? null
);

const activePosts = computed(() => {
    if (!selectedClassroom.value || !selectedSubject.value) return [];
    return classroomPosts.value[subjectKey(selectedClassroom.value.id, selectedSubject.value.id)] ?? [];
});

const stats = computed(() => ({
    classrooms: props.classrooms.length,
    subjects: props.classrooms.reduce((sum, classroom) => sum + (classroom.subjects?.length ?? 0), 0),
    assignments: Object.values(classroomPosts.value).flat().filter((post) => post.kind === 'Assignment').length,
}));

watch(selectedClassroom, (classroom) => {
    selectedSubjectId.value = classroom?.subjects?.[0]?.id ?? null;
});

function subjectKey(classroomId, subjectId) {
    return `${classroomId}:${subjectId}`;
}

function selectClassroom(classroom) {
    selectedClassId.value = classroom.id;
    selectedSubjectId.value = classroom.subjects?.[0]?.id ?? null;
}

function submitPost() {
    if (!canPost.value || !selectedClassroom.value || !selectedSubject.value || !postForm.value.title.trim() || !postForm.value.body.trim()) return;

    const key = subjectKey(selectedClassroom.value.id, selectedSubject.value.id);
    classroomPosts.value[key] = [
        {
            id: `${key}-${Date.now()}`,
            kind: postForm.value.kind,
            title: postForm.value.title.trim(),
            body: postForm.value.body.trim(),
            author: props.currentUserName,
            date: 'Just now',
            due: postForm.value.kind === 'Assignment' ? postForm.value.due.trim() || 'Due date not set' : null,
            marks: postForm.value.kind === 'Assignment' ? Number(postForm.value.marks) || null : null,
            fresh: true,
        },
        ...(classroomPosts.value[key] ?? []),
    ];

    postForm.value = { kind: 'Update', title: '', body: '', due: '', marks: '' };
}

function postStyle(kind) {
    return {
        Assignment: 'border-[#09B884]/45 bg-[#8BED9A]/16',
        Homework: 'border-emerald-200 bg-emerald-50',
        'Class test': 'border-amber-200 bg-amber-50',
        Update: 'border-stone-200 bg-white',
    }[kind] ?? 'border-stone-200 bg-white';
}
</script>

<template>
    <AppLayout title="Classroom">
        <div class="classroom-shell space-y-5">
            <section class="relative overflow-hidden rounded-2xl border border-[#8BED9A]/50 bg-white p-5 shadow-sm">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(139,237,154,0.24),transparent_34%),linear-gradient(135deg,rgba(9,184,132,0.08),transparent_45%)]"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#1e2924] text-[#8BED9A] shadow-lg shadow-[#1e2924]/20">
                            <BookOpenCheck class="h-6 w-6" />
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-[#1e2924]">Classroom</h2>
                            <p class="text-sm font-medium text-slate-500">{{ activeRoutineName || 'Active routine not selected' }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-[#8BED9A]/16 px-4 py-2">
                            <p class="text-lg font-black text-[#1e2924]">{{ stats.classrooms }}</p>
                            <p class="text-[10px] font-black uppercase tracking-wider text-[#1e2924]/55">Classes</p>
                        </div>
                        <div class="rounded-xl bg-[#8BED9A]/16 px-4 py-2">
                            <p class="text-lg font-black text-[#1e2924]">{{ stats.subjects }}</p>
                            <p class="text-[10px] font-black uppercase tracking-wider text-[#1e2924]/55">Subjects</p>
                        </div>
                        <div class="rounded-xl bg-[#8BED9A]/16 px-4 py-2">
                            <p class="text-lg font-black text-[#1e2924]">{{ stats.assignments }}</p>
                            <p class="text-[10px] font-black uppercase tracking-wider text-[#1e2924]/55">Tasks</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-[20rem_minmax(0,1fr)]">
                <aside class="surface-card overflow-hidden">
                    <div class="border-b border-stone-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-black text-[#1e2924]">Classrooms</p>
                            <span class="rounded-full bg-[#8BED9A]/20 px-2.5 py-1 text-xs font-black capitalize text-[#1e2924]">{{ role }}</span>
                        </div>
                        <div class="relative mt-3">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="search" type="text" class="field-control w-full pl-9" placeholder="Search classes or subjects" />
                        </div>
                    </div>

                    <div class="max-h-[35rem] space-y-2 overflow-y-auto p-3">
                        <button
                            v-for="classroom in filteredClassrooms"
                            :key="classroom.id"
                            type="button"
                            class="group w-full rounded-xl border p-3 text-left transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                            :class="String(selectedClassId) === String(classroom.id) ? 'border-[#09B884]/55 bg-[#8BED9A]/18 shadow-sm' : 'border-stone-200 bg-white hover:border-[#8BED9A]/60'"
                            @click="selectClassroom(classroom)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-950">{{ classroom.name }}</p>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ classroom.classTeacherName }}</p>
                                </div>
                                <span class="rounded-lg bg-white px-2 py-1 text-xs font-black text-[#09B884] shadow-sm">{{ classroom.subjects?.length ?? 0 }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span v-for="subject in classroom.subjects?.slice(0, 3)" :key="subject.id" class="rounded-full bg-white/80 px-2 py-1 text-[10px] font-bold text-slate-600">
                                    {{ subject.name }}
                                </span>
                            </div>
                        </button>

                        <p v-if="!filteredClassrooms.length" class="rounded-xl border border-dashed border-stone-300 py-10 text-center text-sm font-medium text-slate-500">
                            No classrooms found.
                        </p>
                    </div>
                </aside>

                <main v-if="selectedClassroom" class="min-w-0 space-y-5">
                    <section class="relative overflow-hidden rounded-2xl border border-[#8BED9A]/45 bg-[#1e2924] p-5 text-white shadow-lg shadow-[#1e2924]/10">
                        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_85%_10%,rgba(139,237,154,0.22),transparent_30%)]"></div>
                        <div class="relative flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.24em] text-[#8BED9A]">Active classroom</p>
                                <h3 class="mt-2 text-2xl font-black">{{ selectedClassroom.name }}</h3>
                                <p class="mt-1 text-sm font-semibold text-white/65">Class teacher: {{ selectedClassroom.classTeacherName }}</p>
                            </div>
                            <div class="flex gap-2">
                                <span class="rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-xs font-bold text-white/80">{{ selectedClassroom.access }}</span>
                                <span class="rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-xs font-bold text-white/80">{{ selectedClassroom.subjects?.length ?? 0 }} subjects</span>
                            </div>
                        </div>
                    </section>

                    <section class="surface-card overflow-hidden">
                        <div class="overflow-x-auto border-b border-stone-200 bg-stone-50/70 p-2">
                            <div class="flex min-w-max gap-2">
                                <button
                                    v-for="subject in selectedClassroom.subjects"
                                    :key="subject.id"
                                    type="button"
                                    class="group relative min-h-11 rounded-xl border px-4 text-sm font-black transition-all duration-300"
                                    :class="String(selectedSubjectId) === String(subject.id) ? 'border-[#09B884]/60 bg-white text-[#1e2924] shadow-sm' : 'border-transparent text-slate-500 hover:bg-white hover:text-[#1e2924]'"
                                    @click="selectedSubjectId = subject.id"
                                >
                                    <span class="mr-2 inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: subject.accent }"></span>
                                    {{ subject.name }}
                                </button>
                            </div>
                        </div>

                        <div v-if="selectedSubject" class="grid gap-px bg-stone-200 xl:grid-cols-[minmax(0,1fr)_24rem]">
                            <div class="min-w-0 bg-white p-4 sm:p-5">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-base font-black text-[#1e2924]">{{ selectedSubject.name }}</p>
                                        <p class="text-sm font-semibold text-slate-500">{{ selectedSubject.teacherName }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 rounded-xl bg-[#8BED9A]/16 px-3 py-2 text-xs font-black text-[#1e2924]">
                                        <MessageSquareText class="h-4 w-4 text-[#09B884]" />
                                        {{ activePosts.length }} posts
                                    </div>
                                </div>

                                <TransitionGroup name="post-list" tag="div" class="mt-4 space-y-3">
                                    <article
                                        v-for="post in activePosts"
                                        :key="post.id"
                                        class="rounded-xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                        :class="postStyle(post.kind)"
                                    >
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-[11px] font-black uppercase tracking-wide text-[#1e2924] shadow-sm">
                                                        <ClipboardList v-if="post.kind === 'Assignment'" class="h-3.5 w-3.5 text-[#09B884]" />
                                                        <Megaphone v-else class="h-3.5 w-3.5 text-[#09B884]" />
                                                        {{ post.kind }}
                                                    </span>
                                                    <span v-if="post.fresh" class="rounded-full bg-[#1e2924] px-2.5 py-1 text-[11px] font-black uppercase tracking-wide text-[#8BED9A]">New</span>
                                                </div>
                                                <h4 class="mt-3 text-lg font-black text-slate-950">{{ post.title }}</h4>
                                                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ post.body }}</p>
                                            </div>
                                            <div v-if="post.marks || post.due" class="grid min-w-36 gap-2">
                                                <span v-if="post.due" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 shadow-sm">
                                                    <CalendarClock class="h-3.5 w-3.5 text-[#09B884]" />
                                                    {{ post.due }}
                                                </span>
                                                <span v-if="post.marks" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 shadow-sm">
                                                    {{ post.marks }} marks
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-white/70 pt-3 text-xs font-semibold text-slate-500">
                                            <span>Posted by <strong class="text-slate-700">{{ post.author }}</strong> - {{ post.date }}</span>
                                            <span v-if="post.kind === 'Assignment' && role === 'student'" class="inline-flex items-center gap-1 text-[#09B884]">
                                                <FileUp class="h-3.5 w-3.5" />
                                                Submission open
                                            </span>
                                            <span v-else-if="post.kind === 'Assignment'" class="inline-flex items-center gap-1 text-[#09B884]">
                                                <Users class="h-3.5 w-3.5" />
                                                24/31 submitted
                                            </span>
                                        </div>
                                    </article>
                                </TransitionGroup>
                            </div>

                            <aside class="bg-white p-4 sm:p-5">
                                <div v-if="canPost" class="sticky top-5 rounded-xl border border-[#8BED9A]/55 bg-white p-4 shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#8BED9A]/18 text-[#09B884]">
                                            <Send class="h-4 w-4" />
                                        </div>
                                        <p class="text-sm font-black text-[#1e2924]">Create classroom post</p>
                                    </div>

                                    <div class="mt-4 space-y-3">
                                        <div class="grid grid-cols-2 gap-2">
                                            <button
                                                v-for="kind in ['Update', 'Homework', 'Class test', 'Assignment']"
                                                :key="kind"
                                                type="button"
                                                class="rounded-lg border px-3 py-2 text-xs font-black transition"
                                                :class="postForm.kind === kind ? 'border-[#09B884]/70 bg-[#8BED9A]/20 text-[#1e2924]' : 'border-stone-200 bg-white text-slate-500 hover:border-[#09B884]/45'"
                                                @click="postForm.kind = kind"
                                            >
                                                {{ kind }}
                                            </button>
                                        </div>
                                        <input v-model="postForm.title" type="text" class="field-control w-full" placeholder="Post title" />
                                        <textarea v-model="postForm.body" rows="5" class="field-control w-full resize-none" placeholder="Write the update, homework, test details, or assignment instructions"></textarea>
                                        <div v-if="postForm.kind === 'Assignment'" class="grid grid-cols-2 gap-2">
                                            <input v-model="postForm.due" type="text" class="field-control w-full" placeholder="Due date" />
                                            <input v-model="postForm.marks" type="number" min="0" class="field-control w-full" placeholder="Marks" />
                                        </div>
                                        <button type="button" class="btn-primary min-h-11 w-full" :disabled="!postForm.title.trim() || !postForm.body.trim()" @click="submitPost">
                                            <Plus class="h-4 w-4" />
                                            Post to {{ selectedSubject.name }}
                                        </button>
                                    </div>
                                </div>

                                <div v-else class="rounded-xl border border-[#8BED9A]/55 bg-[#8BED9A]/14 p-4">
                                    <div class="flex items-center gap-3">
                                        <CheckCircle2 class="h-5 w-5 text-[#09B884]" />
                                        <p class="text-sm font-black text-[#1e2924]">Student view</p>
                                    </div>
                                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                        Assignments, homework, syllabus updates, and class test notices from your teachers will appear here by subject.
                                    </p>
                                </div>
                            </aside>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.classroom-shell {
    animation: classroom-rise 420ms ease-out both;
}

.post-list-enter-active,
.post-list-leave-active {
    transition: all 260ms ease;
}

.post-list-enter-from {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
}

.post-list-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}

@keyframes classroom-rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
