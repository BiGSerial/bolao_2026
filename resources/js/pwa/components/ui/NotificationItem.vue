<template>
    <div
        class="flex items-start gap-3 rounded-xl border px-3 py-3 transition-colors cursor-pointer active:bg-bolao-bg3"
        :class="item.read_at ? 'border-white/[0.06] bg-bolao-bg2' : 'border-bolao-accent/20 bg-bolao-accent/[0.04]'"
        @click="$emit('read', item.id)"
    >
        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
             :class="item.read_at ? 'bg-white/[0.06]' : 'bg-bolao-accent/15'">
            <i class="ti text-base" :class="[icon, item.read_at ? 'text-bolao-muted' : 'text-bolao-accent']"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold leading-snug"
               :class="item.read_at ? 'text-slate-300' : 'text-white'">
                {{ title }}
            </p>
            <p v-if="body" class="text-xs text-bolao-muted mt-0.5 leading-snug line-clamp-2">{{ body }}</p>
            <p class="text-[10px] text-bolao-muted2 mt-1">{{ timeAgo }}</p>
        </div>
        <div v-if="!item.read_at" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-bolao-accent"></div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
});

defineEmits(['read']);

const icon = computed(() => {
    const t = props.item.type ?? '';
    if (t.includes('Invite')) return 'ti-mail';
    if (t.includes('Rank')) return 'ti-trophy';
    if (t.includes('Match') || t.includes('Goal')) return 'ti-ball-football';
    return 'ti-bell';
});

const title = computed(() => props.item.data?.title ?? props.item.data?.message ?? 'Notificação');
const body  = computed(() => props.item.data?.body ?? props.item.data?.detail ?? '');

const timeAgo = computed(() => {
    if (!props.item.created_at) return '';
    const diff = Date.now() - new Date(props.item.created_at).getTime();
    const m = Math.floor(diff / 60000);
    if (m < 1) return 'agora';
    if (m < 60) return `${m} min atrás`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h atrás`;
    return `${Math.floor(h / 24)}d atrás`;
});
</script>
