<template>
    <div class="auth-shell relative min-h-screen overflow-x-hidden bg-[#f7fbf8] text-slate-900">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_20%_18%,rgba(139,237,154,0.34),transparent_34%),radial-gradient(ellipse_at_78%_72%,rgba(9,184,132,0.16),transparent_38%),linear-gradient(135deg,rgba(255,255,255,0.96)_0%,rgba(232,255,240,0.78)_42%,rgba(30,41,36,0.08)_100%)]"></div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-[72vh] auth-grid"></div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-36 bg-gradient-to-t from-white/70 via-white/30 to-transparent"></div>

        <div class="relative flex min-h-screen items-center justify-center px-4 py-6 sm:px-6 lg:py-10">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.auth-grid {
    background-image:
        linear-gradient(rgba(9, 184, 132, 0.22) 1px, transparent 1px),
        linear-gradient(90deg, rgba(9, 184, 132, 0.22) 1px, transparent 1px);
    background-size: 34px 34px;
    mask-image: linear-gradient(to top, black 0%, rgba(0, 0, 0, 0.92) 28%, rgba(0, 0, 0, 0.48) 62%, transparent 100%);
}
</style>
