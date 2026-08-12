<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppSidebar from '@/Components/AppSidebar.vue';
import AppTopbar from '@/Components/AppTopbar.vue';

const props = defineProps({
    title: { type: String, default: 'Dashboard' },
    liveRefresh: { type: Boolean, default: false },
    refreshInterval: { type: Number, default: 15000 },
    flush: { type: Boolean, default: false },
});

const sidebarCollapsed = ref(
    typeof localStorage !== 'undefined' && localStorage.getItem('scholarly_sidebar_collapsed') === '1'
);

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('scholarly_sidebar_collapsed', sidebarCollapsed.value ? '1' : '0');
    }
}

let refreshTimer = null;

function userIsTyping() {
    const element = document.activeElement;
    if (!element) return false;

    return ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName) || element.isContentEditable;
}

function refreshPage() {
    if (!props.liveRefresh || document.hidden || userIsTyping()) return;

    router.reload({
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function refreshOnFocus() {
    if (!document.hidden) refreshPage();
}

onMounted(() => {
    if (!props.liveRefresh) return;

    refreshTimer = window.setInterval(refreshPage, props.refreshInterval);
    document.addEventListener('visibilitychange', refreshOnFocus);
    window.addEventListener('focus', refreshPage);
});

onUnmounted(() => {
    if (refreshTimer) window.clearInterval(refreshTimer);
    document.removeEventListener('visibilitychange', refreshOnFocus);
    window.removeEventListener('focus', refreshPage);
});
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen bg-white text-slate-900">
        <AppSidebar :collapsed="sidebarCollapsed" @toggle="toggleSidebar" />

        <div class="flex min-w-0 flex-1 flex-col">
            <AppTopbar :title="title" />

            <main class="min-w-0 flex-1 overflow-x-hidden overflow-y-auto bg-white" :class="flush ? 'p-0 pb-24 sm:pb-0' : 'p-3 pb-24 sm:p-6'">
                <slot />
            </main>
        </div>
    </div>
</template>
