<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Eye, Megaphone, Send } from 'lucide-vue-next';

const props = defineProps({
    notices: { type: Array, default: () => [] },
    urgencyOptions: { type: Array, default: () => [] },
    audienceOptions: { type: Array, default: () => [] },
    totalStaff: { type: Number, default: 18 },
});

// Role Verification Logic
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const urgencyClasses = {
    Urgent: { border: 'border-red-200 bg-red-50', badge: 'bg-red-50 text-red-700 border border-red-200' },
    Important: { border: 'border-amber-200 bg-amber-50', badge: 'bg-amber-50 text-amber-700 border border-amber-200' },
    Normal: { border: 'border-stone-300 bg-white', badge: 'bg-blue-50 text-blue-700 border border-blue-200' },
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

function submitNotice() {
    if (!form.value.title || !form.value.message) return;

    localNotices.value.unshift({
        id: Date.now(),
        title: form.value.title,
        message: form.value.message,
        urgency: form.value.urgency,
        postedBy: 'Current User',
        postedDate: 'Just now',
        readCount: 0,
        totalStaff: props.totalStaff,
    });

    form.value = blankForm();
    showPreview.value = false;
    titleInput.value?.focus();
}
</script>

<template>
    <AppLayout title="Institutional Noticeboard">
        <div class="space-y-6">

            <div class="grid grid-cols-1 gap-6 items-start" :class="isAdmin ? 'lg:grid-cols-3' : 'max-w-4xl mx-auto'">
                
                <div class="space-y-4" :class="isAdmin ? 'lg:col-span-2' : ''">
                    <div class="border-b border-stone-200 pb-2">
                        <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider">Active Broadcast Archives</h3>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="notice in localNotices"
                            :key="notice.id"
                            class="rounded-lg border p-5 space-y-3 transition-colors"
                            :class="urgencyClasses[notice.urgency]?.border ?? urgencyClasses.Normal.border"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <span
                                        class="inline-block rounded px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider"
                                        :class="urgencyClasses[notice.urgency]?.badge ?? urgencyClasses.Normal.badge"
                                    >
                                        {{ notice.urgency }} Notice
                                    </span>
                                    <h4 class="text-sm font-bold text-slate-950 tracking-wide pt-1">{{ notice.title }}</h4>
                                </div>
                                <span class="text-[11px] text-slate-500 whitespace-nowrap">{{ notice.postedDate }}</span>
                            </div>

                            <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">
                                {{ notice.message }}
                            </p>

                            <div class="pt-3 border-t border-stone-200/60 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500">
                                <div>
                                    Posted by: <span class="text-slate-600 font-medium">{{ notice.postedBy }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Eye class="h-3.5 w-3.5 text-slate-500" />
                                    <span>Read by {{ notice.readCount }}/{{ notice.totalStaff }} staff members</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="localNotices.length === 0" class="text-center py-12 text-xs text-slate-500 italic border border-dashed border-stone-300 rounded-lg">
                            No active administrative announcements recorded on the live stream.
                        </div>
                    </div>
                </div>

                <div v-if="isAdmin" class="surface-card p-5 space-y-4">
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                            <Send class="h-4 w-4 text-blue-700" />
                            Compose Official Notice
                        </h4>
                        <p class="text-[11px] text-slate-500 leading-normal">
                            Draft a localized bulletin card to instantly distribute across the global staff ecosystem dashboard layout panels.
                        </p>
                    </div>

                    <div class="space-y-3 pt-2">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Notice Heading Title</label>
                            <input
                                ref="titleInput"
                                v-model="form.title"
                                type="text"
                                placeholder="e.g., Campus Faculty General Meeting rescheduled"
                                class="w-full bg-stone-100 border border-stone-300 rounded-lg px-3 py-2 text-xs text-slate-800 placeholder-slate-600 focus:outline-none focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Detailed Message Context</label>
                            <textarea
                                v-model="form.message"
                                rows="5"
                                placeholder="State core logistical operational facts concisely..."
                                class="w-full bg-stone-100 border border-stone-300 rounded-lg px-3 py-2 text-xs text-slate-800 placeholder-slate-600 focus:outline-none focus:border-blue-500 resize-none"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Urgency Level</label>
                                <select
                                    v-model="form.urgency"
                                    class="w-full bg-stone-100 border border-stone-300 rounded-lg px-2.5 py-2 text-xs text-slate-600 focus:outline-none focus:border-blue-500"
                                >
                                    <option v-for="u in urgencyOptions" :key="u" :value="u">{{ u }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Target Audience</label>
                                <select
                                    v-model="form.audience"
                                    class="w-full bg-stone-100 border border-stone-300 rounded-lg px-2.5 py-2 text-xs text-slate-600 focus:outline-none focus:border-blue-500"
                                >
                                    <option v-for="a in audienceOptions" :key="a" :value="a">{{ a }}</option>
                                </select>
                            </div>
                        </div>

                        <div
                            v-if="showPreview && form.title"
                            class="rounded-lg border p-3 bg-stone-50 border-stone-300 space-y-1 text-xs"
                        >
                            <span class="text-[9px] font-bold uppercase tracking-wider text-blue-700 block">Live Form Preview Drawer:</span>
                            <h5 class="font-bold text-slate-900 truncate">{{ form.title }}</h5>
                            <p class="text-slate-600 line-clamp-2 text-[11px] italic leading-tight">{{ form.message || 'No description body drafted...' }}</p>
                        </div>

                        <div class="pt-2 flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-white hover:text-slate-800 transition-colors"
                                @click="togglePreview"
                            >
                                {{ showPreview ? 'Hide Preview' : 'Preview Notice' }}
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-lg bg-blue-700 hover:bg-blue-700 text-slate-950 font-bold text-xs py-2 px-3 transition-colors shadow-lg shadow-none disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!form.title || !form.message"
                                @click="submitNotice"
                            >
                                Post Notice
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>