<template>
    <div class="pwa-page">

        <template v-if="loading">
            <div class="pwa-section space-y-3 pt-4">
                <SkeletonCard v-for="i in 4" :key="i" />
            </div>
        </template>

        <template v-else-if="data">
            <div class="pwa-section pt-4 space-y-3">
                <div v-if="competitionOptions.length > 1" class="dash-comp-picker">
                    <label class="comp-select-label">Competição</label>
                    <div class="dash-comp-select-wrap">
                        <i class="ti ti-trophy dash-comp-icon"></i>
                        <select v-model="selectedCompetitionCode" class="comp-select dash-comp-select">
                            <option v-for="comp in competitionOptions" :key="comp.code" :value="comp.code">
                                {{ comp.name }}
                            </option>
                        </select>
                        <i class="ti ti-chevron-down dash-comp-arrow"></i>
                    </div>
                </div>

                <p class="pwa-section-label">
                    <i class="ti ti-trophy text-bolao-accent"></i>
                    Meus bolões
                    <span v-if="totalPending > 0" class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-bolao-red/15 text-bolao-red text-[9px] font-bold">
                        <i class="ti ti-edit text-[9px]"></i>
                        {{ totalPending }} pend.
                    </span>
                </p>

                <div v-if="filteredMemberPools.length" class="space-y-3">
                    <template v-for="group in memberPoolsByComp" :key="group.compName">
                        <p v-if="memberPoolsByComp.length > 1" class="text-[10px] font-bold text-bolao-muted2 uppercase tracking-widest mt-3 mb-1">{{ group.compName }}</p>

                        <div v-for="pool in group.pools" :key="pool.id" class="pool-card-v2">

                            <!-- Main clickable area -->
                            <button class="pool-card-main" @click="router.push(`/pwa/pools/${pool.id}`)">
                                <div class="pool-card-icon">
                                    <i class="ti ti-trophy text-bolao-accent text-2xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-base font-extrabold text-slate-100 truncate leading-tight">{{ pool.name }}</p>
                                    <p class="text-[12px] text-bolao-muted truncate mt-0.5">{{ pool.competition?.name }}</p>
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                              :class="pool.membership?.role === 'owner' ? 'bg-bolao-accent/15 text-bolao-accent' : 'bg-white/[0.07] text-bolao-muted2'">
                                            {{ roleLabel(pool.membership?.role) }}
                                        </span>
                                        <span v-if="pool.membership?.status === 'pending'" class="text-[10px] font-bold text-amber-400 flex items-center gap-0.5">
                                            <i class="ti ti-clock text-[10px]"></i> pendente
                                        </span>
                                    </div>
                                </div>
                                <i class="ti ti-chevron-right text-bolao-accent/70 text-lg shrink-0"></i>
                            </button>

                            <!-- Actions row inside the card -->
                            <div class="pool-card-actions">
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
                    </template>
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

const memberPoolsByComp = computed(() => {
    if (!filteredMemberPools.value.length) return [];
    const map = new Map();
    for (const pool of filteredMemberPools.value) {
        const compName = pool.competition?.name ?? 'Sem competição';
        if (!map.has(compName)) map.set(compName, []);
        map.get(compName).push(pool);
    }
    return Array.from(map.entries()).map(([compName, pools]) => ({ compName, pools }));
});

const totalPending = computed(() =>
    (data.value?.member_pools ?? []).filter((p) => p.membership?.status === 'pending').length,
);

const limits = computed(() => data.value?.limits ?? { max_pools: 5, joined_pools: 0, can_join_more: true });
const maxPools = computed(() => Number(limits.value.max_pools || 5));
const joinedPools = computed(() =>
    (data.value?.member_pools ?? []).filter((p) =>
        !selectedCompetitionCode.value || p.competition?.code === selectedCompetitionCode.value,
    ).length,
);
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
        const persistedCode = String(localStorage.getItem(LAST_COMPETITION_CODE_KEY) || '').toUpperCase();
        const hasCurrent = competitionOptions.value.some((c) => c.code === selectedCompetitionCode.value);
        const hasPersisted = competitionOptions.value.some((c) => c.code === persistedCode);
        if (hasCurrent) return;
        if (hasPersisted) {
            selectedCompetitionCode.value = persistedCode;
            return;
        }
        selectedCompetitionCode.value = competitionOptions.value[0]?.code ?? 'WC';
    } catch {
        error.value = 'Não foi possível carregar os bolões.';
    } finally {
        loading.value = false;
    }
}

watch(selectedCompetitionCode, (code) => {
    const normalized = String(code || '').toUpperCase();
    if (!normalized) return;
    localStorage.setItem(LAST_COMPETITION_CODE_KEY, normalized);
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
/* ── Pool card v2 ─────────────────────────────────────── */
.pool-card-v2 {
    border-radius: 16px;
    border: 1px solid rgba(245,166,35,0.18);
    background: #13161b;
    overflow: hidden;
    transition: border-color 0.15s;
}
.pool-card-v2:active { border-color: rgba(245,166,35,0.35); }

.pool-card-main {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    text-align: left;
    padding: 16px 14px 14px;
    background: none;
    border: none;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    transition: background 0.12s;
}
.pool-card-main:active { background: rgba(255,255,255,0.04); }

.pool-card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: rgba(245,166,35,0.12);
    flex-shrink: 0;
}

.pool-card-actions {
    display: flex;
    border-top: 1px solid rgba(255,255,255,0.06);
}

.pool-action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 8px;
    font-size: 12px;
    font-weight: 700;
    color: #94a3b8;
    background: none;
    border: none;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
    -webkit-tap-highlight-color: transparent;
}
.pool-action-btn:active { background: rgba(255,255,255,0.04); color: #f1f5f9; }
.pool-action-btn + .pool-action-btn { border-left: 1px solid rgba(255,255,255,0.06); }

.pool-action-danger { color: #f87171; }
.pool-action-danger:active { color: #fca5a5; }

.pool-empty {
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.06);
    background: #13161b;
    padding: 24px 16px;
    text-align: center;
}

.config-card {
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.08);
    background: #131a2a;
    padding: 10px;
}

.leave-btn {
    width: 100%;
    border-radius: 10px;
    border: 1px solid rgba(239, 68, 68, 0.35);
    background: rgba(239, 68, 68, 0.08);
    color: #fca5a5;
    font-size: 11px;
    font-weight: 700;
    padding: 7px 10px;
}

.leave-btn:disabled { opacity: 0.6; }

.invite-code-input.filled {
    color: #f5a623;
    font-weight: 800;
    letter-spacing: 0.03em;
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

.dash-comp-picker { border-radius: 12px; border: 1px solid rgba(255,255,255,0.08); background: #121722; padding: 10px; }
.comp-select-label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #8b9bb4; margin-bottom: 6px; font-weight: 700; }
.dash-comp-select-wrap { position: relative; display: flex; align-items: center; }
.dash-comp-icon { position: absolute; left: 10px; color: #f5a623; font-size: 14px; pointer-events: none; }
.dash-comp-arrow { position: absolute; right: 10px; color: #7a8394; font-size: 13px; pointer-events: none; }
.comp-select { width: 100%; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); background: #151a24; color: #f8fafc; font-size: 13px; font-weight: 700; padding: 8px 30px 8px 30px; appearance: none; -webkit-appearance: none; }
</style>
