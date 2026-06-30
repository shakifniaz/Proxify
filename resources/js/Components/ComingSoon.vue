<script setup>
import { Construction } from 'lucide-vue-next';

defineProps({
    description: { type: String, default: 'This module is coming up next.' },
});
</script>

<template>
    <div
        class="flex min-h-[60vh] flex-col items-center justify-center rounded-lg border border-dashed border-stone-300 bg-white text-center shadow-sm"
    >
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-stone-100">
            <Construction class="h-6 w-6 text-slate-600" />
        </div>
        <p class="text-sm font-medium text-slate-700">{{ description }}</p>
        <p class="mt-1 text-xs text-slate-500">Routing is wired up — the screen will be built here.</p>
    </div>
</template>
