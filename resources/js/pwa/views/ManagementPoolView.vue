<template>
    <div class="pwa-page">
        <div v-if="loading" class="pwa-section space-y-3 pt-4">
            <div v-for="i in 5" :key="i" class="pwa-skeleton h-20 w-full"></div>
        </div>

        <div v-else-if="pool" class="pwa-section pt-4 pb-6 space-y-3">
            <section class="hero-card">
                <p class="hero-comp">{{ pool.competition?.name }}</p>
                <h1 class="hero-title">{{ pool.name }}</h1>
            </section>

            <div class="segmented">
                <button v-for="tab in tabs" :key="tab.id" class="seg-item" :class="{ active: activeTab === tab.id }" @click="setTab(tab.id)">
                    {{ tab.label }}
                </button>
            </div>

            <Transition name="fade-slide" mode="out-in">
                <section v-if="activeTab === 'users'" key="users" class="card-shell space-y-3">
                    <div class="invite-panel">
                        <div class="section-head">
                            <h2><i class="ti ti-user-plus text-bolao-accent"></i> Convite rápido</h2>
                        </div>
                        <div class="grid gap-2">
                            <input v-model="inviteEmail" type="email" class="field" placeholder="E-mail do participante" />
                            <input v-model="inviteSector" type="text" class="field" placeholder="Setor / Departamento (opcional)" />
                            <button class="action primary" :disabled="inviteLoading" @click="sendInvite">{{ inviteLoading ? 'Enviando...' : 'Enviar Convite' }}</button>
                        </div>
                    </div>

                    <div class="users-head">
                        <h2>Membros ativos ({{ activeMembers.length }})</h2>
                        <span class="users-meta">{{ pendingMembers.length ? `${pendingMembers.length} pendências` : 'Sem pendências' }}</span>
                    </div>

                    <div class="members-list-card">
                        <article v-if="ownerMember" class="member-row-v2 owner">
                            <div class="member-avatar">{{ initials(ownerMember) }}</div>
                            <div class="member-main">
                                <p class="member-name">
                                    {{ ownerMember.user?.name }}
                                    <span class="role-chip owner">Dono</span>
                                </p>
                                <p class="member-sub">{{ ownerMember.user?.email }}</p>
                            </div>
                        </article>

                        <article v-for="member in nonOwnerActiveMembers" :key="member.id" class="member-row-v2">
                            <div class="member-avatar alt">{{ initials(member) }}</div>
                            <div class="member-main">
                                <p class="member-name">
                                    {{ member.user?.name }}
                                    <span class="role-chip">{{ member.role === 'manager' ? 'Gestor' : 'Membro' }}</span>
                                </p>
                                <p class="member-sub">{{ member.user?.email }}</p>
                                <p v-if="member.sector" class="member-sector">Setor: {{ member.sector }}</p>
                            </div>
                            <div class="relative">
                                <button class="menu-trigger" @click="toggleMemberMenu(member.id)">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div v-if="memberMenuId === member.id" class="member-menu">
                                    <button class="member-menu-item" @click="toggleRole(member)">
                                        <i class="ti ti-shield-check text-emerald-400"></i>
                                        {{ member.role === 'manager' ? 'Virar Membro' : 'Virar Gestor' }}
                                    </button>
                                    <button class="member-menu-item danger" @click="remove(member)">
                                        <i class="ti ti-user-minus text-rose-400"></i>
                                        Remover
                                    </button>
                                </div>
                            </div>
                        </article>

                        <div v-if="pendingMembers.length" class="pending-strip">
                            <span>{{ pendingMembers.length }} pendente(s)</span>
                            <button class="action small" @click="approveAllPending">Aprovar todos</button>
                        </div>
                    </div>
                </section>

                <section v-else-if="activeTab === 'config'" key="config" class="card-shell space-y-3">
                    <div class="section-head"><h2>Configuração</h2></div>
                    <input v-model="configForm.name" class="field" placeholder="Nome do bolão" />
                    <textarea v-model="configForm.description" class="field min-h-[80px]" placeholder="Descrição"></textarea>
                    <textarea v-model="configForm.instructions" class="field min-h-[80px]" placeholder="Regulamento"></textarea>
                    <div class="grid grid-cols-2 gap-2">
                        <select v-model="configForm.visibility" class="field">
                            <option value="private">Privado</option>
                            <option value="invite_only">Somente convite</option>
                            <option value="public">Público</option>
                        </select>
                        <select v-model="configForm.status" class="field">
                            <option value="active">Ativo</option>
                            <option value="blocked">Bloqueado</option>
                            <option value="archived">Arquivado</option>
                        </select>
                    </div>

                    <label class="flex items-start gap-3 rounded-xl border border-white/15 bg-[#161c28] px-3 py-3 cursor-pointer">
                        <input v-model="configForm.allow_prediction_changes" type="checkbox" class="mt-1 h-5 w-5 shrink-0 rounded border-white/30 bg-[#0f131b] accent-[#f5a623]">
                        <span class="min-w-0">
                            <span class="block text-[16px] leading-tight font-semibold text-slate-100">Permitir edição de palpite</span>
                            <span class="mt-1 block text-[14px] leading-snug text-slate-400">Participante pode alterar o palpite até o bloqueio.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-white/15 bg-[#161c28] px-3 py-3 cursor-pointer">
                        <input v-model="configForm.closed_predictions" type="checkbox" class="mt-1 h-5 w-5 shrink-0 rounded border-white/30 bg-[#0f131b] accent-[#f5a623]">
                        <span class="min-w-0">
                            <span class="block text-[16px] leading-tight font-semibold text-slate-100">Palpite fechado</span>
                            <span class="mt-1 block text-[14px] leading-snug text-slate-400">Após o início da 1ª partida do bolão, bloqueia novos envios e alterações.</span>
                        </span>
                    </label>

                    <div v-if="configForm.allow_prediction_changes" class="space-y-1">
                        <label class="text-[10px] text-bolao-muted2 uppercase tracking-wide">Minutos antes da partida para bloquear edição</label>
                        <input v-model.number="configForm.prediction_lock_minutes" type="number" min="10" class="field" />
                    </div>

                    <div class="rounded-lg border border-white/10 bg-white/[0.03] p-2 space-y-2">
                        <p class="text-[11px] font-semibold text-slate-200">Setores / Departamentos</p>
                        <p class="text-[11px] text-bolao-muted">Use setores para organizar participantes no bolão.</p>

                        <div class="flex gap-2">
                            <input
                                v-model="newSector"
                                type="text"
                                class="field"
                                maxlength="80"
                                placeholder="Nome do setor..."
                                @keydown.enter.prevent="addSector"
                            >
                            <button type="button" class="action small" @click="addSector">Adicionar</button>
                        </div>

                        <div v-if="!configForm.sectors.length" class="text-[11px] text-bolao-muted border border-white/10 rounded-lg p-2">
                            Nenhum setor configurado.
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <div
                                v-for="(sector, index) in configForm.sectors"
                                :key="`${sector}-${index}`"
                                class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1"
                            >
                                <span class="text-[12px] text-slate-100">{{ sector }}</span>
                                <button type="button" class="text-bolao-red text-[12px] leading-none" @click="removeSector(index)">✕</button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-white/10 bg-white/[0.03] p-2 space-y-2">
                        <p class="text-[11px] font-semibold text-slate-200">Pontuação por acerto</p>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="space-y-1">
                                <label class="text-[10px] text-bolao-muted2 uppercase tracking-wide">Placar exato</label>
                                <input v-model.number="configForm.points_exact_score" type="number" min="0" max="20" class="field" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] text-bolao-muted2 uppercase tracking-wide">Resultado</label>
                                <input v-model.number="configForm.points_correct_result" type="number" min="0" max="20" class="field" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] text-bolao-muted2 uppercase tracking-wide">Gols time</label>
                                <input v-model.number="configForm.points_correct_goals" type="number" min="0" max="20" class="field" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-white/10 bg-white/[0.03] p-2 space-y-2">
                        <p class="text-[11px] font-semibold text-slate-200">Critérios de desempate</p>
                        <p class="text-[11px] text-bolao-muted">Segure um critério por ~0,35s para arrastar e reordenar.</p>
                        <div class="flex gap-2">
                            <select v-model="newTieBreaker" class="field">
                                <option value="">Selecionar critério...</option>
                                <option v-for="opt in availableTieBreakers" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                            <button type="button" class="action small" @click="addTieBreaker">Adicionar</button>
                        </div>

                        <div v-if="!configForm.tie_breakers.length" class="text-[11px] text-bolao-muted border border-white/10 rounded-lg p-2">
                            Sem desempate configurado.
                        </div>
                        <TransitionGroup v-else name="tie-move" tag="div" class="space-y-2">
                            <div
                                v-for="(criterion, index) in configForm.tie_breakers"
                                :key="criterion"
                                class="tie-row flex items-center justify-between gap-2 rounded-lg border border-white/10 bg-white/[0.02] px-2 py-2"
                                :class="{
                                    dragging: draggingTieCriterion === criterion,
                                    'drop-target': dragOverTieIndex === index && draggingTieCriterion !== criterion,
                                }"
                                :data-tie-index="index"
                                @pointerdown="onTiePointerDown($event, criterion, index)"
                                @pointermove="onTiePointerMove($event)"
                                @pointerup="onTiePointerUp($event)"
                                @pointercancel="onTiePointerUp($event)"
                            >
                                <span class="text-[12px] text-slate-100 truncate">{{ tieBreakerLabels[criterion] ?? criterion }}</span>
                                <div class="flex items-center gap-1">
                                    <span class="drag-pill">Segure e arraste</span>
                                    <button type="button" class="action small danger" @click="removeTieBreakerAt(index)">Remover</button>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>

                    <button class="action primary" :disabled="savingConfig" @click="saveConfig">{{ savingConfig ? 'Salvando...' : 'Salvar alterações' }}</button>
                </section>

                <section v-else key="summary" class="card-shell space-y-3">
                    <div class="section-head"><h2>Resumo estatístico</h2></div>
                    <div class="summary-grid">
                        <div class="summary-box"><span>Pontos distribuídos</span><b>{{ pool.stats?.points_distributed ?? 0 }}</b></div>
                        <div class="summary-box"><span>Palpites computados</span><b>{{ pool.stats?.predictions_counted ?? 0 }}</b></div>
                        <div class="summary-box"><span>Líder</span><b>{{ pool.stats?.leader_name || '—' }}</b></div>
                        <div class="summary-box"><span>Pontos do líder</span><b>{{ pool.stats?.leader_points ?? 0 }}</b></div>
                    </div>
                    <button class="action danger" :disabled="pool.status === 'archived' || finalizing" @click="finalizePoolNow">
                        {{ finalizing ? 'Finalizando...' : 'Finalizar bolão' }}
                    </button>
                </section>
            </Transition>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import { getPool, updatePool, finalizePool } from '../api/pools';
