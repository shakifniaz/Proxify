<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    CalendarDays,
    CheckCircle2,
    Clock3,
    FileText,
    Plus,
    Search,
    Send,
    ShieldCheck,
    SlidersHorizontal,
    UserCheck,
    UserX,
    XCircle,
} from 'lucide-vue-next';

const props = defineProps({
    requests: { type: Array, default: () => [] },
    leaveBalances: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    year: { type: [String, Number], default: '' },
    activeRoutineName: { type: String, default: '' },
    routinePeriods: { type: Array, default: () => [] },
});

const page = usePage();
const isAdmin = computed(() => (page.props.auth?.user?.role ?? 'admin') === 'admin');
const currentUser = computed(() => page.props.auth?.user?.name || 'User');

const localRequests = ref(props.requests.map((request) => ({ ...request })));
const teacherAllowances = ref(
    props.leaveBalances.map((teacher, index) => ({
        id: teacher.id ?? index + 1,
        routineId: teacher.routineId ?? null,
        teacher: teacher.teacher,
        subject: teacher.subject ?? '',
        maxLeaves: Number(teacher.maxLeaves ?? 12),
        used: Number(teacher.used ?? 0),
        pending: Number(teacher.pending ?? 0),
        paid: Number(teacher.paid ?? 0),
        casual: Number(teacher.casual ?? 0),
        unpaid: Number(teacher.unpaid ?? 0),
        discretionary: Number(teacher.discretionary ?? 0),
    }))
);

watch(() => props.requests, (requests) => {
    localRequests.value = requests.map((request) => ({ ...request }));
}, { deep: true });

watch(() => props.leaveBalances, (balances) => {
    teacherAllowances.value = balances.map((teacher, index) => ({
        id: teacher.id ?? index + 1,
        routineId: teacher.routineId ?? null,
        teacher: teacher.teacher,
        subject: teacher.subject ?? '',
        maxLeaves: Number(teacher.maxLeaves ?? 12),
        used: Number(teacher.used ?? 0),
        pending: Number(teacher.pending ?? 0),
        paid: Number(teacher.paid ?? 0),
        casual: Number(teacher.casual ?? 0),
        unpaid: Number(teacher.unpaid ?? 0),
        discretionary: Number(teacher.discretionary ?? 0),
    }));
}, { deep: true });

const typeFilter = ref('All types');
const statusFilter = ref('All status');
const search = ref('');
const allowanceSearch = ref('');
const activeAdminView = ref('desk');
const selectedTeacherId = ref(null);
const savingAllowanceId = ref(null);
const savedAllowanceId = ref(null);
const selectedLeavePeriodKeys = ref([]);
const requestForm = ref({
    type: props.typeOptions[0] ?? 'Paid leave',
    startDate: '',
    endDate: '',
    duration: 'Full day',
    reason: '',
});

const statusOptions = ['All status', 'Pending', 'Approved', 'Rejected'];
const durationOptions = ['Full day', 'Morning only', 'Afternoon only', 'Selected periods'];

