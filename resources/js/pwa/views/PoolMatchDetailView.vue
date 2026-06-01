<template>
    <div class="pwa-page pb-4">

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center items-center pt-20">
            <i class="ti ti-loader-2 text-3xl text-bolao-accent animate-spin"></i>
        </div>

        <template v-else-if="detail">

            <!-- ── Match Hero ── -->
            <div class="match-hero px-4 pt-4 pb-3">

                <!-- Status / Live badge -->
                <div class="flex justify-center mb-3">
                    <span v-if="detail.status === 'PENALTY_SHOOTOUT'" class="live-badge">
                        <span class="live-dot"></span> Pênaltis
                    </span>
                    <span v-else-if="detail.status === 'EXTRA_TIME'" class="live-badge">
                        <span class="live-dot"></span> Prorrogação
                        <span class="ml-1 opacity-80">· {{ displayMinute }}'<span v-if="detail.live_clock?.extra_minute" class="opacity-70">+{{ detail.live_clock.extra_minute }}</span></span>
                    </span>
                    <span v-else-if="isLive" class="live-badge">
                        <span class="live-dot"></span>
                        {{ displayMinute }}'<span v-if="detail.live_clock?.extra_minute" class="opacity-70">+{{ detail.live_clock.extra_minute }}</span>
                    </span>
                    <span v-else-if="isPaused" class="status-badge status-badge--paused">Intervalo</span>
                    <span v-else-if="isFinished" class="status-badge status-badge--finished">Encerrado</span>
                    <span v-else class="status-badge">{{ formatMatchDate(detail.local_date) }}</span>
                </div>

                <!-- Teams + Score -->
                <div class="flex items-center justify-between gap-3">
                    <div class="flex-1 flex flex-col items-center gap-1.5 min-w-0">
                        <img v-if="detail.home_team.crest" :src="detail.home_team.crest" class="w-14 h-14 object-contain shrink-0" alt="">
                        <div v-else class="w-14 h-14 rounded-full bg-bolao-bg3 flex items-center justify-center text-lg font-black text-bolao-muted">
                            {{ detail.home_team.tla }}
                        </div>
                        <span class="text-xs font-bold text-white text-center leading-tight truncate w-full text-center px-1">
                            {{ detail.home_team.short_name || detail.home_team.name }}
                        </span>
                    </div>

                    <div class="flex flex-col items-center shrink-0">
                        <div class="font-bc text-5xl font-black text-white leading-none tracking-tight">
                            {{ detail.score.home ?? 0 }}&nbsp;:&nbsp;{{ detail.score.away ?? 0 }}
                        </div>
                        <div v-if="hasHalfScore" class="text-[11px] text-bolao-muted mt-1 font-semibold">
                            ({{ detail.score.home_half ?? 0 }} : {{ detail.score.away_half ?? 0 }}) 1ºT
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col items-center gap-1.5 min-w-0">
                        <img v-if="detail.away_team.crest" :src="detail.away_team.crest" class="w-14 h-14 object-contain shrink-0" alt="">
                        <div v-else class="w-14 h-14 rounded-full bg-bolao-bg3 flex items-center justify-center text-lg font-black text-bolao-muted">
                            {{ detail.away_team.tla }}
                        </div>
                        <span class="text-xs font-bold text-white text-center leading-tight truncate w-full text-center px-1">
                            {{ detail.away_team.short_name || detail.away_team.name }}
                        </span>
                    </div>
                </div>

                <!-- Goal scorers -->
                <div v-if="hasGoalScorers" class="flex justify-between mt-2.5 px-1 gap-4">
                    <div class="flex-1 space-y-0.5">
                        <div v-for="g in detail.goals.home" :key="g.minute + g.name" class="flex items-center gap-1 text-[11px] text-bolao-muted">
                            <span class="text-[12px] leading-none">⚽</span>
                            <span class="truncate">{{ g.name }}</span>
                            <span class="shrink-0 opacity-60">{{ g.minute }}'<span v-if="g.extra_minute">+{{ g.extra_minute }}</span></span>
                        </div>
                    </div>
                    <div class="flex-1 space-y-0.5 text-right">
                        <div v-for="g in detail.goals.away" :key="g.minute + g.name" class="flex items-center justify-end gap-1 text-[11px] text-bolao-muted">
                            <span class="shrink-0 opacity-60">{{ g.minute }}'<span v-if="g.extra_minute">+{{ g.extra_minute }}</span></span>
                            <span class="truncate">{{ g.name }}</span>
                            <span class="text-[12px] leading-none">⚽</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── Sticky Tabs ── -->
            <div class="sticky top-0 z-10 bg-bolao-bg1 border-b border-white/5">
                <div class="flex">
                    <button v-if="poolId" @click="activeTab = 'palpites'" :class="tabCls('palpites')" class="tab-btn">
                        <i class="ti ti-users"></i><span>Palpites</span>
                    </button>
                    <button @click="activeTab = 'eventos'" :class="tabCls('eventos')" class="tab-btn">
                        <i class="ti ti-timeline-event"></i><span>Eventos</span>
                    </button>
                    <button @click="activeTab = 'stats'" :class="tabCls('stats')" class="tab-btn">
                        <i class="ti ti-chart-bar"></i><span>Stats</span>
                    </button>
                    <button @click="activeTab = 'escalacao'" :class="tabCls('escalacao')" class="tab-btn">
                        <i class="ti ti-shirt"></i><span>Escalação</span>
                    </button>
                </div>
            </div>

            <!-- ── Tab Content ── -->
            <div class="pwa-section pt-3">

                <!-- ─ Palpites ─ -->
                <template v-if="activeTab === 'palpites' && poolId">
                    <div v-if="loadingPredictions" class="flex justify-center pt-8">
                        <i class="ti ti-loader-2 text-2xl text-bolao-accent animate-spin"></i>
                    </div>
                    <div v-else-if="sortedPredictions.length === 0" class="text-center py-10 text-bolao-muted text-sm">
                        Nenhum palpite registrado ainda.
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="item in sortedPredictions"
                            :key="item.user.id"
                            class="pwa-card p-3 flex items-center justify-between"
                            :class="{ 'border-bolao-accent/40 bg-bolao-accent/5': isMe(item.user.id) }"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-bolao-bg4 flex items-center justify-center text-[10px] font-black text-bolao-muted shrink-0">
                                    {{ getInitials(item.user.name) }}
                                </div>
                                <span class="text-sm font-bold text-white">{{ item.user.name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <template v-if="item.prediction === 'hidden'">
                                    <div class="bg-bolao-bg3 px-3 py-1 rounded-lg border border-white/5">
                                        <i class="ti ti-lock text-bolao-muted2 text-xs"></i>
                                    </div>
                                </template>
                                <template v-else-if="item.prediction">
                                    <div class="bg-bolao-bg4 px-3 py-1 rounded-lg border border-white/10 font-bc text-lg font-bold text-white">
                                        {{ item.prediction.home_score }}&times;{{ item.prediction.away_score }}
                                    </div>
                                </template>
                                <template v-else>
                                    <span class="text-[10px] text-bolao-muted2 italic uppercase font-bold">Sem palpite</span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ─ Eventos ─ -->
                <template v-else-if="activeTab === 'eventos'">
                    <div v-if="!detail.has_detail || detail.events.length === 0" class="text-center py-10 text-bolao-muted text-sm px-4">
                        <i class="ti ti-timeline-event-text text-3xl block mb-2 opacity-40"></i>
                        {{ detail.has_detail ? 'Nenhum evento registrado.' : 'Eventos disponíveis durante e após a partida.' }}
                    </div>
                    <div v-else class="space-y-px">
                        <div
                            v-for="(ev, i) in detail.events"
                            :key="i"
                            class="event-row"
                            :class="ev.is_home ? 'event-row--home' : 'event-row--away'"
                        >
                            <!-- Home side (right-aligned, seta à esquerda do nome) -->
                            <div class="event-side" :class="ev.is_home ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                                <template v-if="ev.type === 'substitution'">
                                    <div class="flex items-center justify-end gap-1 leading-tight">
                                        <i class="ti ti-arrow-up text-emerald-400 shrink-0" style="font-size:10px"></i>
                                        <span class="text-[13px] font-bold text-white truncate">{{ ev.secondary || '?' }}</span>
                                    </div>
                                    <div class="flex items-center justify-end gap-1 leading-tight">
                                        <i class="ti ti-arrow-down text-red-400 shrink-0" style="font-size:10px"></i>
                                        <span class="text-[12px] text-bolao-muted truncate">{{ ev.player }}</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="text-[13px] font-bold text-white text-right leading-tight truncate">{{ ev.player }}</div>
                                    <div v-if="ev.secondary && ev.type === 'goal'" class="text-[11px] text-bolao-muted text-right">
                                        Assist: {{ ev.secondary }}
                                    </div>
                                </template>
                            </div>

                            <!-- Minute + Icon (center) -->
                            <div class="event-center">
                                <span class="event-icon-wrap">{{ eventIcon(ev) }}</span>
                                <span class="event-minute-label">
                                    {{ ev.minute }}'<span v-if="ev.extra_minute" class="opacity-60">+{{ ev.extra_minute }}</span>
                                </span>
                            </div>

                            <!-- Away side (left-aligned, seta à direita do nome) -->
                            <div class="event-side" :class="!ev.is_home ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                                <template v-if="ev.type === 'substitution'">
                                    <div class="flex items-center gap-1 leading-tight">
                                        <span class="text-[13px] font-bold text-white truncate">{{ ev.secondary || '?' }}</span>
                                        <i class="ti ti-arrow-up text-emerald-400 shrink-0" style="font-size:10px"></i>
                                    </div>
                                    <div class="flex items-center gap-1 leading-tight">
                                        <span class="text-[12px] text-bolao-muted truncate">{{ ev.player }}</span>
                                        <i class="ti ti-arrow-down text-red-400 shrink-0" style="font-size:10px"></i>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="text-[13px] font-bold text-white leading-tight truncate">{{ ev.player }}</div>
                                    <div v-if="ev.secondary && ev.type === 'goal'" class="text-[11px] text-bolao-muted">
                                        Assist: {{ ev.secondary }}
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Team labels -->
                        <div class="flex justify-between px-2 pt-3 pb-1 text-[10px] font-bold uppercase text-bolao-muted tracking-wider">
                            <span>{{ detail.home_team.short_name || detail.home_team.tla }}</span>
                            <span>{{ detail.away_team.short_name || detail.away_team.tla }}</span>
                        </div>
                    </div>
                </template>

                <!-- ─ Stats ─ -->
                <template v-else-if="activeTab === 'stats'">
                    <div v-if="!detail.has_detail || detail.stats.length === 0" class="text-center py-10 text-bolao-muted text-sm px-4">
                        <i class="ti ti-chart-bar text-3xl block mb-2 opacity-40"></i>
                        {{ detail.has_detail ? 'Estatísticas não disponíveis para esta partida.' : 'Estatísticas disponíveis durante e após a partida.' }}
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="row in detail.stats" :key="row.label" class="stat-row">
                            <!-- Values -->
                            <div class="flex justify-between items-center mb-1 px-1">
                                <span class="text-sm font-black text-white w-10 text-left">{{ row.home }}</span>
                                <span class="text-[11px] font-bold text-bolao-muted uppercase tracking-wide text-center flex-1">{{ row.label }}</span>
                                <span class="text-sm font-black text-white w-10 text-right">{{ row.away }}</span>
                            </div>
                            <!-- Bar -->
                            <div v-if="statHasBar(row)" class="flex h-1.5 rounded-full overflow-hidden bg-white/5">
                                <div class="h-full bg-bolao-accent rounded-l-full transition-all duration-700" :style="{ width: statBarPct(row, 'home') + '%' }"></div>
                                <div class="h-full bg-blue-500 rounded-r-full transition-all duration-700" :style="{ width: statBarPct(row, 'away') + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ─ Escalação ─ -->
                <template v-else-if="activeTab === 'escalacao'">
                    <div v-if="!detail.has_detail || (!detail.lineups.home.starters.length && !detail.lineups.away.starters.length)"
                         class="text-center py-10 text-bolao-muted text-sm px-4">
                        <i class="ti ti-shirt text-3xl block mb-2 opacity-40"></i>
                        Escalação disponível próximo à partida.
                    </div>
                    <div v-else>
                        <!-- Team toggle -->
                        <div class="flex rounded-xl overflow-hidden border border-white/10 mb-4">
                            <button @click="lineupSide = 'home'" class="flex-1 py-2 text-sm font-bold transition-colors"
                                :class="lineupSide === 'home' ? 'bg-bolao-accent text-bolao-bg1' : 'text-bolao-muted bg-transparent'">
                                {{ detail.home_team.short_name || detail.home_team.name }}
                            </button>
                            <button @click="lineupSide = 'away'" class="flex-1 py-2 text-sm font-bold transition-colors"
                                :class="lineupSide === 'away' ? 'bg-bolao-accent text-bolao-bg1' : 'text-bolao-muted bg-transparent'">
                                {{ detail.away_team.short_name || detail.away_team.name }}
                            </button>
                        </div>

                        <template v-for="side in [lineupSide]" :key="side">
                            <!-- Formation + Coach -->
                            <div class="flex justify-between items-center mb-3 px-1">
                                <span v-if="detail.lineups[side].formation" class="text-xs font-bold text-bolao-accent bg-bolao-accent/10 px-2 py-0.5 rounded-full">
                                    {{ detail.lineups[side].formation }}
                                </span>
                                <span v-if="detail.lineups[side].coach" class="text-[11px] text-bolao-muted">
                                    <i class="ti ti-user-star text-[10px]"></i> {{ detail.lineups[side].coach }}
                                </span>
                            </div>

                            <!-- Starters -->
                            <p class="pwa-section-label mb-2">Titulares</p>
                            <div class="space-y-1 mb-5">
                                <template v-for="(item, i) in processLineup(detail.lineups[side].starters)" :key="i">

                                    <!-- Regular player -->
                                    <div v-if="item.type === 'player'" class="lineup-row">
                                        <span class="lineup-num">{{ item.player.number ?? '—' }}</span>
                                        <span class="lineup-name">{{ item.player.name }}</span>
                                        <span class="lineup-pos">{{ item.player.position }}</span>
                                    </div>

                                    <!-- Substitution pair -->
                                    <div v-else class="lineup-sub-group">
                                        <div class="lineup-row lineup-row--out">
                                            <span class="lineup-num opacity-40">{{ item.playerOut?.number ?? '—' }}</span>
                                            <span class="lineup-name text-bolao-muted">{{ item.playerOut?.name }}</span>
                                            <span class="lineup-pos">{{ item.playerOut?.position }}</span>
                                            <div class="lineup-sub-badge lineup-sub-badge--out">
                                                <i class="ti ti-arrow-down text-[9px]"></i>{{ item.playerIn.sub_minute }}'
                                            </div>
                                        </div>
                                        <div class="lineup-row lineup-row--in">
                                            <span class="lineup-num" style="color:#34d399">{{ item.playerIn.number ?? '—' }}</span>
                                            <span class="lineup-name font-bold" style="color:#34d399">{{ item.playerIn.name }}</span>
                                            <span class="lineup-pos" style="color:rgba(52,211,153,.55)">{{ item.playerIn.position }}</span>
                                            <div class="lineup-sub-badge lineup-sub-badge--in">
                                                <i class="ti ti-arrow-up text-[9px]"></i>{{ item.playerIn.sub_minute }}'
                                            </div>
                                        </div>
                                    </div>

                                </template>
                            </div>

                            <!-- Bench -->
                            <template v-if="detail.lineups[side].bench.length">
                                <p class="pwa-section-label mb-2">Banco</p>
                                <div class="space-y-1">
                                    <div v-for="(p, i) in detail.lineups[side].bench" :key="'b'+i" class="lineup-row lineup-row--bench">
                                        <span class="lineup-num opacity-40">{{ p.number ?? '—' }}</span>
                                        <span class="lineup-name text-bolao-muted">{{ p.name }}</span>
                                        <span class="lineup-pos">{{ p.position }}</span>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>
                </template>

            </div>
        </template>

        <!-- Error state -->
        <div v-else class="text-center py-20 text-bolao-muted px-4">
            <i class="ti ti-mood-sad text-4xl block mb-3 opacity-40"></i>
            Não foi possível carregar os dados da partida.
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../store/auth';
import { useEcho } from '../composables/useEcho';
import client from '../api/client';

const route   = useRoute();
const auth    = useAuthStore();
const emit    = defineEmits(['set-title']);

const matchId = computed(() => Number(route.params.matchId));
const poolId  = computed(() => route.params.poolId ? Number(route.params.poolId) : null);

const loading            = ref(true);
const loadingPredictions = ref(false);
const detail             = ref(null);
const predictions        = ref([]);
const activeTab          = ref(poolId.value ? 'palpites' : 'eventos');
const lineupSide         = ref('home');

// Live clock
let clockTimer   = null;
let pollTimer    = null;
let echoChannel  = null;

// ── Computed ──────────────────────────────────────────────────────────────

const LIVE_STATUSES    = ['IN_PLAY', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'];
const PAUSED_STATUSES  = ['PAUSED', 'HALFTIME'];
const FINISHED_STATUSES = ['FINISHED', 'AWARDED'];

const isLive     = computed(() => LIVE_STATUSES.includes(detail.value?.status));
const isPaused   = computed(() => PAUSED_STATUSES.includes(detail.value?.status));
const isFinished = computed(() => FINISHED_STATUSES.includes(detail.value?.status));

const displayMinute = computed(() => {
    const base = detail.value?.live_clock?.minute ?? 0;
    return Math.min(130, base);
});

const hasHalfScore = computed(() => {
    const s = detail.value?.score;
    return s && (s.home_half !== null && s.home_half !== undefined)
        && (detail.value?.status !== 'TIMED' && detail.value?.status !== 'SCHEDULED');
});

const hasGoalScorers = computed(() => {
    return detail.value?.goals?.home?.length || detail.value?.goals?.away?.length;
});

const sortedPredictions = computed(() => {
    return [...(predictions.value || [])].sort((a, b) => {
        if (isMe(a.user.id)) return -1;
        if (isMe(b.user.id)) return 1;
        return a.user.name.localeCompare(b.user.name);
    });
});

// ── Methods ───────────────────────────────────────────────────────────────

function tabCls(name) {
    return activeTab.value === name
        ? 'text-bolao-accent border-b-2 border-bolao-accent'
        : 'text-bolao-muted border-b-2 border-transparent';
}

function isMe(userId) {
    return Number(userId) === Number(auth.user?.id);
}

function getInitials(name) {
    return name?.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() || '?';
}

function formatMatchDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function eventIcon(ev) {
    if (ev.type === 'goal') {
        if (ev.subtype === 'own_goal') return '⚽🔴';
        if (ev.subtype === 'penalty')  return '⚽🎯';
        return '⚽';
    }
    if (ev.type === 'card') {
        if (ev.subtype === 'red')        return '🟥';
        if (ev.subtype === 'yellow_red') return '🟨🟥';
        return '🟨';
    }
    if (ev.type === 'substitution') return '↕';
    return '•';
}

function processLineup(starters) {
    const result = [];
    let i = 0;
    while (i < starters.length) {
        const p = starters[i];
        if (p.sub_in) {
            result.push({ type: 'sub', playerIn: p, playerOut: starters[i + 1] ?? null });
            i += 2;
        } else if (p.sub_out) {
            result.push({ type: 'player', player: p });
            i++;
        } else {
            result.push({ type: 'player', player: p });
            i++;
        }
    }
    return result;
}

function statHasBar(row) {
    const h = parseStatNum(row.home);
    const a = parseStatNum(row.away);
    return h !== null || a !== null;
}

function parseStatNum(val) {
    if (!val || val === '-') return null;
    if (typeof val === 'string' && val.endsWith('%')) return parseFloat(val);
    const n = parseFloat(val);
    return isNaN(n) ? null : n;
}

function statBarPct(row, side) {
    const isPercent = (v) => typeof v === 'string' && v.endsWith('%');

    if (isPercent(row.home) || isPercent(row.away)) {
        const v = parseStatNum(side === 'home' ? row.home : row.away);
        return v ?? 0;
    }

    const h = parseStatNum(row.home) ?? 0;
    const a = parseStatNum(row.away) ?? 0;
    const total = h + a;
    if (total === 0) return 50;
    return Math.round((side === 'home' ? h : a) / total * 100);
}

// ── Data fetching ─────────────────────────────────────────────────────────

async function loadDetail() {
    try {
        const { data: res } = await client.get(`/matches/${matchId.value}/detail`);
        detail.value = res.data;

        const title = `${detail.value.home_team.short_name || detail.value.home_team.tla} × ${detail.value.away_team.short_name || detail.value.away_team.tla}`;
        emit('set-title', title);

        syncLiveClock();
    } catch (e) {
        console.error('Erro ao carregar detalhe da partida:', e);
    }
}

async function loadPredictions() {
    if (!poolId.value) return;
    loadingPredictions.value = true;
    try {
        const { data: res } = await client.get(`/pools/${poolId.value}/matches/${matchId.value}/predictions`);
        predictions.value = res.data.predictions ?? [];
    } catch (e) {
        console.error('Erro ao carregar palpites:', e);
    } finally {
        loadingPredictions.value = false;
    }
}

// ── Live clock ────────────────────────────────────────────────────────────

function syncLiveClock() {
    stopClock();
}

function startClock() {
    syncLiveClock();
}

function stopClock() {
    if (clockTimer) {
        clearInterval(clockTimer);
        clockTimer = null;
    }
}

// ── Real-time ─────────────────────────────────────────────────────────────

function handleMatchUpdated(e) {
    if (Number(e.id) !== matchId.value) return;
    loadDetail();
}

function handleMatchDetailUpdated(e) {
    if (Number(e.match_id) !== matchId.value) return;
    loadDetail();
    if (poolId.value) loadPredictions();
}

function subscribeEcho() {
    try {
        echoChannel = useEcho().channel('matches');
        echoChannel
            .listen('.MatchUpdated', handleMatchUpdated)
            .listen('.MatchDetailUpdated', handleMatchDetailUpdated);
    } catch (err) {
        console.warn('Echo não disponível, usando polling:', err.message);
    }
}

function unsubscribeEcho() {
    if (echoChannel) {
        echoChannel.stopListening('.MatchUpdated', handleMatchUpdated);
        echoChannel.stopListening('.MatchDetailUpdated', handleMatchDetailUpdated);
        echoChannel = null;
    }
}

function startPolling() {
    pollTimer = setInterval(() => {
        if (isLive.value || isPaused.value) {
            loadDetail();
        }
    }, 30000);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

// ── Lifecycle ─────────────────────────────────────────────────────────────

onMounted(async () => {
    emit('set-title', 'Detalhe da Partida');

    loading.value = true;
    await Promise.all([
        loadDetail(),
        loadPredictions(),
    ]);
    loading.value = false;

    startClock();
    subscribeEcho();
    startPolling();
});

onUnmounted(() => {
    stopClock();
    stopPolling();
    unsubscribeEcho();
});
</script>

<style scoped>
/* ── Hero ── */
.match-hero {
    background: linear-gradient(180deg, rgba(245,166,35,0.06) 0%, transparent 100%);
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

/* ── Live badge ── */
.live-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(239,68,68,0.15);
    border: 1px solid rgba(239,68,68,0.4);
    color: #ef4444;
    font-size: 12px;
    font-weight: 800;
    padding: 3px 10px;
    border-radius: 999px;
    letter-spacing: 0.03em;
}
.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #ef4444;
    animation: pulse-dot 1.5s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.4; transform: scale(0.7); }
}

/* ── Status badge ── */
.status-badge {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    background: rgba(255,255,255,0.06);
    color: #94a3b8;
    border: 1px solid rgba(255,255,255,0.08);
}
.status-badge--paused {
    background: rgba(234,179,8,0.12);
    border-color: rgba(234,179,8,0.3);
    color: #eab308;
}
.status-badge--finished {
    background: rgba(100,116,139,0.12);
    border-color: rgba(100,116,139,0.2);
    color: #64748b;
}

/* ── Tabs ── */
.tab-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
    padding: 8px 2px 6px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    transition: color 0.15s;
    cursor: pointer;
    background: transparent;
}
.tab-btn i { font-size: 16px; line-height: 1; }

/* ── Events timeline ── */
.event-row {
    display: grid;
    grid-template-columns: 1fr 56px 1fr;
    align-items: center;
    gap: 4px;
    padding: 7px 8px;
    border-radius: 8px;
    transition: background 0.1s;
}
.event-row:hover { background: rgba(255,255,255,0.03); }
.event-row--home { border-left: 2px solid rgba(245,166,35,0.4); }
.event-row--away { border-right: 2px solid rgba(59,130,246,0.4); }

.event-side {
    min-width: 0;
}

.event-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
}
.event-icon-wrap {
    font-size: 14px;
    line-height: 1;
}
.event-minute-label {
    font-size: 10px;
    font-weight: 800;
    color: #64748b;
    white-space: nowrap;
}