import { getPoolMembers, updatePoolMember, removePoolMember, inviteToPool } from '../api/pool-members';
import client from '../api/client';

const emit = defineEmits(['set-title']);
const route = useRoute();

const poolId = computed(() => route.params.id);
const loading = ref(true);
const pool = ref(null);
const members = ref([]);
const activeTab = ref(['users', 'config', 'summary'].includes(String(route.query.tab || '')) ? String(route.query.tab) : 'users');
const finalizing = ref(false);
const savingConfig = ref(false);
const inviteLoading = ref(false);
const inviteEmail = ref('');
const inviteSector = ref('');
const memberMenuId = ref(null);
const newTieBreaker = ref('');
const newSector = ref('');
const draggingTieCriterion = ref(null);
const draggingTieIndex = ref(null);
const dragOverTieIndex = ref(null);
let tieHoldTimer = null;
let tiePointerId = null;
let tieStartX = 0;
let tieStartY = 0;
let lastTieSwapAt = 0;
let tieDragRaf = null;

const tabs = [
    { id: 'users', label: 'Usuários' },
    { id: 'config', label: 'Configurar' },
    { id: 'summary', label: 'Resumo' },
];

const configForm = ref({
    name: '', description: '', instructions: '', visibility: 'invite_only', status: 'active',
    allow_prediction_changes: true, closed_predictions: false, allow_pending_member_predictions: true,
    prediction_lock_minutes: 10, points_exact_score: 5, points_correct_result: 3, points_correct_goals: 1,
    correct_goals_mode: 'both_teams', sectors: [], tie_breakers: [],
});
const tieBreakerOptions = [
    { value: 'exact_scores', label: 'Mais placares exatos' },
    { value: 'correct_results', label: 'Mais resultados corretos' },
    { value: 'correct_home_goals', label: 'Mais gols do mandante corretos' },
    { value: 'correct_away_goals', label: 'Mais gols do visitante corretos' },
    { value: 'predictions_counted', label: 'Mais palpites válidos' },
];
const tieBreakerLabels = Object.fromEntries(tieBreakerOptions.map((o) => [o.value, o.label]));
const enabledTieBreakerOptions = computed(() => {
    const enabled = [];
    if (Number(configForm.value.points_exact_score) > 0) enabled.push('exact_scores');
    if (Number(configForm.value.points_correct_result) > 0) enabled.push('correct_results');
    if (Number(configForm.value.points_correct_goals) > 0) {
        enabled.push('correct_home_goals');
        enabled.push('correct_away_goals');
    }
    enabled.push('predictions_counted');
    return tieBreakerOptions.filter((o) => enabled.includes(o.value));
});
const availableTieBreakers = computed(() =>
    enabledTieBreakerOptions.value.filter((o) => !configForm.value.tie_breakers.includes(o.value)),
);

