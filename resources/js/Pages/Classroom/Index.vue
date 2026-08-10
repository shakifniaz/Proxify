<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { getApps, initializeApp } from 'firebase/app';
import { getAuth, signInAnonymously } from 'firebase/auth';
import {
    addDoc,
    collection,
    deleteDoc,
    doc,
    getFirestore,
    initializeFirestore,
    onSnapshot,
    serverTimestamp,
    setDoc,
    where,
    query,
} from 'firebase/firestore';
import {
    getDownloadURL,
    getStorage,
    ref as storageRef,
    uploadBytes,
} from 'firebase/storage';
import {
    BookOpenCheck,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    FileText,
    FileUp,
    GraduationCap,
    Image,
    Loader2,
    Maximize2,
    Megaphone,
    NotebookPen,
    Paperclip,
    Plus,
    Search,
    Send,
    Timer,
    UploadCloud,
    Users,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    classrooms: { type: Array, default: () => [] },
    role: { type: String, default: 'admin' },
    activeRoutineName: { type: String, default: null },
    currentUser: { type: Object, default: () => ({}) },
    firebaseConfig: { type: Object, default: () => ({}) },
});

const firebaseReady = computed(() =>
    ['apiKey', 'authDomain', 'projectId', 'storageBucket', 'appId'].every((key) => Boolean(props.firebaseConfig?.[key]))
);
const institutionKey = computed(() => String(props.currentUser.institutionId ?? 'global'));
const userKey = computed(() => `${props.role}-${props.currentUser.id ?? 'guest'}`);
const roleLabel = computed(() => props.role === 'admin' ? 'Admin' : props.role === 'teacher' ? 'Teacher' : 'Student');

const selectedClassId = ref(props.classrooms[0]?.id ?? null);
const selectedSubjectId = ref(props.classrooms[0]?.subjects?.[0]?.id ?? null);
const search = ref('');
const posts = ref([]);
const submissions = ref({});
const comments = ref({});
const loading = ref(false);
const saving = ref(false);
const firebaseError = ref('');
const imageViewer = ref(null);
const postUploadInput = ref(null);
const submissionUploadInput = ref(null);
const pendingPostFiles = ref([]);
const pendingSubmissionFiles = ref([]);
const selectedPostId = ref(null);
const streamEl = ref(null);
const postForm = ref({
    type: 'Update',
    title: '',
    body: '',
    classTestDate: '',
    deadlineDate: '',
    deadlineTime: '',
    marks: '',
});
const submissionForm = ref({ note: '', link: '' });
const commentForm = ref('');
let firebase = null;
let db = null;
let storage = null;
let postsUnsubscribe = null;
const submissionUnsubscribers = new Map();
const commentUnsubscribers = new Map();

const postTypes = [
    { value: 'Update', label: 'Update', icon: Megaphone },
    { value: 'Homework', label: 'Homework', icon: NotebookPen },
    { value: 'Class test', label: 'Class test', icon: Timer },
    { value: 'Assignment', label: 'Assignment', icon: ClipboardList },
];

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
    props.classrooms.find((classroom) => String(classroom.id) === String(selectedClassId.value))
    ?? filteredClassrooms.value[0]
    ?? props.classrooms[0]
    ?? null
);

const selectedSubject = computed(() =>
    selectedClassroom.value?.subjects?.find((subject) => String(subject.id) === String(selectedSubjectId.value))
    ?? selectedClassroom.value?.subjects?.[0]
    ?? null
);
const selectedPost = computed(() =>
    sortedPosts.value.find((post) => String(post.id) === String(selectedPostId.value)) ?? null
);
const currentStudentSubmission = computed(() => {
    if (!selectedPost.value || props.role !== 'student') return null;

    return (submissions.value[selectedPost.value.id] ?? [])
        .find((submission) => String(submission.studentId) === String(userKey.value)) ?? null;
});

const canPost = computed(() => Boolean(selectedSubject.value?.canPost));
const canSubmit = computed(() => props.role === 'student');
const classroomSubjectKey = computed(() =>
    selectedClassroom.value && selectedSubject.value
        ? `${selectedClassroom.value.id}:${selectedSubject.value.id}`
        : ''
);
const sortedPosts = computed(() =>
    [...posts.value].sort((a, b) => Number(b.createdAt?.seconds ?? b.localCreatedAt ?? 0) - Number(a.createdAt?.seconds ?? a.localCreatedAt ?? 0))
);
const assignmentCount = computed(() => sortedPosts.value.filter((post) => post.type === 'Assignment').length);
const stats = computed(() => ({
    classes: props.classrooms.length,
    subjects: props.classrooms.reduce((sum, classroom) => sum + (classroom.subjects?.length ?? 0), 0),
    posts: sortedPosts.value.length,
}));

watch(selectedClassroom, (classroom) => {
    if (!classroom) return;
    const subjectExists = classroom.subjects?.some((subject) => String(subject.id) === String(selectedSubjectId.value));
    if (!subjectExists) selectedSubjectId.value = classroom.subjects?.[0]?.id ?? null;
});

watch(classroomSubjectKey, (key) => {
    subscribePosts(key);
}, { immediate: false });

watch(posts, () => {
    nextTick(() => {
        if (streamEl.value) streamEl.value.scrollTop = 0;
    });
});

onMounted(async () => {
    if (!firebaseReady.value) return;
    loading.value = true;
    try {
        firebase = await loadFirebase();
        const app = firebase.getApps().length ? firebase.getApps()[0] : firebase.initializeApp(props.firebaseConfig);
        db = getOrCreateFirestore(app);
        storage = firebase.getStorage(app);
        const auth = firebase.getAuth(app);
        if (!auth.currentUser) await withTimeout(firebase.signInAnonymously(auth), 'Firebase sign-in took too long.');
        subscribePosts(classroomSubjectKey.value);
    } catch (error) {
        firebaseError.value = error?.message ?? 'Firebase could not be initialized.';
    } finally {
        loading.value = false;
    }
});

