<template>
    <div
        class="rank-row"
        :class="isMe ? 'rank-me' : 'rank-default'"
    >
        <!-- Position -->
        <div class="rank-pos">
            <span v-if="row.position === 1" class="text-xl">🥇</span>
            <span v-else-if="row.position === 2" class="text-xl">🥈</span>
            <span v-else-if="row.position === 3" class="text-xl">🥉</span>
            <span v-else class="text-sm font-bold" :class="isMe ? 'text-bolao-accent' : 'text-bolao-muted2'">
                {{ row.position }}
            </span>
        </div>

        <!-- Name + sector -->
        <div class="flex-1 min-w-0">
            <p class="text-sm font-bold truncate leading-tight" :class="isMe ? 'text-bolao-accent' : 'text-slate-100'">
                {{ row.user?.name ?? '—' }}
                <span v-if="isMe" class="ml-1 text-[10px] font-normal text-bolao-muted">(você)</span>
            </p>
            <p v-if="row.sector" class="text-[10px] text-bolao-muted2 mt-0.5">{{ row.sector }}</p>
        </div>

        <!-- Stats -->
        <div class="flex items-center gap-3 shrink-0">
            <!-- Exact scores -->
            <div class="text-center hidden xs:block">
                <p class="text-[9px] text-bolao-muted2 uppercase tracking-wide leading-none mb-0.5">Exatos</p>
                <p class="text-xs font-bold text-slate-300">{{ row.exact_scores ?? 0 }}</p>
            </div>
            <!-- Points -->
            <div class="text-center min-w-[36px]">
                <p class="text-[9px] text-bolao-muted2 uppercase tracking-wide leading-none mb-0.5">Pts</p>
                <p class="text-base font-extrabold font-bc leading-none" :class="isMe ? 'text-bolao-accent' : 'text-white'">
                    {{ row.points_total ?? 0 }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../../store/auth';

const props = defineProps({ row: { type: Object, required: true } });
const auth = useAuthStore();
const isMe = computed(() => props.row.user?.id === auth.user?.id);
</script>

<style scoped>
.rank-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid;
}
.rank-default { background: #13161b; border-color: rgba(255,255,255,0.06); }
.rank-me      { background: rgba(245,166,35,0.07); border-color: rgba(245,166,35,0.22); }

.rank-pos {
    width: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    text-align: center;
}
</style>