const pendingMembers = computed(() => members.value.filter((m) => m.status === 'pending'));
const activeMembers = computed(() => members.value.filter((m) => m.status === 'active'));
const ownerMember = computed(() => activeMembers.value.find((m) => m.role === 'owner') ?? null);
const nonOwnerActiveMembers = computed(() => activeMembers.value.filter((m) => m.role !== 'owner'));
const roleLabel = (r) => ({ owner: 'Dono', manager: 'Gestor', member: 'Membro' }[r] ?? r ?? '');

onMounted(async () => {
    emit('set-title', 'Gestão detalhada');
    await load();
});

async function load() {
    loading.value = true;
    try {
        const [{ data: poolRes }, { data: membersRes }, { data: managedRes }] = await Promise.all([
            getPool(poolId.value),
            getPoolMembers(poolId.value),
            client.get('/pools?managed=1'),
        ]);

        const base = poolRes?.data ?? {};
        const managed = (managedRes?.data?.items ?? []).find((item) => Number(item.id) === Number(poolId.value));
        pool.value = { ...base, stats: managed?.stats ?? null, invite_code: managed?.invite_code ?? base.invite_code };
        members.value = membersRes?.data?.items ?? [];
        configForm.value = {
            name: base?.name ?? '',
            description: base?.description ?? '',
            instructions: base?.instructions ?? '',
            visibility: base?.visibility ?? 'invite_only',
            status: base?.status ?? 'active',
            allow_prediction_changes: !!base?.allow_prediction_changes,
            closed_predictions: !!base?.closed_predictions,
            allow_pending_member_predictions: !!base?.allow_pending_member_predictions,
            prediction_lock_minutes: Number(base?.prediction_lock_minutes ?? 10),
            points_exact_score: Number(base?.scoring?.points_exact_score ?? 5),
            points_correct_result: Number(base?.scoring?.points_correct_result ?? 3),
            points_correct_goals: Number(base?.scoring?.points_correct_goals ?? 1),
            correct_goals_mode: base?.scoring?.correct_goals_mode ?? 'both_teams',
            sectors: Array.isArray(base?.sectors) ? base.sectors : [],
            tie_breakers: Array.isArray(base?.tie_breakers) ? base.tie_breakers : [],
        };

    } finally {
        loading.value = false;
    }
}

