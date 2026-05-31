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

                <div v-if="filteredMemberPools.length" class="space-y-2">
                    <template v-for="group in memberPoolsByComp" :key="group.compName">
                        <p v-if="memberPoolsByComp.length > 1" class="text-[10px] font-bold text-bolao-muted2 uppercase tracking-widest mt-3 mb-1">{{ group.compName }}</p>
                        <div v-for="pool in group.pools" :key="pool.id" class="pool-card-wrap">
                            <button class="pool-card w-full text-left" @click="router.push(`/pwa/pools/${pool.id}`)">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-bolao-accent/10">
                                    <i class="ti ti-trophy text-bolao-accent text-xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-slate-100 truncate">{{ pool.name }}</p>
                                    <p class="text-[11px] text-bolao-muted truncate mt-0.5">{{ pool.competition?.name }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-1.5 shrink-0">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="pool.membership?.role === 'owner' ? 'bg-bolao-accent/15 text-bolao-accent' : 'bg-white/[0.07] text-bolao-muted2'">
                                        {{ roleLabel(pool.membership?.role) }}
                                    </span>
                                    <span v-if="pool.membership?.status === 'pending'" class="text-[10px] font-bold text-amber-400 flex items-center gap-0.5">
                                        <i class="ti ti-clock text-[10px]"></i> pendente
                                    </span>
                                </div>
                                <i class="ti ti-chevron-right text-bolao-muted2 text-sm shrink-0"></i>
                            </button>
                            <button
                                v-if="canLeave(pool)"
                                class="leave-btn"
                                :disabled="leaving === pool.id"
                                @click="doLeave(pool)"
                            >
                                {{ leaving === pool.id ? 'Saindo...' : 'Sair do bolão' }}
                            </button>
                        </div>
                    </template>
                </div>

                <div v-else class="pool-empty">
                    <i class="ti ti-trophy-off text-3xl text-bolao-muted2 mb-2 block"></i>
                    <p class="text-sm text-bolao-muted">Você não participa de nenhum bolão ainda.</p>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <p class="pwa-section-label m-0">
                        <i class="ti ti-settings text-bolao-accent"></i>
                        Configurações
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
                        maxlength="8"
                    />
                    <button class="pwa-btn-secondary w-full" :disabled="joiningCode || !canJoinByCode" @click="doJoinByCode">
                        {{ joiningCode ? 'Entrando...' : 'Entrar no bolão' }}
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
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { getPools, joinPool as apiJoinPool, joinPoolByCode as apiJoinPoolByCode, leavePool as apiLeavePool } from '../api/pools';
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

const roleLabel = (role) => ({ owner: 'Dono', manager: 'Gestor', member: 'Membro' }[role] ?? role ?? '');

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

const canJoinByCode = computed(() => canJoinMore.value && joinCode.value.trim().length === 8);

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

async function doJoinByCode() {
    joiningCode.value = true;
    try {
        const res = await apiJoinPoolByCode(joinCode.value.trim().toUpperCase());
        joinCode.value = '';
        setFeedback(res?.data?.message ?? 'Solicitação enviada com sucesso.');
        await load();
    } catch (err) {
        setFeedback(err?.response?.data?.message ?? 'Código inválido ou indisponível.', 'err');
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
</script>

<style scoped>
.pool-card-wrap { display: grid; gap: 6px; }

.pool-card {
    display: flex;
    align-items: center;
    gap: 12px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.07);
    background: #13161b;
    padding: 12px;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    -webkit-tap-highlight-color: transparent;
}
.pool-card:active { background: #1c2029; border-color: rgba(255,255,255,0.12); }

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

.dash-comp-picker { border-radius: 12px; border: 1px solid rgba(255,255,255,0.08); background: #121722; padding: 10px; }
.comp-select-label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #8b9bb4; margin-bottom: 6px; font-weight: 700; }
.dash-comp-select-wrap { position: relative; display: flex; align-items: center; }
.dash-comp-icon { position: absolute; left: 10px; color: #f5a623; font-size: 14px; pointer-events: none; }
.dash-comp-arrow { position: absolute; right: 10px; color: #7a8394; font-size: 13px; pointer-events: none; }
.comp-select { width: 100%; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); background: #151a24; color: #f8fafc; font-size: 13px; font-weight: 700; padding: 8px 30px 8px 30px; appearance: none; -webkit-appearance: none; }
</style>
