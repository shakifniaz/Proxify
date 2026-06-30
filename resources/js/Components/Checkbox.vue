<script setup>
const checked = defineModel('checked', { type: [Array, Boolean], default: false });
</script>

<template>
    <input
        v-model="checked"
        type="checkbox"
        class="h-4 w-4 rounded border-stone-300 bg-white text-blue-700 focus:ring-blue-500"
    />
</template>
