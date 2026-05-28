<template>
    <div class="pwa-page">
        <div class="pwa-section pt-4 space-y-4">
            <div class="space-y-1">
                <p class="pwa-section-label m-0">
                    <i class="ti ti-settings text-bolao-accent"></i>
                    Criar Bolão
                </p>
                <p class="text-xs text-bolao-muted">Preencha as informações e salve para criar o bolão.</p>
            </div>

            <div class="config-card space-y-3">
                <p class="section-title">Informações Básicas</p>

                <div class="field-wrap">
                    <label class="field-label">Nome do bolão</label>
                    <input v-model="form.name" class="pwa-input" placeholder="Ex.: Bolão da Firma" maxlength="120" />
                </div>

                <div class="field-wrap">
                    <label class="field-label">Competição</label>
                    <select v-model="form.competition_code" class="pwa-input">
                        <option v-for="comp in competitionOptions" :key="comp.code" :value="comp.code">{{ comp.name }}</option>
                    </select>
                </div>

                <div class="field-wrap">
                    <label class="field-label">Descrição</label>
                    <textarea v-model="form.description" class="pwa-input min-h-[70px]" placeholder="Resumo rápido do bolão (opcional)" maxlength="1000"></textarea>
                </div>

                <div class="field-wrap">
                    <label class="field-label">Instruções / regulamento</label>
                    <textarea v-model="form.instructions" class="pwa-input min-h-[90px]" placeholder="Regras para os participantes (opcional)" maxlength="3000"></textarea>
                </div>

                <div class="field-wrap">
                    <label class="field-label">Visibilidade</label>
                    <select v-model="form.visibility" class="pwa-input">
                        <option value="invite_only">Somente convite</option>
                        <option value="public">Público</option>
                        <option value="private">Privado</option>
                    </select>
                    <p class="field-help">Define quem consegue encontrar e entrar no bolão.</p>
                </div>
            </div>

            <div class="config-card space-y-3">
                <p class="section-title">Regras de Palpite</p>

                <div class="field-wrap">
                    <label class="field-label">Bloqueio antes do jogo (minutos)</label>
                    <input v-model.number="form.prediction_lock_minutes" type="number" min="10" class="pwa-input" />
                    <p class="field-help">Após esse tempo, o palpite fica travado.</p>
                </div>

                <label class="toggle-row">
                    <input v-model="form.allow_prediction_changes" type="checkbox">
                    <span>
                        <span class="field-label">Permitir edição de palpite</span>
                        <span class="field-help block">Participante pode alterar até o bloqueio.</span>
                    </span>
                </label>

                <label class="toggle-row">
                    <input v-model="form.closed_predictions" type="checkbox">
                    <span>
                        <span class="field-label">Palpite fechado</span>
                        <span class="field-help block">Após a 1ª partida começar, não será possível criar/alterar palpites.</span>
                    </span>
                </label>

                <label class="toggle-row">
                    <input v-model="form.allow_pending_member_predictions" type="checkbox">
                    <span>
                        <span class="field-label">Permitir palpites de pendentes</span>
                        <span class="field-help block">Usuário pendente já pode palpitar.</span>
                    </span>
                </label>
            </div>

            <div class="config-card space-y-3">
                <p class="section-title">Pontuação</p>

                <div class="grid grid-cols-3 gap-2">
                    <div class="field-wrap">
                        <label class="field-label">Placar exato</label>
                        <input v-model.number="form.points_exact_score" type="number" min="0" max="20" class="pwa-input" />
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Resultado</label>
                        <input v-model.number="form.points_correct_result" type="number" min="0" max="20" class="pwa-input" />
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">Gols</label>
                        <input v-model.number="form.points_correct_goals" type="number" min="0" max="20" class="pwa-input" />
                    </div>
                </div>

                <div class="field-wrap">
                    <label class="field-label">Regra do bônus de gols</label>
                    <select v-model="form.correct_goals_mode" class="pwa-input">
                        <option value="both_teams">Vale gols de qualquer time</option>
                        <option value="winner_only">Vale só gols do vencedor</option>
                    </select>
                </div>
            </div>

            <div class="config-card space-y-3">
                <p class="section-title">Critérios de desempate</p>
                <p class="field-help">Só aparecem critérios com pontuação maior que zero.</p>

                <div class="flex gap-2">
                    <select v-model="newTieBreaker" class="pwa-input">
                        <option value="">Selecionar critério...</option>
                        <option v-for="opt in availableTieBreakers" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <button type="button" class="pwa-btn-secondary px-3 shrink-0" @click="addTieBreaker">Adicionar</button>
                </div>

                <div v-if="!form.tie_breakers.length" class="text-[11px] text-bolao-muted border border-white/10 rounded-lg p-2">
                    Sem desempate configurado.
                </div>
                <div v-else class="space-y-2">
                    <p class="text-[11px] text-bolao-muted">No celular, use ↑ ↓ para ordenar. No desktop, também pode arrastar.</p>
                    <div
                        v-for="(criterion, index) in form.tie_breakers"
                        :key="criterion"
                        class="rank-row"
                        :class="{
                            'drag-active': draggingTieBreakerIndex === index,
                            'drop-target': touchTieBreakerIndex === index || dragOverTieBreakerIndex === index,
                        }"
                        :data-tie-index="index"
                        draggable="true"
                        @dragstart="onTieBreakerDragStart(index)"
                        @dragover.prevent="onTieBreakerDragOver(index)"
                        @drop="onTieBreakerDrop(index)"
                        @dragend="onTieBreakerDragEnd"
                        @touchstart="onTieBreakerTouchStart($event, index)"
                        @touchmove.prevent="onTieBreakerTouchMove"
                        @touchend="onTieBreakerTouchEnd"
                        @touchcancel="onTieBreakerTouchEnd"
                    >
                        <div class="min-w-0 flex items-center gap-2">
                            <span class="drag-handle" aria-hidden="true">⋮⋮</span>
                            <span class="prio-badge">Prioridade {{ index + 1 }}</span>
                            <span class="text-[12px] text-slate-100 truncate">{{ tieBreakerLabels[criterion] ?? criterion }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" class="mini-action" :disabled="index===0" @click="moveTieBreaker(index, 'up')">↑</button>
                            <button type="button" class="mini-action" :disabled="index===form.tie_breakers.length-1" @click="moveTieBreaker(index, 'down')">↓</button>
                            <button type="button" class="mini-action danger" @click="removeTieBreakerAt(index)">Remover</button>
                        </div>
                    </div>
                </div>
            </div>

            <button class="pwa-btn-primary w-full" :disabled="saving || !form.name.trim()" @click="save">
                {{ saving ? 'Salvando...' : 'Salvar e criar bolão' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getPools, createPool } from '../api/pools';

const emit = defineEmits(['set-title']);
const router = useRouter();
const route = useRoute();
const basePools = ref(null);
const saving = ref(false);
const LAST_COMPETITION_CODE_KEY = 'pwa_last_competition_code';

const form = ref({
    name: '',
    description: '',
    instructions: '',
    competition_code: 'WC',
    visibility: 'invite_only',
    allow_prediction_changes: true,
    closed_predictions: false,
    allow_pending_member_predictions: true,
    prediction_lock_minutes: 10,
    points_exact_score: 5,
    points_correct_result: 3,
    points_correct_goals: 1,
    correct_goals_mode: 'both_teams',
    tie_breakers: [],
});
const newTieBreaker = ref('');
const draggedTieBreakerIndex = ref(null);
const touchTieBreakerIndex = ref(null);
const draggingTieBreakerIndex = ref(null);
const dragOverTieBreakerIndex = ref(null);
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
    if (Number(form.value.points_exact_score) > 0) enabled.push('exact_scores');
    if (Number(form.value.points_correct_result) > 0) enabled.push('correct_results');
    if (Number(form.value.points_correct_goals) > 0) {
        enabled.push('correct_home_goals');
        enabled.push('correct_away_goals');
    }
    enabled.push('predictions_counted');
    return tieBreakerOptions.filter((o) => enabled.includes(o.value));
});
const availableTieBreakers = computed(() =>
    enabledTieBreakerOptions.value.filter((o) => !form.value.tie_breakers.includes(o.value)),
);

