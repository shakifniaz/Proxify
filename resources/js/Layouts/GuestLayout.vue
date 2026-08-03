<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="auth-shell relative min-h-screen overflow-hidden bg-white text-slate-900">
        <div class="pointer-events-none absolute inset-0 auth-grid opacity-80"></div>
        <div class="pointer-events-none absolute -left-28 top-16 h-80 w-80 rounded-full bg-[#8BED9A]/35 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 bottom-10 h-96 w-96 rounded-full bg-[#09B884]/15 blur-3xl"></div>
        <div class="pointer-events-none absolute left-1/2 top-1/2 h-[34rem] w-[34rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#1e2924]/5 blur-3xl"></div>

        <div class="relative flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
            <div class="w-full max-w-6xl">
                <div class="mb-6 flex items-center justify-between gap-4">
                    <Link href="/" class="inline-flex items-center gap-3">
                        <span class="brand-wordmark text-3xl text-[#1e2924]">Campulse</span>
                    </Link>
                    <div class="hidden rounded-full border border-[#8BED9A]/55 bg-white/80 px-3 py-1.5 text-xs font-bold text-[#1e2924] shadow-sm backdrop-blur sm:block">
                        School management workspace
                    </div>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-grid {
    background-image:
        linear-gradient(rgba(9, 184, 132, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(9, 184, 132, 0.08) 1px, transparent 1px);
    background-size: 34px 34px;
    mask-image: radial-gradient(circle at center, black, transparent 78%);
}
</style>
