<div class="p-4 sm:p-6 lg:p-8 space-y-6 animate-fade-in">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-white">Sync da API</h1>
            <p class="text-sm text-slate-400 mt-1">
                Última sincronização bem-sucedida:
                <span class="text-slate-300">
                    {{ $lastSuccess ? $lastSuccess->synced_at->diffForHumans() : 'nunca' }}
                </span>
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.matches.manual-correction') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-slate-700/60 px-3 py-2 text-sm font-medium text-slate-200 ring-1 ring-slate-600 hover:bg-slate-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Ir para o jogo (Ver detalhes)
            </a>

            <button wire:click="triggerSync"
                    wire:loading.attr="disabled"
                    wire:target="triggerSync"
                    class="btn-primary shrink-0">
                <svg wire:loading wire:target="triggerSync" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <svg wire:loading.remove wire:target="triggerSync" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span wire:loading.remove wire:target="triggerSync">Sincronizar Agora</span>
                <span wire:loading wire:target="triggerSync">Sincronizando…</span>
            </button>
        </div>
    </div>

    {{-- Mensagem de resultado --}}
    @if($syncMessage)
    <div class="{{ $syncSuccess ? 'alert-success' : 'alert-error' }}">
        {{ $syncMessage }}
    </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="stat-card">
            <span class="stat-value">{{ $totalMatches }}</span>
            <span class="stat-label">Jogos na base</span>
        </div>
        <div class="stat-card">
            <span class="stat-value {{ $liveMatches > 0 ? 'text-red-400' : 'text-slate-400' }}">{{ $liveMatches }}</span>
            <span class="stat-label">Ao vivo agora</span>
        </div>
        <div class="stat-card">
            <span class="stat-value">{{ $totalSyncs }}</span>
            <span class="stat-label">Total de syncs</span>
        </div>
        <div class="stat-card">
            <span class="stat-value {{ $failedSyncs > 0 ? 'text-red-400' : 'text-emerald-400' }}">{{ $failedSyncs }}</span>
            <span class="stat-label">Falhas (24h)</span>
        </div>
    </div>

    {{-- Gráfico de linhas por horário --}}
    <div>
        <h2 class="text-base font-semibold text-white mb-3">Volumetria por Horário (24h) por API</h2>
        <div class="card p-4 space-y-3">
            @php
                $series = $apiHourlyVolume['series'] ?? [];
                $labels = $apiHourlyVolume['labels'] ?? [];
            @endphp

            @if(count($series) > 0)
                <div
                    id="api-hourly-chart-container"
                    wire:ignore
                    data-labels='@json($labels)'
                    data-series='@json($series)'
                    class="h-64 rounded border border-slate-800 bg-slate-900/50 p-2">
                    <canvas id="api-hourly-chart"></canvas>
                </div>

                <p class="text-[11px] text-slate-500">Janela: últimas 24 horas. Cada linha representa uma API.</p>
            @else
                <p class="text-sm text-slate-500">Sem dados para o gráfico no momento.</p>
            @endif
        </div>
    </div>

    {{-- Histórico de logs --}}
    <div>
        <h2 class="text-base font-semibold text-white mb-3">Histórico de Sincronizações</h2>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-800">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Data/Hora</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase hidden sm:table-cell">Total</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase hidden sm:table-cell">Alterados</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase hidden md:table-cell">HTTP</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Mensagem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($logs as $log)
                        <tr class="hover:bg-slate-800/20 transition-colors" wire:key="log-{{ $log->id }}">
                            <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                                <span>{{ $log->synced_at?->format('d/m/Y H:i:s') ?? '—' }}</span>
                                <span class="block text-slate-600">{{ $log->synced_at?->diffForHumans() }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($log->success)
                                    <span class="badge-green">Sucesso</span>
                                @else
                                    <span class="badge-red">Falha</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-400 text-right hidden sm:table-cell">
                                {{ $log->records_total ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-right hidden sm:table-cell">
                                <span class="{{ $log->records_changed > 0 ? 'text-emerald-400 font-semibold' : 'text-slate-600' }} text-sm">
                                    {{ $log->records_changed ?: '0' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500 text-right hidden md:table-cell">
                                {{ $log->http_status ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400 max-w-xs truncate">
                                {{ $log->message ?? '—' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                                Nenhum log de sincronização encontrado.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
            <div class="px-4 py-3 border-t border-slate-800">
                {{ $logs->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    (() => {
        const palette = ['#22d3ee', '#60a5fa', '#34d399', '#f59e0b', '#f472b6', '#a78bfa'];

        function renderApiHourlyChart() {
            const container = document.getElementById('api-hourly-chart-container');
            const canvas = document.getElementById('api-hourly-chart');
            if (!container || !canvas || !window.Chart) return;

            const labels = JSON.parse(container.dataset.labels || '[]');
            const series = JSON.parse(container.dataset.series || '[]');
            if (!Array.isArray(series) || series.length === 0) return;

            const datasets = series.map((line, idx) => ({
                label: String(line.api || 'unknown').replaceAll('_', ' ').toUpperCase(),
                data: Array.isArray(line.values) ? line.values : [],
                borderColor: palette[idx % palette.length],
                backgroundColor: palette[idx % palette.length],
                borderWidth: 2,
                pointRadius: 2,
                pointHoverRadius: 4,
                tension: 0.25,
                fill: false,
            }));

            if (window.apiHourlyVolumeChart) {
                window.apiHourlyVolumeChart.destroy();
            }

            window.apiHourlyVolumeChart = new window.Chart(canvas.getContext('2d'), {
                type: 'line',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { color: '#cbd5e1' } },
                    },
                    scales: {
                        x: {
                            ticks: { color: '#94a3b8', maxTicksLimit: 12 },
                            grid: { color: 'rgba(30,41,59,0.7)' },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#94a3b8', precision: 0 },
                            grid: { color: 'rgba(30,41,59,0.7)' },
                        },
                    },
                },
            });
        }

        document.addEventListener('livewire:init', () => {
            renderApiHourlyChart();
            Livewire.hook('morphed', ({ el }) => {
                if (el && el.id === 'api-hourly-chart-container') {
                    renderApiHourlyChart();
                }
            });
        });

        document.addEventListener('livewire:navigated', renderApiHourlyChart);
        document.addEventListener('DOMContentLoaded', renderApiHourlyChart);
    })();
</script>
@endpush