const competitionOptions = computed(() => {
    const fromApi = Array.isArray(basePools.value?.competitions) ? basePools.value.competitions : [];
    if (fromApi.length) return fromApi;

    const source = [...(basePools.value?.member_pools ?? []), ...(basePools.value?.discoverable_pools ?? [])];
    const uniq = new Map();
    source.forEach((p) => {
        const code = p.competition?.code;
        if (!code || uniq.has(code)) return;
        uniq.set(code, { code, name: p.competition?.name ?? code });
    });

    if (!uniq.size) uniq.set('WC', { code: 'WC', name: 'FIFA World Cup' });
    return Array.from(uniq.values());
});

async function loadBase() {
    const res = await getPools();
    basePools.value = res.data.data;
    const requested = String(route.query.competition || '').toUpperCase();
    const persisted = String(localStorage.getItem(LAST_COMPETITION_CODE_KEY) || '').toUpperCase();
    if (requested && competitionOptions.value.some((c) => c.code === requested)) {
        form.value.competition_code = requested;
        return;
    }
    if (persisted && competitionOptions.value.some((c) => c.code === persisted)) {
        form.value.competition_code = persisted;
        return;
    }
    if (!competitionOptions.value.find((c) => c.code === form.value.competition_code)) {
        form.value.competition_code = competitionOptions.value[0]?.code ?? 'WC';
    }
}

async function save() {
    saving.value = true;
    try {
        const res = await createPool(form.value);
        const poolId = res?.data?.data?.pool?.id;
        if (poolId) router.replace(`/pwa/pools/${poolId}?tab=manage`);
    } finally {
        saving.value = false;
    }
}