const selectedTeacher = computed(() => teacherAllowances.value.find((teacher) => teacher.id === selectedTeacherId.value));
const pendingRequests = computed(() => localRequests.value.filter((request) => request.status === 'pending'));
const approvedProxyLeaves = computed(() => localRequests.value.filter((request) => request.status === 'approved' && request.proxyRelevant));
const teacherRequests = computed(() => localRequests.value.filter((request) => request.teacherName === currentUser.value));
const totalUsed = computed(() => teacherAllowances.value.reduce((sum, teacher) => sum + teacher.used, 0));
const totalAllowance = computed(() => teacherAllowances.value.reduce((sum, teacher) => sum + teacher.maxLeaves, 0));
const averageUsage = computed(() => Math.round((totalUsed.value / Math.max(1, totalAllowance.value)) * 100));
const activeClassPeriods = computed(() => props.routinePeriods.filter((period) => (period.type ?? 'class') === 'class'));
const periodKeys = computed(() => activeClassPeriods.value.map((period) => period.key));
const periodGridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${Math.max(activeClassPeriods.value.length, 1)}, minmax(0, 1fr))`,
}));
const presetPeriodKeys = computed(() => ({
    'Full day': periodKeys.value,
    'Morning only': periodKeysBeforeBreak(),
    'Afternoon only': periodKeysAfterTiffinBreak(),
    'Selected periods': selectedLeavePeriodKeys.value,
}));

const filteredAllowances = computed(() => {
    const needle = allowanceSearch.value.trim().toLowerCase();
    if (!needle) return teacherAllowances.value;

    return teacherAllowances.value.filter((teacher) => `${teacher.teacher} ${teacher.subject}`.toLowerCase().includes(needle));
});

const dateRangeInvalid = computed(() => {
    if (!requestForm.value.startDate || !requestForm.value.endDate) return false;
    return requestForm.value.startDate > requestForm.value.endDate;
});

const canSubmitLeaveRequest = computed(() => {
    const hasTeacher = !isAdmin.value || Boolean(selectedTeacher.value);
    return hasTeacher
        && requestForm.value.startDate
        && requestForm.value.endDate
        && !dateRangeInvalid.value
        && requestForm.value.reason.trim()
        && selectedLeavePeriodKeys.value.length > 0;
});

const filteredRequests = computed(() => {
    const needle = search.value.trim().toLowerCase();
    return localRequests.value.filter((request) => {
        const matchesType = typeFilter.value === 'All types' || request.type === typeFilter.value;
        const matchesStatus = statusFilter.value === 'All status' || request.status === statusFilter.value.toLowerCase();
        const matchesSearch = !needle || `${request.teacherName} ${request.type} ${request.reason}`.toLowerCase().includes(needle);
        return matchesType && matchesStatus && matchesSearch;
    });
});

function initials(name = '') {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function usagePercent(teacher) {
    return Math.min(100, Math.round((teacher.used / Math.max(1, teacher.maxLeaves)) * 100));
}

function remainingLeaves(teacher) {
    return Math.max(0, teacher.maxLeaves - teacher.used);
}

function teacherBalanceFor(name) {
    return teacherAllowances.value.find((teacher) => teacher.teacher === name);
}

function requestLeaveStats(request) {
    const balance = teacherBalanceFor(request.teacherName);
    if (balance) {
        return {
            maxLeaves: balance.maxLeaves,
            used: balance.used,
            pending: balance.pending,
            remaining: remainingLeaves(balance),
        };
    }

    return request.leaveStats ?? null;
}

function samePeriodSet(first = [], second = []) {
    if (first.length !== second.length) return false;

    const selected = new Set(first);
    return second.every((key) => selected.has(key));
}

function periodKeysBeforeBreak() {
    const breakIndex = props.routinePeriods.findIndex((period) => (period.type ?? 'class') === 'break');
    const periods = breakIndex >= 0
        ? props.routinePeriods.slice(0, breakIndex)
        : props.routinePeriods.slice(0, Math.ceil(props.routinePeriods.length / 2));

    return periods.filter((period) => (period.type ?? 'class') === 'class').map((period) => period.key);
}

function periodKeysAfterTiffinBreak() {
    const breakIndexes = props.routinePeriods
        .map((period, index) => ((period.type ?? 'class') === 'break' ? index : null))
        .filter((index) => index !== null);
    const breakIndex = breakIndexes.length ? breakIndexes[breakIndexes.length - 1] : Math.floor(props.routinePeriods.length / 2) - 1;

    return props.routinePeriods
        .slice(breakIndex + 1)
        .filter((period) => (period.type ?? 'class') === 'class')
        .map((period) => period.key);
}

function syncPeriodPreset() {
    const matchingPreset = ['Full day', 'Morning only', 'Afternoon only'].find((preset) => samePeriodSet(selectedLeavePeriodKeys.value, presetPeriodKeys.value[preset]));
    requestForm.value.duration = matchingPreset ?? 'Selected periods';
}

function applyAvailabilityPreset() {
    if (requestForm.value.duration === 'Selected periods') {
        if (!selectedLeavePeriodKeys.value.length && periodKeys.value.length) {
            selectedLeavePeriodKeys.value = [periodKeys.value[0]];
        }
        return;
    }

    selectedLeavePeriodKeys.value = [...(presetPeriodKeys.value[requestForm.value.duration] ?? [])];
}

function toggleLeavePeriod(periodKey) {
    selectedLeavePeriodKeys.value = selectedLeavePeriodKeys.value.includes(periodKey)
        ? selectedLeavePeriodKeys.value.filter((key) => key !== periodKey)
        : [...selectedLeavePeriodKeys.value, periodKey];

    syncPeriodPreset();
}

function periodButtonClass(periodKey) {
    return selectedLeavePeriodKeys.value.includes(periodKey)
        ? 'border-[#09B884] bg-[#8BED9A]/15 text-[#1e2924] shadow-sm'
        : 'border-stone-200 bg-white text-slate-600 hover:bg-stone-50';
}

function allowanceTone(teacher) {
    const percent = usagePercent(teacher);
    if (percent >= 90) return 'bg-red-500';
    if (percent >= 70) return 'bg-amber-500';
    return 'bg-[#1e2924]/95';
}

function statusClass(status) {
    if (status === 'approved') return 'border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#1e2924]';
    if (status === 'rejected') return 'border-red-200 bg-red-50 text-red-700';
    return 'border-amber-200 bg-amber-50 text-amber-800';
}

function typeClass(type) {
    if (type === 'Paid leave') return 'border-[#8BED9A]/70 bg-[#8BED9A]/15 text-[#1e2924]';
    if (type === 'Casual leave') return 'border-[#09B884]/30 bg-[#09B884]/10 text-[#04795a]';
    if (type === 'Unpaid leave') return 'border-slate-200 bg-slate-50 text-slate-700';
    return 'border-[#8BED9A]/50 bg-[#8BED9A]/10 text-[#1e2924]';
}

function setRequestStatus(id, status) {
    const request = localRequests.value.find((item) => item.id === id);
    if (!request) return;

    router.patch(`/leave-requests/${id}/status`, { status }, {
        preserveScroll: true,
    });
}

function saveTeacherAllowance(teacher) {
    if (!teacher?.routineId || savingAllowanceId.value === teacher.id) return;

    const maxLeaves = Math.max(0, Math.min(365, Number(teacher.maxLeaves ?? 0)));
    teacher.maxLeaves = maxLeaves;
    savingAllowanceId.value = teacher.id;
    savedAllowanceId.value = null;

    router.put('/leave-requests/allowances', {
        routineId: teacher.routineId,
        teacherId: String(teacher.id),
        teacherName: teacher.teacher,
        year: Number(props.year),
        maxLeaves,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            savedAllowanceId.value = teacher.id;
            window.setTimeout(() => {
                if (savedAllowanceId.value === teacher.id) {
                    savedAllowanceId.value = null;
                }
            }, 1400);
        },
        onFinish: () => {
            if (savingAllowanceId.value === teacher.id) {
                savingAllowanceId.value = null;
            }
        },
    });
}

function submitLeaveRequest() {
    if (!canSubmitLeaveRequest.value) return;

    const teacherName = isAdmin.value ? selectedTeacher.value?.teacher : currentUser.value;
    router.post('/leave-requests', {
        routineId: selectedTeacher.value?.routineId ?? null,
        teacherId: isAdmin.value ? String(selectedTeacher.value?.id ?? '') : null,
        teacherName,
        subject: selectedTeacher.value?.subject ?? '',
        type: requestForm.value.type,
        startDate: requestForm.value.startDate,
        endDate: requestForm.value.endDate,
        duration: requestForm.value.duration,
        periods: [...selectedLeavePeriodKeys.value],
        reason: requestForm.value.reason,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            requestForm.value = {
                type: props.typeOptions[0] ?? 'Paid leave',
                startDate: '',
                endDate: '',
                duration: 'Full day',
                reason: '',
            };
            selectedTeacherId.value = null;
            selectedLeavePeriodKeys.value = [...periodKeys.value];
        },
    });
}

function formatDate(value) {
    const [year, month, day] = String(value || '').split('-');
    if (!year || !month || !day) return value;
    return `${day}/${month}/${year.slice(-2)}`;
}

selectedLeavePeriodKeys.value = [...periodKeys.value];
</script>

<template>
    <AppLayout title="Leave Management" live-refresh :refresh-interval="12000">
        <div class="space-y-5">
            <template v-if="isAdmin">
                <div class="space-y-5">
                    <section class="surface-card overflow-hidden">
                        <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#8BED9A]/18 text-[#09B884]">
                                        <CalendarDays class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#09B884]">Active routine</p>
                                        <p class="mt-1 truncate text-lg font-black text-[#1e2924]">{{ activeRoutineName || 'Current active routine' }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-2 sm:min-w-[28rem]">
                                <div class="rounded-xl bg-[#8BED9A]/14 px-3 py-2">
                                    <p class="text-[10px] font-black uppercase text-[#1e2924]/55">Year</p>
                                    <p class="text-lg font-black text-[#1e2924]">{{ year }}</p>
                                </div>
                                <div class="rounded-xl bg-amber-50 px-3 py-2">
                                    <p class="text-[10px] font-black uppercase text-amber-700/70">Pending</p>
                                    <p class="text-lg font-black text-[#1e2924]">{{ pendingRequests.length }}</p>
                                </div>
                                <div class="rounded-xl bg-[#8BED9A]/14 px-3 py-2">
                                    <p class="text-[10px] font-black uppercase text-[#1e2924]/55">Proxy</p>
                                    <p class="text-lg font-black text-[#1e2924]">{{ approvedProxyLeaves.length }}</p>
                                </div>
                                <div class="rounded-xl bg-stone-50 px-3 py-2">
                                    <p class="text-[10px] font-black uppercase text-[#1e2924]/55">Used</p>
                                    <p class="text-lg font-black text-[#1e2924]">{{ averageUsage }}%</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="surface-card p-3">
                        <div class="relative grid w-full overflow-hidden rounded-2xl border border-[#8BED9A]/50 bg-[#8BED9A]/10 p-1.5 shadow-sm shadow-[#8BED9A]/20 sm:w-[33rem] sm:grid-cols-2">
                            <div
                                class="absolute inset-y-1.5 left-1.5 w-[calc(50%-0.375rem)] rounded-xl bg-[#1e2924] shadow-lg shadow-[#1e2924]/20 transition-transform duration-300 ease-out"
                                :class="activeAdminView === 'allowances' ? 'translate-x-full' : 'translate-x-0'"
                            ></div>
                            <button
                                type="button"
                                class="relative z-10 flex min-h-14 items-center justify-between gap-3 rounded-xl px-3 text-left transition-colors duration-300"
                                :class="activeAdminView === 'desk' ? 'text-white' : 'text-[#1e2924] hover:text-[#09B884]'"
                                @click="activeAdminView = 'desk'"
                            >
                                <span class="flex min-w-0 items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition-colors duration-300"
                                        :class="activeAdminView === 'desk' ? 'border-white/15 bg-white/14 text-[#BDF8C8]' : 'border-[#8BED9A]/60 bg-white/80 text-[#09B884]'"
                                    >
                                        <ShieldCheck class="h-4 w-4" />
                                    </span>
                                    <span class="truncate text-sm font-black">Leave desk</span>
                                </span>
                                <span
                                    class="min-w-8 shrink-0 rounded-full border px-2 py-1 text-center text-xs font-black transition-colors duration-300"
                                    :class="activeAdminView === 'desk' ? 'border-white/20 bg-white/16 text-white' : 'border-[#8BED9A]/70 bg-white/80 text-[#1e2924]'"
                                >
                                    {{ pendingRequests.length }}
                                </span>
                            </button>
                            <button
                                type="button"
                                class="relative z-10 flex min-h-14 items-center justify-between gap-3 rounded-xl px-3 text-left transition-colors duration-300"
                                :class="activeAdminView === 'allowances' ? 'text-white' : 'text-[#1e2924] hover:text-[#09B884]'"
                                @click="activeAdminView = 'allowances'"
                            >
                                <span class="flex min-w-0 items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition-colors duration-300"
                                        :class="activeAdminView === 'allowances' ? 'border-white/15 bg-white/14 text-[#BDF8C8]' : 'border-[#8BED9A]/60 bg-white/80 text-[#09B884]'"
                                    >
                                        <SlidersHorizontal class="h-4 w-4" />
                                    </span>
                                    <span class="truncate text-sm font-black">Allowances</span>
                                </span>
                                <span
                                    class="min-w-8 shrink-0 rounded-full border px-2 py-1 text-center text-xs font-black transition-colors duration-300"
                                    :class="activeAdminView === 'allowances' ? 'border-white/20 bg-white/16 text-white' : 'border-[#8BED9A]/70 bg-white/80 text-[#1e2924]'"
                                >
                                    {{ teacherAllowances.length }}
                                </span>
                            </button>
                        </div>
                    </section>

                    <template v-if="activeAdminView === 'desk'">
                    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(26rem,0.75fr)]">
                        <section class="surface-card overflow-hidden">
                            <div class="border-b border-stone-200 bg-white p-4">
                                <div class="flex flex-col gap-3 2xl:flex-row 2xl:items-center 2xl:justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                                            <ShieldCheck class="h-5 w-5" />
                                        </div>
                                        <div>
                                            <p class="text-base font-black text-slate-950">Approval queue</p>
                                            <p class="text-xs font-semibold text-slate-500">{{ filteredRequests.length }} matching requests</p>
                                        </div>
                                    </div>
                                    <div class="grid gap-2 md:grid-cols-[minmax(12rem,1fr)_10rem_10rem] 2xl:w-[34rem]">
                                        <div class="relative min-w-0">
                                            <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                            <input v-model="search" type="text" class="field-control w-full pl-9" placeholder="Search teacher or reason" />
                                        </div>
                                        <select v-model="typeFilter" class="field-control bg-white pr-10">
                                            <option>All types</option>
                                            <option v-for="type in typeOptions" :key="type">{{ type }}</option>
                                        </select>
                                        <select v-model="statusFilter" class="field-control bg-white pr-10">
                                            <option v-for="status in statusOptions" :key="status">{{ status }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="max-h-[34rem] divide-y divide-stone-200 overflow-y-auto overscroll-contain">
                                <div v-if="!filteredRequests.length" class="p-10 text-center">
                                    <ShieldCheck class="mx-auto h-8 w-8 text-slate-300" />
                                    <p class="mt-2 text-sm font-semibold text-slate-600">No leave requests found</p>
                                </div>
                                <div v-for="request in filteredRequests" :key="request.id" class="p-4 transition hover:bg-stone-50/70">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="flex min-w-0 gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-stone-50 text-xs font-black text-slate-700">
                                                {{ request.initials || initials(request.teacherName) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-sm font-black text-slate-950">{{ request.teacherName }}</p>
                                                    <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="typeClass(request.type)">{{ request.type }}</span>
                                                    <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold capitalize" :class="statusClass(request.status)">{{ request.status }}</span>
                                                    <span v-if="requestLeaveStats(request)" class="rounded-full border border-[#8BED9A]/70 bg-white px-2 py-0.5 text-[11px] font-semibold text-[#1e2924]">
                                                        {{ requestLeaveStats(request).remaining }} leaves left
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-sm font-semibold text-slate-600">{{ request.dateRange }} - {{ request.days }} day{{ request.days === 1 ? '' : 's' }} - {{ request.duration }}</p>
                                                <p class="mt-2 max-w-3xl text-sm text-slate-500">{{ request.reason }}</p>
                                                <div v-if="request.periods?.length" class="mt-3 flex flex-wrap gap-1.5">
                                                    <span v-for="period in request.periods" :key="`${request.id}-${period}`" class="rounded-md border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-2 py-1 text-[11px] font-semibold text-[#1e2924]">
                                                        {{ period }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex shrink-0 items-center justify-end gap-2">
                                            <button v-if="request.status === 'pending'" type="button" class="rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-700 transition hover:bg-red-50" @click="setRequestStatus(request.id, 'rejected')">
                                                <XCircle class="mr-1 inline h-3.5 w-3.5" />
                                                Reject
                                            </button>
                                            <button v-if="request.status === 'pending'" type="button" class="rounded-lg bg-[#1e2924]/95 px-3 py-2 text-xs font-bold text-white shadow-sm shadow-black/10 transition hover:bg-[#1e2924]" @click="setRequestStatus(request.id, 'approved')">
                                                <CheckCircle2 class="mr-1 inline h-3.5 w-3.5" />
                                                Approve
                                            </button>
                                            <span v-else class="rounded-lg border border-stone-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reviewed</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="surface-card p-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#8BED9A]/18 text-[#09B884]">
                                    <UserX class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-base font-black text-[#1e2924]">Approved leaves</p>
                                    <p class="text-xs font-semibold text-slate-500">{{ approvedProxyLeaves.length }} ready for class coverage</p>
                                </div>
                            </div>

                            <div v-if="!approvedProxyLeaves.length" class="mt-5 rounded-xl border border-dashed border-stone-300 bg-stone-50 p-10 text-center">
                                <ShieldCheck class="mx-auto h-8 w-8 text-slate-300" />
                                <p class="mt-2 text-sm font-semibold text-slate-600">No approved leaves ready</p>
                            </div>

                            <div v-else class="mt-5 max-h-[34rem] space-y-3 overflow-y-auto overscroll-contain pr-1">
                                <div v-for="request in approvedProxyLeaves" :key="`handoff-${request.id}`" class="rounded-xl border border-[#8BED9A]/70 bg-[#8BED9A]/15 p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-black text-[#1e2924]">{{ request.teacherName }}</p>
                                            <p class="mt-1 text-xs font-semibold text-[#04795a]">{{ request.dateRange }} - {{ request.duration }}</p>
                                            <div v-if="request.periods?.length" class="mt-3 flex flex-wrap gap-1.5">
                                                <span v-for="period in request.periods" :key="`approved-${request.id}-${period}`" class="rounded-md bg-white/80 px-2 py-1 text-[11px] font-bold text-[#1e2924]">{{ period }}</span>
                                            </div>
                                        </div>
                                        <UserCheck class="h-4 w-4 text-[#09B884]" />
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <section class="surface-card overflow-hidden">
                        <div class="border-b border-stone-200 bg-white p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#8BED9A]/18 text-[#09B884]">
                                        <Plus class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-base font-black text-[#1e2924]">Create leave request</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ selectedLeavePeriodKeys.length }} periods selected</p>
                                    </div>
                                </div>
                                <button type="button" class="hidden min-h-10 items-center justify-center gap-2 rounded-xl bg-[#1e2924] px-4 text-sm font-black text-white shadow-md shadow-[#1e2924]/15 transition hover:-translate-y-0.5 hover:bg-[#1e2924]/90 disabled:cursor-not-allowed disabled:bg-slate-300 sm:flex" :disabled="!canSubmitLeaveRequest" @click="submitLeaveRequest">
                                    <Send class="h-4 w-4" />
                                    Submit request
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-px bg-stone-200/80 xl:grid-cols-[minmax(16rem,0.8fr)_minmax(18rem,0.9fr)_minmax(0,1.45fr)]">
                            <div class="bg-white p-5">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-2 rounded-full bg-[#09B884]"></div>
                                    <p class="text-sm font-black text-[#1e2924]">Teacher</p>
                                </div>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label class="section-title">Teacher</label>
                                        <select v-model="selectedTeacherId" class="field-control mt-1 w-full bg-white">
                                            <option :value="null" disabled>Select teacher</option>
                                            <option v-for="teacher in teacherAllowances" :key="teacher.id" :value="teacher.id">{{ teacher.teacher }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="section-title">Leave type</label>
                                        <select v-model="requestForm.type" class="field-control mt-1 w-full bg-white">
                                            <option v-for="type in typeOptions" :key="type" :value="type">{{ type }}</option>
                                        </select>
                                    </div>
                                    <div v-if="selectedTeacher" class="rounded-xl border border-[#8BED9A]/60 bg-[#8BED9A]/12 p-3">
                                        <p class="truncate text-sm font-black text-[#1e2924]">{{ selectedTeacher.teacher }}</p>
                                        <p class="mt-1 text-xs font-semibold text-[#04795a]">{{ remainingLeaves(selectedTeacher) }} leaves left from {{ selectedTeacher.maxLeaves }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-5">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-2 rounded-full bg-amber-500"></div>
                                    <p class="text-sm font-black text-[#1e2924]">Dates</p>
                                </div>
                                <div class="mt-4 space-y-4">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="section-title">Start</label>
                                            <input v-model="requestForm.startDate" type="date" class="field-control mt-1 w-full bg-white" :max="requestForm.endDate || undefined" />
                                        </div>
                                        <div>
                                            <label class="section-title">End</label>
                                            <input v-model="requestForm.endDate" type="date" class="field-control mt-1 w-full bg-white" :min="requestForm.startDate || undefined" />
                                        </div>
                                    </div>
                                    <p v-if="dateRangeInvalid" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">End date cannot be before start date.</p>
                                    <div>
                                        <label class="section-title">Availability impact</label>
                                        <select v-model="requestForm.duration" class="field-control mt-1 w-full bg-white" @change="applyAvailabilityPreset">
                                            <option v-for="duration in durationOptions" :key="duration">{{ duration }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full bg-[#1e2924]"></div>
                                        <p class="text-sm font-black text-[#1e2924]">Impact</p>
                                    </div>
                                    <span class="rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-3 py-1 text-xs font-black text-[#1e2924]">{{ selectedLeavePeriodKeys.length }} selected</span>
                                </div>

                                <div class="mt-4">
                                    <label class="section-title">Periods</label>
                                    <div class="mt-2 grid gap-1.5" :style="periodGridStyle">
                                        <button v-for="period in activeClassPeriods" :key="period.key" type="button" class="truncate rounded-md border px-1 py-1.5 text-[11px] font-semibold leading-none transition" :class="periodButtonClass(period.key)" @click="toggleLeavePeriod(period.key)">
                                            {{ period.label }}
                                        </button>
                                    </div>
                                    <p v-if="!selectedLeavePeriodKeys.length" class="mt-2 text-xs font-semibold text-red-600">Select at least one period.</p>
                                </div>

                                <div class="mt-4">
                                    <label class="section-title">Reason</label>
                                    <textarea v-model="requestForm.reason" rows="3" class="field-control mt-1 w-full resize-none bg-white" placeholder="Reason for leave"></textarea>
                                </div>
                                <button type="button" class="btn-primary mt-4 flex min-h-12 w-full items-center justify-center gap-2 sm:hidden" :disabled="!canSubmitLeaveRequest" @click="submitLeaveRequest">
                                    <Send class="h-4 w-4" />
                                    Submit request
                                </button>
                            </div>
                        </div>
                    </section>
                    </template>

                    <template v-else>
                        <section class="surface-card overflow-hidden">
                            <div class="flex flex-col gap-3 border-b border-stone-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#8BED9A]/18 text-[#09B884]">
                                        <SlidersHorizontal class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-base font-black text-slate-950">Teacher leave allowance</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ filteredAllowances.length }} teachers visible</p>
                                    </div>
                                </div>
                                <div class="relative sm:w-80">
                                    <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                    <input v-model="allowanceSearch" type="text" class="field-control w-full pl-9" placeholder="Search teacher or subject" />
                                </div>
                            </div>

                            <div class="max-h-[36rem] overflow-y-auto overscroll-contain bg-stone-200">
                                <div class="grid gap-px lg:grid-cols-2">
                                    <div v-for="teacher in filteredAllowances" :key="teacher.id" class="bg-white p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-black text-slate-950">{{ teacher.teacher }}</p>
                                                <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ teacher.subject || 'Subject not set' }}</p>
                                            </div>
                                            <div class="w-24">
                                                <label class="section-title">Max</label>
                                                <input v-model.number="teacher.maxLeaves" min="0" max="365" type="number" class="field-control-sm mt-1 w-full bg-white text-center" @change="saveTeacherAllowance(teacher)" />
                                            </div>
                                        </div>
                                        <div v-if="savingAllowanceId === teacher.id || savedAllowanceId === teacher.id" class="mt-2 text-right text-[11px] font-semibold" :class="savingAllowanceId === teacher.id ? 'text-slate-500' : 'text-[#04795a]'">
                                            {{ savingAllowanceId === teacher.id ? 'Saving' : 'Saved' }}
                                        </div>

                                        <div class="mt-4">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-semibold text-slate-600">{{ teacher.used }} used</span>
                                                <span class="text-slate-500">{{ remainingLeaves(teacher) }} remaining</span>
                                            </div>
                                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-stone-100">
                                                <div class="h-full rounded-full" :class="allowanceTone(teacher)" :style="{ width: `${usagePercent(teacher)}%` }"></div>
                                            </div>
                                        </div>

                                        <div class="mt-4 grid grid-cols-4 gap-2 text-center">
                                            <div class="rounded-md bg-[#8BED9A]/15 px-2 py-2"><p class="text-sm font-bold text-[#1e2924]">{{ teacher.paid }}</p><p class="text-[10px] text-[#1e2924]">Paid</p></div>
                                            <div class="rounded-md bg-teal-50 px-2 py-2"><p class="text-sm font-bold text-teal-700">{{ teacher.casual }}</p><p class="text-[10px] text-teal-700">Casual</p></div>
                                            <div class="rounded-md bg-slate-50 px-2 py-2"><p class="text-sm font-bold text-slate-700">{{ teacher.unpaid }}</p><p class="text-[10px] text-slate-600">Unpaid</p></div>
                                            <div class="rounded-md bg-[#8BED9A]/10 px-2 py-2"><p class="text-sm font-bold text-[#1e2924]">{{ teacher.discretionary }}</p><p class="text-[10px] text-[#1e2924]">Discretionary</p></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </template>
                </div>
            </template>

            <template v-else>
                <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div class="surface-card overflow-hidden">
                        <div class="border-b border-stone-200 bg-white p-5">
                            <div class="flex items-center gap-2">
                                <FileText class="h-4 w-4 text-slate-500" />
                                <p class="text-sm font-semibold text-slate-950">My leave history</p>
                            </div>
                        </div>
                        <div v-if="!teacherRequests.length" class="p-10 text-center text-sm text-slate-500">No leave requests yet.</div>
                        <div v-else class="divide-y divide-stone-200">
                            <div v-for="request in teacherRequests" :key="request.id" class="p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-slate-950">{{ request.type }}</p>
                                            <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold capitalize" :class="statusClass(request.status)">{{ request.status }}</span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-600">{{ request.dateRange }} - {{ request.days }} day{{ request.days === 1 ? '' : 's' }}</p>
                                        <p class="mt-2 text-sm text-slate-500">{{ request.reason }}</p>
                                    </div>
                                    <Clock3 class="h-4 w-4 text-slate-400" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="surface-card p-5">
                        <div class="flex items-center gap-2">
                            <CalendarDays class="h-4 w-4 text-[#09B884]" />
                            <p class="text-sm font-semibold text-slate-950">New leave request</p>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div>
                                <label class="section-title">Leave type</label>
                                <select v-model="requestForm.type" class="field-control mt-1 w-full bg-white">
                                    <option v-for="type in typeOptions" :key="type" :value="type">{{ type }}</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="section-title">Start</label>
                                    <input v-model="requestForm.startDate" type="date" class="field-control mt-1 w-full bg-white" :max="requestForm.endDate || undefined" />
                                </div>
                                <div>
                                    <label class="section-title">End</label>
                                    <input v-model="requestForm.endDate" type="date" class="field-control mt-1 w-full bg-white" :min="requestForm.startDate || undefined" />
                                </div>
                            </div>
                            <p v-if="dateRangeInvalid" class="-mt-1 text-xs font-semibold text-red-600">End date cannot be before start date.</p>
                            <div>
                                <label class="section-title">Duration</label>
                                <select v-model="requestForm.duration" class="field-control mt-1 w-full bg-white" @change="applyAvailabilityPreset">
                                    <option v-for="duration in durationOptions" :key="duration">{{ duration }}</option>
                                </select>
                            </div>
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <label class="section-title">Periods</label>
                                    <span class="text-xs font-semibold text-slate-500">{{ selectedLeavePeriodKeys.length }} selected</span>
                                </div>
                                <div class="mt-2 grid gap-1" :style="periodGridStyle">
                                    <button
                                        v-for="period in activeClassPeriods"
                                        :key="period.key"
                                        type="button"
                                        class="truncate rounded-md border px-1 py-1.5 text-[11px] font-semibold leading-none transition"
                                        :class="periodButtonClass(period.key)"
                                        @click="toggleLeavePeriod(period.key)"
                                    >
                                        {{ period.label }}
                                    </button>
                                </div>
                                <p v-if="!selectedLeavePeriodKeys.length" class="mt-2 text-xs font-semibold text-red-600">Select at least one period.</p>
                            </div>
                            <div>
                                <label class="section-title">Reason</label>
                                <textarea v-model="requestForm.reason" rows="4" class="field-control mt-1 w-full resize-none bg-white" placeholder="Reason for leave"></textarea>
                            </div>
                            <button type="button" class="btn-primary flex w-full items-center justify-center gap-2" :disabled="!canSubmitLeaveRequest" @click="submitLeaveRequest">
                                <Send class="h-4 w-4" />
                                Submit request
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
