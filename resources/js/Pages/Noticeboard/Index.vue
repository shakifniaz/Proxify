<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    BadgeCheck,
    CheckCircle2,
    ChevronDown,
    Edit3,
    Eye,
    Megaphone,
    Plus,
    Search,
    Send,
    ShieldCheck,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    notices: { type: Array, default: () => [] },
    institutionalNotices: { type: Array, default: () => [] },
    staffNotices: { type: Array, default: () => [] },
    urgencyOptions: { type: Array, default: () => ['Low', 'Normal', 'Important', 'Urgent'] },
    visibilityOptions: { type: Array, default: () => ['All', 'Teachers', 'Admins'] },
    totalStaff: { type: Number, default: 0 },
});

const page = usePage();
const authUser = computed(() => ({
    name: page.props.auth?.user?.name ?? 'User',
    role: page.props.auth?.user?.role ?? 'admin',
}));
const isAdmin = computed(() => authUser.value.role?.toLowerCase() === 'admin');
const isTeacher = computed(() => authUser.value.role?.toLowerCase() === 'teacher');
const canUseStaffBoard = computed(() => isAdmin.value || isTeacher.value);

const institutional = ref((props.institutionalNotices.length ? props.institutionalNotices : props.notices).map((notice) => ({
    visibility: 'All',
    totalViewers: props.totalStaff,
    ...notice,
})));
const staff = ref(props.staffNotices.map((notice) => ({
    acknowledgedBy: [],
    owner: notice.postedBy === authUser.value.name,
    ...notice,
})));

watch(() => props.institutionalNotices, (notices) => {
    institutional.value = (notices.length ? notices : props.notices).map((notice) => ({
        visibility: 'All',
        totalViewers: props.totalStaff,
        ...notice,
    }));
}, { deep: true });

watch(() => props.staffNotices, (notices) => {
    staff.value = notices.map((notice) => ({
        acknowledgedBy: [],
        owner: notice.postedBy === authUser.value.name,
        ...notice,
    }));
}, { deep: true });

const activeTab = ref('institutional');
const search = ref('');
const urgencyFilter = ref('All urgency');
const visibilityFilter = ref('All visibility');
const editingNoticeId = ref(null);

const newNotice = () => ({
    title: '',
    message: '',
    urgency: 'Normal',
    visibility: 'Teachers',
});

const form = ref(newNotice());

const urgencyStyles = {
    Low: {
        card: 'border-slate-300 bg-slate-100 shadow-slate-200/70',
        badge: 'border-slate-300 bg-white/85 text-slate-700',
        glow: 'from-slate-400/70',
        dot: 'bg-slate-400',
    },
    Normal: {
        card: 'border-[#8BED9A] bg-[#8BED9A]/35 shadow-[#8BED9A]/30',
        badge: 'border-[#8BED9A] bg-white/85 text-[#1e2924]',
        glow: 'from-[#09B884]/80',
        dot: 'bg-[#09B884]',
    },
    Important: {
        card: 'border-amber-300 bg-amber-100 shadow-amber-200/60',
        badge: 'border-amber-300 bg-white/85 text-amber-900',
        glow: 'from-amber-500/80',
        dot: 'bg-amber-500',
    },
    Urgent: {
        card: 'border-red-300 bg-red-100 shadow-red-200/70',
        badge: 'border-red-300 bg-white/90 text-red-800',
        glow: 'from-red-600/85',
        dot: 'bg-red-500',
    },
};

const currentList = computed(() => activeTab.value === 'institutional' ? institutional.value : staff.value);
const canCreate = computed(() => (activeTab.value === 'institutional' && isAdmin.value) || (activeTab.value === 'staff' && canUseStaffBoard.value));
const showVisibility = computed(() => activeTab.value === 'institutional');
const filteredNotices = computed(() => currentList.value.filter((notice) => {
    const query = search.value.trim().toLowerCase();
    const matchesQuery = !query || [notice.title, notice.message, notice.postedBy, notice.visibility]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(query));
    const matchesUrgency = urgencyFilter.value === 'All urgency' || notice.urgency === urgencyFilter.value;
    const canSeeNotice = activeTab.value !== 'institutional'
        || isAdmin.value
        || notice.visibility === 'All'
        || (notice.visibility === 'Teachers' && isTeacher.value);
    const matchesVisibility = activeTab.value !== 'institutional'
        || visibilityFilter.value === 'All visibility'
        || notice.visibility === visibilityFilter.value;

    return canSeeNotice && matchesQuery && matchesUrgency && matchesVisibility;
}));

