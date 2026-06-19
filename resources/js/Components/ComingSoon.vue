<script setup>
import { Construction } from 'lucide-vue-next';

defineProps({
    description: { type: String, default: 'This module is coming up next.' },
});
</script>

<template>
    <div
        class="flex min-h-[60vh] flex-col items-center justify-center rounded-xl border border-dashed border-slate-800 bg-slate-900/40 text-center"
    >
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-800">
            <Construction class="h-6 w-6 text-slate-400" />
        </div>
        <p class="text-sm font-medium text-slate-300">{{ description }}</p>
        <p class="mt-1 text-xs text-slate-500">Routing is wired up — the screen will be built here.</p>
    </div>
</template>