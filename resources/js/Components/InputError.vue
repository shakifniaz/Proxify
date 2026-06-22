<script setup>
defineProps({
    message: { type: String, default: null },
});
</script>

<template>
    <div v-show="message">
        <p class="text-xs text-rose-400">{{ message }}</p>
    </div>
</template>