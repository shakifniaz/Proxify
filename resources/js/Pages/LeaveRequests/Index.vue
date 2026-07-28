<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertTriangle,
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
const currentUser = computed(() => page.props.auth?.user?.name || 'Shakif Niaz');

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

const typeFilter = ref('All types');
const statusFilter = ref('All status');
const search = ref('');
const allowanceSearch = ref('');
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
    request.status = status;
    request.proxyRelevant = status === 'approved';

    const teacher = teacherAllowances.value.find((item) => item.teacher === request.teacherName);
    if (!teacher) return;

    teacher.pending = Math.max(0, localRequests.value.filter((item) => item.teacherName === teacher.teacher && item.status === 'pending').length);
    teacher.used = localRequests.value
        .filter((item) => item.teacherName === teacher.teacher && item.status === 'approved')
        .reduce((sum, item) => sum + item.days, 0);
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

    const start = new Date(`${requestForm.value.startDate}T00:00:00`);
    const end = new Date(`${requestForm.value.endDate}T00:00:00`);
    const days = Math.max(1, Math.round((end - start) / 86400000) + 1);
    const teacherName = isAdmin.value ? selectedTeacher.value?.teacher : currentUser.value;

    localRequests.value.unshift({
        id: Date.now(),
        teacherName,
        initials: initials(teacherName),
        subject: selectedTeacher.value?.subject ?? '',
        type: requestForm.value.type,
        dateRange: `${formatDate(requestForm.value.startDate)} - ${formatDate(requestForm.value.endDate)}`,
        days,
        duration: requestForm.value.duration,
        status: 'pending',
        reason: requestForm.value.reason,
        submittedAt: 'Just now',
        proxyRelevant: false,
        periods: [...selectedLeavePeriodKeys.value],
    });

    requestForm.value = {
        type: props.typeOptions[0] ?? 'Paid leave',
        startDate: '',
        endDate: '',
        duration: 'Full day',
        reason: '',
    };
    selectedTeacherId.value = null;
    selectedLeavePeriodKeys.value = [...periodKeys.value];
}

function formatDate(value) {
    const [year, month, day] = String(value || '').split('-');
    if (!year || !month || !day) return value;
    return `${day}/${month}/${year.slice(-2)}`;
}

selectedLeavePeriodKeys.value = [...periodKeys.value];
</script>

