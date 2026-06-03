<template>
    <div class="pwa-page">

        <template v-if="loading">
            <div class="pwa-section space-y-3 pt-4">
                <SkeletonCard v-for="i in 4" :key="i" />
            </div>
        </template>

        <template v-else-if="data">
            <div class="pwa-section pt-4 space-y-3">

                <!-- Filtro de competição -->
                <div v-if="competitionOptions.length > 0" class="comp-filter-row">
                    <button
                        class="comp-filter-chip"
                        :class="selectedCompetitionCode === '' ? 'active' : ''"
                        @click="selectedCompetitionCode = ''"
                    >
                        Todos
                    </button>
                    <button
                        v-for="comp in competitionOptions"
                        :key="comp.code"
                        class="comp-filter-chip"
                        :class="selectedCompetitionCode === comp.code ? 'active' : ''"
                        @click="selectedCompetitionCode = comp.code"
                    >
                        {{ comp.name }}
                    </button>
                </div>

                <p class="pwa-section-label">
                    <i class="ti ti-trophy text-bolao-accent"></i>
                    Meus bolões
                    <span v-if="totalPending > 0" class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-bolao-red/15 text-bolao-red text-[9px] font-bold">
                        <i class="ti ti-clock text-[9px]"></i>
                        {{ totalPending }} pendente{{ totalPending > 1 ? 's' : '' }}
                    </span>
                </p>

                <div v-if="filteredMemberPools.length" class="space-y-3">
                    <div v-for="pool in filteredMemberPools" :key="pool.id" class="pool-card-v2">

                        <!-- Cabeçalho: campeonato em destaque -->
                        <button class="pool-card-main" @click="router.push(`/pwa/pools/${pool.id}`)">
                            <div class="pool-card-body">
                                <!-- Campeonato — chamada forte -->
                                <div class="pool-comp-badge">
                                    <i class="ti ti-trophy-filled text-[11px]"></i>
                                    <span>{{ pool.competition?.name ?? '—' }}</span>
                                </div>

                                <!-- Nome do bolão -->
                                <p class="pool-name">{{ pool.name }}</p>

                                <!-- Badges de status -->
                                <div class="pool-badges-row">
                                    <span class="pool-role-badge"
                                          :class="pool.membership?.role === 'owner' ? 'owner' : pool.membership?.role === 'manager' ? 'manager' : ''">
                                        {{ roleLabel(pool.membership?.role) }}
                                    </span>
                                    <span v-if="pool.membership?.status === 'pending'" class="pool-status-badge pending">
                                        <i class="ti ti-clock text-[9px]"></i> Aguardando aprovação
                                    </span>
                                    <span v-if="pool.closed_predictions" class="pool-status-badge locked">
                                        <i class="ti ti-lock text-[9px]"></i> Palpite único
                                    </span>
                                </div>

                                <!-- Pontuação -->
                                <div v-if="pool.scoring" class="pool-scoring-row">
                                    <span class="score-pill exact">
                                        <i class="ti ti-target"></i>
                                        {{ pool.scoring.points_exact_score }}pts exato
                                    </span>
                                    <span class="score-pill result">
                                        <i class="ti ti-check"></i>
                                        {{ pool.scoring.points_correct_result }}pts resultado
                                    </span>
                                    <span v-if="pool.scoring.points_correct_goals > 0" class="score-pill goals">
                                        <i class="ti ti-ball-football"></i>
                                        {{ pool.scoring.points_correct_goals }}pts gols
                                    </span>
                                </div>
                            </div>
                            <i class="ti ti-chevron-right text-bolao-accent/60 text-xl shrink-0 ml-2"></i>
                        </button>

                        <!-- Collapse: descrição + regulamento -->
                        <Transition name="pool-collapse">
                            <div v-if="expandedPoolId === pool.id && (pool.description || pool.instructions)" class="pool-collapse-body">
                                <div v-if="pool.description" class="collapse-block">
                                    <p class="collapse-label"><i class="ti ti-align-left"></i> Descrição</p>
                                    <p class="collapse-text">{{ pool.description }}</p>
                                </div>
                                <div v-if="pool.instructions" class="collapse-block" :class="pool.description ? 'mt-3' : ''">
                                    <p class="collapse-label"><i class="ti ti-clipboard-text"></i> Regulamento</p>
                                    <p class="collapse-text">{{ pool.instructions }}</p>
                                </div>
                            </div>
                        </Transition>

                        <!-- Barra de ações -->
                        <div class="pool-card-actions">
                            <button v-if="pool.description || pool.instructions"
                                    class="pool-action-btn"
                                    :class="expandedPoolId === pool.id ? 'pool-action-active' : ''"
                                    @click.stop="toggleExpand(pool.id)">
                                <i class="ti text-[13px]" :class="expandedPoolId === pool.id ? 'ti-chevron-up' : 'ti-file-description'"></i>
                                <span>{{ expandedPoolId === pool.id ? 'Fechar' : 'Regras' }}</span>
                            </button>
                            <button v-if="pool.invite_code"
                                    class="pool-action-btn"
                                    @click.stop="copyCode(pool)">
                                <i class="ti text-[13px]" :class="copiedId === pool.id ? 'ti-check text-emerald-400' : 'ti-copy'"></i>
                                <span>{{ copiedId === pool.id ? 'Copiado!' : pool.invite_code }}</span>
                            </button>
                            <button v-if="canLeave(pool)"
                                    class="pool-action-btn pool-action-danger"
                                    :disabled="leaving === pool.id"
                                    @click.stop="doLeave(pool)">
                                <i class="ti ti-door-exit text-[13px]"></i>
                                <span>{{ leaving === pool.id ? 'Saindo...' : 'Sair' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="pool-empty">
                    <i class="ti ti-trophy-off text-3xl text-bolao-muted2 mb-2 block"></i>
                    <p class="text-sm text-bolao-muted">Você não participa de nenhum bolão ainda.</p>
                </div>

                <div class="flex items-center justify-between gap-2 mt-2">
                    <p class="pwa-section-label m-0">
                        <i class="ti ti-plus-circle text-bolao-accent"></i>
                        Criar ou entrar
                    </p>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full border border-bolao-accent/30 text-bolao-accent bg-bolao-accent/10">
                        {{ joinedPools }}/{{ maxPools }} bolões
                    </span>
                </div>

                <div v-if="feedback" class="rounded-xl px-3 py-2 text-xs font-semibold" :class="feedbackType === 'ok' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' : 'bg-red-500/10 text-red-300 border border-red-500/30'">
                    {{ feedback }}
                </div>

                <div class="config-card space-y-2">
                    <p class="text-[11px] font-bold text-bolao-muted uppercase tracking-wider">Criar bolão</p>
                    <button class="pwa-btn-primary w-full" :disabled="!canJoinMore" @click="goToCreatePool">
                        Criar bolão
                    </button>
                </div>

                <div class="config-card space-y-2">
                    <p class="text-[11px] font-bold text-bolao-muted uppercase tracking-wider">Entrar com código</p>
                    <p class="text-[11px] text-bolao-muted">Cole o convite de 8 caracteres para entrar rapidamente.</p>
                    <input
                        v-model="joinCode"
                        class="pwa-input uppercase invite-code-input text-center"
                        :class="{ filled: joinCode.trim().length > 0 }"
                        placeholder="Código de convite (8 caracteres)"
                        inputmode="text"
                        maxlength="8"
                        @input="onJoinCodeInput"
                    />
                    <div v-if="lookupLoading" class="invite-lookup-status">
                        <i class="ti ti-loader-2 animate-spin"></i>
                        Buscando bolão...
                    </div>
                    <div v-else-if="lookupError" class="invite-lookup-status invite-lookup-error">
                        <i class="ti ti-alert-circle"></i>
                        {{ lookupError }}
                    </div>
                    <div v-else-if="lookupPool" class="invite-lookup-result">
                        <div class="flex items-center gap-3">
                            <div class="invite-lookup-icon">
                                <i class="ti ti-trophy"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-extrabold text-slate-100">{{ lookupPool.name }}</p>
                                <p class="truncate text-[11px] text-bolao-muted">{{ lookupPool.competition?.name }}</p>
                            </div>
                            <span v-if="lookupPool.membership?.status === 'active'" class="invite-status-pill">ativo</span>
                            <span v-else-if="lookupPool.membership?.status === 'pending'" class="invite-status-pill invite-status-pending">pendente</span>
                        </div>

                        <select
                            v-if="lookupPool.sectors?.length"
                            v-model="selectedInviteSector"
                            class="comp-select mt-2"
                        >
                            <option value="">Selecione um setor</option>
                            <option v-for="sector in lookupPool.sectors" :key="sector" :value="sector">
                                {{ sector }}
                            </option>
                        </select>
                    </div>
                    <button class="pwa-btn-secondary w-full" :disabled="joiningCode || !canJoinLookupPool" @click="doJoinLookupPool">
                        {{ joiningCode ? 'Solicitando...' : joinLookupButtonLabel }}
                    </button>
                </div>
            </div>

            <div v-if="filteredDiscoverablePools.length" class="pwa-section mt-2">
                <p class="pwa-section-label">
                    <i class="ti ti-search text-bolao-muted2"></i>
                    Descobrir
                </p>
                <div class="space-y-2">
                    <div v-for="pool in filteredDiscoverablePools" :key="pool.id" class="pool-card">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/[0.05]">
                            <i class="ti ti-trophy text-bolao-muted text-xl"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-100 truncate">{{ pool.name }}</p>
                            <p class="text-[11px] text-bolao-muted truncate mt-0.5">{{ pool.competition?.name }}</p>
                        </div>
                        <button class="shrink-0 rounded-xl border border-bolao-accent/40 px-3 py-2 text-xs font-bold text-bolao-accent active:bg-bolao-accent/10 transition-colors" :disabled="joining === pool.id || !canJoinMore" @click.stop="doJoin(pool)">
                            {{ joining === pool.id ? '...' : 'Entrar' }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div v-else-if="error" class="pwa-section text-center py-14">
            <i class="ti ti-wifi-off text-4xl text-bolao-muted2 mb-3 block"></i>
            <p class="text-sm text-bolao-muted mb-4">{{ error }}</p>
            <button class="pwa-btn-secondary" @click="load">Tentar novamente</button>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { getPools, joinPool as apiJoinPool, leavePool as apiLeavePool, lookupPoolByCode as apiLookupPoolByCode } from '../api/pools';
import SkeletonCard from '../components/ui/SkeletonCard.vue';

const emit = defineEmits(['set-title']);
const router = useRouter();

const loading = ref(true);
const data = ref(null);
const error = ref('');
const joining = ref(null);
const joiningCode = ref(false);
const leaving = ref(null);
const feedback = ref('');
const feedbackType = ref('ok');
const selectedCompetitionCode = ref('');
const LAST_COMPETITION_CODE_KEY = 'pwa_last_competition_code';

const joinCode = ref('');
const copiedId = ref(null);
const expandedPoolId = ref(null);

function toggleExpand(poolId) {
    expandedPoolId.value = expandedPoolId.value === poolId ? null : poolId;
}
const lookupPool = ref(null);
const lookupLoading = ref(false);
const lookupError = ref('');
const selectedInviteSector = ref('');
let lookupTimer = null;
let lookupRequestId = 0;

const roleLabel = (role) => ({ owner: 'Dono', manager: 'Gestor', member: 'Membro' }[role] ?? role ?? '');

async function copyCode(pool) {
    const code = pool.invite_code;
    if (!code) return;
    try {
        await navigator.clipboard.writeText(code);
    } catch {
        // fallback: select text
    }
    copiedId.value = pool.id;
    setTimeout(() => { copiedId.value = null; }, 2000);
}

const totalPending = computed(() =>
    (data.value?.member_pools ?? []).filter((p) => p.membership?.status === 'pending').length,
);

const limits = computed(() => data.value?.limits ?? { max_pools: 5, joined_pools: 0, can_join_more: true });
const maxPools = computed(() => Number(limits.value.max_pools || 5));
const joinedPools = computed(() => (data.value?.member_pools ?? []).length);
const canJoinMore = computed(() => joinedPools.value < maxPools.value);

const normalizedJoinCode = computed(() => normalizeInviteCode(joinCode.value));
const joinLookupButtonLabel = computed(() => {
    if (lookupPool.value?.membership?.status === 'active') return 'Você já participa';
    if (lookupPool.value?.membership?.status === 'pending') return 'Solicitação em análise';
    return 'Solicitar entrada';
});
const canJoinLookupPool = computed(() =>
    canJoinMore.value
    && lookupPool.value
    && lookupPool.value.can_request_join !== false
    && (!lookupPool.value.sectors?.length || selectedInviteSector.value !== ''),
);

const competitionOptions = computed(() => {
    const fromApi = Array.isArray(data.value?.competitions) ? data.value.competitions : [];
    if (fromApi.length) return fromApi;
    return [{ code: 'WC', name: 'FIFA World Cup' }];
});

const filteredMemberPools = computed(() =>
    (data.value?.member_pools ?? []).filter((p) =>
        !selectedCompetitionCode.value || p.competition?.code === selectedCompetitionCode.value,
    ),
);

const filteredDiscoverablePools = computed(() =>
    (data.value?.discoverable_pools ?? []).filter((p) =>
        !selectedCompetitionCode.value || p.competition?.code === selectedCompetitionCode.value,
    ),
);

function setFeedback(message, type = 'ok') {
    feedback.value = message;
    feedbackType.value = type;
}

function normalizeInviteCode(value) {
    return String(value || '').replace(/[^a-z0-9]/gi, '').toUpperCase().slice(0, 8);
}

function onJoinCodeInput() {
    const normalized = normalizeInviteCode(joinCode.value);
    if (joinCode.value !== normalized) {
        joinCode.value = normalized;
    }
}

function clearInviteLookup() {
    lookupPool.value = null;
    lookupError.value = '';
    lookupLoading.value = false;
    selectedInviteSector.value = '';
}

function canLeave(pool) {
    return ['manager', 'member'].includes(pool?.membership?.role) || pool?.membership?.status === 'pending';
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await getPools();
        data.value = res.data.data;
        // Restaura filtro persistido, mas mantém "Todos" (vazio) como fallback
        if (selectedCompetitionCode.value === '') {
            const persisted = String(localStorage.getItem(LAST_COMPETITION_CODE_KEY) || '').toUpperCase();
            if (persisted && competitionOptions.value.some((c) => c.code === persisted)) {
                selectedCompetitionCode.value = persisted;
            }
            // sem persisted → fica '' = "Todos"
        }
    } catch {
        error.value = 'Não foi possível carregar os bolões.';
    } finally {
        loading.value = false;
    }
}

watch(selectedCompetitionCode, (code) => {
    localStorage.setItem(LAST_COMPETITION_CODE_KEY, String(code || '').toUpperCase());
});

watch(normalizedJoinCode, (code) => {
    if (lookupTimer) {
        clearTimeout(lookupTimer);
        lookupTimer = null;
    }

    lookupRequestId += 1;
    const requestId = lookupRequestId;

    if (code.length < 8) {
        clearInviteLookup();
        return;
    }

    lookupLoading.value = true;
    lookupPool.value = null;
    lookupError.value = '';
    selectedInviteSector.value = '';

    lookupTimer = setTimeout(async () => {
        try {
            const res = await apiLookupPoolByCode(code);
            if (requestId !== lookupRequestId) return;
            lookupPool.value = res?.data?.data?.pool ?? null;
            lookupError.value = lookupPool.value ? '' : 'Bolão não encontrado.';
        } catch (err) {
            if (requestId !== lookupRequestId) return;
            lookupError.value = err?.response?.data?.error?.message ?? 'Código inválido ou inexistente.';
            lookupPool.value = null;
        } finally {
            if (requestId === lookupRequestId) {
                lookupLoading.value = false;
            }
        }
    }, 300);
});

function goToCreatePool() {
    const code = selectedCompetitionCode.value || competitionOptions.value[0]?.code || 'WC';
    router.push(`/pwa/pools/create?competition=${encodeURIComponent(code)}`);
}

async function doJoin(pool) {
    joining.value = pool.id;
    try {
        const res = await apiJoinPool(pool.id);
        setFeedback(res?.data?.message ?? 'Solicitação enviada com sucesso.');
        await load();
    } catch (err) {
        setFeedback(err?.response?.data?.message ?? 'Não foi possível entrar no bolão.', 'err');
        await load();
    } finally {
        joining.value = null;
    }
}

async function doJoinLookupPool() {
    if (!lookupPool.value) return;

    joiningCode.value = true;
    try {
        const res = await apiJoinPool(lookupPool.value.id, selectedInviteSector.value || null);
        joinCode.value = '';
        clearInviteLookup();
        setFeedback(res?.data?.message ?? 'Solicitação enviada com sucesso.');
        await load();
    } catch (err) {
        setFeedback(err?.response?.data?.message ?? err?.response?.data?.error?.message ?? 'Não foi possível solicitar entrada.', 'err');
        if (normalizedJoinCode.value.length === 8) {
            lookupRequestId += 1;
            clearInviteLookup();
        }
    } finally {
        joiningCode.value = false;
    }
}

async function doLeave(pool) {
    leaving.value = pool.id;
    try {
        const res = await apiLeavePool(pool.id);
        setFeedback(res?.data?.message ?? 'Você saiu do bolão.');
        await load();
    } catch (err) {
        setFeedback(err?.response?.data?.message ?? 'Não foi possível sair do bolão.', 'err');
    } finally {
        leaving.value = null;
    }
}

onMounted(() => {
    emit('set-title', 'Bolões');
    load();
});

onBeforeUnmount(() => {
    if (lookupTimer) clearTimeout(lookupTimer);
});
</script>

<style scoped>
/* ── Filtro de competição ─────────────────────────────── */
.comp-filter-row {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.comp-filter-chip {
    padding: 6px 14px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.04);
    color: #8fa4c0;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
    -webkit-tap-highlight-color: transparent;
    white-space: nowrap;
}
.comp-filter-chip.active {
    border-color: rgba(245,166,35,0.55);
    background: rgba(245,166,35,0.14);
    color: #ffd084;
}
.comp-filter-chip:active { opacity: 0.7; }

/* ── Card do bolão ───────────────────────────────────── */
.pool-card-v2 {
    border-radius: 18px;
    border: 1px solid rgba(255,255,255,0.09);
    background: linear-gradient(160deg, #141920 0%, #0f1319 100%);
    overflow: hidden;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.pool-card-v2:active { border-color: rgba(245,166,35,0.3); box-shadow: 0 0 0 1px rgba(245,166,35,0.15); }

.pool-card-main {
    display: flex;
    align-items: center;
    width: 100%;
    text-align: left;
    padding: 0;
    background: none;
    border: none;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    transition: background 0.12s;
}
.pool-card-main:active { background: rgba(255,255,255,0.025); }

.pool-card-body {
    flex: 1;
    min-width: 0;
    padding: 14px 0 14px 16px;
}

/* Campeonato — chamada forte */
.pool-comp-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(245,166,35,0.13);
    border: 1px solid rgba(245,166,35,0.3);
    border-radius: 999px;
    padding: 3px 10px 3px 8px;
    font-size: 11px;
    font-weight: 800;
    color: #f5a623;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    margin-bottom: 8px;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Nome do bolão */
.pool-name {
    font-size: 19px;
    font-weight: 800;
    color: #ffffff;
    line-height: 1.15;
    letter-spacing: -0.01em;
    margin-bottom: 8px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Badges de role e status */
.pool-badges-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.pool-role-badge {
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 3px 9px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.14);
    color: #94a3b8;
    background: rgba(255,255,255,0.05);
}
.pool-role-badge.owner {
    border-color: rgba(245,166,35,0.4);
    color: #f5a623;
    background: rgba(245,166,35,0.1);
}
.pool-role-badge.manager {
    border-color: rgba(99,179,237,0.4);
    color: #90cdf4;
    background: rgba(99,179,237,0.08);
}
.pool-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
}
.pool-status-badge.pending {
    border: 1px solid rgba(251,191,36,0.35);
    color: #fbbf24;
    background: rgba(251,191,36,0.1);
}
.pool-status-badge.locked {
    border: 1px solid rgba(252,165,165,0.3);
    color: #fca5a5;
    background: rgba(252,165,165,0.08);
}

/* Pontuação */
.pool-scoring-row {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}
.score-pill {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.03);
    color: #6b82a4;
}
.score-pill.exact { border-color: rgba(245,166,35,0.2); color: #c8901e; }
.score-pill.result { border-color: rgba(99,179,237,0.2); color: #5a8fab; }
.score-pill.goals { border-color: rgba(110,231,183,0.2); color: #4a9970; }

/* Collapse */
.pool-collapse-body {
    padding: 12px 16px 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
    background: rgba(255,255,255,0.015);
}
.collapse-block {}
.collapse-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #6b82a4;
    margin-bottom: 5px;
}
.collapse-text {
    font-size: 13px;
    color: #b0c4de;
    line-height: 1.65;
    white-space: pre-wrap;
}

/* Transition collapse */
.pool-collapse-enter-active,
.pool-collapse-leave-active { transition: all .2s ease; overflow: hidden; }
.pool-collapse-enter-from,
.pool-collapse-leave-to { opacity: 0; max-height: 0; }
.pool-collapse-enter-to,
.pool-collapse-leave-from { opacity: 1; max-height: 600px; }

/* Barra de ações */
.pool-card-actions {
    display: flex;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.pool-action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 11px 8px;
    font-size: 11px;
    font-weight: 700;
    color: #6b82a4;
    background: none;
    border: none;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
    -webkit-tap-highlight-color: transparent;
}
.pool-action-btn:active { background: rgba(255,255,255,0.04); color: #e2e8f0; }
.pool-action-btn + .pool-action-btn { border-left: 1px solid rgba(255,255,255,0.06); }
.pool-action-active { color: #f5a623 !important; }
.pool-action-danger { color: #f87171; }
.pool-action-danger:active { color: #fca5a5; }

/* Empty state */
.pool-empty {
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.06);
    background: #13161b;
    padding: 28px 16px;
    text-align: center;
}

/* Seção criar/entrar */
.config-card {
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.08);
    background: #131a2a;
    padding: 12px;
}

.invite-code-input.filled {
    color: #f5a623;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-align: center;
}

.invite-lookup-status {
    display: flex;
    align-items: center;
    gap: 8px;
    border-radius: 10px;
    border: 1px solid rgba(245,166,35,0.2);
    background: rgba(245,166,35,0.08);
    color: #fbbf24;
    font-size: 12px;
    font-weight: 700;
    padding: 9px 10px;
}
.invite-lookup-error {
    border-color: rgba(248,113,113,0.28);
    background: rgba(248,113,113,0.08);
    color: #fca5a5;
}
.invite-lookup-result {
    border-radius: 10px;
    border: 1px solid rgba(34,197,94,0.25);
    background: rgba(34,197,94,0.08);
    padding: 10px;
}
.invite-lookup-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(34,197,94,0.14);
    color: #86efac;
    flex-shrink: 0;
}
.invite-status-pill {
    border-radius: 999px;
    background: rgba(34,197,94,0.15);
    color: #86efac;
    font-size: 10px;
    font-weight: 800;
    padding: 4px 7px;
}
.invite-status-pending {
    background: rgba(251,191,36,0.15);
    color: #fcd34d;
}

/* Discoverable pools */
.pool-card {
    display: flex;
    align-items: center;
    gap: 12px;
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.07);
    background: #131922;
    padding: 12px;
}

/* Comp select (lookup) */
.comp-select {
    width: 100%;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.1);
    background: #151a24;
    color: #f8fafc;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 12px;
    appearance: none;
    -webkit-appearance: none;
}
</style>
