<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Paperclip, Download } from 'lucide-vue-next';

const props = defineProps({
    requests: { type: Array, default: () => [] }, // [{ id, teacherName, initials, avatarColor, type, dateRange, days, status, reason, attachment }]
    leaveBalances: { type: Array, default: () => [] }, // [{ teacher, sick, casual, annual, used }]
    typeOptions: { type: Array, default: () => [] },
    year: { type: [String, Number], default: '' },
});

const avatarColors = {
    emerald: 'bg-emerald-500/20 text-emerald-300',
    sky: 'bg-sky-500/20 text-sky-300',
    violet: 'bg-violet-500/20 text-violet-300',
    amber: 'bg-amber-500/20 text-amber-300',
    rose: 'bg-rose-500/20 text-rose-300',
};

const statusOptions = ['All status', 'Pending', 'Approved'];

const typeFilter = ref('All types');
const statusFilter = ref('All status');

// Local, mutable copy — approve/reject mutates this, never props directly.
const localRequests = ref(props.requests.map((r) => ({ ...r })));

const pendingRequests = computed(() =>
    localRequests.value.filter((r) => r.status === 'pending' && (typeFilter.value === 'All types' || r.type === typeFilter.value))
);
const approvedRequests = computed(() =>
    localRequests.value.filter((r) => r.status === 'approved' && (typeFilter.value === 'All types' || r.type === typeFilter.value))
);

const showPending = computed(() => statusFilter.value === 'All status' || statusFilter.value === 'Pending');
const showApproved = computed(() => statusFilter.value === 'All status' || statusFilter.value === 'Approved');

const decision = ref(null); // { request, action: 'approve' | 'reject', note }

function startDecision(request, action) {
    decision.value = { request, action, note: '' };
}
function cancelDecision() {
    decision.value = null;
}
function confirmDecision() {
    if (!decision.value) return;
    const { request, action } = decision.value;
    request.status = action === 'approve' ? 'approved' : 'rejected';
    request.decisionNote = decision.value.note;
    decision.value = null;
}

function usedBadgeClass(used) {
    if (used >= 6) return 'bg-rose-500/15 text-rose-400';
    if (used >= 3) return 'bg-amber-500/15 text-amber-400';
    return 'bg-emerald-500/15 text-emerald-400';
}
</script>

<template>
    <AppLayout title="Leave Requests">
        <div class="space-y-6">
            <!-- Page header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-white">Leave management</h2>
                <div class="flex items-center gap-2">
                    <select
                        v-model="typeFilter"
                        class="rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                    >
                        <option v-for="t in ['All types', ...typeOptions]" :key="t" :value="t">{{ t }}</option>
                    </select>
                    <select
                        v-model="statusFilter"
                        class="rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                    >
                        <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg border border-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/50"
                    >
                        <Download class="h-4 w-4" />
                        Export
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <!-- Pending approval -->
                    <div v-if="showPending" class="rounded-xl border border-slate-800 bg-slate-900/50">
                        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                            <p class="text-sm font-semibold text-white">Pending approval</p>
                            <span class="rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-medium text-amber-400">
                                {{ pendingRequests.length }} request{{ pendingRequests.length === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <p v-if="!pendingRequests.length" class="px-5 py-6 text-sm text-slate-500">No pending requests.</p>

                        <div class="divide-y divide-slate-800">
                            <div v-for="request in pendingRequests" :key="request.id" class="px-5 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                            :class="avatarColors[request.avatarColor]"
                                        >
                                            {{ request.initials }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">{{ request.teacherName }}</p>
                                            <p class="text-xs text-slate-500">{{ request.type }} &middot; {{ request.dateRange }} &middot; {{ request.days }} day{{ request.days === 1 ? '' : 's' }}</p>
                                        </div>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-medium text-amber-400">Pending</span>
                                </div>

                                <p class="mt-3 text-sm text-slate-300">{{ request.reason }}</p>

                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <span
                                        v-if="request.attachment"
                                        class="flex items-center gap-1.5 rounded-lg border border-slate-800 bg-slate-800/60 px-2.5 py-1 text-xs text-slate-300"
                                    >
                                        <Paperclip class="h-3 w-3" />
                                        {{ request.attachment }}
                                    </span>
                                    <span v-else></span>

                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-3 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-500/25"
                                            @click="startDecision(request, 'reject')"
                                        >
                                            Reject
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-slate-950 hover:bg-emerald-400"
                                            @click="startDecision(request, 'approve')"
                                        >
                                            Approve
                                        </button>
                                    </div>
                                </div>

                                <div v-if="decision && decision.request === request" class="mt-3 rounded-lg border border-slate-800 bg-slate-800/40 p-3">
                                    <label class="text-xs font-medium text-slate-400">
                                        Note {{ decision.action === 'reject' ? '(visible to the teacher)' : '(optional)' }}
                                    </label>
                                    <textarea
                                        v-model="decision.note"
                                        rows="2"
                                        class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                                        :placeholder="decision.action === 'approve' ? 'e.g. Approved, get well soon' : 'e.g. Please reapply with updated dates'"
                                    ></textarea>
                                    <div class="mt-2 flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-800 px-3 py-1.5 text-xs font-medium text-slate-400 hover:bg-slate-800/50"
                                            @click="cancelDecision"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                                            :class="decision.action === 'approve' ? 'bg-emerald-500 text-slate-950 hover:bg-emerald-400' : 'bg-rose-500 text-white hover:bg-rose-400'"
                                            @click="confirmDecision"
                                        >
                                            Confirm {{ decision.action === 'approve' ? 'Approval' : 'Rejection' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recently approved -->
                    <div v-if="showApproved" class="rounded-xl border border-slate-800 bg-slate-900/50">
                        <div class="border-b border-slate-800 px-5 py-4">
                            <p class="text-sm font-semibold text-white">Recently approved</p>
                        </div>

                        <p v-if="!approvedRequests.length" class="px-5 py-6 text-sm text-slate-500">Nothing approved yet.</p>

                        <div class="divide-y divide-slate-800">
                            <div v-for="request in approvedRequests" :key="request.id" class="flex items-center justify-between gap-3 px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                        :class="avatarColors[request.avatarColor]"
                                    >
                                        {{ request.initials }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-100">{{ request.teacherName }}</p>
                                        <p class="text-xs text-slate-500">{{ request.type }} &middot; {{ request.dateRange }} &middot; approved</p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-medium text-emerald-400">Done</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leave balances -->
                <div class="rounded-xl border border-slate-800 bg-slate-900/50">
                    <div class="border-b border-slate-800 px-5 py-4">
                        <p class="text-sm font-semibold text-white">Leave balances &mdash; {{ year }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs uppercase tracking-wider text-slate-500">
                                    <th class="px-5 py-3 font-medium">Teacher</th>
                                    <th class="px-3 py-3 font-medium">Sick</th>
                                    <th class="px-3 py-3 font-medium">Casual</th>
                                    <th class="px-3 py-3 font-medium">Annual</th>
                                    <th class="px-3 py-3 font-medium">Used</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <tr v-for="b in leaveBalances" :key="b.teacher">
                                    <td class="px-5 py-3 font-medium text-slate-200">{{ b.teacher }}</td>
                                    <td class="px-3 py-3 text-slate-400">{{ b.sick }}</td>
                                    <td class="px-3 py-3 text-slate-400">{{ b.casual }}</td>
                                    <td class="px-3 py-3 text-slate-400">{{ b.annual }}</td>
                                    <td class="px-3 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="usedBadgeClass(b.used)">
                                            {{ b.used }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>