function setTab(tab) {
    activeTab.value = tab;
    const url = `/pwa/management/pools/${poolId.value}?tab=${encodeURIComponent(tab)}`;
    window.history.replaceState(window.history.state, '', url);
}

function copyInviteCode(code) {
    navigator.clipboard?.writeText(String(code || '').trim());
}

function initials(member) {
    const base = String(member?.user?.name || '').trim();
    if (!base) return '?';
    return base.split(/\s+/).map((w) => w[0]?.toUpperCase()).slice(0, 2).join('');
}

function toggleMemberMenu(memberId) {
    memberMenuId.value = memberMenuId.value === memberId ? null : memberId;
}

async function sendInvite() {
    if (!inviteEmail.value.trim()) return;
    inviteLoading.value = true;
    try {
        await inviteToPool(poolId.value, { email: inviteEmail.value.trim(), sector: inviteSector.value.trim() || null });
        inviteEmail.value = '';
        inviteSector.value = '';
    } finally {
        inviteLoading.value = false;
    }
}

async function updateStatus(member, status) {
    memberMenuId.value = null;
    await updatePoolMember(poolId.value, member.id, { status });
    await load();
}

async function toggleRole(member) {
    memberMenuId.value = null;
    await updatePoolMember(poolId.value, member.id, { role: member.role === 'manager' ? 'member' : 'manager' });
    await load();
}

