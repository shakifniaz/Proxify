<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Bell,
    CheckCheck,
    MessageCircle,
    Paperclip,
    Search,
    Send,
    ShieldCheck,
    UsersRound,
} from 'lucide-vue-next';

const props = defineProps({
    channels: { type: Array, default: () => [] },
    currentUserName: { type: String, default: 'User' },
    role: { type: String, default: 'admin' },
    storageBackend: { type: String, default: 'Firebase' },
});

const selectedChannelId = ref(props.channels[0]?.id ?? null);
const search = ref('');
const draft = ref('');
const localMessages = ref(
    Object.fromEntries(props.channels.map((channel) => [channel.id, [...(channel.messages ?? [])]]))
);

const filteredChannels = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (!needle) return props.channels;

    return props.channels.filter((channel) =>
        [channel.name, channel.subtitle]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(needle))
    );
});

const activeChannel = computed(() =>
    props.channels.find((channel) => channel.id === selectedChannelId.value) ?? props.channels[0] ?? null
);

const activeMessages = computed(() =>
    activeChannel.value ? localMessages.value[activeChannel.value.id] ?? [] : []
);

const totalUnread = computed(() => props.channels.reduce((sum, channel) => sum + Number(channel.unread || 0), 0));
const onlineCount = computed(() => props.channels.reduce((sum, channel) => sum + Number(channel.online || 0), 0));

function sendMessage() {
    if (!activeChannel.value || !draft.value.trim()) return;

    localMessages.value[activeChannel.value.id] = [
        ...(localMessages.value[activeChannel.value.id] ?? []),
        {
            id: Date.now(),
            author: props.currentUserName,
            role: props.role === 'admin' ? 'Admin' : 'Teacher',
            time: 'Just now',
            body: draft.value.trim(),
            mine: true,
        },
    ];

    draft.value = '';
}
</script>

