<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { getApps, initializeApp } from 'firebase/app';
import { getAuth, signInAnonymously } from 'firebase/auth';
import { collection, getFirestore, initializeFirestore, onSnapshot } from 'firebase/firestore';
import { Bell, BellRing, BookOpenCheck, CheckCheck, ClipboardList, Loader2, Search, ShieldCheck } from 'lucide-vue-next';

const props = defineProps({
    title: { type: String, default: '' },
});

const today = computed(() =>
    new Date().toLocaleDateString('en-US', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    })
);

const page = usePage();
const role = computed(() => String(page.props.auth?.user?.role ?? 'admin').toLowerCase());
const open = ref(false);
const loading = ref(false);
const backendItems = ref([]);
const classroomItems = ref([]);
const readKeys = ref(new Set());
const error = ref('');
const panelRef = ref(null);
const bellRef = ref(null);
const searchOpen = ref(false);
const searchQuery = ref('');
const searchPanelRef = ref(null);
const searchButtonRef = ref(null);
const searchInputRef = ref(null);
const remoteSearchItems = ref([]);
const searchLoading = ref(false);
const searchError = ref('');
let classroomUnsubscribe = null;
const submissionUnsubscribers = new Map();

const items = computed(() =>
    [...backendItems.value, ...classroomItems.value]
        .map((item) => ({ ...item, read: item.read || readKeys.value.has(item.key) }))
        .sort((a, b) => Number(b.timestamp ?? 0) - Number(a.timestamp ?? 0))
        .slice(0, 30)
);
const unreadCount = computed(() => items.value.filter((item) => !item.read).length);
const badgeLabel = computed(() => unreadCount.value > 9 ? '9+' : String(unreadCount.value));
const featureResults = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const classrooms = rankedResults(remoteSearchItems.value, query);
    const features = rankedResults(featureMap.value, query);

    if (!query) {
        return [
            ...classrooms,
            ...features.slice(0, 8),
        ];
    }

    return [
        ...classrooms,
        ...features,
    ];
});
const featureMap = computed(() => {
    const shared = [
        feature('Dashboard', '/dashboard', 'Overview, today, home, summary'),
        feature('Routines', '/routines', 'Routine, timetable, schedule, periods'),
        feature('Exam Schedule', '/exam-schedule', 'Exam, duties, invigilation, halls'),
        feature('Noticeboard', '/noticeboard', 'Notice, announcement, staff post'),
        feature('Classroom', '/classroom', 'Classroom, assignments, submissions, homework, tests'),
        feature('Settings', '/settings', 'Settings, preferences, notifications, profile'),
    ];

    if (role.value === 'student') {
        return shared.filter((item) => !['Settings'].includes(item.name)).concat([
            feature('Personal Settings', '/settings', 'Personal, theme, notification settings'),
        ]);
    }

    if (role.value === 'teacher') {
        return shared.concat([
            feature('My Leave', '/leave-requests', 'Leave request, approval, rejection, balance'),
            feature('Staffroom', '/staffroom', 'Staffroom, staff discussion'),
            feature('My Classrooms', '/classrooms', 'Classes, sections, join codes'),
        ]);
    }

    return shared.concat([
        feature('Class Coverage', '/proxy-manager', 'Proxy, substitute, absent teachers, approvals'),
        feature('Leave Requests', '/leave-requests', 'Leave approval, rejection, balance'),
        feature('Staffroom', '/staffroom', 'Staffroom, staff discussion'),
        feature('Analytics', '/analytics', 'Analytics, reports, charts, insights'),
        feature('Classes', '/classrooms', 'Classrooms, sections, join codes, students'),
        feature('Teachers', '/teachers', 'Teachers, staff, profiles, join codes'),
        feature('Create Routine', '/routines/create', 'Create routine, generate timetable'),
        feature('Database Cleanup', '/settings', 'Clear database, classrooms, teachers, routines, admin settings'),
    ]);
});

onMounted(() => {
    fetchNotifications();
    document.addEventListener('click', closeFromOutside);
    document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', closeFromOutside);
    document.removeEventListener('keydown', closeOnEscape);
    classroomUnsubscribe?.();
    submissionUnsubscribers.forEach((unsubscribe) => unsubscribe?.());
});