onUnmounted(() => {
    postsUnsubscribe?.();
    submissionUnsubscribers.forEach((unsubscribe) => unsubscribe?.());
    commentUnsubscribers.forEach((unsubscribe) => unsubscribe?.());
});

async function loadFirebase() {
    return {
        addDoc,
        collection,
        deleteDoc,
        doc,
        getApps,
        getAuth,
        getDownloadURL,
        getFirestore,
        getStorage,
        initializeApp,
        initializeFirestore,
        onSnapshot,
        query,
        ref: storageRef,
        serverTimestamp,
        setDoc,
        signInAnonymously,
        uploadBytes,
        where,
    };
}

function getOrCreateFirestore(app) {
    try {
        return firebase.initializeFirestore(app, {
            experimentalAutoDetectLongPolling: true,
            useFetchStreams: false,
        });
    } catch {
        return firebase.getFirestore(app);
    }
}

function postsCollection() {
    return firebase.collection(db, 'institutions', institutionKey.value, 'classroomPosts');
}

function submissionsCollection(postId) {
    return firebase.collection(db, 'institutions', institutionKey.value, 'classroomPosts', postId, 'submissions');
}

function commentsCollection(postId) {
    return firebase.collection(db, 'institutions', institutionKey.value, 'classroomPosts', postId, 'comments');
}

function subscribePosts(key) {
    if (!key || !db) return;
    postsUnsubscribe?.();
    submissionUnsubscribers.forEach((unsubscribe) => unsubscribe?.());
    commentUnsubscribers.forEach((unsubscribe) => unsubscribe?.());
    submissionUnsubscribers.clear();
    commentUnsubscribers.clear();
    posts.value = [];
    submissions.value = {};
    comments.value = {};
    selectedPostId.value = null;

    const queryRef = firebase.query(postsCollection(), firebase.where('classroomSubjectKey', '==', key));
    postsUnsubscribe = firebase.onSnapshot(queryRef, (snapshot) => {
        posts.value = snapshot.docs.map((postDoc) => ({
            id: postDoc.id,
            ...postDoc.data(),
        }));
        posts.value.forEach((post) => {
            if (post.type === 'Assignment') subscribeSubmissions(post.id);
            subscribeComments(post.id);
        });
    }, (error) => {
        firebaseError.value = `Classroom posts could not be loaded: ${error?.message ?? 'Permission denied.'}`;
    });
}

function subscribeComments(postId) {
    if (commentUnsubscribers.has(postId)) return;

    const unsubscribe = firebase.onSnapshot(commentsCollection(postId), (snapshot) => {
        comments.value = {
            ...comments.value,
            [postId]: snapshot.docs.map((commentDoc) => ({
                id: commentDoc.id,
                ...commentDoc.data(),
            })).sort((a, b) => Number(a.createdAt?.seconds ?? 0) - Number(b.createdAt?.seconds ?? 0)),
        };
    }, (error) => {
        firebaseError.value = `Comments could not be loaded: ${error?.message ?? 'Permission denied.'}`;
    });
    commentUnsubscribers.set(postId, unsubscribe);
}

function subscribeSubmissions(postId) {
    if (submissionUnsubscribers.has(postId)) return;

    const unsubscribe = firebase.onSnapshot(submissionsCollection(postId), (snapshot) => {
        submissions.value = {
            ...submissions.value,
            [postId]: snapshot.docs.map((submissionDoc) => ({
                id: submissionDoc.id,
                ...submissionDoc.data(),
            })).sort((a, b) => Number(b.createdAt?.seconds ?? 0) - Number(a.createdAt?.seconds ?? 0)),
        };
    }, (error) => {
        firebaseError.value = `Assignment submissions could not be loaded: ${error?.message ?? 'Permission denied.'}`;
    });
    submissionUnsubscribers.set(postId, unsubscribe);
}

function selectClassroom(classroom) {
    selectedClassId.value = classroom.id;
    selectedSubjectId.value = classroom.subjects?.[0]?.id ?? null;
}

async function submitPost() {
    if (!canPost.value || !selectedClassroom.value || !selectedSubject.value || !postForm.value.title.trim() || saving.value) return;

    saving.value = true;
    firebaseError.value = '';
    try {
        const attachments = await uploadFiles(pendingPostFiles.value, 'posts');
        const assignmentDeadline = postForm.value.type === 'Assignment'
            ? [postForm.value.deadlineDate, postForm.value.deadlineTime].filter(Boolean).join('T')
            : '';

        await firebase.addDoc(postsCollection(), {
            classroomSubjectKey: classroomSubjectKey.value,
            classroomId: String(selectedClassroom.value.id),
            classroomName: selectedClassroom.value.name,
            subjectId: String(selectedSubject.value.id),
            subjectName: selectedSubject.value.name,
            subjectTeacherName: selectedSubject.value.teacherName,
            type: postForm.value.type,
            title: postForm.value.title.trim(),
            body: postForm.value.body.trim(),
            classTestDate: postForm.value.type === 'Class test' ? postForm.value.classTestDate : '',
            deadline: assignmentDeadline,
            deadlineDate: postForm.value.type === 'Assignment' ? postForm.value.deadlineDate : '',
            deadlineTime: postForm.value.type === 'Assignment' ? postForm.value.deadlineTime : '',
            marks: postForm.value.type === 'Assignment' ? Number(postForm.value.marks) || null : null,
            attachments,
            authorId: userKey.value,
            authorName: props.currentUser.name ?? 'User',
            authorRole: roleLabel.value,
            createdAt: firebase.serverTimestamp(),
            localCreatedAt: Date.now(),
        });
        postForm.value = { type: 'Update', title: '', body: '', classTestDate: '', deadlineDate: '', deadlineTime: '', marks: '' };
        pendingPostFiles.value = [];
    } catch (error) {
        firebaseError.value = friendlyFirebaseError(error, 'Post could not be saved.');
    } finally {
        saving.value = false;
    }
}

