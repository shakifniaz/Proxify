<script setup>
import { computed } from 'vue';
import { Bell, MessageSquare, Search } from 'lucide-vue-next';

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
</script>

<template>
    <header
        class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-800 bg-slate-950/80 px-4 backdrop-blur sm:px-6"
    >
        <h1 class="truncate text-lg font-semibold text-white">{{ title }}</h1>

        <div class="flex items-center gap-3">
            <span class="hidden text-sm text-slate-500 sm:inline">{{ today }}</span>

            <button
                type="button"
                class="relative flex h-9 w-9 items-center justify-center rounded-lg border border-slate-800 text-slate-400 hover:bg-slate-800/70 hover:text-slate-100"
            >
                <Bell class="h-4 w-4" />
                <span class="absolute right-2 top-2 h-1.5 w-1.5 rounded-full bg-rose-500"></span>
            </button>

            <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-800 text-slate-400 hover:bg-slate-800/70 hover:text-slate-100"
            >
                <MessageSquare class="h-4 w-4" />
            </button>

            <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-800 text-slate-400 hover:bg-slate-800/70 hover:text-slate-100"
            >
                <Search class="h-4 w-4" />
            </button>
        </div>
    </header>
</template>