async function toggleNotifications() {
    open.value = !open.value;
    if (open.value) await fetchNotifications();
}

async function fetchNotifications() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/notifications');
        backendItems.value = response.data.items ?? [];
        readKeys.value = new Set([
            ...readKeys.value,
            ...backendItems.value.filter((item) => item.read).map((item) => item.key),
        ]);
        setupClassroomNotifications(response.data.classroomContext ?? {});
    } catch {
        error.value = 'Notifications could not be loaded.';
    } finally {
        loading.value = false;
    }
}

async function markAllRead() {
    const keys = items.value.map((item) => item.key);
    if (!keys.length) return;

    readKeys.value = new Set([...readKeys.value, ...keys]);
    backendItems.value = backendItems.value.map((item) => ({ ...item, read: true }));
    classroomItems.value = classroomItems.value.map((item) => ({ ...item, read: true }));

    try {
        await window.axios.post('/notifications/read', { keys });
    } catch {
        error.value = 'Read state could not be saved.';
    }
}

async function markOneRead(key) {
    if (!key || readKeys.value.has(key)) return;

    readKeys.value = new Set([...readKeys.value, key]);
    try {
        await window.axios.post('/notifications/read', { keys: [key] });
    } catch {
        error.value = 'Read state could not be saved.';
    }
}

async function setupClassroomNotifications(context) {
    classroomUnsubscribe?.();
    submissionUnsubscribers.forEach((unsubscribe) => unsubscribe?.());
    submissionUnsubscribers.clear();
    classroomItems.value = [];

    const config = context.firebaseConfig ?? {};
    const configured = ['apiKey', 'authDomain', 'projectId', 'appId'].every((key) => Boolean(config[key]));
    const allowedSections = new Set((context.sectionIds ?? []).map(String));
    if (!configured || !allowedSections.size) return;

    try {
        const app = getApps().length ? getApps()[0] : initializeApp(config);
        let db;
        try {
            db = initializeFirestore(app, { experimentalAutoDetectLongPolling: true, useFetchStreams: false });
        } catch {
            db = getFirestore(app);
        }

        const auth = getAuth(app);
        if (!auth.currentUser) await signInAnonymously(auth);

        const feed = collection(db, 'institutions', String(context.institutionId ?? 'global'), 'classroomPosts');
        classroomUnsubscribe = onSnapshot(feed, (snapshot) => {
            const posts = snapshot.docs
                .map((document) => ({ id: document.id, ...document.data() }))
                .filter((post) => allowedSections.has(String(post.classroomId ?? '')));

            const postItems = posts
                .filter((post) => shouldNotifyForPost(post, context))
                .map((post) => classroomPostItem(post));

            classroomItems.value = [
                ...postItems,
                ...classroomItems.value.filter((item) => item.type === 'submission'),
            ];

            subscribeSubmissions(db, context, posts);
        }, () => {
            error.value = 'Live classroom notifications are unavailable.';
        });
    } catch {
        error.value = 'Live classroom notifications are unavailable.';
    }
}

function subscribeSubmissions(db, context, posts) {
    const assignmentPosts = posts.filter((post) => post.type === 'Assignment' && shouldNotifyForSubmissionPost(post, context));
    const activePostIds = new Set(assignmentPosts.map((post) => post.id));

    submissionUnsubscribers.forEach((unsubscribe, postId) => {
        if (!activePostIds.has(postId)) {
            unsubscribe?.();
            submissionUnsubscribers.delete(postId);
        }
    });

    assignmentPosts.forEach((post) => {
        if (submissionUnsubscribers.has(post.id)) return;

        const submissions = collection(db, 'institutions', String(context.institutionId ?? 'global'), 'classroomPosts', post.id, 'submissions');
        const unsubscribe = onSnapshot(submissions, (snapshot) => {
            const nextSubmissionItems = snapshot.docs.map((document) => submissionItem(post, { id: document.id, ...document.data() }));
            classroomItems.value = [
                ...classroomItems.value.filter((item) => !(item.type === 'submission' && item.postId === post.id)),
                ...nextSubmissionItems,
            ];
        });
        submissionUnsubscribers.set(post.id, unsubscribe);
    });
}