async function submitAssignment(post) {
    if (!canSubmit.value || !post || (!submissionForm.value.note.trim() && !submissionForm.value.link.trim() && !pendingSubmissionFiles.value.length) || saving.value) return;

    saving.value = true;
    firebaseError.value = '';
    try {
        const attachments = await uploadFiles(pendingSubmissionFiles.value, `submissions/${post.id}`);
        await firebase.setDoc(firebase.doc(submissionsCollection(post.id), userKey.value), {
            studentId: userKey.value,
            studentName: props.currentUser.name ?? 'Student',
            className: selectedClassroom.value?.name ?? '',
            note: submissionForm.value.note.trim(),
            link: submissionForm.value.link.trim(),
            attachments,
            createdAt: firebase.serverTimestamp(),
        }, { merge: false });
        submissionForm.value = { note: '', link: '' };
        pendingSubmissionFiles.value = [];
    } catch (error) {
        firebaseError.value = friendlyFirebaseError(error, 'Submission could not be saved.');
    } finally {
        saving.value = false;
    }
}

async function unsubmitAssignment(post) {
    const submission = currentStudentSubmission.value;
    if (!canSubmit.value || !post || !submission || saving.value) return;

    saving.value = true;
    firebaseError.value = '';
    try {
        await firebase.deleteDoc(firebase.doc(submissionsCollection(post.id), submission.id));
        submissionForm.value = { note: submission.note ?? '', link: submission.link ?? '' };
        pendingSubmissionFiles.value = [];
    } catch (error) {
        firebaseError.value = friendlyFirebaseError(error, 'Submission could not be removed.');
    } finally {
        saving.value = false;
    }
}

async function submitComment(post) {
    if (!post || !commentForm.value.trim() || saving.value) return;

    saving.value = true;
    firebaseError.value = '';
    try {
        await firebase.addDoc(commentsCollection(post.id), {
            body: commentForm.value.trim(),
            authorId: userKey.value,
            authorName: props.currentUser.name ?? 'User',
            authorRole: roleLabel.value,
            createdAt: firebase.serverTimestamp(),
        });
        commentForm.value = '';
    } catch (error) {
        firebaseError.value = friendlyFirebaseError(error, 'Comment could not be saved.');
    } finally {
        saving.value = false;
    }
}

async function uploadFiles(files, folder) {
    const uploads = files.map(async (file, index) => {
        const cleanName = file.name.replace(/[^\w.\-() ]+/g, '_');
        const path = `institutions/${institutionKey.value}/classroom/${selectedClassroom.value.id}/${selectedSubject.value.id}/${folder}/${Date.now()}-${index}-${cleanName}`;
        const ref = firebase.ref(storage, path);
        await withTimeout(firebase.uploadBytes(ref, file, {
            contentType: file.type || 'application/octet-stream',
            customMetadata: {
                userId: userKey.value,
                userName: props.currentUser.name ?? 'User',
                classroomId: String(selectedClassroom.value.id),
                subjectId: String(selectedSubject.value.id),
            },
        }), `Upload timed out for ${file.name}.`);
        return {
            name: file.name,
            type: file.type,
            size: file.size,
            url: await firebase.getDownloadURL(ref),
            path,
        };
    });
    return Promise.all(uploads);
}

function addFiles(event, target) {
    firebaseError.value = '';
    const selectedFiles = Array.from(event.target.files ?? []);
    const maxSize = 20 * 1024 * 1024;
    const accepted = selectedFiles.filter((file) => file.size <= maxSize);
    const rejected = selectedFiles.length - accepted.length;
    if (rejected) firebaseError.value = `${rejected} file${rejected === 1 ? '' : 's'} skipped. Each file must be 20 MB or smaller.`;

    if (target === 'post') {
        pendingPostFiles.value = [...pendingPostFiles.value, ...accepted].slice(0, 8);
    } else {
        pendingSubmissionFiles.value = [...pendingSubmissionFiles.value, ...accepted].slice(0, 8);
    }
    event.target.value = '';
}

function removeFile(target, index) {
    if (target === 'post') {
        pendingPostFiles.value = pendingPostFiles.value.filter((_, fileIndex) => fileIndex !== index);
    } else {
        pendingSubmissionFiles.value = pendingSubmissionFiles.value.filter((_, fileIndex) => fileIndex !== index);
    }
}

function withTimeout(promise, message, timeout = 30000) {
    return Promise.race([
        promise,
        new Promise((_, reject) => window.setTimeout(() => reject(new Error(message)), timeout)),
    ]);
}

function friendlyFirebaseError(error, fallback) {
    const message = error?.message ?? fallback;
    const lower = message.toLowerCase();
    if (lower.includes('permission')) return 'Firebase rules blocked this classroom action. Allow authenticated reads and writes for classroomPosts and classroom storage paths.';
    if (lower.includes('bucket')) return 'Firebase Storage bucket was not found. Check FIREBASE_STORAGE_BUCKET in .env.';
    return message;
}

function typeStyle(type) {
    return {
        Update: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        Homework: 'border-sky-200 bg-sky-50 text-sky-800',
        'Class test': 'border-amber-200 bg-amber-50 text-amber-800',
        Assignment: 'border-violet-200 bg-violet-50 text-violet-800',
    }[type] ?? 'border-stone-200 bg-stone-50 text-stone-700';
}

function cardWash(type) {
    return {
        Update: 'from-emerald-50 to-white',
        Homework: 'from-sky-50 to-white',
        'Class test': 'from-amber-50 to-white',
        Assignment: 'from-violet-50 to-white',
    }[type] ?? 'from-stone-50 to-white';
}