/* ── Stats ── */
.stat-row {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.04);
    border-radius: 10px;
    padding: 10px 12px 8px;
}

/* ── Lineup list ── */
.lineup-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.04);
    background: rgba(255,255,255,0.02);
}
.lineup-row--bench { opacity: 0.55; }
.lineup-row--out   { background: rgba(239,68,68,0.04); border-radius: 0; border: 0; border-bottom: 1px solid rgba(255,255,255,0.04); }
.lineup-row--in    { background: rgba(52,211,153,0.05); border-radius: 0; border: 0; }

.lineup-sub-group {
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.06);
}

.lineup-num {
    font-size: 11px;
    font-weight: 900;
    color: #f59e0b;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
    font-family: 'Barlow Condensed', 'Barlow', sans-serif;
}
.lineup-name {
    flex: 1;
    min-width: 0;
    font-size: 13px;
    font-weight: 600;
    color: #e2e8f0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.lineup-pos {
    font-size: 10px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    flex-shrink: 0;
    width: 16px;
    text-align: right;
}
.lineup-sub-badge {
    display: flex;
    align-items: center;
    gap: 2px;
    font-size: 10px;
    font-weight: 800;
    flex-shrink: 0;
    min-width: 32px;
    justify-content: flex-end;
}
.lineup-sub-badge--out { color: #f87171; }
.lineup-sub-badge--in  { color: #34d399; }
</style>