async function remove(member) {
    memberMenuId.value = null;
    await removePoolMember(poolId.value, member.id);
    await load();
}

async function approveAllPending() {
    for (const member of pendingMembers.value) {
        await updateStatus(member, 'active');
    }
}

function addSector() {
    const sector = String(newSector.value || '').replace(/\s+/g, ' ').trim();
    if (!sector) return;
    const exists = configForm.value.sectors.some((item) => item.toLowerCase() === sector.toLowerCase());
    if (exists) return;
    if (configForm.value.sectors.length >= 30) return;
    configForm.value.sectors = [...configForm.value.sectors, sector];
    newSector.value = '';
}

function removeSector(index) {
    const list = [...configForm.value.sectors];
    if (index < 0 || index >= list.length) return;
    list.splice(index, 1);
    configForm.value.sectors = list;
}

function addTieBreaker() {
    const value = String(newTieBreaker.value || '').trim();
    if (!value) return;
    if (!enabledTieBreakerOptions.value.some((o) => o.value === value)) return;
    if (configForm.value.tie_breakers.includes(value)) return;
    if (configForm.value.tie_breakers.length >= 5) return;
    configForm.value.tie_breakers = [...configForm.value.tie_breakers, value];
    newTieBreaker.value = '';
}

function removeTieBreakerAt(index) {
    const list = [...configForm.value.tie_breakers];
    if (index < 0 || index >= list.length) return;
    list.splice(index, 1);
    configForm.value.tie_breakers = list;
}

function moveTieBreaker(index, direction) {
    const list = [...configForm.value.tie_breakers];
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= list.length) return;
    const item = list[index];
    list.splice(index, 1);
    list.splice(target, 0, item);
    configForm.value.tie_breakers = list;
}

function onTiePointerDown(event, criterion, index) {
    if (event.button !== undefined && event.button !== 0) return;
    tiePointerId = event.pointerId ?? null;
    tieStartX = event.clientX ?? 0;
    tieStartY = event.clientY ?? 0;
    draggingTieIndex.value = index;
    dragOverTieIndex.value = index;
    tieHoldTimer = setTimeout(() => {
        draggingTieCriterion.value = criterion;
        lastTieSwapAt = 0;
        event.currentTarget?.setPointerCapture?.(event.pointerId);
    }, 350);
}

function onTiePointerMove(event) {
    if (draggingTieIndex.value === null) return;
    const moved = Math.hypot((event.clientX ?? 0) - tieStartX, (event.clientY ?? 0) - tieStartY);
    if (!draggingTieCriterion.value && moved > 8) {
        clearTieDragState();
        return;
    }
    if (!draggingTieCriterion.value) return;

    if (tieDragRaf) return;
    const x = event.clientX;
    const y = event.clientY;
    tieDragRaf = requestAnimationFrame(() => {
        tieDragRaf = null;
        const hit = document.elementFromPoint(x, y);
        const row = hit?.closest?.('[data-tie-index]');
        if (!row) return;
        const targetIndex = Number(row.getAttribute('data-tie-index'));
        if (!Number.isInteger(targetIndex)) return;
        dragOverTieIndex.value = targetIndex;
        if (targetIndex === draggingTieIndex.value) return;

        const rect = row.getBoundingClientRect();
        const direction = targetIndex > draggingTieIndex.value ? 1 : -1;
        const crossedCenter =
            (direction > 0 && y > rect.top + rect.height * 0.55) ||
            (direction < 0 && y < rect.top + rect.height * 0.45);
        if (!crossedCenter) return;

        const now = performance.now();
        if (now - lastTieSwapAt < 90) return;
        reorderTieBreakers(draggingTieIndex.value, targetIndex);
        draggingTieIndex.value = targetIndex;
        lastTieSwapAt = now;
    });
}

