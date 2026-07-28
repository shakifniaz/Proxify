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
        class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-[#8BED9A]/40 bg-white/95 px-4 backdrop-blur sm:px-6"
    >
        <h1 class="truncate text-lg font-semibold text-[#1e2924]">{{ title }}</h1>

        <div class="flex items-center gap-3">
            <span class="hidden text-sm text-[#1e2924]/60 sm:inline">{{ today }}</span>

            <button
                type="button"
                class="relative flex h-9 w-9 items-center justify-center rounded-lg border border-[#8BED9A]/60 bg-white text-[#1e2924]/70 shadow-sm transition hover:border-[#09B884] hover:bg-[#8BED9A]/15 hover:text-[#1e2924]"
            >
                <Bell class="h-4 w-4" />
                <span class="absolute right-2 top-2 h-1.5 w-1.5 rounded-full bg-[#09B884]"></span>
            </button>

            <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-lg border border-[#8BED9A]/60 bg-white text-[#1e2924]/70 shadow-sm transition hover:border-[#09B884] hover:bg-[#8BED9A]/15 hover:text-[#1e2924]"
            >
                <MessageSquare class="h-4 w-4" />
            </button>

            <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-lg border border-[#8BED9A]/60 bg-white text-[#1e2924]/70 shadow-sm transition hover:border-[#09B884] hover:bg-[#8BED9A]/15 hover:text-[#1e2924]"
            >
                <Search class="h-4 w-4" />
            </button>
        </div>
    </header>
</template>
