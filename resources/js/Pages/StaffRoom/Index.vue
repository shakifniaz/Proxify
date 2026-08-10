<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { getApps, initializeApp } from 'firebase/app';
import { getAuth, signInAnonymously } from 'firebase/auth';
import {
    addDoc,
    collection,
    doc,
    getFirestore,
    initializeFirestore,
    limit,
    onSnapshot,
    orderBy,
    query,
    serverTimestamp,
    setDoc,
    where,
} from 'firebase/firestore';
import {
    getDownloadURL,
    getStorage,
    ref as storageRef,
    uploadBytes,
} from 'firebase/storage';
import {
    AlertCircle,
    CheckCheck,
    FileText,
    Hash,
    Image,
    Loader2,
    Lock,
    Maximize2,
    MessageCircle,
    Paperclip,
    Plus,
    Search,
    Send,
    ShieldCheck,
    UploadCloud,
    UserPlus,
    UsersRound,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    currentUser: { type: Object, default: () => ({}) },
    directory: { type: Array, default: () => [] },
    openChannels: { type: Array, default: () => [] },
    firebaseConfig: { type: Object, default: () => ({}) },
});

const firebaseReady = computed(() =>
    ['apiKey', 'authDomain', 'projectId', 'storageBucket', 'appId'].every((key) => Boolean(props.firebaseConfig?.[key]))
);
const userKey = computed(() => props.currentUser.id ?? `user-${props.currentUser.laravelUserId ?? 'guest'}`);
const institutionKey = computed(() => String(props.currentUser.institutionId ?? 'global'));
const roleLabel = computed(() => props.currentUser.role === 'admin' ? 'Admin' : 'Teacher');
const staffList = computed(() => props.directory.filter((person) => person.id !== userKey.value));
const dmPeople = computed(() => staffList.value.filter((person) => person.canDm !== false));
const availableDmPeople = computed(() => dmPeople.value.length ? dmPeople.value : staffList.value);
const defaultOpenChannelIds = computed(() => new Set(['open-general']));
const publicChannels = computed(() => {
    const general = props.openChannels.find((channel) => channel.id === 'general');

    return [general ?? {
        id: 'general',
        name: 'General staffroom',
        subtitle: 'Open staff discussion',
        locked: true,
        sortOrder: 1,
    }];
});

const activeTab = ref('channels');
const channels = ref([]);
const messages = ref([]);
const selectedChannelId = ref(null);
const search = ref('');
const draft = ref('');
const loading = ref(false);
const sending = ref(false);
const firebaseError = ref('');
const showGroupModal = ref(false);
const showDmModal = ref(false);
const uploadInput = ref(null);
const pendingUploads = ref([]);
const uploadProgress = ref('');
const imageViewer = ref(null);
const groupForm = ref({ name: '', memberIds: [] });
const unsubscribers = [];
let messageUnsubscribe = null;
let messageChannelId = null;
const messagesEl = ref(null);

let firebase = null;
let db = null;
let storage = null;

const filteredChannels = computed(() => {
    const needle = search.value.trim().toLowerCase();
    return channels.value.filter((channel) => {
        const matchesTab = activeTab.value === 'channels'
            ? ['open', 'group'].includes(channel.type)
            : channel.type === 'dm';
        const matchesSearch = !needle || [channel.name, channel.subtitle, channel.lastMessage]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(needle));
        return matchesTab && matchesSearch;
    });
});

const activeChannel = computed(() =>
    channels.value.find((channel) => channel.id === selectedChannelId.value) ?? filteredChannels.value[0] ?? null
);
const totalUnread = computed(() => channels.value.reduce((sum, channel) => sum + Number(channel.unread || 0), 0));
const channelCounts = computed(() => ({
    channels: channels.value.filter((channel) => ['open', 'group'].includes(channel.type)).length,
    dms: channels.value.filter((channel) => channel.type === 'dm').length,
}));

watch(activeChannel, (channel) => {
    if (channel?.id && selectedChannelId.value !== channel.id) {
        selectedChannelId.value = channel.id;
    }
}, { immediate: false });

watch(selectedChannelId, (channelId, previousChannelId) => {
    if (channelId === previousChannelId) return;
    subscribeMessages(channelId);
}, { immediate: false });

watch(messages, () => {
    nextTick(() => {
        if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    });
});

