<div class="p-4 sm:p-6 lg:p-8 animate-fade-in space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white">E-mails Transacionais</h1>
            <p class="text-sm text-slate-400 mt-1">Monitoramento de consumo mensal e histórico de envios (KingHost).</p>
        </div>
        <button type="button" wire:click="$refresh" class="btn-secondary">Atualizar</button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wider text-slate-500">Enviados hoje</p>
            <p class="text-2xl font-bold text-emerald-400 mt-1">{{ number_format((int) ($stats['sent_today'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wider text-slate-500">Falhas hoje</p>
            <p class="text-2xl font-bold text-red-400 mt-1">{{ number_format((int) ($stats['failed_today'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wider text-slate-500">Enviados no mês</p>
            <p class="text-2xl font-bold text-emerald-400 mt-1">{{ number_format((int) ($stats['sent_this_month'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wider text-slate-500">Falhas no mês</p>
            <p class="text-2xl font-bold text-red-400 mt-1">{{ number_format((int) ($stats['failed_this_month'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wider text-slate-500">Limite mensal</p>
            <p class="text-2xl font-bold text-amber-300 mt-1">
                {{ (int) ($stats['monthly_limit'] ?? 0) > 0 ? number_format((int) $stats['monthly_limit'], 0, ',', '.') : 'não configurado' }}
            </p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wider text-slate-500">Saldo disponível</p>
            <p class="text-2xl font-bold text-slate-100 mt-1">
                {{ ($stats['remaining_this_month'] ?? null) !== null ? number_format((int) ($stats['remaining_this_month'] ?? 0), 0, ',', '.') : 'não aplicável' }}
            </p>
        </div>
    </div>

    <div class="card p-5 space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-200">Envios semanais (7 dias)</h2>
            <div class="flex items-center gap-3 text-xs">
                <span class="inline-flex items-center gap-1 text-slate-300">
                    <span class="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-400"></span> Enviados
                </span>
                <span class="inline-flex items-center gap-1 text-slate-300">
                    <span class="inline-block h-2.5 w-2.5 rounded-sm bg-red-400"></span> Falhas
                </span>
            </div>
        </div>

        <div
            class="rounded-xl border border-slate-800 bg-slate-900/30 p-3"
            x-data="transactionalMailWeeklyChart()"
            x-init="render(@js($weeklySeries))"
            x-effect="render(@js($weeklySeries))"
        >
            <div class="h-56">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    <div class="card p-5 space-y-3">
        <h2 class="text-base font-semibold text-slate-200">Últimos envios</h2>
        <div class="overflow-auto max-h-[60vh]">
            <table class="min-w-full">
                <thead class="sticky top-0 z-10 bg-pitch-900">
                    <tr class="border-b border-slate-800">
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Quando</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Para</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Assunto</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">HTTP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($logs as $log)
                    <tr class="hover:bg-slate-800/20 transition-colors">
                        <td class="px-3 py-2 text-xs text-slate-400 whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2 text-xs text-slate-300 max-w-[14rem] truncate">{{ $log->to_address }}</td>
                        <td class="px-3 py-2 text-xs text-slate-200 max-w-[20rem] truncate" title="{{ $log->subject }}">{{ $log->subject }}</td>
                        <td class="px-3 py-2 text-xs whitespace-nowrap">
                            @if($log->status === 'sent')
                            <span class="badge-green">Enviado</span>
                            @elseif($log->status === 'failed')
                            <span class="badge-red">Falhou</span>
                            @else
                            <span class="badge-slate">{{ $log->status }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-400 whitespace-nowrap">{{ $log->last_http_status ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">Nenhum envio registrado.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
        <div class="pt-2 border-t border-slate-800">
            {{ $logs->links() }}
        </div>
        @endif
    </div>

    <script>
        function transactionalMailWeeklyChart() {
            return {
                chart: null,
                render(series) {
                    if (!this.$refs?.canvas || !window.Chart) return;
                    if (this.chart) this.chart.destroy();

                    const labels = (series || []).map((item) => item.label);
                    const sent = (series || []).map((item) => Number(item.sent || 0));
                    const failed = (series || []).map((item) => Number(item.failed || 0));

                    this.chart = new window.Chart(this.$refs.canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [
                                {
                                    label: 'Enviados',
                                    data: sent,
                                    backgroundColor: 'rgba(52, 211, 153, 0.85)',
                                    borderColor: 'rgba(52, 211, 153, 1)',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                },
                                {
                                    label: 'Falhas',
                                    data: failed,
                                    backgroundColor: 'rgba(248, 113, 113, 0.85)',
                                    borderColor: 'rgba(248, 113, 113, 1)',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    grid: { color: 'rgba(148, 163, 184, 0.08)' },
                                    ticks: { color: '#94a3b8' },
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: { color: 'rgba(148, 163, 184, 0.12)' },
                                    ticks: { color: '#94a3b8', precision: 0 },
                                },
                            },
                            plugins: {
                                legend: {
                                    labels: { color: '#cbd5e1' },
                                },
                            },
                        },
                    });
                },
            };
        }
    </script>
</div>
