<script setup>
const checked = defineModel('checked', { type: [Array, Boolean], default: false });
</script>

<template>
    <input
        v-model="checked"
        type="checkbox"
        class="h-4 w-4 rounded border-slate-700 bg-slate-800 text-emerald-500 focus:ring-emerald-500"
    />
</template>