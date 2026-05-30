<template>
    <div class="pred-card" :class="cardClass">

        <!-- Match header -->
        <div class="pred-match-header">
            <!-- Stage / matchday -->
            <div class="flex items-center gap-1.5">
                <span v-if="stageLabel" class="phase-badge" :class="stagePhaseCls">{{ stageLabel }}</span>
                <span v-if="item.match.matchday" class="text-[10px] text-bolao-muted2 font-semibold">
                    Rodada {{ item.match.matchday }}
                </span>
            </div>
            <!-- Date + status -->
            <div class="flex items-center gap-1.5">
                <template v-if="isLive">
                    <span class="live-dot"></span>
                    <span class="text-[10px] font-bold text-red-400">Ao vivo</span>
                </template>
                <template v-else-if="isFinished">
                    <span class="text-[10px] text-bolao-muted2 font-semibold">Encerrado</span>
                </template>
                <template v-else>
                    <span class="text-[10px] text-bolao-muted font-semibold">{{ matchDate }}</span>
                    <span class="text-[10px] font-bold text-bolao-accent">{{ matchTime }}</span>
                    <span v-if="lockTimer" class="text-[10px] text-bolao-muted2">· fecha em {{ lockTimer }}</span>
                </template>
            </div>
        </div>

        <!-- Teams + score row -->
        <div class="pred-teams">
            <!-- Home -->
            <div class="pred-team">
                <img v-if="item.match.home_team?.crest" :src="item.match.home_team.crest" class="pred-crest" alt="">
                <div v-else class="pred-crest-placeholder">{{ item.match.home_team?.tla ?? '?' }}</div>
                <p class="pred-team-name">{{ item.match.home_team?.name ?? '—' }}</p>
            </div>

            <!-- Center: live/final score or editable prediction -->
            <div class="pred-center">
                <template v-if="isFinished || isLive">
                    <p class="pred-score">
                        {{ item.match.score?.home ?? 0 }}
                        <span class="pred-score-sep">–</span>
                        {{ item.match.score?.away ?? 0 }}
                    </p>
                    <span v-if="isLive" class="text-[9px] font-bold text-red-400 uppercase tracking-wide">Ao vivo</span>
                    <span v-else class="text-[9px] text-bolao-muted2">placar final</span>
                </template>
                <template v-else>
                    <div class="pred-inline-inputs">
                        <input class="pred-inline-input" type="number" min="0" max="99" :disabled="batchSaving" :value="localHome ?? 0" @input="onInputScore('home', $event)">
                        <span class="text-lg font-black text-bolao-muted2">×</span>
                        <input class="pred-inline-input" type="number" min="0" max="99" :disabled="batchSaving" :value="localAway ?? 0" @input="onInputScore('away', $event)">
                    </div>
                    <span class="text-[9px] text-bolao-muted2">seu palpite</span>
                </template>
            </div>

            <!-- Away -->
            <div class="pred-team pred-team-right">
                <img v-if="item.match.away_team?.crest" :src="item.match.away_team.crest" class="pred-crest" alt="">
                <div v-else class="pred-crest-placeholder">{{ item.match.away_team?.tla ?? '?' }}</div>
                <p class="pred-team-name">{{ item.match.away_team?.name ?? '—' }}</p>
            </div>
        </div>

        <!-- Prediction area -->
        <div class="pred-footer">

            <!-- LOCKED or FINISHED: read-only -->
            <template v-if="isReadOnly">
                <div class="pred-readonly">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-lock text-xs text-bolao-muted2"></i>
                            <span class="text-xs text-bolao-muted">Seu palpite:</span>
                            <template v-if="item.prediction">
                                <span class="text-sm font-bold text-slate-100">
                                    {{ item.prediction.home_score }} – {{ item.prediction.away_score }}
                                </span>
                                <!-- Result chip -->
                                <span v-if="isFinished && resultClass" :class="resultClass" class="ml-1">
                                    {{ resultLabel }}
                                </span>
                            </template>
                            <span v-else class="text-xs italic text-bolao-muted2">não palpitou</span>
                        </div>
                        
                        <router-link 
                            v-if="isLocked || isFinished || isLive"
                            :to="`/pwa/pools/${poolId}/matches/${item.match.id}`"
                            class="text-[10px] font-bold text-bolao-accent uppercase tracking-wider flex items-center gap-1 active:opacity-60"
                        >
                            Ver todos <i class="ti ti-chevron-right"></i>
                        </router-link>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="pred-open">
                    <!-- Batch mode: only show errors -->
                    <template v-if="batchMode">
                        <p v-if="saveError" class="text-[11px] text-red-400 text-center">{{ saveError }}</p>
                        <p v-if="editingBlockedReason" class="text-[11px] text-red-400 text-center">{{ editingBlockedReason }}</p>
                    </template>
                    <!-- Normal mode: save button -->
                    <template v-else>
                        <div class="flex items-center justify-between gap-2 w-full">
                            <p v-if="saveError" class="text-[11px] text-red-400 flex-1 truncate">{{ saveError }}</p>
                            <p v-else-if="editingBlockedReason" class="text-[11px] text-red-400 flex-1 truncate">{{ editingBlockedReason }}</p>
                            <span v-else class="flex-1"></span>
                            <button
                                class="pred-save-btn"
                                :class="saveBtnClass"
                                :disabled="saving || isSaved || !!editingBlockedReason"
                                @click="doSave"
                            >
                                {{ saveBtnLabel }}
                            </button>
                        </div>
                    </template>
                </div>
            </template>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { savePrediction } from '../../api/predictions';