function onTiePointerUp(event) {
    event.currentTarget?.releasePointerCapture?.(event.pointerId);
    clearTieDragState();
}

function clearTieDragState() {
    if (tieHoldTimer) clearTimeout(tieHoldTimer);
    tieHoldTimer = null;
    if (tieDragRaf) cancelAnimationFrame(tieDragRaf);
    tieDragRaf = null;
    tiePointerId = null;
    draggingTieCriterion.value = null;
    draggingTieIndex.value = null;
    dragOverTieIndex.value = null;
}

function reorderTieBreakers(from, to) {
    const list = [...configForm.value.tie_breakers];
    if (from < 0 || from >= list.length || to < 0 || to >= list.length) return;
    const [item] = list.splice(from, 1);
    list.splice(to, 0, item);
    configForm.value.tie_breakers = list;
}

async function saveConfig() {
    savingConfig.value = true;
    try {
        await updatePool(poolId.value, configForm.value);
        await load();
    } finally {
        savingConfig.value = false;
    }
}

async function finalizePoolNow() {
    const result = await Swal.fire({
        title: 'Finalizar bolão?',
        text: 'Essa ação consolida pontuação e dispara e-mails finais.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Finalizar',
        cancelButtonText: 'Cancelar',
        background: '#0f172a',
        color: '#e2e8f0',
    });
    if (!result.isConfirmed) return;

    finalizing.value = true;
    try {
        await finalizePool(poolId.value);
        await load();
    } finally {
        finalizing.value = false;
    }
}
</script>