function addTieBreaker() {
    const value = String(newTieBreaker.value || '').trim();
    if (!value) return;
    if (!enabledTieBreakerOptions.value.some((o) => o.value === value)) return;
    if (form.value.tie_breakers.includes(value)) return;
    if (form.value.tie_breakers.length >= 5) return;
    form.value.tie_breakers = [...form.value.tie_breakers, value];
    newTieBreaker.value = '';
}

function removeTieBreakerAt(index) {
    const list = [...form.value.tie_breakers];
    if (index < 0 || index >= list.length) return;
    list.splice(index, 1);
    form.value.tie_breakers = list;
}

function moveTieBreaker(index, direction) {
    const list = [...form.value.tie_breakers];
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= list.length) return;
    const item = list[index];
    list.splice(index, 1);
    list.splice(target, 0, item);
    form.value.tie_breakers = list;
}

function onTieBreakerDragStart(index) {
    draggedTieBreakerIndex.value = index;
    draggingTieBreakerIndex.value = index;
}

function onTieBreakerDrop(targetIndex) {
    const from = draggedTieBreakerIndex.value;
    draggedTieBreakerIndex.value = null;
    draggingTieBreakerIndex.value = null;
    dragOverTieBreakerIndex.value = null;
    if (from === null || from === targetIndex) return;
    reorderTieBreakers(from, targetIndex);
}

function onTieBreakerDragOver(index) {
    dragOverTieBreakerIndex.value = index;
}

function onTieBreakerDragEnd() {
    draggedTieBreakerIndex.value = null;
    draggingTieBreakerIndex.value = null;
    dragOverTieBreakerIndex.value = null;
}

function onTieBreakerTouchStart(event, index) {
    if (event.target?.closest('.mini-action')) return;
    touchTieBreakerIndex.value = index;
    draggingTieBreakerIndex.value = index;
}

function onTieBreakerTouchMove(event) {
    const from = touchTieBreakerIndex.value;
    const point = event.touches?.[0];
    if (from === null || !point) return;
    const element = document.elementFromPoint(point.clientX, point.clientY);
    const row = element?.closest?.('[data-tie-index]');
    if (!row) return;
    const targetIndex = Number(row.getAttribute('data-tie-index'));
    if (!Number.isInteger(targetIndex) || targetIndex === from) return;
    reorderTieBreakers(from, targetIndex);
    touchTieBreakerIndex.value = targetIndex;
}

function onTieBreakerTouchEnd() {
    touchTieBreakerIndex.value = null;
    draggingTieBreakerIndex.value = null;
    dragOverTieBreakerIndex.value = null;
}

function reorderTieBreakers(from, targetIndex) {
    const list = [...form.value.tie_breakers];
    if (from < 0 || from >= list.length || targetIndex < 0 || targetIndex >= list.length) return;
    const [item] = list.splice(from, 1);
    list.splice(targetIndex, 0, item);
    form.value.tie_breakers = list;
}

watch(
    () => enabledTieBreakerOptions.value.map((o) => o.value),
    (enabled) => {
        form.value.tie_breakers = form.value.tie_breakers.filter((item) => enabled.includes(item));
    },
);

watch(
    () => form.value.competition_code,
    (code) => {
        const normalized = String(code || '').toUpperCase();
        if (!normalized) return;
        localStorage.setItem(LAST_COMPETITION_CODE_KEY, normalized);
    },
);

onMounted(() => {
    emit('set-title', 'Criar bolão');
    loadBase();
});
</script>

<style scoped>
.config-card {
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.08);
    background: #131a2a;
    padding: 10px;
}

.section-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #94a3b8;
    font-weight: 700;
}

.field-wrap { display: grid; gap: 4px; }

.field-label {
    font-size: 11px;
    font-weight: 700;
    color: #e2e8f0;
}

.field-help {
    font-size: 11px;
    color: #94a3b8;
}

.toggle-row {
    display: flex;
    gap: 8px;
    align-items: flex-start;
}

.rank-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.09);
    background: rgba(255,255,255,0.03);
    padding: 8px;
}
.rank-row.drag-active {
    opacity: 0.72;
    border-color: rgba(245, 166, 35, 0.55);
}
.rank-row.drop-target {
    border-color: rgba(245, 166, 35, 0.75);
    box-shadow: 0 0 0 1px rgba(245, 166, 35, 0.35) inset;
}

.prio-badge {
    font-size: 10px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.mini-action {
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.06);
    color: #cbd5e1;
    font-size: 11px;
    line-height: 1;
    padding: 6px 8px;
}

.mini-action:disabled {
    opacity: 0.38;
}

.mini-action.danger {
    color: #fca5a5;
    border-color: rgba(248,113,113,0.35);
}

.drag-handle {
    color: #64748b;
    letter-spacing: -1px;
    cursor: grab;
}
</style>