const tabStats = computed(() => ({
    institutional: institutional.value.length,
    staff: staff.value.length,
}));

function styleFor(urgency) {
    return urgencyStyles[urgency] ?? urgencyStyles.Normal;
}

function resetForm() {
    editingNoticeId.value = null;
    form.value = newNotice();
    if (activeTab.value === 'institutional') {
        form.value.visibility = 'Teachers';
    }
}

function submitNotice() {
    if (!form.value.title.trim() || !form.value.message.trim()) return;

    const payload = {
        board: activeTab.value,
        title: form.value.title,
        message: form.value.message,
        urgency: form.value.urgency,
        visibility: activeTab.value === 'institutional' ? form.value.visibility : undefined,
    };

    if (editingNoticeId.value) {
        router.patch(`/noticeboard/${editingNoticeId.value}`, payload, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: resetForm,
        });
        return;
    }

    router.post('/noticeboard', payload, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: resetForm,
    });
}

function editNotice(notice) {
    editingNoticeId.value = notice.id;
    form.value = {
        title: notice.title,
        message: notice.message,
        urgency: notice.urgency,
        visibility: notice.visibility ?? 'Teachers',
    };
}

function deleteNotice(notice) {
    router.delete(`/noticeboard/${notice.id}`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            if (editingNoticeId.value === notice.id) resetForm();
        },
    });
}

function canManageNotice(notice) {
    return isAdmin.value || (activeTab.value === 'staff' && notice.owner);
}

function hasAcknowledged(notice) {
    return notice.acknowledgedBy?.includes(authUser.value.name);
}

function toggleAcknowledge(notice) {
    if (activeTab.value !== 'staff') return;
    router.post(`/noticeboard/${notice.id}/acknowledge`, {}, {
        preserveScroll: true,
        preserveState: false,
    });
}

function selectTab(tab) {
    if (tab === 'staff' && !canUseStaffBoard.value) return;
    activeTab.value = tab;
    search.value = '';
    urgencyFilter.value = 'All urgency';
    visibilityFilter.value = 'All visibility';
    resetForm();
}
</script>