function shouldNotifyForPost(post, context) {
    if (context.role === 'student') {
        return ['Assignment', 'Homework', 'Class test'].includes(post.type);
    }

    if (context.role === 'admin') {
        return ['Assignment', 'Homework', 'Class test'].includes(post.type);
    }

    return false;
}

function shouldNotifyForSubmissionPost(post, context) {
    if (context.role === 'admin') return true;
    if (context.role !== 'teacher') return false;

    return String(post.authorId ?? '') === String(context.userKey ?? '');
}

function classroomPostItem(post) {
    const timestamp = firebaseTimestamp(post.createdAt) || Number(post.localCreatedAt ?? 0) || Date.now();
    return {
        key: `classroom:post:${post.id}:${timestamp}`,
        type: 'classroom',
        title: post.type === 'Class test' ? 'Class test posted' : `${post.type || 'Classroom'} posted`,
        message: `${post.title || 'Classroom update'} - ${post.subjectName || post.classroomName || 'Classroom'}`,
        href: '/classroom',
        time: relativeTime(timestamp),
        timestamp: Math.floor(timestamp / 1000),
        tone: post.type === 'Class test' ? 'amber' : 'blue',
        eyebrow: post.classroomName || 'Classroom',
        read: readKeys.value.has(`classroom:post:${post.id}:${timestamp}`),
    };
}

function submissionItem(post, submission) {
    const timestamp = firebaseTimestamp(submission.createdAt) || Date.now();
    return {
        key: `classroom:submission:${post.id}:${submission.id}:${timestamp}`,
        type: 'submission',
        postId: post.id,
        title: 'Assignment submitted',
        message: `${submission.studentName || 'A student'} submitted ${post.title || 'an assignment'}.`,
        href: '/classroom',
        time: relativeTime(timestamp),
        timestamp: Math.floor(timestamp / 1000),
        tone: 'green',
        eyebrow: post.classroomName || 'Classroom',
        read: readKeys.value.has(`classroom:submission:${post.id}:${submission.id}:${timestamp}`),
    };
}

function firebaseTimestamp(value) {
    if (!value) return 0;
    if (typeof value.toMillis === 'function') return value.toMillis();
    if (value.seconds) return Number(value.seconds) * 1000;
    return 0;
}

function relativeTime(milliseconds) {
    const seconds = Math.max(1, Math.round((Date.now() - milliseconds) / 1000));
    if (seconds < 60) return 'Just now';
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.round(hours / 24);
    return `${days}d ago`;
}

function closeFromOutside(event) {
    if (open.value && !panelRef.value?.contains(event.target) && !bellRef.value?.contains(event.target)) {
        open.value = false;
    }

    if (searchOpen.value && !searchPanelRef.value?.contains(event.target) && !searchButtonRef.value?.contains(event.target)) {
        searchOpen.value = false;
    }
}

function closeOnEscape(event) {
    if (event.key === 'Escape') {
        open.value = false;
        searchOpen.value = false;
    }
}

async function toggleSearch() {
    searchOpen.value = !searchOpen.value;
    if (searchOpen.value) {
        open.value = false;
        fetchSearchItems();
        await nextTick();
        searchInputRef.value?.focus();
    }
}

async function fetchSearchItems() {
    if (remoteSearchItems.value.length || searchLoading.value) return;

    searchLoading.value = true;
    searchError.value = '';

    try {
        const response = await window.axios.get('/search/features');
        remoteSearchItems.value = (response.data.items ?? []).map((item) => ({
            ...item,
            keywords: item.keywords ?? `${item.name} ${item.description}`,
            description: item.description ?? item.type ?? 'Open',
        }));
    } catch {
        searchError.value = 'Classroom search is temporarily unavailable.';
    } finally {
        searchLoading.value = false;
    }
}

function openSearchResult(item) {
    if (!item?.href) return;
    searchOpen.value = false;
    router.visit(item.href);
}