<style scoped>
.hero-card,.card-shell{border:1px solid rgba(255,255,255,.12);border-radius:18px;background:linear-gradient(165deg,rgba(16,25,41,.96),rgba(10,15,26,.98));padding:14px}
.hero-comp{font-size:10px;letter-spacing:.09em;text-transform:uppercase;font-weight:800;color:#f5a623}
.hero-title{font-size:28px;font-weight:800;color:#fff;line-height:1.05;margin-top:4px}
.hero-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.hero-pill{padding:4px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.15);color:#dce7f7;background:rgba(255,255,255,.05);font-size:12px;font-weight:700}
.hero-pill.danger{border-color:rgba(255,114,114,.35);background:rgba(255,114,114,.12);color:#ff8d8d}
.invite-chip{margin-top:10px;width:100%;border:1px solid rgba(245,166,35,.3);border-radius:12px;background:rgba(245,166,35,.09);color:#ffd084;padding:11px 12px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px}
.segmented{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.seg-item{border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.03);border-radius:12px;padding:10px 8px;color:#cdd8ea;font-weight:700;font-size:13px}
.seg-item.active{border-color:rgba(245,166,35,.4);background:rgba(245,166,35,.14);color:#ffd084}
.section-head h2{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#8ea0bc;font-weight:800}
.field{width:100%;border:1px solid rgba(255,255,255,.14);background:#121a2a;color:#e8eef9;border-radius:12px;padding:11px 12px;font-size:14px}
.action{width:100%;border-radius:12px;border:1px solid rgba(255,255,255,.14);padding:11px 12px;font-size:14px;font-weight:800;color:#eef3fb;background:rgba(255,255,255,.03)}
.action.primary{border-color:rgba(245,166,35,.4);background:rgba(245,166,35,.13);color:#ffd084}
.action.danger{border-color:rgba(255,114,114,.35);background:rgba(255,114,114,.1);color:#ff9494}
.action.small{width:auto;padding:8px 10px;font-size:12px}
.member-row{display:flex;justify-content:space-between;gap:8px;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:10px;background:rgba(255,255,255,.02)}
.member-name{font-size:14px;font-weight:700;color:#fff}.member-sub{font-size:12px;color:#9fb0c9}.member-actions{display:flex;gap:6px;flex-wrap:wrap;align-items:center;justify-content:flex-end}
.empty-text{font-size:12px;color:#8ea0bc}
.summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.summary-box{border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:10px;background:rgba(255,255,255,.03)}
.summary-box span{display:block;font-size:10px;color:#8ea0bc;letter-spacing:.06em;text-transform:uppercase;font-weight:700}
.summary-box b{display:block;margin-top:2px;font-size:15px;color:#f1f6ff}
.fade-slide-enter-active,.fade-slide-leave-active{transition:all .22s ease}.fade-slide-enter-from,.fade-slide-leave-to{opacity:0;transform:translateY(8px)}

.invite-panel { border:1px solid rgba(255,255,255,.11); border-radius:14px; padding:12px; background:rgba(18, 29, 47, .55); }
.users-head { display:flex; align-items:center; justify-content:space-between; margin-top:6px; }
.users-head h2 { font-size:12px; letter-spacing:.08em; text-transform:uppercase; color:#a9b8d1; font-weight:800; }
.users-meta { font-size:12px; color:#92a5c2; }

.members-list-card { border:1px solid rgba(255,255,255,.12); border-radius:16px; background:linear-gradient(165deg,rgba(17,25,41,.96),rgba(10,15,26,.98)); overflow:visible; }
.member-row-v2 { display:flex; align-items:center; gap:10px; padding:12px 12px; border-bottom:1px solid rgba(255,255,255,.08); }
.member-row-v2:last-of-type { border-bottom:0; }
.member-avatar { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:800; background:rgba(245,166,35,.13); color:#f8bb50; border:1px solid rgba(245,166,35,.28); }
.member-avatar.alt { background:rgba(103,160,255,.15); border-color:rgba(103,160,255,.24); color:#cbe0ff; }
.member-main { min-width:0; flex:1; }
.member-name { font-size:15px; font-weight:800; color:#f4f8ff; line-height:1.1; display:flex; align-items:center; gap:6px; }
.role-chip { font-size:10px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; padding:2px 6px; border-radius:7px; border:1px solid rgba(148,163,184,.35); color:#9fb1cc; background:rgba(148,163,184,.08); }
.role-chip.owner { border-color:rgba(245,166,35,.42); color:#f6b84c; background:rgba(245,166,35,.1); }
.member-sub { margin-top:3px; font-size:12px; color:#9fb0c9; }
.member-sector { margin-top:3px; font-size:12px; color:#7d92b2; }
.role-tag { border:1px solid rgba(144,167,194,.35); border-radius:7px; padding:1px 6px; font-size:10px; color:#a8bbd8; letter-spacing:.05em; }
.role-tag.owner { border-color:rgba(245,166,35,.42); color:#f8bb50; }
.menu-trigger { width:36px; height:36px; border-radius:10px; border:1px solid rgba(255,255,255,.25); display:flex; align-items:center; justify-content:center; color:#c5d4ea; background:rgba(255,255,255,.03); }
.member-menu { position:absolute; right:0; top:42px; z-index:80; min-width:170px; border-radius:12px; border:1px solid rgba(255,255,255,.14); background:#071126; box-shadow:0 12px 24px rgba(0,0,0,.4); overflow:hidden; }
.member-menu-item { width:100%; border:0; background:transparent; color:#dce7f7; font-size:14px; padding:10px 12px; display:flex; align-items:center; gap:8px; text-align:left; font-weight:700; }
.member-menu-item + .member-menu-item { border-top:1px solid rgba(255,255,255,.08); }
.member-menu-item.danger { color:#ff8c97; }
.pending-strip { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px 12px; border-top:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.02); color:#ff8b93; font-size:12px; font-weight:700; }
.tie-row { transition: transform .18s ease, border-color .18s ease, background-color .18s ease, box-shadow .18s ease; touch-action: none; }
.tie-row.dragging { border-color: rgba(245,166,35,.45); background: rgba(245,166,35,.1); box-shadow: 0 8px 18px rgba(0,0,0,.3); transform: scale(1.01); }
.tie-row.drop-target { border-color: rgba(103,160,255,.45); background: rgba(103,160,255,.1); }
.drag-pill { font-size: 10px; font-weight: 700; color: #9bb0ce; border: 1px dashed rgba(155,176,206,.45); border-radius: 999px; padding: 3px 8px; }
.tie-move-move { transition: transform .2s ease; }
</style>
