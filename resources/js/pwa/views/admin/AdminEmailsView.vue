<template>
    <div class="pwa-page">
        <div class="pwa-section pt-3 space-y-3">
            <h1 class="text-xl font-bold text-white">API de E-mails</h1>
            <p class="text-sm text-bolao-muted">Métricas, última execução e últimos e-mails enviados.</p>

            <div class="pwa-card p-4 space-y-1" v-if="summary">
                <p class="text-xs text-bolao-muted2 uppercase tracking-widest">Resumo</p>
                <p class="text-xs text-bolao-muted">Última execução: {{ formatLocalDateTime(data?.last_mail_at) }}</p>
                <div class="grid grid-cols-2 gap-2 mt-1">
                    <div class="rounded-lg border border-white/10 p-2 bg-white/[0.03]"><p class="text-[10px] text-bolao-muted2 uppercase">Enviados hoje</p><p class="text-white text-lg font-bold">{{ summary.sent_today }}</p></div>
                    <div class="rounded-lg border border-white/10 p-2 bg-white/[0.03]"><p class="text-[10px] text-bolao-muted2 uppercase">Falhas hoje</p><p class="text-red-300 text-lg font-bold">{{ summary.failed_today }}</p></div>
                    <div class="rounded-lg border border-white/10 p-2 bg-white/[0.03]"><p class="text-[10px] text-bolao-muted2 uppercase">Enviados mês</p><p class="text-white text-lg font-bold">{{ summary.sent_this_month }}</p></div>
                    <div class="rounded-lg border border-white/10 p-2 bg-white/[0.03]"><p class="text-[10px] text-bolao-muted2 uppercase">Saldo</p><p class="text-emerald-300 text-lg font-bold">{{ summary.remaining_this_month ?? '—' }}</p></div>
                </div>
            </div>

            <div class="pwa-card p-4" v-if="dailyChart.length">
                <p class="text-xs text-bolao-muted2 uppercase tracking-widest mb-2">Volume diário (7 dias)</p>
                <div class="space-y-1">
                    <div v-for="point in dailyChart" :key="point.label" class="flex items-center gap-2">
                        <span class="text-[10px] text-bolao-muted w-10">{{ point.label }}</span>
                        <div class="flex-1 h-2 rounded bg-white/10 overflow-hidden">
                            <div class="h-full bg-bolao-accent" :style="{ width: `${point.pct}%` }"></div>
                        </div>
                        <span class="text-[10px] text-slate-300 w-10 text-right">{{ point.messages }}</span>
                    </div>
                </div>
            </div>

            <button class="pwa-btn-primary w-full" :disabled="running" @click="syncNow">Sincronizar métricas de e-mail</button>
            <div class="pwa-card p-3" v-if="lastOutput">
                <p class="text-xs text-bolao-muted2 uppercase tracking-widest mb-2">Saída do comando</p>
                <pre class="text-[11px] text-slate-200 whitespace-pre-wrap break-words max-h-64 overflow-y-auto">{{ lastOutput }}</pre>
            </div>

            <div class="pwa-card p-3" v-if="recentMails.length">
                <p class="text-xs text-bolao-muted2 uppercase tracking-widest mb-2">Últimos e-mails enviados</p>
                <div class="space-y-2 max-h-72 overflow-y-auto">
                    <div v-for="mail in recentMails" :key="mail.id" class="rounded-lg border border-white/10 bg-white/[0.03] p-2 text-xs">
                        <p class="text-white truncate">{{ mail.subject || '(sem assunto)' }}</p>
                        <p class="text-bolao-muted truncate">Para: {{ mail.to_address }}</p>
                        <p class="text-bolao-muted2">Status: {{ mail.status }} · {{ formatLocalDateTime(mail.sent_at || mail.created_at) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import client from '../../api/client';

const emit = defineEmits(['set-title']);
const data = ref(null);
const summary = ref(null);
const running = ref(false);
const lastOutput = ref('');
let savedScrollTop = 0;

const recentMails = computed(() => {
    const base = Array.isArray(data.value?.recent_mails) ? data.value.recent_mails : [];
    return [...base].sort((a, b) => String(b.created_at || '').localeCompare(String(a.created_at || '')));
});
const dailyChart = computed(() => {
    const base = Array.isArray(data.value?.daily_7d) ? data.value.daily_7d : [];
    const max = Math.max(1, ...base.map((d) => Number(d.messages || 0)));
    return [...base].reverse().map((d) => ({ ...d, pct: Math.round((Number(d.messages || 0) / max) * 100) }));
});

function preserveScrollStart() {
    const main = document.querySelector('.pwa-main');
    savedScrollTop = main instanceof HTMLElement ? main.scrollTop : 0;
}

function preserveScrollEnd() {
    const main = document.querySelector('.pwa-main');
    if (main instanceof HTMLElement) {
        requestAnimationFrame(() => { main.scrollTop = savedScrollTop; });
    }
}

async function loadStatus() {
    preserveScrollStart();
    const { data: res } = await client.get('/admin/emails/status');
    data.value = res.data;
    summary.value = res?.data?.summary ?? null;
    preserveScrollEnd();
}

async function syncNow() {
    running.value = true;
    preserveScrollStart();
    try {
        const { data: res } = await client.post('/admin/emails/sync', {});
        lastOutput.value = res?.data?.output || '(sem saída)';
        await loadStatus();
    } finally {
        running.value = false;
        preserveScrollEnd();
    }
}

onMounted(async () => {
    emit('set-title', 'E-mails');
    await loadStatus();
});

function formatLocalDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>
