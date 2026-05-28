<template>
    <div class="match-card" :class="live ? 'match-live' : ''">

        <!-- Competition + time -->
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] text-bolao-muted2 font-semibold">
                {{ match.competition?.name ?? '' }}
                <template v-if="match.stage"> · {{ stageLabel }}</template>
                <template v-if="match.matchday"> · R{{ match.matchday }}</template>
            </span>
            <div class="flex items-center gap-1">
                <span v-if="live" class="live-dot"></span>
                <span v-if="live" class="text-[10px] font-bold text-red-400">AO VIVO</span>
                <span v-else class="text-[10px] font-bold text-bolao-accent">{{ matchTime }}</span>
            </div>
        </div>

        <!-- Teams + score -->
        <div class="flex items-center gap-2">
            <!-- Home -->
            <div class="flex flex-1 items-center gap-2 min-w-0">
                <img v-if="match.home_team?.crest" :src="match.home_team.crest" class="h-8 w-8 object-contain shrink-0" alt="">
                <div v-else class="crest-ph shrink-0">{{ match.home_team?.tla ?? '?' }}</div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-100 truncate leading-tight">
                        {{ match.home_team?.short_name ?? match.home_team?.name ?? '—' }}
                    </p>
                </div>
            </div>

            <!-- Score / dash -->
            <div class="shrink-0 text-center w-16">
                <p v-if="live || isFinished" class="text-lg font-black text-white font-bc">
                    {{ match.score?.home ?? 0 }} – {{ match.score?.away ?? 0 }}
                </p>
                <p v-else class="text-lg font-black text-bolao-muted2 font-bc">vs</p>
            </div>

            <!-- Away -->
            <div class="flex flex-1 items-center gap-2 justify-end min-w-0">
                <div class="min-w-0 text-right">
                    <p class="text-sm font-bold text-slate-100 truncate leading-tight">
                        {{ match.away_team?.short_name ?? match.away_team?.name ?? '—' }}
                    </p>
                </div>
                <img v-if="match.away_team?.crest" :src="match.away_team.crest" class="h-8 w-8 object-contain shrink-0" alt="">
                <div v-else class="crest-ph shrink-0">{{ match.away_team?.tla ?? '?' }}</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    match: { type: Object, required: true },
    live:  Boolean,
});

const STAGE_MAP = {
    GROUP_STAGE: 'Grupos', LAST_16: 'Oitavas', QUARTER_FINALS: 'Quartas',
    SEMI_FINALS: 'Semi', FINAL: 'Final', THIRD_PLACE: '3º Lugar',
};

const stageLabel = computed(() => STAGE_MAP[props.match.stage] ?? props.match.stage?.replace(/_/g,' ') ?? '');
const isFinished = computed(() => ['FINISHED','AWARDED'].includes(props.match.status));

const matchTime = computed(() => {
    const d = props.match.local_date || props.match.utc_date;
    if (!d) return '—';
    const opts = { hour: '2-digit', minute: '2-digit' };
    if (!props.match.local_date) opts.timeZone = 'America/Sao_Paulo';
    return new Date(d).toLocaleTimeString('pt-BR', opts);
});
</script>

<style scoped>
.match-card {
    padding: 11px 13px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.07);
    background: #13161b;
    transition: border-color 0.15s;
}
.match-live { border-color: rgba(239,68,68,0.28); background: #13161b; }

.crest-ph {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: rgba(255,255,255,0.05);
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; font-weight: 700; color: #4a5568;
    font-family: 'Barlow Condensed', sans-serif;
    flex-shrink: 0;
}
</style>
