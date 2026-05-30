<template>
    <div class="pwa-page" ref="pageRoot">

        <div class="px-4 pt-3">
            <div class="match-context-card">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] text-bolao-muted2 uppercase tracking-wider">Competição</p>
                        <p class="text-sm font-bold text-slate-100 truncate">{{ selectedCompetitionName }}</p>
                    </div>
                    <div class="min-w-0 text-right">
                        <p class="text-[10px] text-bolao-muted2 uppercase tracking-wider">Bolão selecionado</p>
                        <p class="text-sm font-bold text-bolao-accent truncate">{{ selectedPoolName }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status filter + competition selector -->
        <div class="pt-3 pb-1">
            <!-- Status tabs -->
            <div class="flex gap-1.5 px-4 overflow-x-auto no-scrollbar pb-2">
                <button
                    v-for="f in statusFilters"
                    :key="f.value"
                    class="comp-chip shrink-0"
                    :class="activeStatus === f.value ? 'active' : 'inactive'"
                    @click="setStatus(f.value)"
                >
                    <span v-if="f.value === 'IN_PLAY' && hasLiveMatches" class="live-dot mr-1.5" style="width:5px;height:5px;"></span>
                    {{ f.label }}
                </button>
            </div>

        </div>

        <!-- Loading -->
        <div v-if="loading" class="pwa-section space-y-3">
            <SkeletonCard v-for="i in 5" :key="i" />
        </div>

        <!-- Grouped matches list -->
        <div v-else-if="groupedMatches.length" class="pwa-section space-y-1 pt-1">
            <template v-for="group in groupedMatches" :key="group.date">
                <!-- Date group header -->
                <div class="date-group-header">{{ group.date }}</div>

                <div class="space-y-2 mb-2">
                    <div
                        v-for="m in group.matches"
                        :key="m.id"
                        class="match-row cursor-pointer"
                        @click="router.push({ name: 'match-detail', params: { matchId: m.id } })"
                    >
                        <!-- Top bar: competition + stage + status -->
                        <div class="flex items-center justify-between px-3 pt-2.5 pb-1">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span v-if="m.competition?.name" class="text-[10px] text-bolao-muted2 font-semibold truncate">
                                    {{ m.competition.name }}
                                </span>
                                <span v-if="m.stage" class="phase-badge" :class="stagePhaseCls(m.stage)">
                                    {{ stageLabel(m.stage) }}
                                </span>
                                <span v-if="m.matchday" class="text-[10px] text-bolao-muted2">R{{ m.matchday }}</span>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <span
                                    v-if="['IN_PLAY','PAUSED','HALFTIME'].includes(m.status)"
                                    class="flex items-center gap-1 text-[10px] font-bold text-red-400"
                                >
                                    <span class="live-dot" style="width:5px;height:5px"></span> Ao vivo
                                </span>
                                <span
                                    v-else-if="['FINISHED','AWARDED'].includes(m.status)"
                                    class="text-[10px] text-bolao-muted2 font-semibold"
                                >Encerrado</span>
                                <span
                                    v-else
                                    class="text-[10px] font-bold text-bolao-accent"
                                >{{ localTime(m.local_date) }}</span>
                            </div>
                        </div>

                        <!-- Teams + score -->
                        <div class="flex items-center gap-2 px-3 pb-3 pt-1">
                            <!-- Home team -->
                            <div class="flex flex-1 items-center gap-2 min-w-0">
                                <img v-if="m.home_team?.crest" :src="m.home_team.crest" class="match-crest" alt="">
                                <div v-else class="match-crest-ph">{{ m.home_team?.tla ?? '?' }}</div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-200 truncate leading-tight">
                                        {{ m.home_team?.short_name ?? m.home_team?.name ?? '—' }}
                                    </p>
                                    <p v-if="m.home_team?.tla" class="text-[10px] text-bolao-muted2 font-semibold uppercase">
                                        {{ m.home_team.tla }}
                                    </p>
                                </div>
                            </div>

                            <!-- Score / time -->
                            <div class="shrink-0 text-center w-20">
                                <template v-if="['FINISHED','AWARDED','IN_PLAY','PAUSED','HALFTIME'].includes(m.status)">
                                    <p class="text-xl font-black text-white font-bc">
                                        {{ m.score?.home ?? 0 }} – {{ m.score?.away ?? 0 }}
                                    </p>
                                </template>
                                <template v-else>
                                    <p class="text-base font-black text-white font-bc">
                                        {{ localTime(m.local_date) }}
                                    </p>
                                </template>
                            </div>

                            <!-- Away team -->
                            <div class="flex flex-1 items-center gap-2 justify-end min-w-0">
                                <div class="text-right min-w-0">
                                    <p class="text-sm font-bold text-slate-200 truncate leading-tight">
                                        {{ m.away_team?.short_name ?? m.away_team?.name ?? '—' }}
                                    </p>
                                    <p v-if="m.away_team?.tla" class="text-[10px] text-bolao-muted2 font-semibold uppercase">
                                        {{ m.away_team.tla }}
                                    </p>
                                </div>
                                <img v-if="m.away_team?.crest" :src="m.away_team.crest" class="match-crest shrink-0" alt="">
                                <div v-else class="match-crest-ph shrink-0">{{ m.away_team?.tla ?? '?' }}</div>
                            </div>
                        </div>

                        <div v-if="selectedPoolId" class="px-3 pb-3" @click.stop>
                            <div class="rounded-lg border border-white/10 bg-white/[0.03] p-2">
                                <div v-if="isMatchPredictionEditable(m)" class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        min="0"
                                        max="99"
                                        class="score-mini-input"
                                        :value="predictionState[m.id]?.home_score ?? 0"
                                        @input="onPredictionInput(m.id, 'home_score', $event)"
                                        :disabled="predictionState[m.id]?.saving || predictionState[m.id]?.is_locked"
                                    >
                                    <span class="text-bolao-muted2 font-bc font-bold">×</span>
                                    <input
                                        type="number"
                                        min="0"
                                        max="99"
                                        class="score-mini-input"
                                        :value="predictionState[m.id]?.away_score ?? 0"
                                        @input="onPredictionInput(m.id, 'away_score', $event)"
                                        :disabled="predictionState[m.id]?.saving || predictionState[m.id]?.is_locked"
                                    >
                                    <button
                                        class="mini-save-btn"
                                        :class="predBtnClass(predictionState[m.id])"
                                        :disabled="predictionState[m.id]?.saving || predictionState[m.id]?.is_locked || (!predictionState[m.id]?.has_prediction && predBtnLabel(predictionState[m.id]) === 'Salvo ✓')"
                                        @click="saveMatchPrediction(m)"
                                    >
                                        {{ predBtnLabel(predictionState[m.id]) }}
                                    </button>
                                </div>
                                <div v-else class="text-[11px] text-bolao-muted2">
                                    Jogo indisponível para palpite agora.
                                </div>
                                <p v-if="predictionState[m.id]?.is_locked" class="text-[11px] text-bolao-muted2 mt-1">Palpite bloqueado para este jogo.</p>
                                <p v-if="predictionState[m.id]?.error" class="text-[11px] text-red-400 mt-1">{{ predictionState[m.id].error }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div v-if="page < totalPages" ref="infiniteSentinel" class="py-3 text-center text-xs text-bolao-muted">
                {{ loadingMore ? 'Carregando mais jogos...' : 'Role para carregar mais jogos' }}
            </div>
        </div>

        <!-- Empty -->
        <div v-else class="pwa-section text-center py-14">
            <i class="ti ti-ball-football-off text-4xl text-bolao-muted2 mb-3 block"></i>
            <p class="text-sm text-bolao-muted">Nenhum jogo encontrado.</p>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { getMatches } from '../api/matches';
import { getPools } from '../api/pools';
import { getPrediction, savePrediction } from '../api/predictions';
import { useAppStore } from '../store/app';
import SkeletonCard from '../components/ui/SkeletonCard.vue';

const router = useRouter();
const appStore = useAppStore();
const emit = defineEmits(['set-title']);

const statusFilters = [
    { label: 'Ao vivo',    value: 'IN_PLAY' },
    { label: 'Próximos',   value: 'TIMED' },
    { label: 'Encerrados', value: 'FINISHED' },
    { label: 'Todos',      value: '' },
];

const activeStatus = ref('TIMED');
const activeComp   = computed(() => appStore.selectedCompetitionId ?? null);
const MATCHES_STATUS_KEY = 'pwa_matches_status';
const DASHBOARD_POOL_ID_KEY = 'pwa_dashboard_pool_id';
const matches      = ref([]);
const userPools    = ref([]);
const loading      = ref(true);
const loadingMore  = ref(false);
const page         = ref(1);
const totalPages   = ref(1);
const selectedPoolId = ref(null);
const predictionState = ref({});
const infiniteSentinel = ref(null);
const pageRoot = ref(null);
let infiniteObserver = null;
let scrollContainer = null;

const LIVE_STATUSES = ['IN_PLAY', 'PAUSED', 'HALFTIME', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'];
const hasLiveMatches = computed(() => matches.value.some(m => LIVE_STATUSES.includes(m.status)));

const selectedCompetitionName = computed(() =>
    appStore.competitions.find(c => c.id === appStore.selectedCompetitionId)?.name ?? 'Todas',
);
const selectedPool = computed(() =>
    userPools.value.find((p) => Number(p.id) === Number(selectedPoolId.value)) ?? null,
);
const selectedPoolName = computed(() => selectedPool.value?.name ?? 'Nenhum bolão selecionado');

const filteredMatches = computed(() => {
    return [...matches.value].sort((a, b) => {
        const ta = a.local_date ? new Date(a.local_date).getTime() : (a.utc_date ? new Date(a.utc_date).getTime() : 0);
        const tb = b.local_date ? new Date(b.local_date).getTime() : (b.utc_date ? new Date(b.utc_date).getTime() : 0);
        return activeStatus.value === 'TIMED' ? (ta - tb) : (tb - ta);
    });
});

// Group by local date
const groupedMatches = computed(() => {
    const groups = new Map();
    for (const m of filteredMatches.value) {
        const dateKey = m.local_date
            ? new Date(m.local_date).toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit' })
            : 'Sem data';
        if (!groups.has(dateKey)) groups.set(dateKey, []);
        groups.get(dateKey).push(m);
    }
    return Array.from(groups.entries()).map(([date, items]) => ({ date, matches: items }));
});

const STAGE_LABELS = {
    GROUP_STAGE: 'Grupos', LAST_16: 'Oitavas', QUARTER_FINALS: 'Quartas',
    SEMI_FINALS: 'Semifinal', FINAL: 'Final', THIRD_PLACE: '3º Lugar',
};
function stageLabel(s)     { return STAGE_LABELS[s] ?? s?.replace(/_/g, ' ') ?? ''; }
function stagePhaseCls(s)  {
    return { GROUP_STAGE: 'phase-groups', LAST_16: 'phase-r16', QUARTER_FINALS: 'phase-qf',
             SEMI_FINALS: 'phase-sf', FINAL: 'phase-final', THIRD_PLACE: 'phase-sf' }[s] ?? 'phase-groups';
}
function localTime(d) {
    if (!d) return '—';
    return new Date(d).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function localDateYmd(d = new Date()) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function buildMatchParams(p) {
    const params = { page: p, per_page: 30 };
    if (activeStatus.value) params.status = activeStatus.value;
    if (activeComp.value) params.competition_id = activeComp.value;

    if (activeStatus.value === 'TIMED') {
        params.order = 'asc';
        params.date_from = localDateYmd(new Date());
    } else {
        params.order = 'desc';
    }

    return params;
}

async function load(p = 1, append = false) {
    if (p === 1) loading.value = true;
    else loadingMore.value = true;

    try {
        const params = buildMatchParams(p);
        const res = await getMatches(params);
        const items = res.data.data.items ?? [];
        matches.value = append ? [...matches.value, ...items] : items;
        page.value = res.data.meta?.pagination?.page ?? 1;
        totalPages.value = res.data.meta?.pagination?.total_pages ?? 1;
        if (selectedPoolId.value) await preloadPredictions(items);
    } finally {
        loading.value = false;
        loadingMore.value = false;
        await nextTick();
        setupInfiniteObserver();
    }
}

function setStatus(v) {
    activeStatus.value = v;
    load(1);
}

function loadMore() {
    if (loading.value || loadingMore.value) return;
    if (page.value >= totalPages.value) return;
    load(page.value + 1, true);
}

function setupInfiniteObserver() {
    if (infiniteObserver) {
        infiniteObserver.disconnect();
        infiniteObserver = null;
    }
    if (!infiniteSentinel.value || page.value >= totalPages.value) return;

    infiniteObserver = new IntersectionObserver((entries) => {
        const entry = entries[0];
        if (!entry?.isIntersecting) return;
        loadMore();
    }, {
        root: scrollContainer,
        rootMargin: '300px 0px',
        threshold: 0.01,
    });

    infiniteObserver.observe(infiniteSentinel.value);
}

function onContainerScroll() {
    if (!scrollContainer) return;
    if (loading.value || loadingMore.value) return;
    if (page.value >= totalPages.value) return;
    const remaining = scrollContainer.scrollHeight - scrollContainer.scrollTop - scrollContainer.clientHeight;
    if (remaining < 320) loadMore();
}

function isMatchPredictionEditable(match) {
    return ['TIMED', 'SCHEDULED', 'PRE_MATCH'].includes(match?.status);
}

function ensurePredictionState(matchId) {
    if (!predictionState.value[matchId]) {
        predictionState.value[matchId] = {
            home_score: 0,
            away_score: 0,
            initial_home: null,
            initial_away: null,
            has_prediction: false,
            saving: false,
            is_locked: false,
            error: '',
            loaded: false,
        };
    }
    return predictionState.value[matchId];
}

function predBtnLabel(state) {
    if (!state || state.saving) return 'Salvando...';
    if (!state.has_prediction) return 'Palpitar';
    const dirty = state.home_score !== state.initial_home || state.away_score !== state.initial_away;
    return dirty ? 'Salvar' : 'Salvo ✓';
}

function predBtnClass(state) {
    if (!state || !state.has_prediction) return '';
    const dirty = state.home_score !== state.initial_home || state.away_score !== state.initial_away;
    return dirty ? 'mini-save-btn--dirty' : 'mini-save-btn--saved';
}

async function preloadPredictions(items) {
    if (!selectedPoolId.value) return;
    const toLoad = items
        .filter((m) => isMatchPredictionEditable(m))
        .filter((m) => !ensurePredictionState(m.id).loaded);

    await Promise.all(toLoad.map(async (m) => {
        const state = ensurePredictionState(m.id);
        try {
            const res = await getPrediction(selectedPoolId.value, m.id);
            const payload = res.data.data ?? {};
            const pred = payload?.prediction;
            if (pred != null) {
                state.home_score    = Number(pred.home_score ?? 0);
                state.away_score    = Number(pred.away_score ?? 0);
                state.initial_home  = state.home_score;
                state.initial_away  = state.away_score;
                state.has_prediction = true;
            }
            state.is_locked = Boolean(payload?.lock?.is_locked);
            state.loaded = true;
        } catch {
            state.loaded = true;
        }
    }));
}

function onPredictionInput(matchId, field, event) {
    const state = ensurePredictionState(matchId);
    const val = Math.max(0, Math.min(99, Number(event?.target?.value ?? 0)));
    state[field] = Number.isFinite(val) ? val : 0;
    state.saved = false;
    state.error = '';
}

async function saveMatchPrediction(match) {
    if (!selectedPoolId.value) return;
    const state = ensurePredictionState(match.id);
    if (state.is_locked) return;
    state.saving = true;
    state.error = '';
    try {
        await savePrediction(selectedPoolId.value, match.id, state.home_score, state.away_score);
        state.has_prediction = true;
        state.initial_home   = state.home_score;
        state.initial_away   = state.away_score;
    } catch (err) {
        const msg = err?.response?.data?.error?.message ?? err?.response?.data?.message ?? 'Não foi possível salvar o palpite.';
        state.error = String(msg);
        if (String(err?.response?.data?.error?.code || '') === 'PREDICTION_RULE_VIOLATION') {
            state.is_locked = true;
        }
    } finally {
        state.saving = false;
    }
}

onMounted(() => {
    emit('set-title', '');
    const persistedStatus = String(localStorage.getItem(MATCHES_STATUS_KEY) ?? 'TIMED');
    if (statusFilters.some((f) => f.value === persistedStatus)) {
        activeStatus.value = persistedStatus;
    }
    const persistedPool = Number(localStorage.getItem(DASHBOARD_POOL_ID_KEY) ?? 0) || null;
    selectedPoolId.value = persistedPool;

    getPools().then((res) => {
        userPools.value = res.data.data.member_pools ?? [];
        if (selectedPoolId.value && !userPools.value.some((p) => Number(p.id) === Number(selectedPoolId.value))) {
            selectedPoolId.value = null;
        }
        if (!selectedPoolId.value && activeComp.value) {
            selectedPoolId.value = userPools.value.find((p) => Number(p.competition?.id) === Number(activeComp.value))?.id ?? null;
        }
    }).catch(() => {});

    scrollContainer = pageRoot.value?.closest('.pwa-main') ?? null;
    if (scrollContainer) {
        scrollContainer.addEventListener('scroll', onContainerScroll, { passive: true });
    }
    load();
});

onBeforeUnmount(() => {
    if (infiniteObserver) {
        infiniteObserver.disconnect();
        infiniteObserver = null;
    }
    if (scrollContainer) {
        scrollContainer.removeEventListener('scroll', onContainerScroll);
        scrollContainer = null;
    }
});

watch(activeStatus, (value) => {
    localStorage.setItem(MATCHES_STATUS_KEY, String(value ?? ''));
});

watch(() => appStore.selectedCompetitionId, (value) => {
    const poolForComp = userPools.value.find((p) => Number(p.competition?.id) === Number(value ?? 0));
    selectedPoolId.value = poolForComp?.id ?? null;
    predictionState.value = {};
    load(1);
});
</script>

<style scoped>
.match-context-card {
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.08);
    background: #13161b;
    padding: 10px 12px;
}

.match-row {
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.07);
    background: #13161b;
    overflow: hidden;
}

.score-mini-input {
    width: 42px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.12);
    background: #0f131b;
    color: #e5e7eb;
    text-align: center;
    padding: 6px 4px;
    font-weight: 700;
}

.mini-save-btn {
    border-radius: 8px;
    border: 1px solid rgba(245,166,35,.35);
    color: #f5a623;
    font-size: 11px;
    font-weight: 700;
    padding: 6px 10px;
    transition: background .15s, border-color .15s, color .15s;
}
.mini-save-btn--saved {
    border-color: rgba(52,211,153,.4);
    color: #34d399;
    background: rgba(52,211,153,.08);
}
.mini-save-btn--dirty {
    border-color: rgba(245,166,35,.6);
    color: #f5a623;
    background: rgba(245,166,35,.1);
}
.mini-save-btn:disabled {
    opacity: .45;
}

.match-crest {
    width: 36px; height: 36px;
    object-fit: contain;
    flex-shrink: 0;
}

.match-crest-ph {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: rgba(255,255,255,0.06);
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; color: #4a5568;
    font-family: 'Barlow Condensed', sans-serif;
    flex-shrink: 0;
}

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
