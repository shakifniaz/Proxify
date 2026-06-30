<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-stone-100 px-4 py-12 text-slate-900">
        <div class="relative w-full max-w-md">
            <div class="mb-8 flex flex-col items-center gap-3 text-center">
                <Link href="/" class="flex items-center gap-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#c18f5a] shadow-sm">
                        <span class="text-lg font-bold text-slate-950">P</span>
                    </div>
                    <span class="text-xl font-semibold text-slate-950">Proxify</span>
                </Link>
                <p class="text-sm text-slate-500">School Proxy &amp; Routine Management</p>
            </div>

            <div class="surface-card p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