<template>
    <AppLayout title="Staffroom">
        <div class="staffroom-shell space-y-5">
            <section class="relative overflow-hidden rounded-2xl border border-[#8BED9A]/50 bg-white p-5 shadow-sm">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(139,237,154,0.26),transparent_34%),linear-gradient(135deg,rgba(9,184,132,0.08),transparent_46%)]"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#1e2924] text-[#8BED9A] shadow-lg shadow-[#1e2924]/20">
                            <MessageCircle class="h-6 w-6" />
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-[#1e2924]">Staffroom</h2>
                            <p class="text-sm font-medium text-slate-500">Realtime staff chat shell, planned for {{ storageBackend }} sync.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-[#8BED9A]/16 px-4 py-2">
                            <p class="text-lg font-black text-[#1e2924]">{{ channels.length }}</p>
                            <p class="text-[10px] font-black uppercase tracking-wider text-[#1e2924]/55">Channels</p>
                        </div>
                        <div class="rounded-xl bg-[#8BED9A]/16 px-4 py-2">
                            <p class="text-lg font-black text-[#1e2924]">{{ onlineCount }}</p>
                            <p class="text-[10px] font-black uppercase tracking-wider text-[#1e2924]/55">Online</p>
                        </div>
                        <div class="rounded-xl bg-[#8BED9A]/16 px-4 py-2">
                            <p class="text-lg font-black text-[#1e2924]">{{ totalUnread }}</p>
                            <p class="text-[10px] font-black uppercase tracking-wider text-[#1e2924]/55">Unread</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="surface-card grid min-h-[42rem] overflow-hidden lg:grid-cols-[20rem_minmax(0,1fr)]">
                <aside class="border-b border-stone-200 bg-stone-50/60 lg:border-b-0 lg:border-r">
                    <div class="border-b border-stone-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-black text-[#1e2924]">Channels</p>
                            <span class="inline-flex items-center gap-1 rounded-full bg-[#8BED9A]/20 px-2.5 py-1 text-xs font-black text-[#1e2924]">
                                <ShieldCheck class="h-3.5 w-3.5 text-[#09B884]" />
                                Staff only
                            </span>
                        </div>
                        <div class="relative mt-3">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="search" type="text" class="field-control w-full pl-9" placeholder="Search channels" />
                        </div>
                    </div>

                    <div class="max-h-[34rem] space-y-2 overflow-y-auto p-3">
                        <button
                            v-for="channel in filteredChannels"
                            :key="channel.id"
                            type="button"
                            class="group w-full rounded-xl border p-3 text-left transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                            :class="activeChannel?.id === channel.id ? 'border-[#09B884]/55 bg-[#8BED9A]/18 shadow-sm' : 'border-stone-200 bg-white hover:border-[#8BED9A]/60'"
                            @click="selectedChannelId = channel.id"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-950">{{ channel.name }}</p>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ channel.subtitle }}</p>
                                </div>
                                <span v-if="channel.unread" class="rounded-full bg-[#1e2924] px-2 py-0.5 text-xs font-black text-[#8BED9A]">{{ channel.unread }}</span>
                            </div>
                            <div class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/80 px-2 py-1 text-[11px] font-bold text-slate-600">
                                <UsersRound class="h-3.5 w-3.5 text-[#09B884]" />
                                {{ channel.online }} online
                            </div>
                        </button>
                    </div>
                </aside>

                <main v-if="activeChannel" class="flex min-w-0 flex-col bg-white">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 px-5 py-4">
                        <div>
                            <p class="text-base font-black text-[#1e2924]">{{ activeChannel.name }}</p>
                            <p class="text-sm font-semibold text-slate-500">{{ activeChannel.subtitle }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-[#8BED9A]/55 bg-[#8BED9A]/14 px-3 py-2 text-xs font-black text-[#1e2924]">
                                <Bell class="h-3.5 w-3.5 text-[#09B884]" />
                                Live later
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-stone-200 bg-white px-3 py-2 text-xs font-bold text-slate-600">
                                {{ storageBackend }}
                            </span>
                        </div>
                    </header>

                    <div class="flex-1 space-y-4 overflow-y-auto bg-[linear-gradient(180deg,rgba(139,237,154,0.08),rgba(255,255,255,0))] p-5">
                        <TransitionGroup name="chat-list" tag="div" class="space-y-4">
                            <div
                                v-for="message in activeMessages"
                                :key="message.id"
                                class="flex"
                                :class="message.mine ? 'justify-end' : 'justify-start'"
                            >
                                <div class="max-w-[76%] rounded-2xl border px-4 py-3 shadow-sm" :class="message.mine ? 'border-[#1e2924] bg-[#1e2924] text-white' : 'border-stone-200 bg-white text-slate-700'">
                                    <div class="mb-1 flex flex-wrap items-center gap-2 text-xs">
                                        <span class="font-black" :class="message.mine ? 'text-[#8BED9A]' : 'text-[#1e2924]'">{{ message.author }}</span>
                                        <span :class="message.mine ? 'text-white/50' : 'text-slate-400'">{{ message.role }}</span>
                                        <span :class="message.mine ? 'text-white/50' : 'text-slate-400'">{{ message.time }}</span>
                                    </div>
                                    <p class="text-sm leading-relaxed" :class="message.mine ? 'text-white/90' : 'text-slate-600'">{{ message.body }}</p>
                                    <div v-if="message.mine" class="mt-2 flex justify-end text-[#8BED9A]">
                                        <CheckCheck class="h-4 w-4" />
                                    </div>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>

                    <footer class="border-t border-stone-200 bg-white p-4">
                        <div class="flex items-end gap-2 rounded-2xl border border-[#8BED9A]/45 bg-[#8BED9A]/10 p-2">
                            <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#8BED9A]/55 bg-white text-[#09B884] transition hover:bg-[#8BED9A]/20">
                                <Paperclip class="h-4 w-4" />
                            </button>
                            <textarea
                                v-model="draft"
                                rows="1"
                                class="min-h-10 flex-1 resize-none border-0 bg-transparent px-2 py-2 text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                                placeholder="Write a staff message"
                                @keydown.enter.prevent="sendMessage"
                            ></textarea>
                            <button type="button" class="btn-primary min-h-10 px-4" :disabled="!draft.trim()" @click="sendMessage">
                                <Send class="h-4 w-4" />
                                Send
                            </button>
                        </div>
                    </footer>
                </main>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.staffroom-shell {
    animation: staffroom-rise 420ms ease-out both;
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
