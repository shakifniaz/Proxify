<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-12">
        <div class="pointer-events-none absolute -top-32 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-emerald-500/10 blur-3xl"></div>

        <div class="relative w-full max-w-md">
            <div class="mb-8 flex flex-col items-center gap-3 text-center">
                <Link href="/" class="flex items-center gap-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500">
                        <span class="text-lg font-bold text-slate-950">P</span>
                    </div>
                    <span class="text-xl font-semibold text-white">Proxify</span>
                </Link>
                <p class="text-sm text-slate-500">School Proxy &amp; Routine Management</p>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-8 shadow-xl">
                <slot />
            </div>
        </div>
    </div>
</template>