<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Paperclip, Download, Plus, CheckCircle2, XCircle, Send } from 'lucide-vue-next';

const props = defineProps({
    requests: { type: Array, default: () => [] },
    leaveBalances: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    year: { type: [String, Number], default: '' },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
// Enforcing mock testing identity
const currentUser = computed(() => page.props.auth?.user?.name || 'Shakif Niaz');

const avatarColors = {
    emerald: 'bg-blue-50 text-blue-700 border border-blue-200',
    sky: 'bg-sky-500/10 text-sky-400 border border-sky-500/20',
    violet: 'bg-violet-500/10 text-violet-400 border border-violet-500/20',
    amber: 'bg-amber-50 text-amber-700 border border-amber-200',
    rose: 'bg-red-50 text-red-700 border border-red-200',
};

// Teacher-specific data & form logic
const teacherRequests = computed(() => 
    localRequests.value.filter(r => r.teacherName === currentUser.value)
);

const leaveForm = ref({
    type: props.typeOptions[0] || 'Sick Leave',
    startDate: '',
    endDate: '',
    reason: '',
});

function submitLeave() {
    if (!leaveForm.value.startDate || !leaveForm.value.endDate) return;
    
    localRequests.value.unshift({
        id: Date.now(),
        teacherName: currentUser.value,
        initials: currentUser.value.split(' ').map(n => n[0]).join(''),
        avatarColor: 'sky',
        type: leaveForm.value.type,
        dateRange: `${leaveForm.value.startDate} to ${leaveForm.value.endDate}`,
        days: 1, // Auto-mocked
        status: 'pending',
        reason: leaveForm.value.reason,
        attachment: false
    });
    
    leaveForm.value = {
        type: props.typeOptions[0] || 'Sick Leave',
        startDate: '',
        endDate: '',
        reason: ''
    };
}

// Admin-specific logic (Unaltered)
const statusOptions = ['All status', 'Pending', 'Approved', 'Rejected'];
const typeFilter = ref('All types');
const statusFilter = ref('All status');
const localRequests = ref(props.requests.map((r) => ({ ...r })));

const filteredRequests = computed(() => {
    return localRequests.value.filter((r) => {
        const matchesType = typeFilter.value === 'All types' || r.type === typeFilter.value;
        const matchesStatus = statusFilter.value === 'All status' || r.status.toLowerCase() === statusFilter.value.toLowerCase();
        return matchesType && matchesStatus;
    });
});

const pendingRequests = computed(() => localRequests.value.filter((r) => r.status.toLowerCase() === 'pending'));

function handleAction(id, action) {
    const req = localRequests.value.find((r) => r.id === id);
    if (req) req.status = action === 'approve' ? 'approved' : 'rejected';
}

function statusBadgeClass(status) {
    switch (status.toLowerCase()) {
        case 'approved': return 'bg-blue-50 text-blue-700 border border-blue-200';
        case 'rejected': return 'bg-red-50 text-red-700 border border-red-200';
        default: return 'bg-amber-50 text-amber-700 border border-amber-200';
    }
}

function usedBadgeClass(used) {
    if (used > 15) return 'bg-red-50 text-red-700 border border-red-200';
    if (used > 8) return 'bg-amber-50 text-amber-700 border border-amber-200';
    return 'bg-stone-100 text-slate-600 border border-slate-700/50';
}
</script>

<template>
    <AppLayout title="Leave Requests">
        <div class="space-y-6">
            
            <template v-if="isAdmin">
                

                <div v-if="pendingRequests.length > 0" class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-2">
                        Pending Action Needed ({{ pendingRequests.length }})
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="req in pendingRequests" :key="'p-' + req.id" class="rounded-lg border border-amber-200 bg-white p-4 space-y-4">
                            <div class="space-y-2">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold uppercase tracking-wide" :class="avatarColors[req.avatarColor] || avatarColors.violet">
                                            {{ req.initials }}
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold text-slate-900">{{ req.teacherName }}</h4>
                                            <p class="text-[11px] text-slate-500 mt-0.5">Applied for <span class="text-slate-950">{{ req.type }}</span></p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200">
                                        {{ req.days }} {{ req.days === 1 ? 'Day' : 'Days' }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-600 font-normal pt-1 pl-1 border-l border-stone-200">
                                    <span class="text-slate-700 font-medium block">{{ req.dateRange }}</span>
                                    <p class="mt-1 text-slate-600 italic">"{{ req.reason }}"</p>
                                </div>
                            </div>
                            <div class="pt-3 border-t border-stone-200/60 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 ml-auto">
                                    <button @click="handleAction(req.id, 'reject')" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-slate-600 hover:text-red-700 border border-stone-300 rounded-lg">
                                        <XCircle class="h-3 w-3" /> Reject
                                    </button>
                                    <button @click="handleAction(req.id, 'approve')" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-blue-700 hover:bg-blue-800 rounded-lg">
                                        <CheckCircle2 class="h-3 w-3" /> Approve
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    <div class="lg:col-span-2 space-y-4">
                        <div class="flex items-center justify-between border-b border-stone-200 pb-4">
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">All Applications Log</h3>
                            <div class="flex gap-2">
                                <select v-model="typeFilter" class="bg-stone-100 border border-stone-300 rounded-lg px-3 py-1.5 text-xs text-slate-600">
                                    <option>All types</option>
                                    <option v-for="t in typeOptions" :key="t">{{ t }}</option>
                                </select>
                                <select v-model="statusFilter" class="bg-stone-100 border border-stone-300 rounded-lg px-3 py-1.5 text-xs text-slate-600">
                                    <option v-for="s in statusOptions" :key="s">{{ s }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="overflow-hidden surface-card">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-stone-200 bg-stone-100 text-[11px] font-bold uppercase text-slate-600">
                                    <tr>
                                        <th class="px-5 py-3">Teacher</th>
                                        <th class="px-3 py-3">Type</th>
                                        <th class="px-3 py-3">Date</th>
                                        <th class="px-3 py-3 text-center">Days</th>
                                        <th class="px-3 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-200 text-slate-700">
                                    <tr v-for="req in filteredRequests" :key="req.id">
                                        <td class="px-5 py-3 font-medium text-slate-800">{{ req.teacherName }}</td>
                                        <td class="px-3 py-3">{{ req.type }}</td>
                                        <td class="px-3 py-3">{{ req.dateRange }}</td>
                                        <td class="px-3 py-3 text-center">{{ req.days }}</td>
                                        <td class="px-3 py-3">
                                            <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider" :class="statusBadgeClass(req.status)">
                                                {{ req.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Leave Balances</h3>
                        <div class="overflow-hidden surface-card">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-stone-200 bg-stone-100 text-[11px] font-bold uppercase text-slate-600">
                                    <tr>
                                        <th class="px-5 py-3">Teacher</th>
                                        <th class="px-3 py-3">Used</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-200 text-slate-700">
                                    <tr v-for="b in leaveBalances" :key="b.teacher">
                                        <td class="px-5 py-3">{{ b.teacher }}</td>
                                        <td class="px-3 py-3">
                                            <span class="rounded px-1.5 py-0.5 text-[10px] font-bold" :class="usedBadgeClass(b.used)">{{ b.used }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-950">My Leave Requests</h2>
                        <p class="text-sm text-slate-500 mt-1">Submit and track your time-off applications.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 items-start">
                    
                    <div class="lg:col-span-2 space-y-4">
                        <div class="border-b border-stone-200 pb-2">
                            <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider">Application History</h3>
                        </div>

                        <div v-if="teacherRequests.length > 0" class="space-y-3">
                            <div v-for="req in teacherRequests" :key="req.id" class="surface-card p-4 transition-colors hover:border-slate-700">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-sm font-bold text-slate-800">{{ req.type }}</h4>
                                            <span class="rounded px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider" :class="statusBadgeClass(req.status)">
                                                {{ req.status }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 font-medium">{{ req.dateRange }} &middot; {{ req.days }} {{ req.days === 1 ? 'Day' : 'Days' }}</p>
                                        <p v-if="req.reason" class="text-xs text-slate-500 italic pt-1">"{{ req.reason }}"</p>
                                    </div>
                                    <div v-if="req.attachment" class="flex h-8 w-8 items-center justify-center rounded-lg bg-stone-100 text-slate-600">
                                        <Paperclip class="h-4 w-4" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="rounded-lg border border-dashed border-stone-300 py-12 text-center">
                            <p class="text-sm text-slate-500 italic">You have no recorded leave requests.</p>
                        </div>
                    </div>

                    <div class="surface-card p-5 space-y-4">
                        <div class="space-y-1">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <Send class="h-4 w-4 text-blue-700" />
                                Draft New Request
                            </h4>
                            <p class="text-[11px] text-slate-500 leading-normal">
                                Submit a new leave application to the administration desk for approval.
                            </p>
                        </div>

                        <div class="space-y-3 pt-2">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Leave Category</label>
                                <select v-model="leaveForm.type" class="w-full bg-stone-100 border border-stone-300 rounded-lg px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:outline-none">
                                    <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Start Date</label>
                                    <input type="date" v-model="leaveForm.startDate" class="w-full bg-stone-100 border border-stone-300 rounded-lg px-2.5 py-2 text-xs text-slate-600 focus:border-blue-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">End Date</label>
                                    <input type="date" v-model="leaveForm.endDate" class="w-full bg-stone-100 border border-stone-300 rounded-lg px-2.5 py-2 text-xs text-slate-600 focus:border-blue-500 focus:outline-none" />
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Reason for Leave</label>
                                <textarea v-model="leaveForm.reason" rows="3" placeholder="Briefly state the reason for your leave..." class="w-full bg-stone-100 border border-stone-300 rounded-lg px-3 py-2 text-xs text-slate-800 placeholder-slate-600 focus:border-blue-500 focus:outline-none resize-none"></textarea>
                            </div>

                            <div class="pt-2">
                                <button type="button" @click="submitLeave" :disabled="!leaveForm.startDate || !leaveForm.endDate || !leaveForm.reason" class="w-full rounded-lg bg-blue-700 hover:bg-blue-700 text-slate-950 font-bold text-xs py-2.5 px-3 transition-colors shadow-lg shadow-none disabled:opacity-40 disabled:cursor-not-allowed">
                                    Submit Request
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

        </div>
    </AppLayout>
</template>