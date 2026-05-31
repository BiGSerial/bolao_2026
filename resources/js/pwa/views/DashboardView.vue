<template>
    <div class="pwa-page dashboard-page">
        <div
            class="dash-slider"
            ref="sliderEl"
            @touchstart.stop="onTouchStart"
            @touchmove.stop="onTouchMove"
            @touchend.stop="onTouchEnd"
        >
            <!-- Pull to Refresh Indicator -->
            <div 
                class="ptr-indicator" 
                :style="{ transform: `translateY(${Math.min(ptrY, 60)}px)`, opacity: ptrY > 10 ? 1 : 0 }"
            >
                <i class="ti ti-refresh animate-spin text-bolao-accent" v-if="refreshing"></i>
                <i class="ti ti-arrow-down text-bolao-accent" v-else :style="{ transform: `rotate(${Math.min(ptrY * 3, 180)}deg)` }"></i>
            </div>

            <div class="dash-track" :style="trackStyle">
                <!-- LEFT: Ranking + stats do bolão -->
                <section class="dash-panel">
                    <div class="pwa-section pt-3">
                        <h2 class="panel-title">Ranking do bolão</h2>
                        <p class="panel-sub" v-if="selectedPool">{{ selectedPool.name }}</p>
                        <p class="panel-sub" v-else>Nenhum bolão selecionado</p>
                    </div>

                    <div class="pwa-section pt-3">
                        <div class="card p-3">
                            <p class="text-[11px] text-bolao-muted2 uppercase tracking-wider mb-2">Minha posição</p>
                            <div v-if="myRankingRow" class="flex items-center justify-between">
                                <div>
                                    <p class="font-bc text-3xl font-extrabold text-bolao-accent leading-none">{{ myRankingRow.position }}º</p>
                                    <p class="text-xs text-bolao-muted mt-1">{{ myRankingRow.user?.name }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bc text-2xl font-extrabold text-white leading-none">{{ myRankingRow.points_total }}</p>
                                    <p class="text-[11px] text-bolao-muted">pts</p>
                                </div>
                            </div>
                            <p v-else class="text-xs text-bolao-muted">Ranking indisponível.</p>
                        </div>
                    </div>

                    <div class="pwa-section pt-3">
                        <div class="card p-3">
                            <p class="text-[11px] text-bolao-muted2 uppercase tracking-wider mb-2">Ranking completo</p>
                            <div v-if="liveRankingRows.length" class="space-y-1.5">
                                <div
                                    v-for="row in liveRankingRows"
                                    :key="row.user?.id"
                                    class="rank-row"
                                    :class="{ me: row.user?.id === auth.user?.id }"
                                >
                                    <span class="w-6 font-bc font-bold">{{ row.position }}º</span>
                                    <span class="flex-1 truncate">{{ row.user?.name }}</span>
                                    <span class="w-10 text-right font-bc font-bold text-amber-300">{{ row.points_total }}</span>
                                </div>
                            </div>
                            <p v-else class="text-xs text-bolao-muted">Sem dados de ranking.</p>
                        </div>
                    </div>

                    <div class="pwa-section pt-3 pb-6">
                        <div class="grid grid-cols-2 gap-2" v-if="myRankingRow">
                            <div class="card p-3">
                                <p class="text-[10px] text-bolao-muted2">Placar exato</p>
                                <p class="font-bc text-2xl font-bold text-white">{{ myRankingRow?.exact_scores ?? 0 }}</p>
                            </div>
                            <div class="card p-3">
                                <p class="text-[10px] text-bolao-muted2">Resultado</p>
                                <p class="font-bc text-2xl font-bold text-white">{{ myRankingRow?.correct_results ?? 0 }}</p>
                            </div>
                            <div class="card p-3">
                                <p class="text-[10px] text-bolao-muted2">Gols corretos</p>
                                <p class="font-bc text-2xl font-bold text-white">{{ (myRankingRow?.correct_home_goals ?? 0) + (myRankingRow?.correct_away_goals ?? 0) }}</p>
                            </div>
                            <div class="card p-3">
                                <p class="text-[10px] text-bolao-muted2">Contabilizados</p>
                                <p class="font-bc text-2xl font-bold text-white">{{ myRankingRow?.predictions_counted ?? 0 }}</p>
                            </div>
                        </div>
                        <div v-else class="card p-3 text-xs text-bolao-muted">Selecione um bolão para ver seus stats.</div>
                    </div>
                </section>

                <!-- CENTER: ao vivo + próximo + minha posição -->
                <section class="dash-panel">
                    <template v-if="loading">
                        <div class="pwa-section pt-4">
                            <SkeletonBlock height="22px" width="55%" class="mb-1.5" />
                            <SkeletonBlock height="14px" width="70%" />
                        </div>
                        <div class="pwa-section pt-4 space-y-2.5">
                            <SkeletonCard v-for="i in 3" :key="i" />
                        </div>
                    </template>

                    <template v-else-if="data">
                        <div v-if="competitionPools.length" class="pwa-section pt-3">
                            <p class="text-[11px] font-bold text-bolao-muted2 uppercase tracking-widest mb-2">Meus Bolões</p>
                            <div class="flex gap-2 overflow-x-auto no-scrollbar">
                                <button
                                    v-for="p in competitionPools"
                                    :key="p.id"
                                    class="pool-chip shrink-0"
                                    :class="{ active: selectedPoolId === p.id }"
                                    @click="selectPool(p.id)"
                                >
                                    {{ p.name }}
                                </button>
                            </div>
                        </div>
                        <div v-else class="pwa-section pt-3">
                            <div class="card p-3 text-xs text-bolao-muted">Nenhum bolão disponível para esta competição.</div>
                        </div>

                        <div v-if="filteredLiveMatches.length" class="pwa-section pt-4">
                            <div class="section-head mb-2">
                                <p class="section-title"><span class="live-dot"></span>Ao vivo</p>
                                <button class="section-mini-btn" :disabled="refreshing" @click="manualRefresh">
                                    <i class="ti" :class="refreshing ? 'ti-loader-2 animate-spin' : 'ti-refresh'"></i>
                                </button>
                            </div>

                            <div class="space-y-2">
                                <div v-for="m in filteredLiveMatches" :key="m.id" class="live-card cursor-pointer"
                                     @click="router.push({ name: 'match-detail', params: { matchId: m.id } })">
                                    <div class="flex items-center justify-between text-[10px] text-bolao-muted2 mb-2">
                                        <span class="truncate max-w-[60%]">{{ m.competition?.name }}<span v-if="m.matchday"> · R{{ m.matchday }}</span></span>
                                        <span class="text-bolao-red font-bold flex items-center gap-1">
                                            <span class="live-dot"></span>
                                            AO VIVO
                                            <span v-if="liveMinuteLabel(m)" class="text-amber-300">· {{ liveMinuteLabel(m) }}</span>
                                        </span>
                                    </div>
                                    <div class="live-match-grid">
                                        <div class="team-col">
                                            <div class="team-main">
                                                <img v-if="m.home_team?.crest" :src="m.home_team.crest" class="team-crest" alt="">
                                                <span class="team-name truncate block">{{ shortName(m.home_team) }}</span>
                                            </div>
                                            <div v-if="m.goals?.home?.length" class="goal-lines">
                                                <div v-for="(g, idx) in m.goals.home" :key="`h-${m.id}-${idx}`" class="goal-line">
                                                    {{ goalLine(g) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="score-wrap">
                                            <span class="score-main">{{ m.score?.home ?? 0 }}-{{ m.score?.away ?? 0 }}</span>
                                        </div>
                                        <div class="team-col team-col-away">
                                            <div class="team-main team-main-away">
                                                <span class="team-name truncate block text-right">{{ shortName(m.away_team) }}</span>
                                                <img v-if="m.away_team?.crest" :src="m.away_team.crest" class="team-crest" alt="">
                                            </div>
                                            <div v-if="m.goals?.away?.length" class="goal-lines text-right">
                                                <div v-for="(g, idx) in m.goals.away" :key="`a-${m.id}-${idx}`" class="goal-line">
                                                    {{ goalLine(g) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="live-toggle mt-2" @click.stop>
                                        <button class="live-toggle-btn" :class="{ active: liveTab === 'mine' }" @click="liveTab = 'mine'">Meus pontos</button>
                                        <button class="live-toggle-btn" :class="{ active: liveTab === 'ranking' }" @click="liveTab = 'ranking'">Ranking</button>
                                    </div>
                                    <div class="mt-2 text-[11px]">
                                        <template v-if="liveTab === 'mine'">
                                            <span v-if="livePredictionMap[m.id]" class="text-slate-300">
                                                Palpite: <span class="text-bolao-accent font-bc font-bold">{{ livePredictionMap[m.id].home_score }}x{{ livePredictionMap[m.id].away_score }}</span>
                                                · <span class="text-amber-300">{{ livePointsForMatch(m) }} pts</span>
                                            </span>
                                            <span v-else class="text-bolao-muted">Sem palpite seu neste jogo.</span>
                                        </template>
                                        <template v-else>
                                            <span class="text-bolao-muted">Sua posição: </span>
                                            <span class="text-bolao-accent font-bold">{{ myRankingRow?.position ?? '—' }}º</span>
                                            <span class="text-bolao-muted"> · </span>
                                            <span class="text-amber-300 font-bold">{{ myRankingRow?.points_total ?? 0 }} pts</span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="todayOpenMatches.length" class="pwa-section pt-4">
                            <div class="section-head mb-2">
                                <p class="section-title">Próximos jogos do dia</p>
                            </div>

                            <div
                                class="next-carousel"
                                @touchstart.stop="onMatchTouchStart"
                                @touchmove.stop="onMatchTouchMove"
                                @touchend.stop="onMatchTouchEnd"
                            >
                                <div class="next-card next-card-slider">
                                    <div class="next-track" :style="nextTrackStyle">
                                        <div v-for="m in todayOpenMatches" :key="m.id" class="next-slide">
                                            <div class="next-slide-inner cursor-pointer"
                                                 @click="router.push({ name: 'match-detail', params: { matchId: m.id } })">
                                                <div class="next-slide-meta">
                                                    <p class="text-[11px] text-bolao-muted2 truncate">
                                                        {{ m.competition?.name }}<span v-if="m.matchday"> · Rodada {{ m.matchday }}</span>
                                                    </p>
                                                    <span
                                                        class="font-bold shrink-0"
                                                        :class="['FINISHED', 'AWARDED'].includes(m.status) ? 'text-bolao-muted2' : ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'].includes(m.status) ? 'text-bolao-red' : 'text-bolao-accent'"
                                                    >
                                                        {{ ['FINISHED', 'AWARDED'].includes(m.status) ? 'Encerrado' : ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'].includes(m.status) ? 'Ao vivo' : matchTime(m.local_date) }}
                                                    </span>
                                                </div>
                                                <div class="next-hero-teams">
                                                    <div class="next-team">
                                                        <img v-if="m.home_team?.crest" :src="m.home_team.crest" class="next-crest" alt="">
                                                        <p class="next-team-name">{{ shortName(m.home_team) }}</p>
                                                        <p class="next-team-tla">{{ m.home_team?.tla ?? '' }}</p>
                                                    </div>
                                                    <div v-if="['FINISHED', 'AWARDED', 'IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'].includes(m.status)" class="next-score">
                                                        {{ m.score?.home ?? 0 }}-{{ m.score?.away ?? 0 }}
                                                    </div>
                                                    <div v-else class="next-vs">x</div>
                                                    <div class="next-team">
                                                        <img v-if="m.away_team?.crest" :src="m.away_team.crest" class="next-crest" alt="">
                                                        <p class="next-team-name">{{ shortName(m.away_team) }}</p>
                                                        <p class="next-team-tla">{{ m.away_team?.tla ?? '' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pwa-section pt-4 pb-6">
                            <div class="my-position-card" v-if="myRankingRow">
                                <div class="my-position-meta">
                                    <span class="text-bolao-muted2 text-[11px]">Classificação</span>
                                    <span class="text-white text-[28px] font-bc font-extrabold leading-none">{{ myRankingRow.position }}º</span>
                                </div>
                                <div class="my-position-user">
                                    <p class="text-white font-bold truncate">{{ myRankingRow.user?.name }}</p>
                                    <p class="text-bolao-muted text-xs">{{ liveRankingRows.length }} participantes</p>
                                </div>
                                <div class="my-position-points">
                                    <p class="font-bc text-[40px] leading-none font-extrabold text-bolao-accent">{{ myRankingRow.points_total }}</p>
                                    <p class="text-bolao-muted text-xs text-right">pts</p>
                                </div>
                            </div>
                            <div v-else class="card p-3 text-xs text-bolao-muted">Selecione um bolão para acompanhar.</div>
                        </div>
                    </template>
                </section>

                <!-- RIGHT: classificação + agenda com palpite -->
                <section class="dash-panel">
                    <div class="pwa-section pt-3">
                        <div class="section-head">
                            <h2 class="panel-title">Classificação</h2>
                            <span class="text-[10px] uppercase tracking-wider text-bolao-muted2">Pontos · Vitórias · SG · GP</span>
                        </div>
                    </div>

                    <div class="pwa-section pt-3">
                        <div class="standings-card p-2.5">
                            <div v-if="standingsGroups.length" class="standings-scroll space-y-3">
                                <template v-if="shouldUseCupLayout">
                                    <div v-for="group in standingsGroups" :key="group.name" class="cup-group-card">
                                        <p class="text-[10px] text-bolao-muted2 uppercase tracking-wider mb-1 px-1">{{ group.name }}</p>
                                        <div class="standings-head">
                                            <span>#</span><span>Time</span><span>PJ</span><span>V</span><span>E</span><span>D</span><span>SG</span><span>PTS</span>
                                        </div>
                                        <div class="space-y-0.5">
                                            <div v-for="row in group.rows" :key="`${group.name}-${row.team?.id}`" class="standings-row">
                                                <span class="stand-col-pos">{{ row.position ?? '-' }}</span>
                                                <span class="stand-col-team">
                                                    <img v-if="row.team?.crest" :src="row.team.crest" class="stand-crest" alt="">
                                                    <span class="truncate">{{ row.team?.short_name ?? row.team?.name }}</span>
                                                </span>
                                                <span>{{ row.played_games }}</span>
                                                <span>{{ row.won }}</span>
                                                <span>{{ row.draw }}</span>
                                                <span>{{ row.lost }}</span>
                                                <span :class="row.goal_difference >= 0 ? 'text-emerald-400' : 'text-rose-400'">{{ row.goal_difference > 0 ? `+${row.goal_difference}` : row.goal_difference }}</span>
                                                <span class="text-white font-bold">{{ row.points }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div v-for="group in standingsGroups" :key="group.name">
                                        <p class="text-[10px] text-bolao-muted2 uppercase tracking-wider mb-1 px-1">{{ group.name }}</p>
                                        <div class="standings-head">
                                            <span>#</span><span>Time</span><span>PJ</span><span>V</span><span>E</span><span>D</span><span>SG</span><span>PTS</span>
                                        </div>
                                        <div class="space-y-0.5">
                                            <div v-for="row in group.rows" :key="`${group.name}-${row.team?.id}`" class="standings-row">
                                                <span class="stand-col-pos">{{ row.position ?? '-' }}</span>
                                                <span class="stand-col-team">
                                                    <img v-if="row.team?.crest" :src="row.team.crest" class="stand-crest" alt="">
                                                    <span class="truncate">{{ row.team?.short_name ?? row.team?.name }}</span>
                                                </span>
                                                <span>{{ row.played_games }}</span>
                                                <span>{{ row.won }}</span>
                                                <span>{{ row.draw }}</span>
                                                <span>{{ row.lost }}</span>
                                                <span :class="row.goal_difference >= 0 ? 'text-emerald-400' : 'text-rose-400'">{{ row.goal_difference > 0 ? `+${row.goal_difference}` : row.goal_difference }}</span>
                                                <span class="text-white font-bold">{{ row.points }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p v-else class="text-xs text-bolao-muted">Sem classificação disponível.</p>
                        </div>
                    </div>

                    <div class="pwa-section pt-3 pb-6">
                        <h3 class="panel-title text-[28px] mb-2">Resultados recentes</h3>
                        <div class="space-y-2">
                            <div v-for="item in recentResults" :key="item.match.id" class="recent-card p-3 cursor-pointer"
                                 @click="router.push({ name: 'match-detail', params: { matchId: item.match.id } })">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <img v-if="item.match.home_team?.crest" :src="item.match.home_team.crest" class="team-crest" alt="">
                                        <span class="team-name truncate">{{ shortName(item.match.home_team) }}</span>
                                    </div>
                                    <span class="score-main text-[36px]">{{ item.match.score?.home ?? 0 }}-{{ item.match.score?.away ?? 0 }}</span>
                                    <div class="flex items-center justify-end gap-2 min-w-0 flex-1">
                                        <span class="team-name truncate text-right">{{ shortName(item.match.away_team) }}</span>
                                        <img v-if="item.match.away_team?.crest" :src="item.match.away_team.crest" class="team-crest" alt="">
                                    </div>
                                </div>
                                <div class="recent-meta mt-2">
                                    <span class="recent-tag">{{ roundLabel(item.match) }}</span>
                                    <span class="text-bolao-muted2 text-[11px]">{{ formatDateTime(item.match.local_date) }}</span>
                                    <span class="recent-points">{{ formatSignedPoints(item.points) }}</span>
                                </div>
                            </div>
                            <div v-if="!recentResults.length" class="card p-3 text-xs text-bolao-muted">Sem resultados recentes para o bolão selecionado.</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/auth';
import { useAppStore } from '../store/app';
import { getDashboard } from '../api/dashboard';
import { getLiveRanking } from '../api/rankings';
import { getPoolPredictions } from '../api/predictions';
import { getStandings } from '../api/standings';
import { getMatches } from '../api/matches';
import { getPool } from '../api/pools';
import { getPwaEcho } from '../realtime/echo';
import { createHorizontalSwipeTracker } from '../utils/horizontalSwipe';
import SkeletonBlock from '../components/ui/SkeletonBlock.vue';
import SkeletonCard from '../components/ui/SkeletonCard.vue';

const router = useRouter();
const auth = useAuthStore();
const appStore = useAppStore();
const emit = defineEmits(['set-title']);

const loading = ref(true);
const refreshing = ref(false);
const data = ref(null);

const ptrY = ref(0);
let startY = 0;
let isPtrEligible = false;

const selectedPoolId = ref(null);
const liveTab = ref('mine');
const DASHBOARD_POOL_ID_KEY = 'pwa_dashboard_pool_id';
const DASHBOARD_PANEL_INDEX_KEY = 'pwa_dashboard_panel_index';
const DASHBOARD_LIVE_TAB_KEY = 'pwa_dashboard_live_tab';

const liveRankingRows = ref([]);
const poolPredictions = ref([]);
const selectedPoolDetail = ref(null);
const standingsGroups = ref([]);
const dayMatches = ref([]);

const nextMatchIndex = ref(0);

let echo = null;
let poolChannelKey = null;
let poolsChannelKey = null;
let userChannelKey = null;
let matchChannelKeys = [];
const panelIndex = ref(1); // 0 left, 1 center, 2 right
const dragging = ref(false);
const dragDx = ref(0);
const matchDragging = ref(false);
const matchDragDx = ref(0);
const dashboardSwipe = createHorizontalSwipeTracker({
    minDistance: 56,
    minHorizontalRatio: 1.25,
});
const matchSwipe = createHorizontalSwipeTracker({
    minDistance: 40,
    minHorizontalRatio: 1.2,
});

const selectedPool = computed(() => (data.value?.pools ?? []).find((p) => p.id === selectedPoolId.value) ?? null);
const selectedCompetition = computed(() => appStore.competitions.find(c => c.id === appStore.selectedCompetitionId));
const selectedCompetitionType = computed(() => String(selectedCompetition.value?.type ?? '').toUpperCase());
const shouldUseCupLayout = computed(() => {
    if (selectedCompetitionType.value === 'CUP') return true;
    return (standingsGroups.value ?? []).some((g) => {
        const name = String(g?.name ?? '').trim().toUpperCase();
        return name !== '' && name !== 'CLASSIFICAÇÃO GERAL' && name !== 'CLASSIFICACAO GERAL';
    });
});
const competitionPools = computed(() => {
    return data.value?.pools ?? [];
});
const myRankingRow = computed(() => {
    if (!auth.user?.id) return null;
    return liveRankingRows.value.find((r) => r.user?.id === auth.user.id) ?? null;
});
const filteredLiveMatches = computed(() => {
    return data.value?.live_matches ?? [];
});
const livePredictionMap = computed(() => {
    const map = {};
    for (const item of poolPredictions.value) {
        if (item.prediction && ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'].includes(item.match?.status)) {
            map[item.match.id] = item.prediction;
        }
    }
    return map;
});
const todayOpenMatches = computed(() => dayMatches.value);
const recentResults = computed(() => {
    const items = poolPredictions.value ?? [];
    if (!items.length) return [];

    const normalized = items
        .map((item) => ({
            ...item,
            _matchday: Number(item.match?.matchday ?? 0),
            _status: String(item.match?.status ?? ''),
            _time: item.match?.local_date ? new Date(item.match.local_date).getTime() : 0,
        }))
        .filter((item) => item._matchday > 0);

    if (!normalized.length) return [];

    const byMatchday = new Map();
    for (const item of normalized) {
        const md = item._matchday;
        if (!byMatchday.has(md)) byMatchday.set(md, []);
        byMatchday.get(md).push(item);
    }

    const nowTs = Date.now();
    const candidates = [];
    for (const [matchday, group] of byMatchday.entries()) {
        const finished = group.filter((i) => ['FINISHED', 'AWARDED'].includes(i._status));
        if (!finished.length) continue;

        // Usa a data da rodada mais próxima de "agora" para decidir a rodada atual.
        const times = group.map((i) => i._time).filter((t) => Number.isFinite(t) && t > 0);
        if (!times.length) continue;
        const nearestTime = times.reduce((best, t) =>
            Math.abs(t - nowTs) < Math.abs(best - nowTs) ? t : best, times[0]);

        candidates.push({
            matchday,
            nearestDistance: Math.abs(nearestTime - nowTs),
            nearestTime,
            finished,
        });
    }

    if (!candidates.length) return [];

    candidates.sort((a, b) => {
        if (a.nearestDistance !== b.nearestDistance) return a.nearestDistance - b.nearestDistance;
        return b.nearestTime - a.nearestTime;
    });

    return candidates[0].finished
        .sort((a, b) => b._time - a._time)
        .slice(0, 8)
        .map((item) => ({
            ...item,
            points: pointsForPrediction(item),
        }));
});

const trackStyle = computed(() => {
    const base = -panelIndex.value * 100;
    const dragPct = dragging.value ? (dragDx.value / window.innerWidth) * 100 : 0;
    return { transform: `translateX(${base + dragPct}%)` };
});
const nextTrackStyle = computed(() => {
    const base = -nextMatchIndex.value * 100;
    const dragPct = matchDragging.value ? (matchDragDx.value / Math.max(1, window.innerWidth - 48)) * 100 : 0;
    return { transform: `translateX(${base + dragPct}%)` };
});

function normalizePoolId(value) {
    const parsed = Number(value);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function shortName(team) {
    if (!team) return '—';
    return team.short_name ?? team.name ?? team.tla ?? '—';
}
function matchTime(localDate) {
    if (!localDate) return '—';
    return new Date(localDate).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}
function formatDateTime(localDate) {
    if (!localDate) return '—';
    return new Date(localDate).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).replace(',', ' ·');
}
function roundLabel(match) {
    const md = Number(match?.matchday ?? 0);
    if (Number.isFinite(md) && md > 0) return `Rodada ${md}`;
    return 'Rodada';
}
function liveMinuteLabel(match) {
    const min = match?.live_clock?.minute;
    const extra = match?.live_clock?.extra_minute;
    if (!Number.isFinite(min) || min <= 0) return '';
    const capped = Math.min(130, min);
    if (Number.isFinite(extra) && extra > 0) return `${capped}+${extra}'`;
    return `${capped}'`;
}
function goalLine(g) {
    const num = Number.isFinite(g?.number) ? `${g.number} ` : '';
    const name = g?.name ?? 'Jogador';
    const minute = Number.isFinite(g?.minute) ? `${g.minute}` : '?';
    const extra = Number.isFinite(g?.extra_minute) && g.extra_minute > 0 ? `+${g.extra_minute}` : '';
    return `${num}${name} · ${minute}${extra}'`;
}
function localDateYmd(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function detectCompetitionTitle(payload) {
    if (selectedCompetition.value?.name) return selectedCompetition.value.name;
    return payload?.upcoming_matches?.[0]?.competition?.name
        ?? payload?.live_matches?.[0]?.competition?.name
        ?? payload?.pools?.[0]?.competition?.name
        ?? '';
}

function selectPool(id) {
    const normalized = normalizePoolId(id);
    selectedPoolId.value = normalized;
    if (normalized) {
        localStorage.setItem(DASHBOARD_POOL_ID_KEY, String(normalized));
    } else {
        localStorage.removeItem(DASHBOARD_POOL_ID_KEY);
    }
}

function hydrateSelections() {
    const pools = competitionPools.value;
    if (!pools.length) return;
    const stored = normalizePoolId(localStorage.getItem(DASHBOARD_POOL_ID_KEY));
    selectedPoolId.value = stored && pools.some((p) => p.id === stored) ? stored : pools[0].id;
}

async function loadDashboard({ silent = false } = {}) {
    if (!silent) loading.value = true;
    refreshing.value = silent;
    const params = {};
    if (appStore.selectedCompetitionId) params.competition_id = appStore.selectedCompetitionId;
    const res = await getDashboard(params);
    data.value = res.data.data;
    emit('set-title', selectedCompetition.value?.name || detectCompetitionTitle(data.value));
    if (!selectedPoolId.value) hydrateSelections();
    loading.value = false;
    refreshing.value = false;
}

async function loadPoolData() {
    const poolId = normalizePoolId(selectedPoolId.value);
    if (!poolId) {
        selectedPoolId.value = null;
        liveRankingRows.value = [];
        poolPredictions.value = [];
        selectedPoolDetail.value = null;
        return;
    }

    const [rankRes, poolRes] = await Promise.all([
        getLiveRanking(poolId),
        getPool(poolId),
    ]);
    const predictions = await loadAllPoolPredictions(poolId);

    liveRankingRows.value = rankRes.data.data.items ?? [];
    poolPredictions.value = predictions;
    selectedPoolDetail.value = poolRes.data.data ?? null;
}

async function loadAllPoolPredictions(poolId) {
    const perPage = 100;
    const first = await getPoolPredictions(poolId, { per_page: perPage, page: 1 });
    const items = [...(first.data.data.items ?? [])];
    const totalPages = Number(first.data.meta?.pagination?.total_pages ?? 1) || 1;

    if (totalPages <= 1) return items;

    const requests = [];
    for (let page = 2; page <= totalPages; page += 1) {
        requests.push(getPoolPredictions(poolId, { per_page: perPage, page }));
    }
    const pages = await Promise.all(requests);
    for (const res of pages) {
        items.push(...(res.data.data.items ?? []));
    }

    return items;
}

async function loadStandings() {
    const res = await getStandings(appStore.selectedCompetitionId ? { competition_id: appStore.selectedCompetitionId } : {});
    standingsGroups.value = res.data.data.groups ?? [];
}

async function loadDayMatches() {
    const referenceCompetitionId = appStore.selectedCompetitionId;

    const now = new Date();
    const dayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const prevDay = new Date(dayStart);
    prevDay.setDate(prevDay.getDate() - 1);
    const horizonDay = new Date(dayStart);
    horizonDay.setDate(horizonDay.getDate() + 21);

    const paramsWindow = {
        date_from: localDateYmd(prevDay),
        date_to: localDateYmd(horizonDay),
        status: 'TIMED',
        order: 'asc',
        per_page: 100,
    };
    if (referenceCompetitionId) paramsWindow.competition_id = referenceCompetitionId;

    const resWindow = await getMatches(paramsWindow);
    const todayKey = localDateYmd(dayStart);
    const todayList = (resWindow.data.data.items ?? [])
        .filter((m) => {
            if (!m.local_date) return false;
            const d = new Date(m.local_date);
            if (Number.isNaN(d.getTime())) return false;
            return localDateYmd(d) === todayKey;
        })
        .sort((a, b) => {
        const ta = a.local_date ? new Date(a.local_date).getTime() : 0;
        const tb = b.local_date ? new Date(b.local_date).getTime() : 0;
        return ta - tb;
    });

    const hasToday = todayList.length > 0;

    if (hasToday) {
        dayMatches.value = todayList;
        const firstUpcoming = dayMatches.value.findIndex((m) => ['TIMED', 'SCHEDULED', 'PRE_MATCH'].includes(m.status));
        if (firstUpcoming >= 0) {
            nextMatchIndex.value = firstUpcoming;
            return;
        }
        const firstLive = dayMatches.value.findIndex((m) => ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'].includes(m.status));
        if (firstLive >= 0) {
            nextMatchIndex.value = firstLive;
            return;
        }
        nextMatchIndex.value = 0;
        return;
    }

    const items = (resWindow.data.data.items ?? [])
        .filter((m) => m.local_date && !Number.isNaN(new Date(m.local_date).getTime()))
        .sort((a, b) => {
            const ta = a.local_date ? new Date(a.local_date).getTime() : 0;
            const tb = b.local_date ? new Date(b.local_date).getTime() : 0;
            return ta - tb;
        });

    // Sem jogos hoje: pega o primeiro dia futuro da competição que tenha jogos.
    const nextFuture = items.find((m) => {
        const d = new Date(m.local_date);
        d.setHours(0, 0, 0, 0);
        return d.getTime() >= dayStart.getTime();
    });
    if (nextFuture) {
        const nextKey = localDateYmd(new Date(nextFuture.local_date));
        dayMatches.value = items.filter((m) => localDateYmd(new Date(m.local_date)) === nextKey);
    } else {
        dayMatches.value = [];
    }
    nextMatchIndex.value = 0;
}

async function manualRefresh() {
    await loadDashboard({ silent: true });
    await Promise.all([loadPoolData(), loadStandings(), loadDayMatches()]);
}

function nextMatch() {
    if (!todayOpenMatches.value.length) return;
    if (nextMatchIndex.value >= Math.max(0, todayOpenMatches.value.length - 1)) return;
    nextMatchIndex.value += 1;
}

function prevMatch() {
    if (!todayOpenMatches.value.length) return;
    if (nextMatchIndex.value <= 0) return;
    nextMatchIndex.value -= 1;
}

function onMatchTouchStart(e) {
    matchSwipe.start(e);
    matchDragging.value = true;
    matchDragDx.value = 0;
}

function onMatchTouchMove(e) {
    if (!matchDragging.value) return;
    const move = matchSwipe.move(e);
    if (!move.active) return;
    if (move.shouldPreventDefault) e.preventDefault();
    matchDragDx.value = move.dx;
}

function onMatchTouchEnd(e) {
    if (!matchDragging.value) return;
    const end = matchSwipe.end(e);
    matchDragging.value = false;
    matchDragDx.value = 0;
    if (!end.isSwipe) return;
    if (end.direction === 'left') nextMatch();
    else prevMatch();
}

function onTouchStart(e) {
    dashboardSwipe.start(e);
    dragging.value = true;
    dragDx.value = 0;
    
    const panelEl = e.currentTarget.querySelector('.dash-panel');
    isPtrEligible = panelEl && panelEl.scrollTop === 0;
    startY = e.touches[0].clientY;
}

function onTouchMove(e) {
    if (!dragging.value) return;
    const move = dashboardSwipe.move(e);
    
    // Vertical PTR logic
    if (isPtrEligible && !move.isHorizontal && !refreshing.value) {
        const dy = e.touches[0].clientY - startY;
        if (dy > 0) {
            ptrY.value = dy * 0.4; // Resistance
            if (ptrY.value > 10) e.preventDefault();
        }
    }

    if (!move.active) return;
    if (move.shouldPreventDefault) e.preventDefault();
    dragDx.value = move.dx;
}

function onTouchEnd(e) {
    if (!dragging.value) return;
    const end = dashboardSwipe.end(e);
    dragging.value = false;
    dragDx.value = 0;

    if (ptrY.value > 60) {
        manualRefresh();
    }
    ptrY.value = 0;

    if (!end.isSwipe) return;
    if (end.direction === 'right' && panelIndex.value > 0) panelIndex.value -= 1;
    if (end.direction === 'left' && panelIndex.value < 2) panelIndex.value += 1;
}

function livePointsForMatch(m) {
    const pred = livePredictionMap.value[m.id];
    if (!pred) return 0;
    return pointsForScores(pred.home_score, pred.away_score, m.score?.home, m.score?.away);
}

function formatSignedPoints(points) {
    const n = Number.isFinite(points) ? points : 0;
    return n > 0 ? `+${n}` : `${n}`;
}

function pointsForPrediction(item) {
    const pred = item?.prediction;
    const score = item?.match?.score;
    if (!pred || !score) return 0;
    return pointsForScores(pred.home_score, pred.away_score, score.home, score.away);
}

function pointsForScores(predHome, predAway, realHome, realAway) {
    const ph = Number(predHome);
    const pa = Number(predAway);
    const rh = Number(realHome);
    const ra = Number(realAway);
    if (![ph, pa, rh, ra].every(Number.isFinite)) return 0;

    const scoring = selectedPoolDetail.value?.scoring ?? {};
    const exact = Number(scoring.points_exact_score ?? 0);
    const result = Number(scoring.points_correct_result ?? 0);
    const perGoal = Number(scoring.points_correct_goals ?? 0);
    const mode = String(scoring.correct_goals_mode ?? 'both_teams');

    if (ph === rh && pa === ra) return exact;

    let total = 0;
    const predDiff = ph - pa;
    const realDiff = rh - ra;
    if ((predDiff === 0 && realDiff === 0) || (predDiff > 0 && realDiff > 0) || (predDiff < 0 && realDiff < 0)) {
        total += result;
    }

    if (perGoal > 0) {
        if (mode === 'one_team') {
            if (ph === rh || pa === ra) total += perGoal;
        } else {
            if (ph === rh) total += perGoal;
            if (pa === ra) total += perGoal;
        }
    }

    return total;
}

function startRealtime() {
    stopRealtime();
    echo = getPwaEcho();
    if (!echo) return;

    echo.channel('matches')
        .listen('.MatchUpdated', manualRefresh)
        .listen('.MatchDetailUpdated', manualRefresh);

    const poolId = normalizePoolId(selectedPoolId.value);
    const membershipStatus = String(selectedPoolDetail.value?.membership?.status ?? '').toLowerCase();
    const canSubscribePoolChannel = membershipStatus === 'active';
    if (poolId && canSubscribePoolChannel) {
        poolChannelKey = `private-pool.${poolId}`;
        poolsChannelKey = `private-pools.${poolId}`;
        echo.private(`pool.${poolId}`).listen('.RankingUpdated', loadPoolData);
        // Alias plural para novos eventos sem quebrar canal legado.
        echo.private(`pools.${poolId}`).listen('.pool.ranking.updated', loadPoolData);
    }

    const userId = Number(auth.user?.id ?? 0);
    if (userId > 0) {
        userChannelKey = `private-users.${userId}`;
        echo.private(`users.${userId}`)
            .listen('.prediction.scored', manualRefresh)
            .listen('.match.finished', manualRefresh)
            .listen('.match.summary', manualRefresh)
            .listen('.goal.scored', manualRefresh);
    }

    const ids = (todayOpenMatches.value ?? [])
        .map((m) => Number(m?.id ?? 0))
        .filter((id) => Number.isFinite(id) && id > 0);

    matchChannelKeys = [...new Set(ids)].map((id) => `private-matches.${id}`);
    for (const id of [...new Set(ids)]) {
        echo.private(`matches.${id}`)
            .listen('.goal.scored', manualRefresh)
            .listen('.match.finished', manualRefresh)
            .listen('.match.summary', manualRefresh);
    }
}

function stopRealtime() {
    if (!echo) return;
    echo.leave('matches');
    if (poolChannelKey) echo.leave(poolChannelKey);
    if (poolsChannelKey) echo.leave(poolsChannelKey);
    if (userChannelKey) echo.leave(userChannelKey);
    for (const key of matchChannelKeys) echo.leave(key);
    poolChannelKey = null;
    poolsChannelKey = null;
    userChannelKey = null;
    matchChannelKeys = [];
}

watch(selectedPoolId, async () => {
    selectedPoolId.value = normalizePoolId(selectedPoolId.value);
    await loadPoolData();
    startRealtime();
});

watch(() => appStore.selectedCompetitionId, async () => {
    selectedPoolId.value = null;
    localStorage.removeItem(DASHBOARD_POOL_ID_KEY);
    await loadDashboard();
    await Promise.all([loadPoolData(), loadStandings(), loadDayMatches()]);
});

watch(panelIndex, (idx) => {
    const normalized = Math.max(0, Math.min(2, Number(idx) || 1));
    localStorage.setItem(DASHBOARD_PANEL_INDEX_KEY, String(normalized));
});

watch(liveTab, (tab) => {
    const normalized = tab === 'ranking' ? 'ranking' : 'mine';
    localStorage.setItem(DASHBOARD_LIVE_TAB_KEY, normalized);
});

watch(selectedCompetition, (comp) => {
    if (comp?.name) {
        emit('set-title', comp.name);
        return;
    }
    emit('set-title', detectCompetitionTitle(data.value));
});

onMounted(async () => {
    if (!auth.user) await auth.fetchMe();
    const persistedPanel = Number(localStorage.getItem(DASHBOARD_PANEL_INDEX_KEY) ?? 1);
    panelIndex.value = Number.isFinite(persistedPanel) ? Math.max(0, Math.min(2, persistedPanel)) : 1;
    const persistedLiveTab = String(localStorage.getItem(DASHBOARD_LIVE_TAB_KEY) ?? 'mine');
    liveTab.value = persistedLiveTab === 'ranking' ? 'ranking' : 'mine';
    
    await loadDashboard();
    await loadPoolData();
    await loadStandings();
    await loadDayMatches();
    startRealtime();
});

onBeforeUnmount(() => {
    stopRealtime();
});
</script>

<style scoped>
.dashboard-page { overflow: hidden; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.ptr-indicator {
    position: absolute;
    top: -40px;
    left: 0;
    right: 0;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    transition: opacity 0.2s;
}

.dash-slider { height: calc(100dvh - 56px - 68px - env(safe-area-inset-bottom, 0px)); overflow: hidden; position: relative; }
.dash-slider { touch-action: pan-y; }
.dash-track { display: flex; width: 100%; height: 100%; transition: transform 0.22s ease; }
.dash-panel { flex: 0 0 100%; width: 100%; height: 100%; overflow-y: auto; }

.panel-title { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 800; text-transform: uppercase; color: #fff; }
.panel-sub { font-size: 12px; color: #7a8394; margin-top: 2px; }

.card { border-radius: 12px; border: 1px solid rgba(255,255,255,0.08); background: #13161b; }
.rank-row { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.07); background: #171d28; font-size: 11px; }
.rank-row.me { border-color: rgba(245,166,35,0.35); background: rgba(245,166,35,0.1); }

.pool-chip { border-radius: 999px; border: 1px solid rgba(255,255,255,0.08); background: #1a1f2c; color: #7a8394; padding: 6px 12px; font-size: 12px; font-weight: 700; }
.pool-chip.active { background: rgba(245,166,35,0.14); border-color: rgba(245,166,35,0.35); color: #f5a623; }

.section-head { display: flex; align-items: center; justify-content: space-between; }
.section-title { display: inline-flex; align-items: center; gap: 8px; font-size: 16px; font-family: 'Barlow Condensed', sans-serif; font-weight: 800; text-transform: uppercase; color: #f8fafc; }
.section-mini-btn { height: 22px; width: 26px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.08); background: #1a1f2c; color: #7a8394; display: inline-flex; align-items: center; justify-content: center; }
.my-position-card { border-radius: 12px; border: 1px solid rgba(245,166,35,0.9); background: linear-gradient(180deg, #1b2130 0%, #171d28 100%); padding: 12px; display: grid; grid-template-columns: auto 1fr auto; gap: 12px; align-items: center; }
.my-position-meta { display: flex; flex-direction: column; gap: 2px; min-width: 66px; }
.my-position-user { min-width: 0; }
.my-position-points { min-width: 54px; text-align: right; }

.live-card { border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); border-top: 2px solid #f5a623; background: #13161b; padding: 10px 12px; }
.live-match-grid { display: grid; grid-template-columns: 1fr auto 1fr; gap: 8px; align-items: start; }
.team-col { min-width: 0; }
.team-main { display: flex; align-items: center; gap: 8px; min-height: 30px; }
.team-col-away { text-align: right; }
.team-main-away { justify-content: flex-end; }
.score-wrap { display: flex; align-items: center; justify-content: center; min-height: 30px; padding-top: 1px; }
.team-name { font-size: 13px; color: #e2e8f0; font-weight: 700; }
.team-crest { width: 22px; height: 22px; object-fit: contain; flex-shrink: 0; }
.score-main { font-family: 'Barlow Condensed', sans-serif; font-size: 28px; font-weight: 800; color: #fff; line-height: 1; min-width: 60px; text-align: center; }
.goal-lines { margin-top: 2px; display: flex; flex-direction: column; gap: 1px; }
.goal-line { font-size: 10px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.live-toggle { border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); background: #171d28; display: grid; grid-template-columns: 1fr 1fr; overflow: hidden; }
.live-toggle-btn { padding: 6px; font-size: 10px; text-transform: uppercase; font-weight: 700; border: 0; background: transparent; color: #7a8394; }
.live-toggle-btn.active { background: #f5a623; color: #000; }

.next-carousel { position: relative; }
.next-card { flex: 1; border-radius: 14px; border: 1px solid rgba(255,255,255,0.1); border-top: 2px solid #f5a623; background: #13161b; padding: 10px 12px; }
.next-card-slider { overflow: hidden; }
.next-card-slider { padding: 0; touch-action: pan-y; }
.next-track { display: flex; transition: transform 0.22s ease; }
.next-slide { flex: 0 0 100%; width: 100%; max-width: 100%; overflow: hidden; }
.next-slide-inner { padding: 10px 12px; }
.next-slide-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.next-hero-teams { padding: 10px 2px 4px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.next-team { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 4px; }
.next-crest { width: 52px; height: 52px; object-fit: contain; }
.next-team-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 700; color: #f8fafc; text-transform: uppercase; text-align: center; line-height: 1.1; }
.next-team-tla { font-size: 11px; color: #7a8394; }
.next-vs { width: 24px; text-align: center; font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 800; color: #4a5568; }
.next-score { width: 56px; text-align: center; font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 800; color: #f8fafc; line-height: 1; }

.standings-card { border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); background: #13161b; }
.standings-scroll { max-height: min(62dvh, 560px); overflow-y: auto; padding-right: 2px; }
.cup-group-card { border-radius: 10px; border: 1px solid rgba(255,255,255,0.08); background: #121823; padding: 8px; }
.standings-head, .standings-row { display: grid; grid-template-columns: 22px minmax(0,1fr) 24px 20px 20px 20px 28px 30px; align-items: center; gap: 6px; }
.standings-head { color: #7a8394; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; border-bottom: 1px solid rgba(255,255,255,0.08); padding: 6px 8px; }
.standings-row { font-size: 12px; color: #cbd5e1; padding: 7px 8px; border-bottom: 1px solid rgba(255,255,255,0.05); }
.standings-row:last-child { border-bottom: 0; }
.stand-col-pos { font-family: 'Barlow Condensed', sans-serif; font-size: 14px; font-weight: 700; color: #34d399; text-align: center; }
.stand-col-team { min-width: 0; display: inline-flex; align-items: center; gap: 7px; }
.stand-crest { width: 18px; height: 18px; object-fit: contain; flex-shrink: 0; }

.recent-card { border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); background: #13161b; }
.recent-meta { display: flex; align-items: center; gap: 8px; }
.recent-tag { border-radius: 6px; background: #242b39; color: #8b9bb4; padding: 2px 6px; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
.recent-points { margin-left: auto; min-width: 30px; text-align: center; border-radius: 6px; background: #17345d; color: #65a9ff; padding: 2px 7px; font-family: 'Barlow Condensed', sans-serif; font-weight: 700; font-size: 14px; line-height: 1.1; }
</style>
