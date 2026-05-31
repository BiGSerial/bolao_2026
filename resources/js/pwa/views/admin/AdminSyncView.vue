<template>
    <div class="pwa-page">
        <div class="pwa-section pt-3 space-y-3">
            <h1 class="text-xl font-bold text-white">Controle da API</h1>
            <p class="text-sm text-bolao-muted">Executar comandos da versão web e monitorar sincronizações.</p>

            <div class="pwa-card p-4 space-y-1" v-if="status">
                <p class="text-xs text-bolao-muted2 uppercase tracking-widest">Resumo</p>
                <p class="text-sm text-white">Última execução: {{ formatLocalDateTime(status.last_sync) }}</p>
                <p class="text-xs text-bolao-muted">Provider: {{ status.last_provider || '—' }} · último sucesso: {{ status.last_success ? 'sim' : 'não' }}</p>
                <p class="text-xs text-bolao-muted">24h: {{ status.success_24h }} ok / {{ status.errors_24h }} erros</p>
            </div>

            <div class="pwa-card p-4" v-if="hasLineData">
                <p class="text-xs text-bolao-muted2 uppercase tracking-widest mb-2">Volumetria por API (24h)</p>
                <div class="h-52">
                    <canvas ref="lineChartEl"></canvas>
                </div>
            </div>

            <div class="pwa-card p-4 space-y-2" v-if="activityByApi.length">
                <p class="text-xs text-bolao-muted2 uppercase tracking-widest">Atividade por API (24h)</p>
                <div
                    v-for="item in activityByApi"
                    :key="item.api"
                    class="rounded-lg border border-white/10 bg-white/[0.03] p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm text-white font-semibold">{{ item.api }}</p>
                        <p class="text-[11px] text-bolao-muted">pico {{ item.peak_rpm }}/min</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-2 text-xs">
                        <div class="rounded-md border border-white/10 p-2 bg-white/[0.02]">
                            <p class="text-bolao-muted2 uppercase text-[10px]">Req. 24h</p>
                            <p class="text-white font-bold">{{ item.total_24h }}</p>
                        </div>
                        <div class="rounded-md border border-white/10 p-2 bg-white/[0.02]">
                            <p class="text-bolao-muted2 uppercase text-[10px]">Falhas 24h</p>
                            <p class="text-rose-300 font-bold">{{ item.failed_24h }}</p>
                        </div>
                        <div class="rounded-md border border-white/10 p-2 bg-white/[0.02]">
                            <p class="text-bolao-muted2 uppercase text-[10px]">Latência média</p>
                            <p class="text-emerald-300 font-bold">{{ item.avg_latency_ms }} ms</p>
                        </div>
                        <div class="rounded-md border border-white/10 p-2 bg-white/[0.02]">
                            <p class="text-bolao-muted2 uppercase text-[10px]">Minuto de pico</p>
                            <p class="text-slate-200 font-bold">{{ item.peak_minute }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2">
                <button class="pwa-btn-primary" :disabled="running" @click="run('base')">Rodar Sync Base</button>
                <button class="pwa-btn-secondary" :disabled="running" @click="run('details')">Rodar Sync Detalhes</button>
                <button class="pwa-btn-secondary" :disabled="running" @click="run('consolidate')">Rodar Consolidação</button>
            </div>

            <div class="pwa-card p-3" v-if="lastOutput">
                <p class="text-xs text-bolao-muted2 uppercase tracking-widest mb-2">Saída do comando</p>
                <pre class="text-[11px] text-slate-200 whitespace-pre-wrap break-words max-h-64 overflow-y-auto">{{ lastOutput }}</pre>
            </div>

            <div class="pwa-card p-3" v-if="status?.recent?.length">
                <p class="text-xs text-bolao-muted2 uppercase tracking-widest mb-2">Últimas execuções</p>
                <div class="space-y-2 max-h-56 overflow-y-auto">
                    <div v-for="item in status.recent" :key="item.id" class="rounded-lg border border-white/10 bg-white/[0.03] p-2 text-xs">
                        <p class="text-white">{{ item.provider }} · {{ item.success ? 'OK' : 'ERRO' }}</p>
                        <p class="text-bolao-muted">{{ formatLocalDateTime(item.synced_at) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import Chart from 'chart.js/auto';
import client from '../../api/client';

const emit = defineEmits(['set-title']);
const status = ref(null);
const running = ref(false);
const lastOutput = ref('');
const lineChartEl = ref(null);
let lineChart = null;
let savedScrollTop = 0;

const activityByApi = computed(() => {
    const base = Array.isArray(status.value?.activity_by_api_24h) ? status.value.activity_by_api_24h : [];
    return [...base].sort((a, b) => Number(b.total_24h || 0) - Number(a.total_24h || 0));
});

const hasLineData = computed(() => {
    const labels = status.value?.hourly_by_api_24h?.labels;
    const series = status.value?.hourly_by_api_24h?.series;
    return Array.isArray(labels) && labels.length > 0 && Array.isArray(series) && series.length > 0;
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

function destroyLineChart() {
    if (lineChart) {
        lineChart.destroy();
        lineChart = null;
    }
}

async function renderLineChart() {
    if (!hasLineData.value) {
        destroyLineChart();
        return;
    }

    await nextTick();
    const canvas = lineChartEl.value;
    if (!(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    const labels = status.value.hourly_by_api_24h.labels ?? [];
    const series = status.value.hourly_by_api_24h.series ?? [];
    const palette = ['#f3a61f', '#4cc9f0', '#34d399', '#f87171'];
    const datasets = series.map((item, index) => ({
        label: item.api,
        data: Array.isArray(item.values) ? item.values : [],
        borderColor: palette[index % palette.length],
        backgroundColor: palette[index % palette.length],
        borderWidth: 2,
        tension: 0.35,
        pointRadius: 1.8,
        pointHoverRadius: 3,
        fill: false,
    }));

    destroyLineChart();
    lineChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#dbeafe', boxWidth: 10, boxHeight: 10 } },
            },
            scales: {
                x: { ticks: { color: '#8fa5c8', maxTicksLimit: 8 }, grid: { color: 'rgba(255,255,255,0.06)' } },
                y: { beginAtZero: true, ticks: { color: '#8fa5c8', precision: 0 }, grid: { color: 'rgba(255,255,255,0.08)' } },
            },
        },
    });
}

async function loadStatus() {
    preserveScrollStart();
    const { data: res } = await client.get('/admin/sync/status');
    status.value = res.data;
    await renderLineChart();
    preserveScrollEnd();
}

async function run(command) {
    running.value = true;
    preserveScrollStart();
    try {
        const { data: res } = await client.post('/admin/sync/run', { command });
        lastOutput.value = res?.data?.output || '(sem saída)';
        await loadStatus();
    } finally {
        running.value = false;
        preserveScrollEnd();
    }
}

onMounted(async () => {
    emit('set-title', 'Sync API');
    await loadStatus();
});

onBeforeUnmount(() => {
    destroyLineChart();
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
