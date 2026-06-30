<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppSidebar from '@/Components/AppSidebar.vue';
import AppTopbar from '@/Components/AppTopbar.vue';

const props = defineProps({
    title: { type: String, default: 'Dashboard' },
});

const sidebarCollapsed = ref(
    typeof localStorage !== 'undefined' && localStorage.getItem('proxify_sidebar_collapsed') === '1'
);

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('proxify_sidebar_collapsed', sidebarCollapsed.value ? '1' : '0');
    }
}
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen bg-stone-100 text-slate-900">
        <AppSidebar :collapsed="sidebarCollapsed" @toggle="toggleSidebar" />

        <div class="flex min-w-0 flex-1 flex-col">
            <AppTopbar :title="title" />

            <main class="flex-1 overflow-y-auto bg-[radial-gradient(circle_at_top_left,rgba(120,113,108,0.10),transparent_34rem)] p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
