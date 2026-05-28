<template>
    <div
        class="skeleton-block animate-pulse rounded-lg bg-bolao-bg3"
        :style="{ width, height, borderRadius: radius }"
    />
</template>

<script setup>
defineProps({
    width: { type: String, default: '100%' },
    height: { type: String, default: '16px' },
    radius: { type: String, default: '8px' },
});
</script>

<style scoped>
.skeleton-block {
    background: linear-gradient(90deg, #1c2029 25%, #252b38 50%, #1c2029 75%);
    background-size: 600px 100%;
    animation: shimmer 1.5s ease infinite;
}

@keyframes shimmer {
    0%   { background-position: -600px 0; }
    100% { background-position:  600px 0; }
}
</style>