<template>
    <AppLayout title="Noticeboard" live-refresh :refresh-interval="10000">
        <div class="notice-shell relative overflow-hidden rounded-xl border border-[#8BED9A]/45 bg-white shadow-sm">
            <div class="pointer-events-none absolute -right-28 -top-28 h-72 w-72 rounded-full bg-[#8BED9A]/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-36 left-1/4 h-72 w-72 rounded-full bg-[#09B884]/10 blur-3xl"></div>

            <div class="relative border-b border-[#8BED9A]/35 bg-white/80 px-4 py-4 backdrop-blur sm:px-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center">
                    <div
                        class="relative grid w-full overflow-hidden rounded-2xl border border-[#8BED9A]/50 bg-[#8BED9A]/10 p-1.5 shadow-sm shadow-[#8BED9A]/20"
                        :class="canUseStaffBoard ? 'grid-cols-2 sm:w-[31rem]' : 'grid-cols-1 sm:w-[16rem]'"
                    >
                        <div
                            v-if="canUseStaffBoard"
                            class="absolute inset-y-1.5 left-1.5 w-[calc(50%-0.375rem)] rounded-xl bg-[#1e2924] shadow-lg shadow-[#1e2924]/20 transition-transform duration-300 ease-out"
                            :class="activeTab === 'staff' ? 'translate-x-full' : 'translate-x-0'"
                        ></div>
                        <div v-else class="absolute inset-1.5 rounded-xl bg-[#1e2924] shadow-lg shadow-[#1e2924]/20"></div>
                        <button
                            type="button"
                            class="relative z-10 flex min-h-14 items-center justify-between gap-3 rounded-xl px-3 text-left transition-colors duration-300"
                            :class="activeTab === 'institutional' ? 'text-white' : 'text-[#1e2924] hover:text-[#09B884]'"
                            @click="selectTab('institutional')"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition-colors duration-300"
                                    :class="activeTab === 'institutional' ? 'border-white/15 bg-white/14 text-[#BDF8C8]' : 'border-[#8BED9A]/60 bg-white/80 text-[#09B884]'"
                                >
                                    <ShieldCheck class="h-4 w-4" />
                                </span>
                                <span class="truncate text-sm font-black">Institutional</span>
                            </span>
                            <span
                                class="min-w-8 shrink-0 rounded-full border px-2 py-1 text-center text-xs font-black transition-colors duration-300"
                                :class="activeTab === 'institutional' ? 'border-white/20 bg-white/16 text-white' : 'border-[#8BED9A]/70 bg-white/80 text-[#1e2924]'"
                            >
                                {{ tabStats.institutional }}
                            </span>
                        </button>
                        <button
                            v-if="canUseStaffBoard"
                            type="button"
                            class="relative z-10 flex min-h-14 items-center justify-between gap-3 rounded-xl px-3 text-left transition-colors duration-300"
                            :class="activeTab === 'staff' ? 'text-white' : 'text-[#1e2924] hover:text-[#09B884]'"
                            @click="selectTab('staff')"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition-colors duration-300"
                                    :class="activeTab === 'staff' ? 'border-white/15 bg-white/14 text-[#BDF8C8]' : 'border-[#8BED9A]/60 bg-white/80 text-[#09B884]'"
                                >
                                    <Users class="h-4 w-4" />
                                </span>
                                <span class="truncate text-sm font-black">Staff board</span>
                            </span>
                            <span
                                class="min-w-8 shrink-0 rounded-full border px-2 py-1 text-center text-xs font-black transition-colors duration-300"
                                :class="activeTab === 'staff' ? 'border-white/20 bg-white/16 text-white' : 'border-[#8BED9A]/70 bg-white/80 text-[#1e2924]'"
                            >
                                {{ tabStats.staff }}
                            </span>
                        </button>
                    </div>

                </div>
            </div>

            <div
                class="relative grid min-w-0 gap-px overflow-hidden bg-stone-200/80"
                :class="canCreate ? 'xl:grid-cols-[minmax(0,1fr)_25rem]' : 'xl:grid-cols-1'"
            >
                <section class="min-h-[36rem] min-w-0 bg-white/90 p-4 sm:p-5">
                    <div class="flex min-w-0 flex-nowrap items-center gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 text-[#09B884]">
                                <Megaphone class="h-4 w-4" />
                            </div>
                            <p class="min-w-0 max-w-40 text-sm font-bold leading-tight text-slate-950">
                                {{ activeTab === 'institutional' ? 'Institutional notices' : 'Staff notices' }}
                            </p>
                        </div>

                        <div
                            class="ml-auto grid min-w-0 flex-1 gap-2"
                            :class="activeTab === 'institutional'
                                ? 'grid-cols-[minmax(12rem,1fr)_9.5rem_9.5rem]'
                                : 'grid-cols-[minmax(12rem,1fr)_9.5rem]'"
                        >
                            <div class="relative min-w-0">
                                <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                <input v-model="search" type="text" class="field-control w-full pl-9" placeholder="Search notices" />
                            </div>
                            <div class="relative min-w-0">
                                <select v-model="urgencyFilter" class="field-control w-full appearance-none bg-white pl-3 pr-10">
                                    <option>All urgency</option>
                                    <option v-for="urgency in urgencyOptions" :key="urgency">{{ urgency }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                            </div>
                            <div v-if="activeTab === 'institutional'" class="relative min-w-0">
                                <select v-model="visibilityFilter" class="field-control w-full appearance-none bg-white pl-3 pr-10">
                                    <option>All visibility</option>
                                    <option v-for="visibility in visibilityOptions" :key="visibility">{{ visibility }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3">
                        <article
                            v-for="notice in filteredNotices"
                            :key="notice.id"
                            class="group relative overflow-hidden rounded-xl border p-4 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                            :class="styleFor(notice.urgency).card"
                        >
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide" :class="styleFor(notice.urgency).badge">
                                            <span class="h-1.5 w-1.5 rounded-full" :class="styleFor(notice.urgency).dot"></span>
                                            {{ notice.urgency }}
                                        </span>
                                        <span v-if="activeTab === 'institutional'" class="rounded-full border border-stone-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                            {{ notice.visibility ?? 'Teachers' }}
                                        </span>
                                        <span v-else class="rounded-full border border-[#8BED9A]/70 bg-white px-2.5 py-1 text-[11px] font-semibold text-[#1e2924]">
                                            {{ notice.postedBy }}
                                        </span>
                                    </div>
                                    <h3 class="mt-3 text-base font-bold text-slate-950">{{ notice.title }}</h3>
                                    <p class="mt-2 max-w-4xl whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ notice.message }}</p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <button
                                        v-if="activeTab === 'staff'"
                                        type="button"
                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-3 text-xs font-bold transition"
                                        :class="hasAcknowledged(notice) ? 'border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#1e2924]' : 'border-stone-200 bg-white text-slate-600 hover:border-[#09B884]/40 hover:text-[#1e2924]'"
                                        @click="toggleAcknowledge(notice)"
                                    >
                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                        {{ hasAcknowledged(notice) ? 'Acknowledged' : 'Acknowledge' }}
                                    </button>
                                    <button
                                        v-if="canManageNotice(notice)"
                                        type="button"
                                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-stone-200 bg-white text-slate-500 transition hover:border-[#09B884]/40 hover:text-[#1e2924]"
                                        title="Edit notice"
                                        @click="editNotice(notice)"
                                    >
                                        <Edit3 class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="canManageNotice(notice)"
                                        type="button"
                                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-white text-red-600 transition hover:bg-red-50"
                                        title="Delete notice"
                                        @click="deleteNotice(notice)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-white/70 pt-3 text-xs text-slate-500">
                                <span>Posted by <strong class="font-semibold text-slate-700">{{ notice.postedBy }}</strong> - {{ notice.postedDate }}</span>
                                <span v-if="activeTab === 'institutional'" class="inline-flex items-center gap-1">
                                    <Eye class="h-3.5 w-3.5" />
                                    {{ notice.readCount ?? 0 }}/{{ notice.totalViewers ?? props.totalStaff }} read
                                </span>
                                <span v-else class="inline-flex items-center gap-1">
                                    <BadgeCheck class="h-3.5 w-3.5 text-[#09B884]" />
                                    {{ notice.acknowledgedBy?.length ?? 0 }} acknowledged
                                </span>
                            </div>
                        </article>

                        <div v-if="filteredNotices.length === 0" class="rounded-xl border border-dashed border-stone-300 bg-white/80 py-14 text-center text-sm font-medium text-slate-500">
                            No notices match the current filters.
                        </div>
                    </div>
                </section>

                <aside v-if="canCreate" class="min-w-0 bg-white/95 p-4 sm:p-5">
                    <div class="sticky top-6 rounded-xl border border-[#8BED9A]/55 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 text-[#09B884]">
                                    <Send class="h-4 w-4" />
                                </div>
                                <p class="text-sm font-bold text-slate-950">
                                    {{ editingNoticeId ? 'Edit notice' : activeTab === 'institutional' ? 'Create notice' : 'Post staff notice' }}
                                </p>
                            </div>
                            <button
                                v-if="editingNoticeId"
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-stone-200 text-slate-500 hover:bg-stone-50"
                                @click="resetForm"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div>
                                <label class="section-title">Title</label>
                                <input v-model="form.title" type="text" class="field-control mt-1 w-full" placeholder="Write a clear notice title" />
                            </div>
                            <div>
                                <label class="section-title">Message</label>
                                <textarea v-model="form.message" rows="6" class="field-control mt-1 w-full resize-none" placeholder="Keep it direct and useful"></textarea>
                            </div>
                            <div class="grid gap-3" :class="showVisibility ? 'sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2' : ''">
                                <div>
                                    <label class="section-title">Urgency</label>
                                    <div class="relative mt-1">
                                        <select v-model="form.urgency" class="field-control w-full appearance-none bg-white pl-3 pr-10">
                                            <option v-for="urgency in urgencyOptions" :key="urgency">{{ urgency }}</option>
                                        </select>
                                        <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                                <div v-if="showVisibility">
                                    <label class="section-title">Visibility</label>
                                    <div class="relative mt-1">
                                        <select v-model="form.visibility" class="field-control w-full appearance-none bg-white pl-3 pr-10">
                                            <option v-for="visibility in visibilityOptions" :key="visibility">{{ visibility }}</option>
                                        </select>
                                        <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border p-3" :class="styleFor(form.urgency).card">
                                <div class="flex items-center gap-2">
                                    <Eye class="h-4 w-4 text-[#09B884]" />
                                    <p class="truncate text-sm font-bold text-slate-950">{{ form.title || 'Notice preview' }}</p>
                                </div>
                                <p class="mt-2 line-clamp-3 text-xs leading-relaxed text-slate-600">{{ form.message || 'Your notice preview will appear here while composing.' }}</p>
                            </div>

                            <button
                                type="button"
                                class="btn-primary min-h-11 w-full"
                                :disabled="!form.title.trim() || !form.message.trim()"
                                @click="submitNotice"
                            >
                                <Plus v-if="!editingNoticeId" class="h-4 w-4" />
                                <Edit3 v-else class="h-4 w-4" />
                                {{ editingNoticeId ? 'Save changes' : 'Post notice' }}
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.notice-shell {
    animation: notice-rise 420ms ease-out both;
}

@keyframes notice-rise {
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
