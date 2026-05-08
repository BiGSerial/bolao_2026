<div class="p-4 sm:p-6 lg:p-8 space-y-6 animate-fade-in">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-white">Correção Manual de Jogos</h1>
        <p class="text-sm text-slate-400 mt-1">Corrija placares e status. Os palpites são recalculados automaticamente.</p>
    </div>

    @if(session('status'))
    <div class="alert-success">{{ session('status') }}</div>
    @endif

    {{-- Filtros --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-48">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Buscar por seleção..."
                   class="input-field pl-9 py-2 text-sm">
        </div>

        <select wire:model.live="filterStatus" class="select-field w-auto py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="FINISHED">Encerrados</option>
            <option value="IN_PLAY">Ao vivo</option>
            <option value="PAUSED">Intervalo</option>
            <option value="TIMED">Agendados</option>
            <option value="SCHEDULED">Programados</option>
            <option value="POSTPONED">Adiados</option>
        </select>
    </div>

    {{-- Tabela --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-slate-800">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Data</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Partida</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Placar Real</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden sm:table-cell">Grupo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($matches as $match)
                    @php
                        $isEditing = isset($editing[$match->id]);
                        $isLive = in_array($match->status, ['IN_PLAY', 'PAUSED']);
                    @endphp
                    <tr class="transition-colors {{ $isEditing ? 'bg-slate-800/40' : 'hover:bg-slate-800/20' }}"
                        wire:key="match-{{ $match->id }}">

                        {{-- Data --}}
                        <td class="px-4 py-3.5 text-xs text-slate-400 whitespace-nowrap">
                            {{ $match->local_date?->format('d/m H:i') ?? $match->utc_date->format('d/m H:i') }}
                        </td>

                        {{-- Partida --}}
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-2">
                                @if($match->homeTeam?->crest)
                                <img src="{{ $match->homeTeam->crest }}" class="h-6 w-6 object-contain" loading="lazy">
                                @endif
                                <span class="text-sm text-slate-200 font-medium">
                                    {{ $match->homeTeam?->localized_name ?? '?' }}
                                </span>
                                <span class="text-slate-600 text-xs">vs</span>
                                <span class="text-sm text-slate-200 font-medium">
                                    {{ $match->awayTeam?->localized_name ?? '?' }}
                                </span>
                                @if($match->awayTeam?->crest)
                                <img src="{{ $match->awayTeam->crest }}" class="h-6 w-6 object-contain" loading="lazy">
                                @endif
                            </div>
                        </td>

                        {{-- Placar real --}}
                        <td class="px-4 py-3.5 text-center">
                            @if($isEditing)
                            <div class="flex items-center justify-center gap-2">
                                <input type="number" min="0" max="30"
                                       wire:model="editing.{{ $match->id }}.home"
                                       class="w-14 text-center rounded-lg bg-pitch-800 border-slate-600 text-white text-sm font-bold tabular-nums py-1 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                <span class="text-slate-500 font-bold">×</span>
                                <input type="number" min="0" max="30"
                                       wire:model="editing.{{ $match->id }}.away"
                                       class="w-14 text-center rounded-lg bg-pitch-800 border-slate-600 text-white text-sm font-bold tabular-nums py-1 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            </div>
                            @else
                            <span class="text-base font-black text-white tabular-nums">
                                @if($match->home_score_full_time !== null)
                                    {{ $match->home_score_full_time }} × {{ $match->away_score_full_time }}
                                @else
                                    <span class="text-slate-600 text-sm font-normal">— × —</span>
                                @endif
                            </span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3.5">
                            @if($isEditing)
                            <select wire:model="editing.{{ $match->id }}.status"
                                    class="text-xs rounded-lg bg-pitch-800 border-slate-600 text-slate-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 py-1">
                                <option value="TIMED">Agendado</option>
                                <option value="IN_PLAY">Ao Vivo</option>
                                <option value="PAUSED">Intervalo</option>
                                <option value="FINISHED">Encerrado</option>
                                <option value="POSTPONED">Adiado</option>
                                <option value="CANCELLED">Cancelado</option>
                            </select>
                            @else
                            @php
                                $sBadge = match($match->status) {
                                    'FINISHED'  => 'badge-slate',
                                    'IN_PLAY'   => 'badge-red',
                                    'PAUSED'    => 'badge-amber',
                                    'TIMED','SCHEDULED' => 'badge-blue',
                                    default     => 'badge-slate',
                                };
                                $sLabel = match($match->status) {
                                    'FINISHED'  => 'Encerrado',
                                    'IN_PLAY'   => 'Ao Vivo',
                                    'PAUSED'    => 'Intervalo',
                                    'TIMED'     => 'Agendado',
                                    'SCHEDULED' => 'Programado',
                                    'POSTPONED' => 'Adiado',
                                    'CANCELLED' => 'Cancelado',
                                    default     => $match->status,
                                };
                            @endphp
                            <span class="{{ $sBadge }}">{{ $sLabel }}</span>
                            @if($isLive)
                            <span class="ml-1 inline-flex h-2 w-2 rounded-full bg-red-500 animate-ping"></span>
                            @endif
                            @endif
                        </td>

                        {{-- Grupo --}}
                        <td class="px-4 py-3.5 text-xs text-slate-500 hidden sm:table-cell">
                            {{ $match->group_name ?? '—' }}
                        </td>

                        {{-- Ações --}}
                        <td class="px-4 py-3.5">
                            @if($isEditing)
                            <div class="flex gap-2">
                                <button wire:click="saveCorrection({{ $match->id }})"
                                        class="inline-flex items-center gap-1 rounded-md bg-emerald-700/30 px-2.5 py-1.5 text-xs font-medium text-emerald-400 ring-1 ring-emerald-500/30 hover:bg-emerald-700/50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Salvar
                                </button>
                                <button wire:click="cancelEdit({{ $match->id }})"
                                        class="inline-flex items-center gap-1 rounded-md bg-slate-700/50 px-2.5 py-1.5 text-xs font-medium text-slate-400 ring-1 ring-slate-600/30 hover:bg-slate-600/50 transition-colors">
                                    Cancelar
                                </button>
                            </div>
                            @else
                            <button wire:click="startEdit({{ $match->id }})"
                                    class="inline-flex items-center gap-1 rounded-md bg-slate-700/50 px-2.5 py-1.5 text-xs font-medium text-slate-400 ring-1 ring-slate-600/30 hover:bg-slate-600 hover:text-slate-200 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Editar
                            </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                            Nenhum jogo encontrado com esses filtros.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($matches->hasPages())
        <div class="px-4 py-3 border-t border-slate-800">
            {{ $matches->links() }}
        </div>
        @endif
    </div>

    <p class="text-xs text-slate-600">
        ⚠️ Ao salvar uma correção com status "Encerrado", os palpites de todos os bolões serão recalculados automaticamente.
    </p>
</div>
