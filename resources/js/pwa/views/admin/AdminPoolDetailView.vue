<template>
    <div class="pwa-page">
        <div v-if="loading" class="pwa-section pt-8 flex justify-center">
            <i class="ti ti-loader-2 text-3xl text-bolao-accent animate-spin"></i>
        </div>

        <div v-else-if="pool" class="pwa-section pt-3 pb-6 space-y-3">
            <section class="pwa-card p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-bolao-accent">{{ pool.competition?.name }}</p>
                        <h1 class="text-xl font-bold text-white truncate">{{ pool.name }}</h1>
                        <p class="text-xs text-bolao-muted">Dono: {{ pool.owner?.name }} · {{ pool.members?.length || 0 }} membros</p>
                    </div>
                    <span :class="statusCls(pool.status)" class="text-[10px] px-2 py-1 rounded-full font-bold uppercase">{{ pool.status }}</span>
                </div>

                <div v-if="pool.status === 'suspended' && pool.suspension_reason" class="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-300">
                    Motivo da suspensão: {{ pool.suspension_reason }}
                </div>

                <div class="grid grid-cols-2 gap-2 mt-3">
                    <button v-if="pool.status === 'active'" class="pwa-btn-secondary !border-amber-500/30 !text-amber-300" @click="promptSuspend">Suspender</button>
                    <button v-else class="pwa-btn-secondary !border-emerald-500/30 !text-emerald-300" @click="activate">Reativar</button>
                    <button class="pwa-btn-secondary" @click="openGroupAsAdmin">Ver grupo</button>
                </div>
            </section>

            <section class="pwa-card p-4 space-y-2">
                <p class="text-[11px] uppercase tracking-widest text-bolao-muted2">Configuração do Grupo</p>
                <input v-model="form.name" class="field" placeholder="Nome" />
                <textarea v-model="form.description" class="field min-h-[70px]" placeholder="Descrição"></textarea>
                <textarea v-model="form.instructions" class="field min-h-[70px]" placeholder="Regulamento"></textarea>
                <div class="grid grid-cols-2 gap-2">
                    <select v-model="form.visibility" class="field">
                        <option value="private">Privado</option>
                        <option value="invite_only">Somente convite</option>
                        <option value="public">Público</option>
                    </select>
                    <select v-model="form.status" class="field">
                        <option value="active">Ativo</option>
                        <option value="suspended">Suspenso</option>
                        <option value="blocked">Bloqueado</option>
                        <option value="archived">Arquivado</option>
                    </select>
                </div>
                <button class="pwa-btn-primary w-full" :disabled="saving" @click="save">{{ saving ? 'Salvando...' : 'Salvar grupo' }}</button>
            </section>

            <section class="pwa-card p-4 space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] uppercase tracking-widest text-bolao-muted2">Membros do Grupo</p>
                    <span class="text-xs text-bolao-muted">{{ pool.members?.length || 0 }} total</span>
                </div>
                <div class="space-y-2 max-h-[40vh] overflow-y-auto">
                    <div v-for="member in pool.members" :key="member.id" class="rounded-lg border border-white/10 bg-white/[0.03] p-3">
                        <p class="text-sm text-white font-semibold">{{ member.name }}</p>
                        <p class="text-xs text-bolao-muted">{{ member.email }}</p>
                        <p class="text-[11px] text-bolao-muted2 mt-1">{{ member.role }} · {{ member.status }}<span v-if="member.sector"> · {{ member.sector }}</span></p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import client from '../../api/client';

const emit = defineEmits(['set-title']);
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const pool = ref(null);
const form = ref({
    name: '', description: '', instructions: '', visibility: 'invite_only', status: 'active',
    suspension_reason: '', allow_prediction_changes: true, closed_predictions: false,
    allow_pending_member_predictions: true, prediction_lock_minutes: 10,
    points_exact_score: 5, points_correct_result: 3, points_correct_goals: 1,
    correct_goals_mode: 'both_teams', sectors: [], tie_breakers: [],
});

function statusCls(status) {
    if (status === 'active') return 'bg-bolao-green/20 text-bolao-green';
    if (status === 'suspended') return 'bg-amber-500/20 text-amber-500';
    if (status === 'blocked') return 'bg-red-500/20 text-red-400';
    return 'bg-white/10 text-bolao-muted';
}

async function load() {
    loading.value = true;
    try {
        const { data: res } = await client.get(`/admin/pools/${route.params.id}`);
        pool.value = res.data;
        form.value = {
            name: res.data.name ?? '',
            description: res.data.description ?? '',
            instructions: res.data.instructions ?? '',
            visibility: res.data.visibility ?? 'invite_only',
            status: res.data.status ?? 'active',
            suspension_reason: res.data.suspension_reason ?? '',
            allow_prediction_changes: !!res.data.allow_prediction_changes,
            closed_predictions: !!res.data.closed_predictions,
            allow_pending_member_predictions: !!res.data.allow_pending_member_predictions,
            prediction_lock_minutes: Number(res.data.prediction_lock_minutes ?? 10),
            points_exact_score: Number(res.data.scoring?.points_exact_score ?? 5),
            points_correct_result: Number(res.data.scoring?.points_correct_result ?? 3),
            points_correct_goals: Number(res.data.scoring?.points_correct_goals ?? 1),
            correct_goals_mode: res.data.scoring?.correct_goals_mode ?? 'both_teams',
            sectors: Array.isArray(res.data.sectors) ? res.data.sectors : [],
            tie_breakers: Array.isArray(res.data.tie_breakers) ? res.data.tie_breakers : [],
        };
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        await client.patch(`/admin/pools/${route.params.id}`, form.value);
        await load();
    } finally {
        saving.value = false;
    }
}

async function promptSuspend() {
    const result = await Swal.fire({
        title: 'Suspender grupo',
        input: 'textarea',
        inputPlaceholder: 'Motivo da suspensão',
        showCancelButton: true,
        confirmButtonText: 'Suspender',
        cancelButtonText: 'Cancelar',
        background: '#0f172a',
        color: '#e2e8f0',
    });
    if (!result.isConfirmed) return;

    await client.patch(`/admin/pools/${route.params.id}/status`, {
        status: 'suspended',
        suspension_reason: String(result.value || '').trim() || 'Suspenso pelo administrador.',
    });
    await load();
}

async function activate() {
    await client.patch(`/admin/pools/${route.params.id}/status`, { status: 'active' });
    await load();
}

function openGroupAsAdmin() {
    router.push(`/pwa/pools/${route.params.id}`);
}

onMounted(async () => {
    emit('set-title', 'Grupo');
    await load();
});
</script>