function formatDate(value) {
    if (!value) return '';
    const date = value?.toDate?.() ?? new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString([], { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(value) {
    if (!value) return '';
    const date = value?.toDate?.() ?? new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString([], { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatTime(value) {
    const date = value?.toDate?.();
    return date ? date.toLocaleString([], { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'Just now';
}

function fileSize(size) {
    if (!size) return '';
    if (size < 1024 * 1024) return `${Math.ceil(size / 1024)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function postIcon(type) {
    return postTypes.find((item) => item.value === type)?.icon ?? Megaphone;
}

function openImageViewer(file) {
    imageViewer.value = file;
}

function openPost(post) {
    selectedPostId.value = post.id;
    submissionForm.value = { note: '', link: '' };
    pendingSubmissionFiles.value = [];
    commentForm.value = '';
}

function closePost() {
    selectedPostId.value = null;
    submissionForm.value = { note: '', link: '' };
    pendingSubmissionFiles.value = [];
    commentForm.value = '';
}
</script>

<template>
    <AppLayout title="Classroom" flush>
        <div class="classroom-shell flex h-[calc(100vh-4rem)] min-h-0 bg-[#f7faf8]">
            <section v-if="!firebaseReady" class="m-6 w-full rounded-2xl border border-amber-200 bg-amber-50 p-6">
                <p class="text-lg font-black text-[#1e2924]">Firebase is not configured</p>
                <p class="mt-2 text-sm font-semibold text-amber-800">Add your Firebase web app keys in `.env` to enable classroom posts, uploads, and submissions.</p>
            </section>

            <section v-else-if="!classrooms.length" class="m-6 w-full rounded-2xl border border-stone-200 bg-white p-10 text-center shadow-sm">
                <BookOpenCheck class="mx-auto h-10 w-10 text-[#09B884]" />
                <p class="mt-4 text-xl font-black text-[#1e2924]">No classroom is available</p>
                <p class="mt-2 text-sm font-semibold text-slate-500">Create an active routine first so classrooms can be built from class and subject assignments.</p>
            </section>

            <section
                v-else
                class="grid min-h-0 w-full overflow-hidden"
                :class="role === 'student' ? 'grid-cols-1' : 'lg:grid-cols-[18rem_minmax(0,1fr)] xl:grid-cols-[19rem_minmax(0,1fr)_22rem]'"
            >
                <aside v-if="role !== 'student'" class="flex min-h-0 flex-col border-r border-stone-200 bg-white">
                    <div class="border-b border-stone-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full bg-[#8BED9A]/18 px-3 py-1 text-xs font-black uppercase tracking-wide text-[#1e2924]">{{ roleLabel }}</span>
                            <span class="text-xs font-black text-slate-400">{{ stats.classes }} classes</span>
                        </div>
                        <div class="relative mt-3">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="search" class="field-control w-full pl-9" placeholder="Search classes" />
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
                        <button
                            v-for="classroom in filteredClassrooms"
                            :key="classroom.id"
                            type="button"
                            class="group w-full rounded-2xl border p-3 text-left transition-all duration-300"
                            :class="String(selectedClassId) === String(classroom.id) ? 'border-[#09B884]/60 bg-[#8BED9A]/16 shadow-sm' : 'border-stone-200 bg-white hover:border-[#8BED9A]/70 hover:bg-[#fbfffb]'"
                            @click="selectClassroom(classroom)"
                        >
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-white text-sm font-black text-[#1e2924] shadow-sm">
                                    {{ classroom.className?.slice(0, 2) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-black text-[#1e2924]">{{ classroom.name }}</span>
                                    <span class="mt-1 block truncate text-xs font-semibold text-slate-500">{{ classroom.classTeacherName }}</span>
                                </span>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs font-black text-slate-500">
                                <span>{{ classroom.subjects?.length ?? 0 }} subjects</span>
                                <span>{{ classroom.access }}</span>
                            </div>
                        </button>
                    </div>
                </aside>

                <main class="flex min-h-0 min-w-0 flex-col">
                    <header class="border-b border-stone-200 bg-white">
                        <div class="relative overflow-hidden px-5 py-5">
                            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_85%_0%,rgba(139,237,154,0.28),transparent_32%)]"></div>
                            <div class="relative flex flex-wrap items-end justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-xs font-black uppercase tracking-[0.24em] text-[#09B884]">{{ activeRoutineName || 'Active routine' }}</p>
                                    <h2 class="mt-1 truncate text-2xl font-black text-[#1e2924]">{{ selectedClassroom?.name }}</h2>
                                    <p class="mt-1 text-sm font-semibold text-slate-500">Class teacher: {{ selectedClassroom?.classTeacherName }}</p>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-xl bg-white px-4 py-2 shadow-sm ring-1 ring-stone-200">
                                        <p class="text-base font-black text-[#1e2924]">{{ selectedClassroom?.subjects?.length ?? 0 }}</p>
                                        <p class="text-[10px] font-black uppercase text-slate-400">Subjects</p>
                                    </div>
                                    <div class="rounded-xl bg-white px-4 py-2 shadow-sm ring-1 ring-stone-200">
                                        <p class="text-base font-black text-[#1e2924]">{{ stats.posts }}</p>
                                        <p class="text-[10px] font-black uppercase text-slate-400">Posts</p>
                                    </div>
                                    <div class="rounded-xl bg-white px-4 py-2 shadow-sm ring-1 ring-stone-200">
                                        <p class="text-base font-black text-[#1e2924]">{{ assignmentCount }}</p>
                                        <p class="text-[10px] font-black uppercase text-slate-400">Tasks</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto px-4 pb-3">
                            <div class="flex min-w-max gap-2">
                                <button
                                    v-for="subject in selectedClassroom?.subjects"
                                    :key="subject.id"
                                    type="button"
                                    class="relative rounded-2xl border px-4 py-3 text-left transition-all duration-300"
                                    :class="String(selectedSubjectId) === String(subject.id) ? 'border-[#09B884]/70 bg-[#1e2924] text-white shadow-lg shadow-[#1e2924]/10' : 'border-stone-200 bg-white text-[#1e2924] hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/10'"
                                    @click="selectedSubjectId = subject.id"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: subject.accent }"></span>
                                        <span class="text-sm font-black">{{ subject.name }}</span>
                                    </span>
                                    <span class="mt-1 block text-xs font-semibold" :class="String(selectedSubjectId) === String(subject.id) ? 'text-white/60' : 'text-slate-500'">{{ subject.teacherName }}</span>
                                </button>
                            </div>
                        </div>
                    </header>

                    <div ref="streamEl" class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                        <div v-if="firebaseError" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ firebaseError }}</div>
                        <div v-if="loading" class="rounded-2xl border border-stone-200 bg-white p-8 text-center text-sm font-black text-slate-500">
                            <Loader2 class="mx-auto mb-2 h-5 w-5 animate-spin text-[#09B884]" />
                            Loading classroom
                        </div>

                        <div v-if="canPost" class="mb-4 rounded-2xl border border-[#8BED9A]/70 bg-white p-4 shadow-sm xl:hidden">
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <button
                                    v-for="type in postTypes"
                                    :key="type.value"
                                    type="button"
                                    class="rounded-xl border px-3 py-2 text-xs font-black transition"
                                    :class="postForm.type === type.value ? 'border-[#09B884]/70 bg-[#1e2924] text-white' : 'border-stone-200 bg-white text-slate-600 hover:border-[#8BED9A]/70'"
                                    @click="postForm.type = type.value"
                                >
                                    {{ type.label }}
                                </button>
                            </div>
                            <input v-model="postForm.title" class="field-control mt-3 w-full" placeholder="Title" />
                            <textarea v-model="postForm.body" rows="4" class="field-control mt-3 w-full resize-none" placeholder="Write instructions or announcement"></textarea>
                            <input v-if="postForm.type === 'Class test'" v-model="postForm.classTestDate" type="date" class="field-control mt-3 w-full" />
                            <div v-if="postForm.type === 'Assignment'" class="mt-3 grid gap-2 sm:grid-cols-3">
                                <label class="block">
                                    <span class="section-title">Deadline date</span>
                                    <input v-model="postForm.deadlineDate" type="date" class="field-control mt-1 w-full" />
                                </label>
                                <label class="block">
                                    <span class="section-title">Deadline time</span>
                                    <input v-model="postForm.deadlineTime" type="time" class="field-control mt-1 w-full" />
                                </label>
                                <label class="block">
                                    <span class="section-title">Marks</span>
                                    <input v-model="postForm.marks" type="number" min="0" class="field-control mt-1 w-full" placeholder="Marks" />
                                </label>
                            </div>
                            <div v-if="pendingPostFiles.length" class="mt-3 flex flex-wrap gap-2">
                                <span v-for="(file, index) in pendingPostFiles" :key="`${file.name}-${index}`" class="inline-flex items-center gap-2 rounded-xl border border-stone-200 bg-stone-50 px-3 py-2 text-xs font-bold text-[#1e2924]">
                                    {{ file.name }}
                                    <button type="button" @click="removeFile('post', index)"><X class="h-3.5 w-3.5" /></button>
                                </span>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <button type="button" class="btn-secondary min-h-10" @click="postUploadInput?.click()">
                                    <Paperclip class="h-4 w-4" />
                                    Attach
                                </button>
                                <button type="button" class="btn-primary min-h-10 flex-1 justify-center" :disabled="saving || !postForm.title.trim()" @click="submitPost">
                                    <Plus class="h-4 w-4" />
                                    Publish
                                </button>
                            </div>
                        </div>

                        <TransitionGroup name="post-list" tag="div" class="space-y-4">
                            <article
                                v-for="post in sortedPosts"
                                :key="post.id"
                                class="flex h-[17.5rem] cursor-pointer flex-col overflow-hidden rounded-2xl border border-stone-200 bg-gradient-to-br shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-[#8BED9A]/80 hover:shadow-md"
                                :class="cardWash(post.type)"
                                @click="openPost(post)"
                            >
                                <div class="flex h-full flex-col p-5">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-black" :class="typeStyle(post.type)">
                                                    <component :is="postIcon(post.type)" class="h-3.5 w-3.5" />
                                                    {{ post.type }}
                                                </span>
                                                <span class="text-xs font-bold text-slate-500">{{ formatTime(post.createdAt) }}</span>
                                            </div>
                                            <h3 class="mt-3 line-clamp-1 text-xl font-black text-[#1e2924]">{{ post.title }}</h3>
                                            <p class="mt-2 line-clamp-3 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ post.body || 'Open this classroom post for details.' }}</p>
                                        </div>
                                        <div v-if="post.classTestDate || post.deadline || post.marks" class="grid min-w-36 gap-2">
                                            <span v-if="post.classTestDate" class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-xs font-black text-amber-800 shadow-sm">
                                                <CalendarClock class="h-4 w-4" />
                                                Test: {{ formatDate(post.classTestDate) }}
                                            </span>
                                            <span v-if="post.deadline" class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-xs font-black text-violet-800 shadow-sm">
                                                <CalendarClock class="h-4 w-4" />
                                                Due: {{ formatDateTime(post.deadline) }}
                                            </span>
                                            <span v-if="post.marks" class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-xs font-black text-[#1e2924] shadow-sm">
                                                {{ post.marks }} marks
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-auto">
                                        <div v-if="post.attachments?.length" class="mb-4 flex flex-wrap gap-2">
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-black text-[#1e2924] shadow-sm">
                                                <Paperclip class="h-3.5 w-3.5 text-[#09B884]" />
                                                {{ post.attachments.length }} attachment{{ post.attachments.length === 1 ? '' : 's' }}
                                            </span>
                                            <button
                                                v-for="file in post.attachments.filter((attachment) => attachment.type?.startsWith('image/')).slice(0, 2)"
                                                :key="file.url"
                                                type="button"
                                                class="overflow-hidden rounded-lg border border-white bg-white shadow-sm transition hover:scale-[1.03]"
                                                @click.stop="openImageViewer(file)"
                                            >
                                                <img :src="file.url" :alt="file.name" class="h-9 w-12 object-cover" loading="lazy" />
                                            </button>
                                        </div>

                                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/80 pt-4">
                                            <p class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-500">Posted by <strong class="text-[#1e2924]">{{ post.authorName }}</strong> for {{ post.subjectName }}</p>
                                            <div class="flex items-center gap-2">
                                                <span v-if="comments[post.id]?.length" class="rounded-full bg-white px-3 py-1 text-xs font-black text-[#1e2924] shadow-sm">
                                                    {{ comments[post.id]?.length }} comments
                                                </span>
                                                <span v-if="post.type === 'Assignment'" class="rounded-full bg-white px-3 py-1 text-xs font-black text-[#1e2924] shadow-sm">
                                                    {{ role === 'student' ? 'Submit' : `${submissions[post.id]?.length ?? 0} submitted` }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </TransitionGroup>

                        <div v-if="!loading && !sortedPosts.length" class="rounded-3xl border border-dashed border-stone-300 bg-white p-10 text-center">
                            <GraduationCap class="mx-auto h-12 w-12 text-[#09B884]" />
                            <p class="mt-4 text-xl font-black text-[#1e2924]">No posts in this subject yet</p>
                            <p class="mt-2 text-sm font-semibold text-slate-500">Updates, homework, tests, and assignments will appear here.</p>
                        </div>
                    </div>
                </main>

                <aside v-if="role !== 'student'" class="hidden min-h-0 flex-col border-l border-stone-200 bg-white xl:flex">
                    <div class="border-b border-stone-200 p-5">
                        <p class="text-sm font-black text-[#1e2924]">{{ canPost ? 'Create post' : 'Classwork' }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ selectedSubject?.name }} - {{ selectedClassroom?.name }}</p>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-5">
                        <div v-if="canPost" class="rounded-2xl border border-[#8BED9A]/70 bg-[#fbfffb] p-4 shadow-sm">
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="type in postTypes"
                                    :key="type.value"
                                    type="button"
                                    class="rounded-xl border px-3 py-2 text-xs font-black transition"
                                    :class="postForm.type === type.value ? 'border-[#09B884]/70 bg-[#1e2924] text-white' : 'border-stone-200 bg-white text-slate-600 hover:border-[#8BED9A]/70'"
                                    @click="postForm.type = type.value"
                                >
                                    {{ type.label }}
                                </button>
                            </div>
                            <input v-model="postForm.title" class="field-control mt-3 w-full" placeholder="Title" />
                            <textarea v-model="postForm.body" rows="5" class="field-control mt-3 w-full resize-none" placeholder="Write instructions or announcement"></textarea>
                            <input v-if="postForm.type === 'Class test'" v-model="postForm.classTestDate" type="date" class="field-control mt-3 w-full" />
                            <div v-if="postForm.type === 'Assignment'" class="mt-3 grid gap-2">
                                <label class="block">
                                    <span class="section-title">Deadline date</span>
                                    <input v-model="postForm.deadlineDate" type="date" class="field-control mt-1 w-full" />
                                </label>
                                <label class="block">
                                    <span class="section-title">Deadline time</span>
                                    <input v-model="postForm.deadlineTime" type="time" class="field-control mt-1 w-full" />
                                </label>
                                <label class="block">
                                    <span class="section-title">Marks</span>
                                    <input v-model="postForm.marks" type="number" min="0" class="field-control mt-1 w-full" placeholder="Marks" />
                                </label>
                            </div>
                            <input ref="postUploadInput" type="file" class="hidden" multiple @change="addFiles($event, 'post')" />
                            <div v-if="pendingPostFiles.length" class="mt-3 space-y-2">
                                <span v-for="(file, index) in pendingPostFiles" :key="`${file.name}-${index}`" class="flex items-center gap-2 rounded-xl border border-stone-200 bg-white px-3 py-2 text-xs font-bold text-[#1e2924]">
                                    <Image v-if="file.type?.startsWith('image/')" class="h-3.5 w-3.5 text-[#09B884]" />
                                    <FileText v-else class="h-3.5 w-3.5 text-[#09B884]" />
                                    <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                                    <button type="button" @click="removeFile('post', index)"><X class="h-3.5 w-3.5" /></button>
                                </span>
                            </div>
                            <div class="mt-4 grid gap-2">
                                <button type="button" class="btn-secondary min-h-10 justify-center" @click="postUploadInput?.click()">
                                    <UploadCloud class="h-4 w-4" />
                                    Add files
                                </button>
                                <button type="button" class="btn-primary min-h-11 justify-center" :disabled="saving || !postForm.title.trim()" @click="submitPost">
                                    <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                                    <Plus v-else class="h-4 w-4" />
                                    Publish
                                </button>
                            </div>
                        </div>

                        <div v-else class="rounded-2xl border border-[#8BED9A]/70 bg-[#8BED9A]/14 p-5">
                            <CheckCircle2 class="h-6 w-6 text-[#09B884]" />
                            <p class="mt-3 text-sm font-black text-[#1e2924]">Student classroom view</p>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">Open an assignment post to submit work with files, images, notes, or links.</p>
                        </div>
                    </div>
                </aside>
            </section>
        </div>

        <Teleport to="body">
            <Transition name="image-viewer">
                <div v-if="selectedPost" class="fixed inset-0 z-50 flex items-center justify-center bg-[#07110e]/45 p-4 backdrop-blur-sm" @click.self="closePost">
                    <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-white/30 bg-white shadow-2xl">
                        <div class="flex items-start justify-between gap-4 border-b border-stone-200 bg-gradient-to-br p-5" :class="cardWash(selectedPost.type)">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-black" :class="typeStyle(selectedPost.type)">
                                        <component :is="postIcon(selectedPost.type)" class="h-3.5 w-3.5" />
                                        {{ selectedPost.type }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-500">{{ formatTime(selectedPost.createdAt) }}</span>
                                </div>
                                <h3 class="mt-3 text-2xl font-black text-[#1e2924]">{{ selectedPost.title }}</h3>
                                <p class="mt-1 text-sm font-semibold text-slate-500">{{ selectedPost.subjectName }} - {{ selectedPost.classroomName }}</p>
                            </div>
                            <button type="button" class="rounded-xl border border-stone-200 bg-white p-2 text-slate-500 shadow-sm transition hover:bg-stone-50" @click="closePost">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto p-5">
                            <div
                                class="grid gap-5"
                                :class="selectedPost.type === 'Assignment' ? 'xl:grid-cols-[minmax(0,1fr)_20rem]' : 'xl:grid-cols-1'"
                            >
                                <main class="min-w-0 space-y-5">
                                    <section class="rounded-2xl border border-stone-200 bg-white p-5">
                                        <p class="whitespace-pre-line text-sm leading-7 text-slate-650">{{ selectedPost.body || 'No additional details were added.' }}</p>

                                        <div v-if="selectedPost.classTestDate || selectedPost.deadline || selectedPost.marks" class="mt-4 flex flex-wrap gap-2">
                                            <span v-if="selectedPost.classTestDate" class="inline-flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-xs font-black text-amber-800">
                                                <CalendarClock class="h-4 w-4" />
                                                Test date: {{ formatDate(selectedPost.classTestDate) }}
                                            </span>
                                            <span v-if="selectedPost.deadline" class="inline-flex items-center gap-2 rounded-xl bg-violet-50 px-3 py-2 text-xs font-black text-violet-800">
                                                <CalendarClock class="h-4 w-4" />
                                                Deadline: {{ formatDateTime(selectedPost.deadline) }}
                                            </span>
                                            <span v-if="selectedPost.marks" class="inline-flex items-center gap-2 rounded-xl bg-[#8BED9A]/18 px-3 py-2 text-xs font-black text-[#1e2924]">
                                                {{ selectedPost.marks }} marks
                                            </span>
                                        </div>

                                        <div v-if="selectedPost.attachments?.length" class="mt-5 grid gap-3 sm:grid-cols-2">
                                            <button
                                                v-for="file in selectedPost.attachments.filter((attachment) => attachment.type?.startsWith('image/'))"
                                                :key="file.url"
                                                type="button"
                                                class="group relative overflow-hidden rounded-2xl border border-stone-200 bg-white text-left shadow-sm transition hover:scale-[1.01]"
                                                @click="openImageViewer(file)"
                                            >
                                                <img :src="file.url" :alt="file.name" class="h-52 w-full object-cover" loading="lazy" />
                                                <span class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-black/70 to-transparent px-3 pb-2 pt-8 text-xs font-bold text-white opacity-0 transition group-hover:opacity-100">
                                                    <span class="truncate">{{ file.name }}</span>
                                                    <Maximize2 class="h-3.5 w-3.5" />
                                                </span>
                                            </button>
                                            <a
                                                v-for="file in selectedPost.attachments.filter((attachment) => !attachment.type?.startsWith('image/'))"
                                                :key="file.url"
                                                :href="file.url"
                                                target="_blank"
                                                class="flex items-center gap-3 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm font-bold text-[#1e2924] transition hover:bg-white hover:shadow-sm"
                                            >
                                                <FileText class="h-5 w-5 text-[#09B884]" />
                                                <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                                                <span class="text-xs text-slate-400">{{ fileSize(file.size) }}</span>
                                            </a>
                                        </div>
                                    </section>

                                    <section class="rounded-2xl border border-stone-200 bg-white p-4">
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <p class="text-sm font-black text-[#1e2924]">Comments</p>
                                            <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-black text-slate-500">{{ comments[selectedPost.id]?.length ?? 0 }}</span>
                                        </div>
                                        <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
                                            <div v-for="comment in comments[selectedPost.id]" :key="comment.id" class="rounded-2xl bg-stone-50 p-3">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <p class="text-sm font-black text-[#1e2924]">{{ comment.authorName }} <span class="text-xs font-semibold text-slate-500">{{ comment.authorRole }}</span></p>
                                                    <span class="text-xs font-bold text-slate-400">{{ formatTime(comment.createdAt) }}</span>
                                                </div>
                                                <p class="mt-1 text-sm text-slate-600">{{ comment.body }}</p>
                                            </div>
                                            <p v-if="!comments[selectedPost.id]?.length" class="rounded-2xl border border-dashed border-stone-300 py-8 text-center text-sm font-semibold text-slate-500">No comments yet.</p>
                                        </div>
                                        <div class="mt-3 flex gap-2">
                                            <input v-model="commentForm" class="field-control min-w-0 flex-1" placeholder="Add a comment" @keydown.enter.prevent="submitComment(selectedPost)" />
                                            <button type="button" class="btn-primary min-h-11" :disabled="saving || !commentForm.trim()" @click="submitComment(selectedPost)">
                                                <Send class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </section>
                                </main>

                                <aside v-if="selectedPost.type === 'Assignment'" class="space-y-4">
                                    <section v-if="selectedPost.type === 'Assignment' && canSubmit" class="rounded-2xl border border-violet-200 bg-violet-50/50 p-4">
                                        <div v-if="currentStudentSubmission" class="space-y-4">
                                            <div class="rounded-2xl border border-[#8BED9A]/70 bg-white p-4">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#8BED9A]/18 text-[#09B884]">
                                                        <CheckCircle2 class="h-5 w-5" />
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-black text-[#1e2924]">Turned in</p>
                                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ formatTime(currentStudentSubmission.createdAt) }}</p>
                                                    </div>
                                                </div>
                                                <p v-if="currentStudentSubmission.note" class="mt-3 rounded-xl bg-stone-50 p-3 text-sm text-slate-600">{{ currentStudentSubmission.note }}</p>
                                                <a v-if="currentStudentSubmission.link" :href="currentStudentSubmission.link" target="_blank" class="mt-3 inline-flex rounded-full bg-[#8BED9A]/18 px-3 py-1.5 text-xs font-black text-[#1e2924]">Open submitted link</a>
                                                <div v-if="currentStudentSubmission.attachments?.length" class="mt-3 space-y-2">
                                                    <a v-for="file in currentStudentSubmission.attachments" :key="file.url" :href="file.url" target="_blank" class="flex items-center gap-2 rounded-xl border border-stone-200 bg-stone-50 px-3 py-2 text-xs font-bold text-[#1e2924]">
                                                        <FileText class="h-3.5 w-3.5 text-[#09B884]" />
                                                        <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                                                        <span class="text-slate-400">{{ fileSize(file.size) }}</span>
                                                    </a>
                                                </div>
                                            </div>
                                            <button type="button" class="btn-secondary min-h-11 w-full justify-center border-red-200 text-red-700 hover:border-red-300 hover:bg-red-50" :disabled="saving" @click="unsubmitAssignment(selectedPost)">
                                                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                                                <X v-else class="h-4 w-4" />
                                                Unsubmit
                                            </button>
                                        </div>

                                        <div v-else>
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-violet-700 shadow-sm">
                                                    <FileUp class="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <p class="text-sm font-black text-[#1e2924]">Submit assignment</p>
                                                    <p class="text-xs font-semibold text-slate-500">Files, images, notes, or links</p>
                                                </div>
                                            </div>
                                            <textarea v-model="submissionForm.note" rows="4" class="field-control mt-4 w-full resize-none" placeholder="Add a note for your teacher"></textarea>
                                            <input v-model="submissionForm.link" class="field-control mt-3 w-full" placeholder="Paste a link if needed" />
                                            <input ref="submissionUploadInput" type="file" class="hidden" multiple @change="addFiles($event, 'submission')" />
                                            <div v-if="pendingSubmissionFiles.length" class="mt-3 space-y-2">
                                                <span v-for="(file, index) in pendingSubmissionFiles" :key="`${file.name}-${index}`" class="flex items-center gap-2 rounded-xl border border-stone-200 bg-white px-3 py-2 text-xs font-bold text-[#1e2924]">
                                                    <Image v-if="file.type?.startsWith('image/')" class="h-3.5 w-3.5 text-[#09B884]" />
                                                    <FileText v-else class="h-3.5 w-3.5 text-[#09B884]" />
                                                    <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                                                    <button type="button" @click="removeFile('submission', index)"><X class="h-3.5 w-3.5" /></button>
                                                </span>
                                            </div>
                                            <div class="mt-4 grid gap-2">
                                                <button type="button" class="btn-secondary min-h-10 justify-center" @click="submissionUploadInput?.click()">
                                                    <Paperclip class="h-4 w-4" />
                                                    Attach files
                                                </button>
                                                <button type="button" class="btn-primary min-h-11 justify-center" :disabled="saving || (!submissionForm.note.trim() && !submissionForm.link.trim() && !pendingSubmissionFiles.length)" @click="submitAssignment(selectedPost)">
                                                    <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                                                    <Send v-else class="h-4 w-4" />
                                                    Turn in
                                                </button>
                                            </div>
                                        </div>
                                    </section>

                                    <section v-if="selectedPost.type === 'Assignment' && role !== 'student'" class="rounded-2xl border border-stone-200 bg-white p-4">
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <p class="text-sm font-black text-[#1e2924]">Submissions</p>
                                            <span class="rounded-full bg-[#8BED9A]/18 px-2.5 py-1 text-xs font-black text-[#1e2924]">{{ submissions[selectedPost.id]?.length ?? 0 }}</span>
                                        </div>
                                        <div class="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                                            <div v-for="submission in submissions[selectedPost.id]" :key="submission.id" class="rounded-xl bg-stone-50 p-3">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <p class="text-sm font-black text-[#1e2924]">{{ submission.studentName }} <span class="text-xs font-semibold text-slate-500">- {{ submission.className }}</span></p>
                                                    <span class="text-xs font-bold text-slate-400">{{ formatTime(submission.createdAt) }}</span>
                                                </div>
                                                <p v-if="submission.note" class="mt-1 text-sm text-slate-600">{{ submission.note }}</p>
                                                <a v-if="submission.link" :href="submission.link" target="_blank" class="mt-2 inline-flex text-xs font-black text-[#09B884]">Open submitted link</a>
                                                <div v-if="submission.attachments?.length" class="mt-2 flex flex-wrap gap-2">
                                                    <a v-for="file in submission.attachments" :key="file.url" :href="file.url" target="_blank" class="rounded-lg border border-stone-200 bg-white px-2.5 py-1.5 text-xs font-bold text-[#1e2924]">
                                                        {{ file.name }}
                                                    </a>
                                                </div>
                                            </div>
                                            <p v-if="!submissions[selectedPost.id]?.length" class="rounded-xl border border-dashed border-stone-300 py-8 text-center text-sm font-semibold text-slate-500">No submissions yet.</p>
                                        </div>
                                    </section>
                                </aside>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="image-viewer">
                <div v-if="imageViewer" class="fixed inset-0 z-[60] flex items-center justify-center bg-[#07110e]/85 p-4 backdrop-blur-md" @click.self="imageViewer = null">
                    <div class="relative max-h-[92vh] w-full max-w-6xl">
                        <button type="button" class="absolute right-3 top-3 z-10 flex h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-black/45 text-white shadow-lg backdrop-blur transition hover:bg-black/65" @click="imageViewer = null">
                            <X class="h-5 w-5" />
                        </button>
                        <img :src="imageViewer.url" :alt="imageViewer.name" class="mx-auto max-h-[92vh] rounded-2xl object-contain shadow-2xl" />
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.classroom-shell {
    animation: classroom-rise 360ms ease-out both;
}

.post-list-enter-active,
.post-list-leave-active {
    transition: opacity 220ms ease, transform 220ms ease;
}

.post-list-enter-from,
.post-list-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.99);
}

.image-viewer-enter-active,
.image-viewer-leave-active {
    transition: opacity 180ms ease;
}

.image-viewer-enter-from,
.image-viewer-leave-to {
    opacity: 0;
}

@keyframes classroom-rise {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