onMounted(async () => {
    if (!firebaseReady.value) return;

    seedBuiltinChannels();
    loading.value = true;
    try {
        firebase = await loadFirebase();
        const app = firebase.getApps().length ? firebase.getApps()[0] : firebase.initializeApp(props.firebaseConfig);
        db = getOrCreateFirestore(app);
        storage = firebase.getStorage(app);
        const auth = firebase.getAuth(app);
        if (!auth.currentUser) {
            await withTimeout(firebase.signInAnonymously(auth), 'Firebase sign-in took too long. Check Authentication settings and network access.');
        }
        subscribeChannels();
        subscribeMessages(activeChannel.value?.id);
    } catch (error) {
        firebaseError.value = error?.message ?? 'Firebase could not be initialized.';
    } finally {
        loading.value = false;
    }
});

onUnmounted(() => {
    unsubscribers.splice(0).forEach((unsubscribe) => unsubscribe?.());
    messageUnsubscribe?.();
});

async function loadFirebase() {
    return {
        addDoc,
        collection,
        doc,
        getApps,
        getAuth,
        getDownloadURL,
        getFirestore,
        initializeFirestore,
        getStorage,
        initializeApp,
        limit,
        onSnapshot,
        orderBy,
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

function channelsCollection() {
    return firebase.collection(db, 'institutions', institutionKey.value, 'staffroomChannels');
}

function messagesCollection(channelId) {
    return firebase.collection(db, 'institutions', institutionKey.value, 'staffroomChannels', channelId, 'messages');
}

function seedBuiltinChannels() {
    channels.value = publicChannels.value.map((channel) => normalizeChannel(`open-${channel.id}`, {
        type: 'open',
        name: channel.name,
        subtitle: channel.subtitle,
        locked: channel.locked ?? true,
        sortOrder: channel.sortOrder ?? 99,
        memberIds: [],
        memberNames: [],
        lastMessage: '',
    })).sort(channelSort);

    if (!selectedChannelId.value && channels.value.length) {
        selectedChannelId.value = channels.value[0].id;
    }
}

async function ensureOpenChannels() {
    await Promise.all(publicChannels.value.map((channel) =>
        firebase.setDoc(firebase.doc(channelsCollection(), `open-${channel.id}`), {
            type: 'open',
            name: channel.name,
            subtitle: channel.subtitle,
            locked: true,
            sortOrder: channel.sortOrder ?? 99,
            memberIds: [],
            createdBy: 'system',
            updatedAt: firebase.serverTimestamp(),
        }, { merge: true })
    ));
}

function subscribeChannels() {
    const openQuery = firebase.query(channelsCollection(), firebase.where('type', '==', 'open'));
    const memberQuery = firebase.query(channelsCollection(), firebase.where('memberIds', 'array-contains', userKey.value));

    const mergeSnapshot = (snapshot) => {
        const incoming = snapshot.docs
            .filter((doc) => (doc.data().type ?? 'open') !== 'open' || defaultOpenChannelIds.value.has(doc.id))
            .map((doc) => normalizeChannel(doc.id, doc.data()));
        const merged = new Map(channels.value
            .filter((channel) => channel.type !== 'open' || defaultOpenChannelIds.value.has(channel.id))
            .map((channel) => [channel.id, channel]));
        incoming.forEach((channel) => merged.set(channel.id, channel));
        channels.value = [...merged.values()].sort(channelSort);
        if (!selectedChannelId.value && channels.value.length) selectedChannelId.value = channels.value[0].id;
    };

    unsubscribers.push(firebase.onSnapshot(openQuery, mergeSnapshot, (error) => {
        firebaseError.value = `Channels could not be loaded: ${error?.message ?? 'Firestore permission denied.'}`;
    }));
    unsubscribers.push(firebase.onSnapshot(memberQuery, mergeSnapshot, (error) => {
        firebaseError.value = `Private conversations could not be loaded: ${error?.message ?? 'Firestore permission denied.'}`;
    }));
}

function subscribeMessages(channelId) {
    if (!channelId || !db) return;
    if (messageChannelId === channelId) return;
    messageUnsubscribe?.();
    messageUnsubscribe = null;
    messageChannelId = channelId;
    messages.value = [];

    const queryRef = firebase.query(messagesCollection(channelId), firebase.orderBy('createdAt', 'asc'), firebase.limit(80));
    messageUnsubscribe = firebase.onSnapshot(queryRef, (snapshot) => {
        messages.value = snapshot.docs.map((doc) => ({
            id: doc.id,
            ...doc.data(),
            mine: doc.data().senderId === userKey.value,
        }));
    }, (error) => {
        firebaseError.value = `Messages could not be loaded: ${error?.message ?? 'Firestore permission denied.'}`;
    });
}

function withTimeout(promise, message, timeout = 30000) {
    return Promise.race([
        promise,
        new Promise((_, reject) => {
            window.setTimeout(() => reject(new Error(message)), timeout);
        }),
    ]);
}

function normalizeChannel(id, data) {
    const otherNames = (data.memberNames ?? []).filter((name) => name !== props.currentUser.name);
    return {
        id,
        type: data.type ?? 'open',
        name: data.type === 'dm' ? (otherNames[0] ?? data.name ?? 'Direct message') : data.name,
        subtitle: data.subtitle ?? (data.type === 'dm' ? 'Private conversation' : `${(data.memberIds ?? []).length || 'All'} members`),
        memberIds: data.memberIds ?? [],
        memberNames: data.memberNames ?? [],
        lastMessage: data.lastMessage ?? '',
        lastMessageAt: data.lastMessageAt,
        locked: Boolean(data.locked),
        sortOrder: data.sortOrder ?? 99,
        unread: 0,
    };
}

function channelSort(a, b) {
    if (a.type === 'open' && b.type === 'open') return Number(a.sortOrder) - Number(b.sortOrder);
    if (a.type === 'open' && b.type !== 'open') return -1;
    if (b.type === 'open' && a.type !== 'open') return 1;
    return String(b.lastMessageAt?.seconds ?? 0).localeCompare(String(a.lastMessageAt?.seconds ?? 0));
}

async function sendMessage() {
    if (!activeChannel.value || (!draft.value.trim() && !pendingUploads.value.length) || sending.value) return;

    sending.value = true;
    firebaseError.value = '';
    uploadProgress.value = pendingUploads.value.length ? `Uploading ${pendingUploads.value.length} file${pendingUploads.value.length === 1 ? '' : 's'}...` : '';
    try {
        const attachments = await uploadPendingFiles(activeChannel.value.id);
        uploadProgress.value = attachments.length ? 'Attaching files to message...' : '';
        const body = draft.value.trim();
        await firebase.addDoc(messagesCollection(activeChannel.value.id), {
            body,
            attachments,
            senderId: userKey.value,
            senderName: props.currentUser.name,
            senderRole: roleLabel.value,
            createdAt: firebase.serverTimestamp(),
        });
        await firebase.setDoc(firebase.doc(channelsCollection(), activeChannel.value.id), {
            lastMessage: body || `${attachments.length} attachment${attachments.length === 1 ? '' : 's'}`,
            lastMessageAt: firebase.serverTimestamp(),
            updatedAt: firebase.serverTimestamp(),
        }, { merge: true });
        draft.value = '';
        pendingUploads.value = [];
        uploadProgress.value = '';
    } catch (error) {
        firebaseError.value = friendlyFirebaseError(error, 'Message could not be sent.');
    } finally {
        sending.value = false;
        uploadProgress.value = '';
    }
}

async function uploadPendingFiles(channelId) {
    const files = [...pendingUploads.value];
    const uploads = files.map(async (file, index) => {
        uploadProgress.value = `Uploading ${index + 1} of ${files.length}: ${file.name}`;
        const cleanName = file.name.replace(/[^\w.\-() ]+/g, '_');
        const path = `institutions/${institutionKey.value}/staffroom/${channelId}/${Date.now()}-${index}-${cleanName}`;
        const ref = firebase.ref(storage, path);
        await withTimeout(firebase.uploadBytes(ref, file, {
            contentType: file.type || 'application/octet-stream',
            customMetadata: {
                senderId: userKey.value,
                senderName: props.currentUser.name ?? 'User',
                channelId,
            },
        }), `Upload timed out for ${file.name}. Check Firebase Storage rules and network access.`);
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

function pickFiles() {
    uploadInput.value?.click();
}

function addPendingFiles(event) {
    firebaseError.value = '';
    const selectedFiles = Array.from(event.target.files ?? []);
    const maxSize = 20 * 1024 * 1024;
    const oversized = selectedFiles.filter((file) => file.size > maxSize);
    const accepted = selectedFiles.filter((file) => file.size <= maxSize);

    if (oversized.length) {
        firebaseError.value = `${oversized.length} file${oversized.length === 1 ? '' : 's'} skipped. Each upload must be 20 MB or smaller.`;
    }

    pendingUploads.value = [...pendingUploads.value, ...accepted].slice(0, 6);
    event.target.value = '';
}

function removePendingFile(index) {
    pendingUploads.value = pendingUploads.value.filter((_, fileIndex) => fileIndex !== index);
}

async function startDm(person) {
    const ids = [userKey.value, person.id].sort();
    const channelId = `dm-${ids.join('--')}`;
    await firebase.setDoc(firebase.doc(channelsCollection(), channelId), {
        type: 'dm',
        name: person.name,
        subtitle: 'Private conversation',
        memberIds: ids,
        memberNames: [props.currentUser.name, person.name],
        createdBy: userKey.value,
        updatedAt: firebase.serverTimestamp(),
    }, { merge: true });
    selectedChannelId.value = channelId;
    activeTab.value = 'dms';
    showDmModal.value = false;
}

async function createGroup() {
    const name = groupForm.value.name.trim();
    const memberIds = [...new Set([userKey.value, ...groupForm.value.memberIds])];
    if (!name || memberIds.length < 2) return;

    const members = props.directory.filter((person) => memberIds.includes(person.id));
    const docRef = await firebase.addDoc(channelsCollection(), {
        type: 'group',
        name,
        subtitle: `${memberIds.length} members`,
        memberIds,
        memberNames: [props.currentUser.name, ...members.map((person) => person.name)].filter(Boolean),
        createdBy: userKey.value,
        createdAt: firebase.serverTimestamp(),
        updatedAt: firebase.serverTimestamp(),
    });
    selectedChannelId.value = docRef.id;
    activeTab.value = 'channels';
    groupForm.value = { name: '', memberIds: [] };
    showGroupModal.value = false;
}

function toggleGroupMember(personId) {
    groupForm.value.memberIds = groupForm.value.memberIds.includes(personId)
        ? groupForm.value.memberIds.filter((id) => id !== personId)
        : [...groupForm.value.memberIds, personId];
}

function formatTime(message) {
    const date = message.createdAt?.toDate?.();
    return date ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Sending';
}

function fileSize(size) {
    if (!size) return '';
    if (size < 1024 * 1024) return `${Math.ceil(size / 1024)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function openImageViewer(file) {
    imageViewer.value = file;
}

function closeImageViewer() {
    imageViewer.value = null;
}

function friendlyFirebaseError(error, fallback) {
    const code = error?.code ?? '';
    const message = error?.message ?? fallback;

    if (code === 'storage/unauthorized' || message.toLowerCase().includes('permission')) {
        return 'File upload was blocked by Firebase Storage rules. Enable Storage and allow authenticated read/write for the staffroom path.';
    }

    if (code === 'storage/bucket-not-found' || message.toLowerCase().includes('bucket')) {
        return 'Firebase Storage bucket was not found. Check FIREBASE_STORAGE_BUCKET in .env and make sure Storage is enabled in Firebase.';
    }

    if (code === 'storage/retry-limit-exceeded' || message.toLowerCase().includes('timed out')) {
        return message;
    }

    return message;
}
</script>

<template>
    <AppLayout title="Staffroom" flush>
        <div class="staffroom-shell h-[calc(100vh-4rem)]">
            <section v-if="!firebaseReady" class="surface-card p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                        <AlertCircle class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-base font-black text-[#1e2924]">Add Firebase keys to enable chat</p>
                        <p class="mt-1 text-sm font-semibold text-slate-500">The UI is ready, but Firestore and Storage need your Firebase web app config in `.env`.</p>
                    </div>
                </div>
            </section>

            <section v-else class="staffroom-frame grid h-full overflow-hidden xl:grid-cols-[19.5rem_minmax(0,1fr)_17rem]">
                <aside class="flex min-h-0 flex-col border-b border-stone-200 bg-white text-[#1e2924] xl:border-b-0 xl:border-r">
                    <div class="border-b border-stone-200 bg-white p-4">
                        <div class="flex items-center gap-2">
                            <div class="relative grid min-w-0 flex-1 grid-cols-2 overflow-hidden rounded-2xl border border-[#8BED9A]/50 bg-[#8BED9A]/10 p-1.5 shadow-sm shadow-[#8BED9A]/15">
                                <div
                                    class="absolute inset-y-1.5 left-1.5 w-[calc(50%-0.375rem)] rounded-xl bg-[#1e2924] shadow-lg shadow-[#1e2924]/15 transition-transform duration-300 ease-out"
                                    :class="activeTab === 'dms' ? 'translate-x-full' : 'translate-x-0'"
                                ></div>
                                <button
                                    type="button"
                                    class="relative z-10 flex min-h-11 items-center justify-center gap-2 rounded-xl px-2 text-sm font-black transition-colors duration-300"
                                    :class="activeTab === 'channels' ? 'text-white' : 'text-[#1e2924] hover:text-[#09B884]'"
                                    @click="activeTab = 'channels'"
                                >
                                    <span>Public</span>
                                    <span
                                        class="min-w-6 rounded-full border px-1.5 py-0.5 text-center text-[11px] font-black leading-none transition-colors duration-300"
                                        :class="activeTab === 'channels' ? 'border-white/20 bg-white/16 text-white' : 'border-[#8BED9A]/70 bg-white text-[#1e2924]'"
                                    >
                                        {{ channelCounts.channels }}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="relative z-10 flex min-h-11 items-center justify-center gap-2 rounded-xl px-2 text-sm font-black transition-colors duration-300"
                                    :class="activeTab === 'dms' ? 'text-white' : 'text-[#1e2924] hover:text-[#09B884]'"
                                    @click="activeTab = 'dms'"
                                >
                                    <span>DMs</span>
                                    <span
                                        class="min-w-6 rounded-full border px-1.5 py-0.5 text-center text-[11px] font-black leading-none transition-colors duration-300"
                                        :class="activeTab === 'dms' ? 'border-white/20 bg-white/16 text-white' : 'border-[#8BED9A]/70 bg-white text-[#1e2924]'"
                                    >
                                        {{ channelCounts.dms }}
                                    </span>
                                </button>
                            </div>
                            <button
                                type="button"
                                class="chat-icon-button h-12 w-12 shrink-0 rounded-2xl"
                                :title="activeTab === 'dms' ? 'New direct message' : 'New group'"
                                :disabled="!firebaseReady"
                                @click="activeTab === 'dms' ? showDmModal = true : showGroupModal = true"
                            >
                                <Plus class="h-5 w-5" />
                            </button>
                        </div>
                        <div class="relative mt-3">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="search" class="field-control w-full pl-9" placeholder="Search conversations" />
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 space-y-1 overflow-y-auto p-2">
                        <div v-if="loading" class="m-2 flex items-center justify-center gap-2 rounded-xl border border-stone-200 bg-stone-50 p-5 text-sm font-semibold text-slate-500">
                            <Loader2 class="h-4 w-4 animate-spin" />
                            Connecting
                        </div>
                        <button
                            v-for="channel in filteredChannels"
                            :key="channel.id"
                            type="button"
                            class="group w-full rounded-xl border p-3 text-left transition-all duration-200"
                            :class="activeChannel?.id === channel.id ? 'border-[#8BED9A]/70 bg-[#8BED9A]/16 shadow-sm' : 'border-transparent hover:border-stone-200 hover:bg-stone-50'"
                            @click="selectedChannelId = channel.id"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-stone-200 bg-white text-slate-500 shadow-sm">
                                    <MessageCircle v-if="channel.type === 'dm'" class="h-4 w-4" />
                                    <UsersRound v-else-if="channel.type === 'group'" class="h-4 w-4" />
                                    <Hash v-else class="h-4 w-4" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="truncate text-sm font-black text-slate-950">{{ channel.name }}</p>
                                        <Lock v-if="channel.locked" class="h-3.5 w-3.5 text-slate-400" />
                                    </div>
                                    <p class="mt-0.5 truncate text-xs font-semibold text-slate-500">{{ channel.lastMessage || channel.subtitle }}</p>
                                </div>
                            </div>
                        </button>
                        <div v-if="!filteredChannels.length && !loading" class="m-2 rounded-xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-sm font-semibold text-slate-500">
                            No conversations yet.
                        </div>
                    </div>
                </aside>

                <main class="flex min-h-0 min-w-0 flex-col bg-white">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 bg-white px-5 py-3.5">
                        <div v-if="activeChannel" class="min-w-0">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-stone-200 bg-white text-slate-500">
                                    <Hash v-if="activeChannel.type === 'open'" class="h-4 w-4" />
                                    <UsersRound v-else-if="activeChannel.type === 'group'" class="h-4 w-4" />
                                    <MessageCircle v-else class="h-4 w-4" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-base font-black text-[#1e2924]">{{ activeChannel.name }}</p>
                                    <p class="truncate text-xs font-semibold text-slate-500">{{ activeChannel.subtitle }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="inline-flex items-center gap-1.5 rounded-full border border-stone-200 bg-stone-50 px-3 py-1.5 text-xs font-black text-[#1e2924]">
                            <ShieldCheck class="h-3.5 w-3.5 text-[#09B884]" />
                            Live
                        </div>
                    </header>

                    <div ref="messagesEl" class="min-h-0 flex-1 overflow-y-auto bg-[#f7faf8] p-5">
                        <TransitionGroup name="chat-list" tag="div" class="space-y-4">
                            <div v-for="message in messages" :key="message.id" class="flex" :class="message.mine ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[78%] rounded-2xl border px-4 py-3 shadow-sm" :class="message.mine ? 'border-[#1e2924] bg-[#1e2924] text-white' : 'border-stone-200 bg-white text-slate-700'">
                                    <div class="mb-1 flex flex-wrap items-center gap-2 text-xs">
                                        <span class="font-black" :class="message.mine ? 'text-[#8BED9A]' : 'text-[#1e2924]'">{{ message.senderName }}</span>
                                        <span :class="message.mine ? 'text-white/50' : 'text-slate-400'">{{ message.senderRole }}</span>
                                        <span :class="message.mine ? 'text-white/50' : 'text-slate-400'">{{ formatTime(message) }}</span>
                                    </div>
                                    <p v-if="message.body" class="whitespace-pre-line text-sm leading-relaxed" :class="message.mine ? 'text-white/90' : 'text-slate-600'">{{ message.body }}</p>
                                    <div v-if="message.attachments?.length" class="mt-3 grid gap-2">
                                        <button
                                            v-for="file in message.attachments.filter((attachment) => attachment.type?.startsWith('image/'))"
                                            :key="file.url"
                                            type="button"
                                            class="group relative overflow-hidden rounded-xl border text-left transition hover:scale-[1.01]"
                                            :class="message.mine ? 'border-white/15 bg-white/10' : 'border-stone-200 bg-stone-50'"
                                            @click="openImageViewer(file)"
                                        >
                                            <img :src="file.url" :alt="file.name" class="max-h-64 w-full rounded-xl object-cover" loading="lazy" />
                                            <span class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-black/70 to-transparent px-3 pb-2 pt-8 text-xs font-bold text-white opacity-0 transition group-hover:opacity-100">
                                                <span class="truncate">{{ file.name }}</span>
                                                <Maximize2 class="h-3.5 w-3.5 shrink-0" />
                                            </span>
                                        </button>

                                        <a
                                            v-for="file in message.attachments.filter((attachment) => !attachment.type?.startsWith('image/'))"
                                            :key="file.url"
                                            :href="file.url"
                                            target="_blank"
                                            class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold transition"
                                            :class="message.mine ? 'border-white/15 bg-white/10 text-white hover:bg-white/15' : 'border-stone-200 bg-stone-50 text-[#1e2924] hover:bg-stone-100'"
                                        >
                                            <FileText class="h-4 w-4" />
                                            <span class="truncate">{{ file.name }}</span>
                                            <span class="shrink-0 opacity-60">{{ fileSize(file.size) }}</span>
                                        </a>
                                    </div>
                                    <div v-if="message.mine" class="mt-2 flex justify-end text-[#8BED9A]">
                                        <CheckCheck class="h-4 w-4" />
                                    </div>
                                </div>
                            </div>
                        </TransitionGroup>
                        <div v-if="!messages.length" class="flex min-h-[24rem] items-center justify-center">
                            <div class="text-center">
                                <MessageCircle class="mx-auto h-8 w-8 text-[#09B884]" />
                                <p class="mt-2 text-sm font-black text-[#1e2924]">Start the conversation</p>
                                <p class="mt-1 text-sm font-semibold text-slate-500">Messages, images, and files will sync through Firebase.</p>
                            </div>
                        </div>
                    </div>

                    <footer class="shrink-0 border-t border-stone-200 bg-white px-4 py-3">
                        <div v-if="firebaseError" class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{{ firebaseError }}</div>
                        <div v-if="uploadProgress" class="mb-3 rounded-xl border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-3 py-2 text-xs font-bold text-[#1e2924]">{{ uploadProgress }}</div>
                        <div v-if="pendingUploads.length" class="mb-3 flex flex-wrap gap-2">
                            <span v-for="(file, index) in pendingUploads" :key="`${file.name}-${index}`" class="inline-flex items-center gap-2 rounded-xl border border-[#8BED9A]/60 bg-[#8BED9A]/12 px-3 py-2 text-xs font-bold text-[#1e2924]">
                                <Image v-if="file.type?.startsWith('image/')" class="h-3.5 w-3.5 text-[#09B884]" />
                                <FileText v-else class="h-3.5 w-3.5 text-[#09B884]" />
                                <span class="max-w-44 truncate">{{ file.name }}</span>
                                <span class="text-slate-500">{{ fileSize(file.size) }}</span>
                                <button type="button" @click="removePendingFile(index)"><X class="h-3.5 w-3.5" /></button>
                            </span>
                        </div>
                        <div class="flex items-end gap-2 rounded-2xl border border-stone-200 bg-stone-50 p-2 shadow-inner">
                            <input ref="uploadInput" type="file" class="hidden" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" @change="addPendingFiles" />
                            <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-[#09B884] shadow-sm ring-1 ring-stone-200 transition hover:bg-[#8BED9A]/15 hover:ring-[#8BED9A]/70" @click="pickFiles">
                                <Paperclip class="h-4 w-4" />
                            </button>
                            <textarea v-model="draft" rows="1" class="min-h-10 flex-1 resize-none border-0 bg-transparent px-2 py-2 text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-0" placeholder="Write a message" @keydown.enter.exact.prevent="sendMessage"></textarea>
                            <button type="button" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-[#1e2924] px-4 text-sm font-black text-white shadow-sm transition hover:bg-[#2c423a] disabled:bg-slate-300" :disabled="sending || (!draft.trim() && !pendingUploads.length)" @click="sendMessage">
                                <Loader2 v-if="sending" class="h-4 w-4 animate-spin" />
                                <Send v-else class="h-4 w-4" />
                                Send
                            </button>
                        </div>
                    </footer>
                </main>

                <aside class="hidden min-h-0 flex-col border-l border-stone-200 bg-[#fbfcfb] xl:flex">
                    <div class="border-b border-stone-200 bg-white p-4">
                        <p class="text-sm font-black text-[#1e2924]">Staff directory</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ staffList.length }} people available</p>
                    </div>
                    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-4">
                        <button v-for="person in staffList.slice(0, 12)" :key="person.id" type="button" class="flex w-full items-center gap-3 rounded-xl border border-stone-200 bg-white p-3 text-left transition hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/10" :disabled="!person.canDm" @click="startDm(person)">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-stone-200 bg-white text-xs font-black text-slate-500">{{ person.name.split(' ').map((part) => part[0]).slice(0, 2).join('') }}</span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-black text-[#1e2924]">{{ person.name }}</span>
                                <span class="block truncate text-xs font-semibold text-slate-500">{{ person.subtitle }}</span>
                            </span>
                        </button>
                    </div>
                </aside>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="showDmModal || showGroupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-[#1e2924]/35 p-4 backdrop-blur-sm" @click.self="showDmModal = false; showGroupModal = false">
                <div class="surface-card w-full max-w-xl overflow-hidden shadow-2xl">
                    <div class="flex items-center justify-between border-b border-stone-200 bg-white p-5">
                        <p class="text-base font-black text-[#1e2924]">{{ showDmModal ? 'Start direct message' : 'Create staff group' }}</p>
                        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-stone-100" @click="showDmModal = false; showGroupModal = false"><X class="h-4 w-4" /></button>
                    </div>
                    <div class="max-h-[70vh] overflow-y-auto p-5">
                        <div v-if="showGroupModal" class="mb-4">
                            <label class="section-title">Group name</label>
                            <input v-model="groupForm.name" class="field-control mt-1 w-full" placeholder="Example: Science department" />
                        </div>
                        <div class="grid gap-2">
                            <button v-for="person in (showDmModal ? availableDmPeople : staffList)" :key="person.id" type="button" class="flex items-center justify-between gap-3 rounded-xl border p-3 text-left transition hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/10" :class="groupForm.memberIds.includes(person.id) ? 'border-[#09B884]/70 bg-[#8BED9A]/15' : 'border-stone-200 bg-white'" @click="showDmModal ? startDm(person) : toggleGroupMember(person.id)">
                                <span class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-stone-200 bg-white text-xs font-black text-slate-500 shadow-sm">{{ person.name.split(' ').map((part) => part[0]).slice(0, 2).join('') }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-black text-[#1e2924]">{{ person.name }}</span>
                                        <span class="block truncate text-xs font-semibold text-slate-500">{{ person.subtitle }}</span>
                                    </span>
                                </span>
                                <span v-if="showGroupModal" class="rounded-full border px-2 py-1 text-xs font-black" :class="groupForm.memberIds.includes(person.id) ? 'border-[#09B884]/60 bg-[#8BED9A]/20 text-[#1e2924]' : 'border-stone-200 text-slate-500'">
                                    {{ groupForm.memberIds.includes(person.id) ? 'Added' : 'Add' }}
                                </span>
                            </button>
                            <div v-if="showDmModal && !availableDmPeople.length" class="rounded-xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-sm font-semibold text-slate-500">
                                No staff members are available for direct messages yet.
                            </div>
                        </div>
                    </div>
                    <div v-if="showGroupModal" class="border-t border-stone-200 bg-white p-4">
                        <button type="button" class="btn-primary w-full justify-center" :disabled="!groupForm.name.trim() || !groupForm.memberIds.length" @click="createGroup">
                            <Plus class="h-4 w-4" />
                            Create group
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <Transition name="image-viewer">
                <div
                    v-if="imageViewer"
                    class="fixed inset-0 z-[60] flex items-center justify-center bg-[#07110e]/85 p-4 backdrop-blur-md"
                    @click.self="closeImageViewer"
                >
                    <div class="relative max-h-[92vh] w-full max-w-6xl">
                        <button
                            type="button"
                            class="absolute right-3 top-3 z-10 flex h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-black/45 text-white shadow-lg backdrop-blur transition hover:bg-black/65"
                            @click="closeImageViewer"
                        >
                            <X class="h-5 w-5" />
                        </button>
                        <img
                            :src="imageViewer.url"
                            :alt="imageViewer.name"
                            class="mx-auto max-h-[82vh] w-auto max-w-full rounded-2xl border border-white/10 object-contain shadow-2xl shadow-black/40"
                        />
                        <div class="mx-auto mt-3 flex max-w-3xl flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm font-bold text-white backdrop-blur">
                            <span class="min-w-0 truncate">{{ imageViewer.name }}</span>
                            <a :href="imageViewer.url" target="_blank" class="rounded-full bg-[#8BED9A] px-4 py-2 text-xs font-black text-[#1e2924] transition hover:bg-white">
                                Open original
                            </a>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.staffroom-shell {
    animation: staffroom-rise 420ms ease-out both;
}

.staffroom-frame {
    border: 0;
    border-radius: 0;
    background: white;
    box-shadow: none;
}

.chat-list-enter-active,
.chat-list-leave-active {
    transition: all 240ms ease;
}

.chat-list-enter-from {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}

.chat-list-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
}

.chat-icon-button {
    display: inline-flex;
    height: 2.35rem;
    width: 2.35rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: #ffffff;
    color: #1e2924;
    transition: transform 180ms ease, box-shadow 180ms ease, background 180ms ease;
    box-shadow: 0 8px 18px rgba(30, 41, 36, 0.06);
}

.chat-icon-button:hover:not(:disabled) {
    transform: translateY(-1px);
    border-color: rgba(139, 237, 154, 0.8);
    background: rgba(139, 237, 154, 0.12);
    box-shadow: 0 10px 24px rgba(30, 41, 36, 0.1);
}

.chat-icon-button-primary {
    border-color: #1e2924;
    background: #1e2924;
    color: #8BED9A;
}

.chat-icon-button-primary:hover:not(:disabled) {
    background: #1e2924;
}

.chat-icon-button-dark {
    display: inline-flex;
    height: 2.35rem;
    width: 2.35rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.8rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.06);
    color: #d8f7df;
    transition: transform 160ms ease, background 160ms ease, color 160ms ease;
}

.chat-icon-button-dark:hover:not(:disabled) {
    transform: translateY(-1px);
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.chat-icon-button-dark-primary {
    border-color: rgba(139, 237, 154, 0.45);
    background: #8BED9A;
    color: #1e2924;
}

.chat-icon-button-dark-primary:hover:not(:disabled) {
    background: #b7f7bf;
    color: #1e2924;
}

.image-viewer-enter-active,
.image-viewer-leave-active {
    transition: opacity 180ms ease;
}

.image-viewer-enter-active img,
.image-viewer-leave-active img {
    transition: transform 180ms ease, opacity 180ms ease;
}

.image-viewer-enter-from,
.image-viewer-leave-to {
    opacity: 0;
}

.image-viewer-enter-from img,
.image-viewer-leave-to img {
    opacity: 0;
    transform: scale(0.96);
}

@keyframes staffroom-rise {
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
