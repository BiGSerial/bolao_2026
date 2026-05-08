<div class="p-4 sm:p-6 lg:p-8 space-y-6 animate-fade-in">
    <div>
        <h1 class="text-2xl font-bold text-white">Controle de Grupos</h1>
        <p class="text-sm text-slate-400 mt-1">Administre todos os bolões da plataforma e desative grupos que violem regras.</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        @foreach([
            ['active', 'Ativos', 'text-emerald-400', 'border-emerald-800/30 bg-emerald-900/10'],
            ['blocked', 'Desativados', 'text-red-400', 'border-red-800/30 bg-red-900/10'],
            ['archived', 'Arquivados', 'text-slate-400', 'border-slate-700/30 bg-slate-800/20'],
        ] as [$status, $label, $color, $bg])
            <button wire:click="$set('filterStatus', '{{ $filterStatus === $status ? '' : $status }}')"
                    class="card border p-4 text-left transition-all {{ $filterStatus === $status ? $bg : 'hover:border-slate-600' }}">
                <div class="text-2xl font-black {{ $color }}">{{ $counts[$status] ?? 0 }}</div>
                <div class="text-xs text-slate-500 mt-0.5">{{ $label }}</div>
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-48">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome, slug ou código..."
                   class="input-field pl-9 py-2 text-sm">
        </div>
        @if($filterStatus)
            <button wire:click="$set('filterStatus', '')" class="btn-ghost btn-sm border border-slate-700">
                Limpar filtro ✕
            </button>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-12 lg:items-start">
        <div class="lg:col-span-7">
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                        <tr class="border-b border-slate-800">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Grupo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden md:table-cell">Dono</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden lg:table-cell">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden lg:table-cell">Membros</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Ações</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                        @forelse($pools as $pool)
                            @php
                                $isSelected = $selectedPool?->id === $pool->id;
                                $statusBadge = match($pool->status) {
                                    'active' => 'badge-green',
                                    'blocked' => 'badge-red',
                                    'archived' => 'badge-slate',
                                    default => 'badge-slate',
                                };
                                $statusLabel = match($pool->status) {
                                    'active' => 'Ativo',
                                    'blocked' => 'Desativado',
                                    'archived' => 'Arquivado',
                                    default => ucfirst((string) $pool->status),
                                };
                            @endphp
                            <tr class="transition-colors {{ $isSelected ? 'bg-emerald-900/10 ring-1 ring-inset ring-emerald-600/20' : 'hover:bg-slate-800/20' }}"
                                wire:key="pool-{{ $pool->id }}">
                                <td class="px-4 py-3.5">
                                    <button wire:click="selectPool({{ $pool->id }})" class="text-left">
                                        <p class="text-sm font-medium {{ $isSelected ? 'text-emerald-300' : 'text-slate-200' }}">{{ $pool->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $pool->slug }}</p>
                                    </button>
                                </td>
                                <td class="px-4 py-3.5 text-sm text-slate-400 hidden md:table-cell">
                                    {{ $pool->owner?->public_name ?? $pool->owner?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-sm text-slate-400 hidden lg:table-cell">
                                    <span class="font-mono">{{ $pool->invite_code }}</span>
                                </td>
                                <td class="px-4 py-3.5 text-sm text-slate-400 hidden lg:table-cell">
                                    {{ $pool->members_count }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="{{ $statusBadge }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex flex-wrap gap-1.5">
                                        @if($pool->status !== 'blocked')
                                            <button wire:click="deactivate({{ $pool->id }})"
                                                    wire:confirm="Desativar o grupo {{ addslashes($pool->name) }}?"
                                                    class="inline-flex items-center rounded-md bg-red-700/30 px-2 py-1 text-xs font-medium text-red-400 ring-1 ring-red-500/30 hover:bg-red-700/50 transition-colors">
                                                Desativar
                                            </button>
                                        @else
                                            <button wire:click="reactivate({{ $pool->id }})"
                                                    class="inline-flex items-center rounded-md bg-emerald-700/30 px-2 py-1 text-xs font-medium text-emerald-400 ring-1 ring-emerald-500/30 hover:bg-emerald-700/50 transition-colors">
                                                Reativar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                                    Nenhum grupo encontrado.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if($pools->hasPages())
                    <div class="px-4 py-3 border-t border-slate-800">
                        {{ $pools->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div class="lg:col-span-5">
            <div class="card p-4 sm:p-5 lg:sticky lg:top-20">
                @if($selectedPool)
                    @php
                        $statusBadge = match($selectedPool->status) {
                            'active' => 'badge-green',
                            'blocked' => 'badge-red',
                            'archived' => 'badge-slate',
                            default => 'badge-slate',
                        };
                    @endphp
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-white leading-tight">{{ $selectedPool->name }}</h2>
                            <p class="text-xs text-slate-500 mt-1">{{ $selectedPool->slug }}</p>
                        </div>
                        <span class="{{ $statusBadge }}">{{ $selectedPool->status }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 mt-4">
                        <div class="rounded-lg bg-pitch-800 border border-slate-700 p-3">
                            <p class="text-[11px] text-slate-500 uppercase">Convite</p>
                            <p class="font-mono text-slate-200 text-sm mt-1">{{ $selectedPool->invite_code }}</p>
                        </div>
                        <div class="rounded-lg bg-pitch-800 border border-slate-700 p-3">
                            <p class="text-[11px] text-slate-500 uppercase">Membros</p>
                            <p class="text-slate-200 text-sm mt-1">{{ $selectedPool->members_count }}</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Descrição</p>
                            <p class="text-sm text-slate-300 mt-1 whitespace-pre-line">{{ $selectedPool->description ?: 'Sem descrição cadastrada.' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Regras / Instruções</p>
                            <p class="text-sm text-slate-300 mt-1 whitespace-pre-line">{{ $selectedPool->instructions ?: 'Sem regras adicionais cadastradas.' }}</p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Participantes</p>
                        <div class="max-h-72 overflow-y-auto space-y-1 pr-1">
                            @forelse($selectedPool->members as $member)
                                <div class="rounded-lg bg-pitch-800 border border-slate-700 px-3 py-2 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm text-slate-200 truncate">{{ $member->user?->name ?? 'Usuário removido' }}</p>
                                        <p class="text-xs text-slate-500">{{ $member->role }} · {{ $member->status }}</p>
                                    </div>
                                    @if($member->sector)
                                        <span class="badge-slate">{{ $member->sector }}</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">Sem participantes.</p>
                            @endforelse
                        </div>
                    </div>
                @else
                    <p class="text-sm text-slate-500">Selecione um grupo para ver os detalhes.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@script
<script>
    $wire.on('swal:alert', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        Swal.fire({
            icon: data?.icon ?? 'info',
            title: data?.title ?? 'Aviso',
            text: data?.text ?? '',
            confirmButtonText: 'Entendi'
        });
    });
</script>
@endscript
