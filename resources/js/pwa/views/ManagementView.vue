<template>
    <div class="pwa-page">
        <div class="pwa-section pt-3 space-y-3">
            <h1 class="text-xl font-bold text-white">Gestão de Bolões</h1>
            <p class="text-sm text-bolao-muted">Resumo, operações e atalhos dos bolões que você criou.</p>

            <div v-if="competitionOptions.length > 1" class="space-y-1.5">
                <label class="management-label">Competição</label>
                <div class="management-select-wrap">
                    <i class="ti ti-trophy management-select-icon"></i>
                    <button type="button" class="management-select-btn" @click="toggleCompetitionMenu">
                        <span class="management-select-text">{{ selectedCompetitionLabel }}</span>
                        <i class="ti ti-chevron-down management-select-arrow" :class="{ 'is-open': competitionMenuOpen }"></i>
                    </button>
                    <div v-if="competitionMenuOpen" class="management-select-menu">
                        <button type="button" class="management-select-option" :class="{ active: selectedCompetitionCode === '' }" @click="selectCompetition('')">Todas</button>
                        <button v-for="comp in competitionOptions" :key="comp.code" type="button" class="management-select-option" :class="{ active: selectedCompetitionCode === comp.code }" @click="selectCompetition(comp.code)">
                            {{ comp.name }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="pwa-section space-y-3">
            <div v-for="i in 4" :key="i" class="pwa-skeleton h-40 w-full"></div>
        </div>

        <div v-else-if="filteredPools.length" class="pwa-section space-y-3 pb-6">
            <article v-for="pool in filteredPools" :key="pool.id" class="management-card">
                <header class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="management-competition">{{ pool.competition?.name }}</p>
                        <h3 class="management-title">{{ pool.name }}</h3>
                    </div>
                    <span class="management-status" :class="pool.status === 'archived' ? 'is-archived' : 'is-active'">
                        {{ pool.status === 'archived' ? 'Finalizado' : 'Ativo' }}
                    </span>
                </header>

                <section class="management-summary-strip">
                    <div class="summary-col">
                        <span class="summary-title">Participantes</span>
                        <p class="summary-main">
                            <b>{{ pool.members_count }} ativos</b>
                            <span>• {{ pool.pending_count }} pend.</span>
                        </p>
                    </div>
                    <div class="summary-col with-divider">
                        <span class="summary-title">Código de convite</span>
                        <button class="summary-code" @click="copyInviteCode(pool.invite_code)">
                            {{ pool.invite_code }}
                            <i class="ti ti-copy text-[13px]"></i>
                        </button>
                    </div>
                </section>

                <div class="actions-row">
                    <button class="action-btn primary" @click="openPoolPage(pool.id, 'users')">
                        <i class="ti ti-users text-[14px]"></i>
                        Ver usuários
                    </button>
                    <button class="action-btn" @click="openPoolPage(pool.id, 'config')">
                        <i class="ti ti-settings text-[14px]"></i>
                        Configurar
                    </button>
                </div>

                <div class="card-footer">
                    <button
                        class="end-link"
                        :disabled="pool.status === 'archived' || finalizingPoolId === pool.id"
                        @click="finalize(pool)"
                    >
                        <i class="ti ti-alert-triangle text-[14px]"></i>
                        {{ finalizingPoolId === pool.id ? 'Finalizando...' : 'Encerrar este bolão' }}
                    </button>
                </div>
            </article>
        </div>

        <div v-else class="pwa-section py-12 text-center">
            <i class="ti ti-layout-dashboard text-4xl text-bolao-muted2 mb-3 block"></i>
            <p class="text-bolao-muted">Você não possui bolões nessa competição.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import client from '../api/client';
import { finalizePool } from '../api/pools';

const emit = defineEmits(['set-title']);
const router = useRouter();

const loading = ref(true);
const pools = ref([]);
const selectedCompetitionCode = ref('');
const competitionMenuOpen = ref(false);
const finalizingPoolId = ref(null);

const competitionOptions = computed(() => {
    const map = new Map();
    for (const pool of pools.value) {
        if (pool.competition?.code) map.set(pool.competition.code, pool.competition.name);
    }
    return Array.from(map.entries()).map(([code, name]) => ({ code, name }));
});

const filteredPools = computed(() =>
    pools.value.filter((pool) => !selectedCompetitionCode.value || pool.competition?.code === selectedCompetitionCode.value),
);

const selectedCompetitionLabel = computed(() => {
    if (!selectedCompetitionCode.value) return 'Todas';
    return competitionOptions.value.find((c) => c.code === selectedCompetitionCode.value)?.name ?? 'Todas';
});

onMounted(async () => {
    emit('set-title', 'Gestão');
    document.addEventListener('click', onDocumentClick);
    await load();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
});

async function load() {
    loading.value = true;
    try {
        const { data: res } = await client.get('/pools?managed=1');
        pools.value = Array.isArray(res?.data?.items) ? res.data.items : [];
    } finally {
        loading.value = false;
    }
}

function toggleCompetitionMenu() {
    competitionMenuOpen.value = !competitionMenuOpen.value;
}

function selectCompetition(code) {
    selectedCompetitionCode.value = code;
    competitionMenuOpen.value = false;
}

function onDocumentClick(event) {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if (target.closest('.management-select-wrap')) return;
    competitionMenuOpen.value = false;
}

async function finalize(pool) {
    const result = await Swal.fire({
        title: 'Finalizar bolão?',
        text: 'Todos os pontos serão consolidados e os membros receberão e-mail com ranking final.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Finalizar',
        cancelButtonText: 'Cancelar',
        background: '#0f172a',
        color: '#e2e8f0',
    });

    if (!result.isConfirmed) return;

    finalizingPoolId.value = pool.id;
    try {
        const { data: res } = await finalizePool(pool.id);
        await Swal.fire({
            title: 'Bolão finalizado',
            text: res?.data?.message ?? 'Finalizado com sucesso.',
            icon: 'success',
            background: '#0f172a',
            color: '#e2e8f0',
        });
        await load();
    } finally {
        finalizingPoolId.value = null;
    }
}

function openPoolPage(poolId, tab = 'users') {
    router.push(`/pwa/management/pools/${poolId}?tab=${encodeURIComponent(tab)}`);
}

function copyInviteCode(code) {
    navigator.clipboard?.writeText(String(code || '').trim());
}
</script>

<style scoped>
.management-label { font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #95a2b8; }
.management-select-wrap { position: relative; border: 1px solid rgba(255,255,255,.12); border-radius: 14px; background: linear-gradient(180deg, rgba(21,30,48,.9), rgba(15,22,36,.94)); box-shadow: inset 0 1px 0 rgba(255,255,255,.05); }
.management-select-btn { width: 100%; border: 0; background: transparent; color: #e7edf8; font-size: 14px; font-weight: 700; padding: 12px 36px 12px 40px; outline: none; text-align: left; display: flex; align-items: center; justify-content: space-between; }
.management-select-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.management-select-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #f5a623; font-size: 14px; }
.management-select-arrow { color: #8ea0bc; font-size: 14px; transition: transform .2s ease; }
.management-select-arrow.is-open { transform: rotate(180deg); }
.management-select-menu { position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 30; border-radius: 12px; border: 1px solid rgba(255,255,255,.14); background: #111b2d; box-shadow: 0 12px 24px rgba(0,0,0,.38); overflow: hidden; }
.management-select-option { width: 100%; border: 0; background: transparent; color: #dce7f7; text-align: left; padding: 11px 14px; font-size: 14px; font-weight: 700; }
.management-select-option + .management-select-option { border-top: 1px solid rgba(255,255,255,.08); }
.management-select-option.active { background: rgba(245,166,35,.18); color: #ffd084; }

.management-card { border: 1px solid rgba(255,255,255,.12); border-radius: 18px; padding: 12px; background: radial-gradient(120% 90% at 100% 0%, rgba(245,166,35,.08), transparent 45%), linear-gradient(165deg, rgba(17,25,41,.96), rgba(10,15,26,.97)); box-shadow: 0 10px 26px rgba(0,0,0,.28); display: flex; flex-direction: column; gap: 10px; }
.management-competition { font-size: 10px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; color: #f5a623; }
.management-title { margin-top: 2px; font-size: 24px; line-height: 1.08; font-weight: 800; color: #fff; }
.management-status { font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; border-radius: 999px; padding: 4px 9px; border: 1px solid transparent; }
.management-status.is-active { background: rgba(0, 199, 120, .12); border-color: rgba(0, 199, 120, .25); color: #24d08a; }
.management-status.is-archived { background: rgba(148,163,184,.12); border-color: rgba(148,163,184,.2); color: #c2cedf; }

.management-summary-strip { border:1px solid rgba(255,255,255,.08); border-radius:12px; background:rgba(4,10,20,.55); padding:9px 10px; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
.summary-col { min-width:0; }
.summary-col.with-divider { border-left:1px solid rgba(255,255,255,.1); padding-left:8px; }
.summary-title { display:block; font-size:10px; letter-spacing:.09em; text-transform:uppercase; font-weight:700; color:#8092ae; }
.summary-main { margin-top:4px; font-size:13px; color:#f0f5fe; line-height:1.2; }
.summary-main b { font-weight:800; }
.summary-main span { color:#ff6f6f; font-weight:700; margin-left:4px; }
.summary-code { margin-top:4px; display:flex; align-items:center; gap:6px; font-size:14px; font-weight:800; color:#f7bb53; }
.actions-row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
.action-btn { width:100%; border-radius:12px; border:1px solid rgba(255,255,255,.14); background:rgba(19,32,56,.86); color:#edf2fb; font-size:14px; font-weight:700; padding:8px 10px; min-height:48px; display:flex; align-items:center; justify-content:center; gap:8px; white-space:nowrap; }
.action-btn.primary { border-color:rgba(245,166,35,.4); background:rgba(245,166,35,.15); color:#ffd084; }
.action-btn:active { transform: translateY(1px); }
.action-btn:disabled { opacity:.55; }
.card-footer { border-top:1px solid rgba(255,255,255,.08); padding-top:8px; display:flex; justify-content:flex-end; }
.end-link { color:#d86c78; font-size:14px; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
</style>
