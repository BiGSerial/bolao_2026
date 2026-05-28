<template>
    <div class="rounded-xl border border-white/[0.06] p-4 space-y-3" style="background:#13161b">
        <SkeletonBlock height="14px" width="60%" />
        <SkeletonBlock height="12px" width="80%" />
        <SkeletonBlock height="12px" width="50%" />
    </div>
</template>

<script setup>
import SkeletonBlock from './SkeletonBlock.vue';
</script>
