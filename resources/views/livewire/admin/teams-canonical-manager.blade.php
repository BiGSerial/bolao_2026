<div class="p-4 sm:p-6 lg:p-8 space-y-6 animate-fade-in">
    <div>
        <h1 class="text-2xl font-bold text-white">Times Canônicos (Brasil)</h1>
        <p class="text-sm text-slate-400 mt-1">Edite o nome oficial exibido no app (ex.: Atlético-MG, Athletico-PR).</p>
    </div>

    <div class="relative max-w-xl">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome, sigla ou canônico..."
               class="input-field pl-9 py-2 text-sm">
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                <tr class="border-b border-slate-800">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Time</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Sigla</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Nome canônico BR</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Ação</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                @forelse($teams as $team)
                    <tr wire:key="team-{{ $team->id }}" class="hover:bg-slate-800/20 transition-colors">
                        <td class="px-4 py-3 text-sm text-slate-200">
                            <div class="flex items-center gap-2">
                                @if($team->crest)
                                    <img src="{{ $team->crest }}" alt="" class="h-5 w-5 object-contain">
                                @endif
                                <div>
                                    <p class="font-medium">{{ $team->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $team->short_name ?: '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-400 font-mono">{{ $team->tla ?: '—' }}</td>
                        <td class="px-4 py-3 min-w-[280px]">
                            <input type="text"
                                   wire:model.defer="canonicalNames.{{ $team->id }}"
                                   placeholder="Ex.: Atlético-MG"
                                   class="input-field py-2 text-sm">
                        </td>
                        <td class="px-4 py-3">
                            <button wire:click="saveTeam({{ $team->id }})" class="btn-primary btn-sm">
                                Salvar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-500">Nenhum time encontrado.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($teams->hasPages())
            <div class="px-4 py-3 border-t border-slate-800">
                {{ $teams->links() }}
            </div>
        @endif
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