const props = defineProps({
    item: { type: Object, required: true },
    poolId: { type: [Number, String], required: true },
    editingBlockedReason: { type: String, default: '' },
    batchMode: { type: Boolean, default: false },
    batchHomeScore: { type: Number, default: null },
    batchAwayScore: { type: Number, default: null },
    batchSaving: { type: Boolean, default: false },
});
const emit = defineEmits(['saved', 'changed']);

const localHome = ref(props.batchMode ? (props.batchHomeScore ?? props.item.prediction?.home_score ?? 0) : (props.item.prediction?.home_score ?? 0));
const localAway = ref(props.batchMode ? (props.batchAwayScore ?? props.item.prediction?.away_score ?? 0) : (props.item.prediction?.away_score ?? 0));
const initialHome = ref(props.item.prediction?.home_score ?? 0);
const initialAway = ref(props.item.prediction?.away_score ?? 0);
const localHasPrediction = ref(props.item.prediction != null);
const saving = ref(false);
const saveError = ref('');

const isDirty  = computed(() => localHome.value !== initialHome.value || localAway.value !== initialAway.value);
const isSaved  = computed(() => localHasPrediction.value && !isDirty.value);

const saveBtnLabel = computed(() => {
    if (saving.value) return 'Salvando...';
    if (isSaved.value) return 'Salvo ✓';
    if (!localHasPrediction.value) return 'Palpitar';
    return 'Salvar';
});
const saveBtnClass = computed(() => {
    if (isSaved.value) return 'pred-save-btn--saved';
    if (isDirty.value) return 'pred-save-btn--dirty';
    return '';
});

const isLive     = computed(() => ['IN_PLAY', 'PAUSED', 'HALFTIME'].includes(props.item.match?.status));
const isFinished = computed(() => ['FINISHED', 'AWARDED'].includes(props.item.match?.status));
const isLocked   = computed(() => !!props.item.lock?.is_locked);
const isReadOnly = computed(() => isLocked.value || isFinished.value || isLive.value || !!props.editingBlockedReason);

// Card border/bg based on state
const cardClass = computed(() => {
    if (isFinished.value) return 'card-finished';
    if (isLive.value)     return 'card-live';
    if (isLocked.value)   return 'card-locked';
    return 'card-open';
});

