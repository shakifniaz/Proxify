<script setup>
defineProps({
    value: { type: String, default: null },
});
</script>

<template>
    <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
        <slot v-if="!value" />
        <template v-else>{{ value }}</template>
    </label>
</template>