function feature(name, href, keywords) {
    return {
        key: `feature:${name}`,
        name,
        href,
        keywords,
        description: keywords.split(',').slice(0, 3).join(',').trim(),
        type: 'feature',
    };
}

function scoreFeature(feature, query) {
    const name = feature.name.toLowerCase();
    const keywords = feature.keywords.toLowerCase();
    const haystack = `${name} ${keywords}`;

    if (name === query) return 100;
    if (name.startsWith(query)) return 80;
    if (name.includes(query)) return 60;
    if (keywords.includes(query)) return 40;

    return query
        .split(/\s+/)
        .filter((part) => part && haystack.includes(part))
        .length * 10;
}

function rankedResults(collection, query) {
    if (!query) return collection;

    return collection
        .map((item) => ({
            ...item,
            score: scoreFeature(item, query),
        }))
        .filter((item) => item.score > 0)
        .sort((a, b) => b.score - a.score || a.name.localeCompare(b.name));
}

function toneClass(tone) {
    return {
        red: 'bg-red-100 text-red-700',
        amber: 'bg-amber-100 text-amber-700',
        green: 'bg-emerald-100 text-emerald-700',
        blue: 'bg-blue-100 text-blue-700',
    }[tone] ?? 'bg-slate-100 text-slate-700';
}

