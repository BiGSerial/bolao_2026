<template>
    <RouterLink :to="`/app/pools/${pool.id}`"
                class="flex items-center gap-3 rounded-xl border border-white/[0.07] bg-bolao-bg2 px-3 py-2.5 active:bg-bolao-bg3 transition-colors">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-bolao-accent/10">
            <i class="ti ti-trophy text-bolao-accent text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-slate-200 truncate">{{ pool.name }}</p>
            <p class="text-[11px] text-bolao-muted truncate">{{ pool.competition_name ?? '' }}</p>
        </div>
        <i class="ti ti-chevron-right text-bolao-muted2 shrink-0"></i>
    </RouterLink>
</template>

<script setup>
import { RouterLink } from 'vue-router';
defineProps({ pool: { type: Object, required: true } });
</script>
