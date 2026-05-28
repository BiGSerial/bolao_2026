<template>
    <div
        class="rounded-lg border p-3 flex flex-col gap-1 transition-colors"
        :class="[
            accent ? 'border-bolao-accent/35 bg-bolao-accent/[0.1]' :
            warn   ? 'border-amber-400/35 bg-amber-400/[0.1]' :
                     'border-white/[0.07] bg-bolao-bg2'
        ]"
    >
        <i :class="['ti', icon, 'text-xl', accent ? 'text-bolao-accent' : warn ? 'text-amber-400' : 'text-bolao-muted']"></i>
        <p class="text-[22px] font-bold leading-none" :class="accent ? 'text-bolao-accent' : warn ? 'text-amber-400' : 'text-white'">
            {{ value ?? '—' }}
        </p>
        <p class="text-[11px] font-semibold text-bolao-muted uppercase tracking-wide leading-none">{{ label }}</p>
    </div>
</template>

<script setup>
defineProps({
    label: String,
    value: [Number, String],
    icon: { type: String, default: 'ti-circle' },
    accent: Boolean,
    warn: Boolean,
});
</script>
