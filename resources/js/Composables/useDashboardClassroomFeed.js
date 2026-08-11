import { computed, onMounted, onUnmounted, ref } from 'vue';
import { getApps, initializeApp } from 'firebase/app';
import { getAuth, signInAnonymously } from 'firebase/auth';
import { collection, getFirestore, initializeFirestore, onSnapshot } from 'firebase/firestore';

export function useDashboardClassroomFeed(context, fallback = []) {
    const posts = ref([]);
    const loading = ref(false);
    const error = ref('');
    let unsubscribe = null;

    const config = computed(() => context?.firebaseConfig ?? {});
    const configured = computed(() =>
        ['apiKey', 'authDomain', 'projectId', 'appId'].every((key) => Boolean(config.value?.[key]))
    );

    const updates = computed(() => {
        if (!posts.value.length) return fallback;

        return [...posts.value]
            .sort((a, b) => timestamp(b) - timestamp(a))
            .slice(0, 4)
            .map((post) => ({
                id: post.id,
                subject: post.subjectName || 'Classroom',
                title: post.title || post.type || 'New update',
                message: post.body || `${post.type || 'Update'} posted by ${post.authorName || 'your teacher'}.`,
                type: post.type || 'Update',
                due: deadlineLabel(post),
                classroom: post.classroomName || '',
            }));
    });

    const assignmentCount = computed(() =>
        posts.value.filter((post) => post.type === 'Assignment' && !isPast(post.deadline)).length
    );

    const createdAssignments = computed(() => posts.value
        .filter((post) => post.type === 'Assignment' && isOwnedPost(post, context?.authorId))
        .sort((a, b) => deadlineTimestamp(a) - deadlineTimestamp(b))
        .slice(0, 5)
        .map(toDashboardPost));

    const upcomingAssignments = computed(() => posts.value
        .filter((post) => post.type === 'Assignment' && !isPast(post.deadline))
        .sort((a, b) => deadlineTimestamp(a) - deadlineTimestamp(b))
        .slice(0, 5)
        .map(toDashboardPost));

    const upcomingTests = computed(() => posts.value
        .filter((post) => post.type === 'Class test'
            && !isPastDay(post.classTestDate)
            && isOwnedPost(post, context?.authorId))
        .sort((a, b) => dateTimestamp(a.classTestDate) - dateTimestamp(b.classTestDate))
        .slice(0, 5)
        .map(toDashboardPost));

    onMounted(async () => {
        if (!configured.value || !(context?.sectionIds ?? []).length) return;

        loading.value = true;
        try {
            const app = getApps().length ? getApps()[0] : initializeApp(config.value);
            let db;
            try {
                db = initializeFirestore(app, { experimentalAutoDetectLongPolling: true, useFetchStreams: false });
            } catch {
                db = getFirestore(app);
            }

            const auth = getAuth(app);
            if (!auth.currentUser) await signInAnonymously(auth);

            const allowedSections = new Set(context.sectionIds.map(String));
            const feed = collection(db, 'institutions', String(context.institutionId ?? 'global'), 'classroomPosts');

            unsubscribe = onSnapshot(feed, (snapshot) => {
                posts.value = snapshot.docs
                    .map((document) => ({ id: document.id, ...document.data() }))
                    .filter((post) => allowedSections.has(String(post.classroomId ?? '')));
                error.value = '';
            }, () => {
                error.value = 'Live classroom updates are temporarily unavailable.';
            });
        } catch {
            error.value = 'Live classroom updates are temporarily unavailable.';
        } finally {
            loading.value = false;
        }
    });

    onUnmounted(() => unsubscribe?.());

    return { updates, assignmentCount, createdAssignments, upcomingAssignments, upcomingTests, loading, error };
}

function toDashboardPost(post) {
    return {
        id: post.id,
        subject: post.subjectName || 'Classroom',
        title: post.title || post.type || 'Classroom post',
        message: post.body || '',
        type: post.type || 'Update',
        due: deadlineLabel(post),
        deadline: post.deadline || '',
        classroom: post.classroomName || '',
        marks: post.marks || null,
    };
}

function isOwnedPost(post, authorId) {
    return !authorId || String(post.authorId ?? '') === String(authorId);
}

function deadlineTimestamp(post) {
    return dateTimestamp(post.deadline) || Number.MAX_SAFE_INTEGER;
}

function dateTimestamp(value) {
    const time = new Date(value || '').getTime();
    return Number.isFinite(time) ? time : Number.MAX_SAFE_INTEGER;
}

function isPastDay(value) {
    if (!value) return false;
    const date = new Date(value);
    const today = new Date();
    date.setHours(23, 59, 59, 999);
    today.setHours(0, 0, 0, 0);
    return date.getTime() < today.getTime();
}

function timestamp(post) {
    return Number(post?.createdAt?.seconds ?? 0) * 1000 || Number(post?.localCreatedAt ?? 0);
}

function isPast(value) {
    if (!value) return false;
    const time = new Date(value).getTime();
    return Number.isFinite(time) && time < Date.now();
}

function deadlineLabel(post) {
    if (post.type === 'Class test' && post.classTestDate) return `Test ${formatDate(post.classTestDate)}`;
    if (post.deadline) return `Due ${formatDate(post.deadline)}`;
    return post.type || 'Update';
}

function formatDate(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(date);
}