function iconFor(type) {
    return {
        notice: BellRing,
        leave: CheckCheck,
        proxy: ShieldCheck,
        exam: ClipboardList,
        classroom: BookOpenCheck,
        submission: ClipboardList,
        directory: BookOpenCheck,
    }[type] ?? Bell;
}
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-[#8BED9A]/40 bg-white/95 px-3 backdrop-blur sm:h-16 sm:px-6"
    >
        <h1 class="min-w-0 truncate text-base font-black text-[#1e2924] sm:text-lg sm:font-semibold">{{ title }}</h1>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <span class="hidden text-sm text-[#1e2924]/60 sm:inline">{{ today }}</span>

            <div class="relative">
                <button
                    ref="bellRef"
                    type="button"
                    class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-[#8BED9A]/60 bg-white text-[#1e2924]/70 shadow-sm transition hover:border-[#09B884] hover:bg-[#8BED9A]/15 hover:text-[#1e2924] sm:h-9 sm:w-9 sm:rounded-lg"
                    :aria-expanded="open"
                    aria-label="Open notifications"
                    @click.stop="toggleNotifications"
                >
                    <Bell class="h-4 w-4" />
                    <span
                        v-if="unreadCount"
                        class="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-[#09B884] px-1 text-[10px] font-black text-white ring-2 ring-white"
                    >
                        {{ badgeLabel }}
                    </span>
                </button>

                <div
                    v-if="open"
                    ref="panelRef"
                    class="fixed inset-x-3 top-16 z-50 max-h-[calc(100vh-6rem)] overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl shadow-slate-900/10 sm:absolute sm:inset-auto sm:right-0 sm:top-12 sm:w-[min(92vw,24rem)] sm:rounded-lg"
                >
                    <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
                        <div>
                            <p class="text-sm font-black text-[#1e2924]">Notifications</p>
                            <p class="text-xs font-semibold text-slate-500">{{ unreadCount }} unread</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-stone-200 px-2.5 py-1.5 text-xs font-bold text-slate-600 transition hover:border-[#09B884] hover:text-[#1e2924]"
                            @click="markAllRead"
                        >
                            <CheckCheck class="h-3.5 w-3.5" />
                            Mark read
                        </button>
                    </div>

                    <div v-if="loading" class="flex items-center justify-center gap-2 px-4 py-8 text-sm font-semibold text-slate-500">
                        <Loader2 class="h-4 w-4 animate-spin" />
                        Loading notifications
                    </div>

                    <div v-else class="max-h-[calc(100vh-12rem)] overflow-y-auto sm:max-h-[28rem]">
                        <p v-if="error" class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-700">{{ error }}</p>

                        <Link
                            v-for="item in items"
                            :key="item.key"
                            :href="item.href"
                            class="flex gap-3 border-b border-stone-100 px-4 py-3 transition hover:bg-stone-50"
                            :class="item.read ? 'bg-white' : 'bg-[#8BED9A]/10'"
                            @click="markOneRead(item.key)"
                        >
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="toneClass(item.tone)">
                                <component :is="iconFor(item.type)" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-start justify-between gap-3">
                                    <span class="truncate text-sm font-black text-slate-950">{{ item.title }}</span>
                                    <span v-if="!item.read" class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#09B884]"></span>
                                </span>
                                <span class="mt-1 line-clamp-2 block text-xs font-semibold leading-5 text-slate-500">{{ item.message }}</span>
                                <span class="mt-2 flex items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-slate-400">
                                    <span class="truncate">{{ item.eyebrow }}</span>
                                    <span class="shrink-0">{{ item.time }}</span>
                                </span>
                            </span>
                        </Link>

                        <div v-if="!items.length" class="px-4 py-10 text-center">
                            <Bell class="mx-auto h-6 w-6 text-slate-300" />
                            <p class="mt-3 text-sm font-bold text-slate-600">No notifications yet</p>
                            <p class="mt-1 text-xs font-semibold text-slate-400">New notices, classroom activity, substitution work, and leave updates will appear here.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative">
                <button
                    ref="searchButtonRef"
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#8BED9A]/60 bg-white text-[#1e2924]/70 shadow-sm transition hover:border-[#09B884] hover:bg-[#8BED9A]/15 hover:text-[#1e2924] sm:h-9 sm:w-9 sm:rounded-lg"
                    :aria-expanded="searchOpen"
                    aria-label="Search features"
                    @click.stop="toggleSearch"
                >
                    <Search class="h-4 w-4" />
                </button>

                <div
                    v-if="searchOpen"
                    ref="searchPanelRef"
                    class="fixed inset-x-3 top-16 z-50 max-h-[calc(100vh-6rem)] overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl shadow-slate-900/10 sm:absolute sm:inset-auto sm:right-0 sm:top-12 sm:w-[min(92vw,24rem)] sm:rounded-lg"
                >
                    <div class="border-b border-stone-200 p-3">
                        <div class="flex items-center gap-2 rounded-lg border border-stone-200 bg-stone-50 px-3 py-2">
                            <Search class="h-4 w-4 shrink-0 text-slate-400" />
                            <input
                                ref="searchInputRef"
                                v-model="searchQuery"
                                type="search"
                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:ring-0"
                                placeholder="Search class, subject, or feature"
                                @keydown.enter.prevent="openSearchResult(featureResults[0])"
                            />
                        </div>
                        <p v-if="searchError" class="mt-2 text-xs font-semibold text-amber-700">{{ searchError }}</p>
                    </div>

                    <div class="max-h-[calc(100vh-12rem)] overflow-y-auto py-1 sm:max-h-80">
                        <div v-if="searchLoading" class="flex items-center gap-2 px-4 py-3 text-xs font-semibold text-slate-500">
                            <Loader2 class="h-3.5 w-3.5 animate-spin" />
                            Loading classrooms
                        </div>

                        <Link
                            v-for="featureItem in featureResults"
                            :key="featureItem.key || featureItem.name"
                            :href="featureItem.href"
                            class="flex items-center gap-3 px-4 py-3 transition hover:bg-stone-50"
                            @click="searchOpen = false"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                :class="featureItem.type === 'subject' || featureItem.type === 'classroom' ? 'bg-blue-100 text-blue-700' : 'bg-[#8BED9A]/20 text-[#04795a]'"
                            >
                                <BookOpenCheck v-if="featureItem.type === 'subject' || featureItem.type === 'classroom'" class="h-4 w-4" />
                                <Search v-else class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-black text-slate-950">{{ featureItem.name }}</span>
                                <span class="mt-0.5 block truncate text-xs font-semibold text-slate-500">{{ featureItem.description }}</span>
                            </span>
                        </Link>

                        <div v-if="!featureResults.length" class="px-4 py-8 text-center">
                            <Search class="mx-auto h-6 w-6 text-slate-300" />
                            <p class="mt-3 text-sm font-bold text-slate-600">No matching feature</p>
                            <p class="mt-1 text-xs font-semibold text-slate-400">Try a subject, classroom, proxy, routine, leave, notice, or settings.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