const matchTime = computed(() => {
    if (!props.item.match?.local_date) return '—';
    return new Date(props.item.match.local_date).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

const matchDate = computed(() => {
    if (!props.item.match?.local_date) return '';
    return new Date(props.item.match.local_date).toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' });
});

const lockTimer = computed(() => {
    if (isReadOnly.value || !props.item.lock?.lock_at) return '';
    const ms = new Date(props.item.lock.lock_at) - Date.now();
    if (ms <= 0) return '';
    const h = Math.floor(ms / 3600000);
    const m = Math.floor((ms % 3600000) / 60000);
    if (h >= 24) return `${Math.floor(h / 24)}d`;
    if (h > 0)   return `${h}h${m}m`;
    return `${m}min`;
});

const STAGE_MAP = {
    GROUP_STAGE: 'Grupos', LAST_16: 'Oitavas', QUARTER_FINALS: 'Quartas',
    SEMI_FINALS: 'Semifinal', FINAL: 'Final', THIRD_PLACE: '3º Lugar',
};
const stageLabel = computed(() => STAGE_MAP[props.item.match?.stage] ?? null);

const stagePhaseCls = computed(() => ({
    GROUP_STAGE: 'phase-groups', LAST_16: 'phase-r16', QUARTER_FINALS: 'phase-qf',
    SEMI_FINALS: 'phase-sf', FINAL: 'phase-final', THIRD_PLACE: 'phase-sf',
})[props.item.match?.stage] ?? 'phase-groups');

// Result chip (only when finished and has prediction and score)
const result = computed(() => {
    if (!isFinished.value || !props.item.prediction) return null;
    const aH = props.item.match.score?.home;
    const aA = props.item.match.score?.away;
    if (aH == null || aA == null) return null;
    const pH = props.item.prediction.home_score;
    const pA = props.item.prediction.away_score;
    if (pH === aH && pA === aA) return 'exact';
    if (Math.sign(pH - pA) === Math.sign(aH - aA)) return 'winner';
    return 'miss';
});

const resultClass = computed(() => ({
    exact: 'pts-exact', winner: 'pts-winner', miss: 'pts-miss',
})[result.value] ?? null);

const resultLabel = computed(() => ({
    exact: '✓ Placar exato', winner: '→ Vencedor', miss: '✗ Errou',
})[result.value] ?? '');

watch(() => props.item.prediction, (p) => {
    if (props.batchMode) return;
    const h = p?.home_score ?? 0;
    const a = p?.away_score ?? 0;
    localHome.value = h;
    localAway.value = a;
    initialHome.value = h;
    initialAway.value = a;
    localHasPrediction.value = p != null;
});
watch(() => props.batchHomeScore, (v) => {
    if (!props.batchMode) return;
    localHome.value = v ?? props.item.prediction?.home_score ?? 0;
});
watch(() => props.batchAwayScore, (v) => {
    if (!props.batchMode) return;
    localAway.value = v ?? props.item.prediction?.away_score ?? 0;
});

function onInputScore(side, evt) {
    const val = Math.max(0, Math.min(99, Number(evt?.target?.value ?? 0)));
    if (side === 'home') localHome.value = Number.isFinite(val) ? val : 0;
    else localAway.value = Number.isFinite(val) ? val : 0;
    if (props.batchMode) {
        emit('changed', {
            match_id: props.item.match?.id,
            home_score: localHome.value,
            away_score: localAway.value,
        });
    }
}

async function doSave() {
    if (props.editingBlockedReason) {
        saveError.value = props.editingBlockedReason;
        return;
    }
    saving.value = true;
    saveError.value = '';
    try {
        await savePrediction(props.poolId, props.item.match.id, localHome.value, localAway.value);
        localHasPrediction.value = true;
        initialHome.value = localHome.value;
        initialAway.value = localAway.value;
        emit('saved', { matchId: props.item.match.id, home: localHome.value, away: localAway.value });
    } catch (err) {
        const code = err.response?.data?.error?.code;
        saveError.value = code === 'PREDICTION_RULE_VIOLATION'
            ? (err.response?.data?.error?.message ?? 'Palpite bloqueado.')
            : 'Erro ao salvar. Tente novamente.';
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
/* Card container */
.pred-card {
    border-radius: 12px;
    border: 1px solid;
    overflow: hidden;
    transition: border-color 0.2s;
}

.card-open     { background: #13161b; border-color: rgba(245,166,35,0.25); }
.card-locked   { background: #13161b; border-color: rgba(255,255,255,0.08); }
.card-live     { background: #13161b; border-color: rgba(239,68,68,0.30); }
.card-finished { background: #13161b; border-color: rgba(255,255,255,0.06); }

/* Match header bar */
.pred-match-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 12px 6px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

/* Teams row */
.pred-teams {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 12px 10px;
}

.pred-team {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    flex: 1;
}

.pred-team-right { align-items: center; }

.pred-crest {
    width: 38px; height: 38px;
    object-fit: contain;
    drop-shadow: 0 2px 4px rgba(0,0,0,0.4);
}

.pred-crest-placeholder {
    width: 38px; height: 38px;
    border-radius: 8px;
    background: rgba(255,255,255,0.06);
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: #4a5568;
    font-family: 'Barlow Condensed', sans-serif;
}

.pred-team-name {
    font-size: 11px;
    font-weight: 700;
    color: #cbd5e1;
    text-align: center;
    line-height: 1.2;
    max-width: 90px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pred-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
    min-width: 56px;
    gap: 2px;
}

.pred-score {
    font-size: 22px;
    font-weight: 900;
    color: #fff;
    font-family: 'Barlow Condensed', sans-serif;
    letter-spacing: 0.02em;
    line-height: 1;
}

.pred-score-sep { margin: 0 2px; color: #4a5568; }

/* Footer */
.pred-footer {
    border-top: 1px solid rgba(255,255,255,0.05);
}

.pred-readonly {
    padding: 10px 12px;
}

.pred-open {
    padding: 8px 12px 10px;
    display: flex;
    flex-direction: column;
}
.pred-save-btn {
    border-radius: 8px;
    border: 1px solid rgba(245,166,35,.35);
    color: #f5a623;
    font-size: 11px;
    font-weight: 700;
    padding: 5px 12px;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background .15s, border-color .15s, color .15s;
}
.pred-save-btn--saved {
    border-color: rgba(52,211,153,.4);
    color: #34d399;
    background: rgba(52,211,153,.08);
}
.pred-save-btn--dirty {
    border-color: rgba(245,166,35,.6);
    background: rgba(245,166,35,.1);
}
.pred-save-btn:disabled { opacity: .45; }

.pred-inline-inputs {
    display: flex;
    align-items: center;
    gap: 6px;
}

.pred-inline-input {
    width: 32px;
    text-align: center;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    background: #1b2230;
    color: #fff;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 20px;
    font-weight: 800;
    line-height: 1;
    padding: 2px 0;
    -moz-appearance: textfield;
}
.pred-inline-input:focus { outline: none; border-color: rgba(245,166,35,0.5); }
.pred-inline-input::-webkit-outer-spin-button,
.pred-inline-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
</style>