<template>
    <AppLayout title="Leave Management">
        <div class="space-y-5 xl:flex xl:h-[calc(100vh-7rem)] xl:flex-col xl:gap-3 xl:overflow-hidden xl:space-y-0">
            <template v-if="isAdmin">
                <div class="grid gap-4 xl:min-h-0 xl:flex-1 xl:grid-cols-[minmax(0,1fr)_25rem] xl:overflow-hidden 2xl:grid-cols-[minmax(0,1fr)_26rem]">
                    <div class="space-y-5 xl:flex xl:min-h-0 xl:flex-col xl:space-y-0 xl:gap-4">
                        <div class="surface-card overflow-hidden xl:flex xl:min-h-0 xl:flex-1 xl:flex-col">
                            <div class="border-b border-stone-200 bg-white p-4">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950">Approval queue</p>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:justify-end xl:flex-nowrap">
                                        <div class="relative min-w-0 sm:w-64">
                                            <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                            <input v-model="search" type="text" class="field-control w-full pl-9" placeholder="Search teacher or reason" />
                                        </div>
                                        <select v-model="typeFilter" class="field-control min-w-44 bg-white pr-10">
                                            <option>All types</option>
                                            <option v-for="type in typeOptions" :key="type">{{ type }}</option>
                                        </select>
                                        <select v-model="statusFilter" class="field-control min-w-44 bg-white pr-10">
                                            <option v-for="status in statusOptions" :key="status">{{ status }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="h-[24rem] divide-y divide-stone-200 overflow-y-auto overscroll-contain xl:h-auto xl:min-h-0 xl:flex-1">
                                <div v-for="request in filteredRequests" :key="request.id" class="p-4 transition hover:bg-stone-50/70">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="flex min-w-0 gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-stone-200 bg-stone-50 text-xs font-bold text-slate-700">
                                                {{ request.initials || initials(request.teacherName) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-sm font-semibold text-slate-950">{{ request.teacherName }}</p>
                                                    <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="typeClass(request.type)">{{ request.type }}</span>
                                                    <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold capitalize" :class="statusClass(request.status)">{{ request.status }}</span>
                                                </div>
                                                <p class="mt-1 text-sm text-slate-600">{{ request.dateRange }} - {{ request.days }} day{{ request.days === 1 ? '' : 's' }} - {{ request.duration }}</p>
                                                <p class="mt-2 max-w-3xl text-sm text-slate-500">{{ request.reason }}</p>
                                                <div v-if="request.periods?.length" class="mt-3 flex flex-wrap gap-1.5">
                                                    <span v-for="period in request.periods" :key="`${request.id}-${period}`" class="rounded-md border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-2 py-1 text-[11px] font-semibold text-[#1e2924]">
                                                        {{ period }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex shrink-0 items-center justify-end gap-2">
                                            <button
                                                v-if="request.status === 'pending'"
                                                type="button"
                                                class="rounded-md border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50"
                                                @click="setRequestStatus(request.id, 'rejected')"
                                            >
                                                <XCircle class="mr-1 inline h-3.5 w-3.5" />
                                                Reject
                                            </button>
                                            <button
                                                v-if="request.status === 'pending'"
                                                type="button"
                                                class="rounded-md bg-[#1e2924]/95 px-3 py-2 text-xs font-semibold text-white shadow-sm shadow-black/10 transition hover:bg-[#1e2924]"
                                                @click="setRequestStatus(request.id, 'approved')"
                                            >
                                                <CheckCircle2 class="mr-1 inline h-3.5 w-3.5" />
                                                Approve
                                            </button>
                                            <span v-else class="rounded-md border border-stone-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">
                                                Reviewed
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="surface-card overflow-hidden xl:flex xl:min-h-0 xl:flex-1 xl:flex-col">
                            <div class="flex flex-col gap-3 border-b border-stone-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-950">Teacher leave allowance</p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <div class="relative sm:w-72">
                                        <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                        <input v-model="allowanceSearch" type="text" class="field-control w-full pl-9" placeholder="Search teacher or subject" />
                                    </div>
                                </div>
                            </div>

                            <div class="h-[24rem] overflow-y-auto overscroll-contain bg-stone-200 xl:h-auto xl:min-h-0 xl:flex-1">
                                <div class="grid gap-px lg:grid-cols-2">
                                    <div v-for="teacher in filteredAllowances" :key="teacher.id" class="bg-white p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-950">{{ teacher.teacher }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500">{{ teacher.subject || 'Subject not set' }}</p>
                                            </div>
                                            <div class="w-24">
                                                <label class="section-title">Max</label>
                                                <input
                                                    v-model.number="teacher.maxLeaves"
                                                    min="0"
                                                    max="365"
                                                    type="number"
                                                    class="field-control-sm mt-1 w-full bg-white text-center"
                                                    @change="saveTeacherAllowance(teacher)"
                                                />
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
                                            <div class="rounded-md bg-[#8BED9A]/15 px-2 py-2">
                                                <p class="text-sm font-bold text-[#1e2924]">{{ teacher.paid }}</p>
                                                <p class="text-[10px] text-[#1e2924]">Paid</p>
                                            </div>
                                            <div class="rounded-md bg-teal-50 px-2 py-2">
                                                <p class="text-sm font-bold text-teal-700">{{ teacher.casual }}</p>
                                                <p class="text-[10px] text-teal-700">Casual</p>
                                            </div>
                                            <div class="rounded-md bg-slate-50 px-2 py-2">
                                                <p class="text-sm font-bold text-slate-700">{{ teacher.unpaid }}</p>
                                                <p class="text-[10px] text-slate-600">Unpaid</p>
                                            </div>
                                            <div class="rounded-md bg-[#8BED9A]/10 px-2 py-2">
                                                <p class="text-sm font-bold text-[#1e2924]">{{ teacher.discretionary }}</p>
                                                <p class="text-[10px] text-[#1e2924]">Discretionary</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="space-y-5 xl:min-h-0 xl:overflow-y-auto xl:overscroll-contain">
                        <div class="surface-card p-4">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Active routine</p>
                                <p class="mt-0.5 truncate text-sm font-bold text-slate-950">{{ activeRoutineName || 'Current active routine' }}</p>
                            </div>
                            <div class="mt-3 grid grid-cols-4 gap-2">
                                <div class="rounded-md bg-stone-50 px-2 py-1.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Year</p>
                                    <p class="text-sm font-bold leading-tight text-slate-950">{{ year }}</p>
                                </div>
                                <div class="rounded-md bg-stone-50 px-2 py-1.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Pending</p>
                                    <p class="text-sm font-bold leading-tight text-slate-950">{{ pendingRequests.length }}</p>
                                </div>
                                <div class="rounded-md bg-stone-50 px-2 py-1.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Proxy</p>
                                    <p class="text-sm font-bold leading-tight text-slate-950">{{ approvedProxyLeaves.length }}</p>
                                </div>
                                <div class="rounded-md bg-stone-50 px-2 py-1.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Used</p>
                                    <p class="text-sm font-bold leading-tight text-slate-950">{{ averageUsage }}%</p>
                                </div>
                            </div>
                        </div>

                        <div class="surface-card p-5">
                            <div class="flex items-center gap-2">
                                <UserX class="h-4 w-4 text-rose-700" />
                                <p class="text-sm font-semibold text-slate-950">Proxy Manager handoff</p>
                            </div>

                            <div v-if="!approvedProxyLeaves.length" class="mt-4 rounded-lg border border-dashed border-stone-300 bg-stone-50 p-6 text-center">
                                <ShieldCheck class="mx-auto h-7 w-7 text-slate-300" />
                                <p class="mt-2 text-sm font-semibold text-slate-600">No approved leaves ready</p>
                            </div>

                            <div v-else class="mt-4 space-y-3">
                                <div v-for="request in approvedProxyLeaves" :key="`handoff-${request.id}`" class="rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-[#1e2924]">{{ request.teacherName }}</p>
                                            <p class="mt-0.5 text-xs text-[#04795a]">{{ request.dateRange }} - {{ request.duration }}</p>
                                        </div>
                                        <UserCheck class="h-4 w-4 text-[#09B884]" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="surface-card p-5">
                            <div class="flex items-center gap-2">
                                <Plus class="h-4 w-4 text-[#09B884]" />
                                <p class="text-sm font-semibold text-slate-950">Create leave request</p>
                            </div>

                            <div class="mt-4 space-y-3">
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
                                    <label class="section-title">Availability impact</label>
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
                    </aside>
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
