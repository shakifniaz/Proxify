<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Eye, Megaphone, Send } from 'lucide-vue-next';

const props = defineProps({
    notices: { type: Array, default: () => [] }, // [{ id, title, message, urgency, postedBy, postedDate, readCount, totalStaff }]
    urgencyOptions: { type: Array, default: () => [] },
    audienceOptions: { type: Array, default: () => [] },
    totalStaff: { type: Number, default: 18 },
});

const urgencyClasses = {
    Urgent: { border: 'border-rose-500', badge: 'bg-rose-500/15 text-rose-400' },
    Important: { border: 'border-amber-500', badge: 'bg-amber-500/15 text-amber-400' },
    Normal: { border: 'border-emerald-500', badge: 'bg-emerald-500/15 text-emerald-400' },
};

const localNotices = ref(props.notices.map((n) => ({ ...n })));

const blankForm = () => ({
    title: '',
    message: '',
    urgency: props.urgencyOptions[0] ?? 'Normal',
    audience: props.audienceOptions[0] ?? 'All staff',
});
const form = ref(blankForm());
const showPreview = ref(false);
const titleInput = ref(null);

function togglePreview() {
    showPreview.value = !showPreview.value;
}

function focusTitle() {
    titleInput.value?.focus();
}

function quickUrgent() {
    form.value.urgency = 'Urgent';
    focusTitle();
}

function readRateColor(notice) {
    const rate = notice.totalStaff ? notice.readCount / notice.totalStaff : 0;
    if (rate >= 0.9) return 'text-emerald-400';
    if (rate >= 0.5) return 'text-amber-400';
    return 'text-rose-400';
}

function formatToday() {
    return new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function submitNotice() {
    if (!form.value.title || !form.value.message) return;
    const nextId = Math.max(0, ...localNotices.value.map((n) => n.id)) + 1;
    localNotices.value.unshift({
        id: nextId,
        title: form.value.title,
        message: form.value.message,
        urgency: form.value.urgency,
        postedBy: 'Admin',
        postedDate: formatToday(),
        readCount: 0,
        totalStaff: props.totalStaff,
    });
    form.value = blankForm();
    showPreview.value = false;
}
</script>

<template>
    <AppLayout title="Noticeboard">
        <div class="space-y-6">
            <!-- Page header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-white">Noticeboard</h2>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-2 text-sm font-semibold text-rose-300 hover:bg-rose-500/25"
                        @click="quickUrgent"
                    >
                        <Megaphone class="h-4 w-4" />
                        Broadcast urgent
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                        @click="focusTitle"
                    >
                        <Send class="h-4 w-4" />
                        Post notice
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Notices feed -->
                <div class="space-y-4 lg:col-span-2">
                    <div
                        v-for="notice in localNotices"
                        :key="notice.id"
                        class="rounded-xl border-l-4 bg-slate-900/50 p-5"
                        :class="urgencyClasses[notice.urgency].border"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-semibold text-white">{{ notice.title }}</p>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="urgencyClasses[notice.urgency].badge"
                            >
                                {{ notice.urgency }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-300">{{ notice.message }}</p>
                        <div class="mt-3 flex items-center justify-between text-xs">
                            <span class="text-slate-500">Posted by {{ notice.postedBy }} &middot; {{ notice.postedDate }}</span>
                            <span class="flex items-center gap-1 font-medium" :class="readRateColor(notice)">
                                <Eye class="h-3 w-3" /> {{ notice.readCount }} / {{ notice.totalStaff }} read
                            </span>
                        </div>
                    </div>

                    <p v-if="!localNotices.length" class="rounded-xl border border-dashed border-slate-800 px-5 py-8 text-center text-sm text-slate-500">
                        No notices yet.
                    </p>
                </div>

                <!-- Post new notice -->
                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                    <p class="text-sm font-semibold text-white">Post new notice</p>

                    <div
                        v-if="showPreview"
                        class="mt-4 rounded-xl border-l-4 bg-slate-800/40 p-4"
                        :class="urgencyClasses[form.urgency].border"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-semibold text-white">{{ form.title || 'Notice title…' }}</p>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="urgencyClasses[form.urgency].badge">
                                {{ form.urgency }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-300">{{ form.message || 'Notice message…' }}</p>
                        <p class="mt-2 text-xs text-slate-500">To: {{ form.audience }}</p>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Title</label>
                            <input
                                ref="titleInput"
                                v-model="form.title"
                                type="text"
                                placeholder="Notice title..."
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Message</label>
                            <textarea
                                v-model="form.message"
                                rows="4"
                                placeholder="Write your notice here..."
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                            ></textarea>
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Urgency Level</label>
                            <select
                                v-model="form.urgency"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            >
                                <option v-for="u in urgencyOptions" :key="u" :value="u">{{ u }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Target Audience</label>
                            <select
                                v-model="form.audience"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            >
                                <option v-for="a in audienceOptions" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/50"
                            @click="togglePreview"
                        >
                            {{ showPreview ? 'Hide preview' : 'Preview' }}
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!form.title || !form.message"
                            @click="submitNotice"
                        >
                            Post notice
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>