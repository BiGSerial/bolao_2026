<div class="p-4 sm:p-6 lg:p-8 animate-fade-in">

    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('pools.index', ['competition' => $competition_code]) }}"
           class="flex items-center justify-center h-9 w-9 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-slate-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-white">Criar Bolão</h1>
            <p class="text-sm text-slate-400 mt-0.5">Configure seu grupo de palpites</p>
        </div>
    </div>

    <form wire:submit="save" class="max-w-2xl space-y-6">

        {{-- Informações básicas --}}
        <div class="card p-6 space-y-5">
            <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Informações Básicas</h2>

            <div>
                <label class="label">Competição <span class="text-red-400">*</span></label>
                <div class="input-field flex items-center justify-between bg-slate-900/60 text-slate-200">
                    <span>{{ $competitionName }}</span>
                    <span class="text-xs text-emerald-400 font-semibold">{{ $competition_code }}</span>
                </div>
                <input type="hidden" wire:model="competition_code">
            </div>

            <div>
                <label class="label">Nome do Bolão <span class="text-red-400">*</span></label>
                <input type="text" wire:model="name"
                       placeholder="Ex: Copa da Família, Bolão do Trabalho..."
                       class="input-field">
                @error('name') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">Descrição</label>
                <textarea wire:model="description" rows="3"
                          placeholder="Descreva brevemente o seu bolão..."
                          class="input-field resize-none"></textarea>
                @error('description') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">Instruções / Regulamento</label>
                <textarea wire:model="instructions" rows="5"
                          placeholder="Regras de desempate, premiação, critérios especiais..."
                          class="input-field resize-none"></textarea>
                <p class="mt-1 text-xs text-slate-600">Visível para todos os membros do bolão.</p>
                @error('instructions') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">Visibilidade</label>
                <select wire:model="visibility" class="select-field">
                    <option value="private">🔒 Privado — apenas convidados diretos</option>
                    <option value="invite_only">🔗 Somente Convite — entra com código</option>
                    <option value="public">🌐 Público — aparece na lista pública</option>
                </select>
                @error('visibility') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Setores --}}
        <div class="card p-6 space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Setores / Departamentos</h2>
                <p class="text-xs text-slate-500 mt-1">Opcional. Permite categorizar participantes por setor dentro do bolão.</p>
            </div>

            @if(!empty($sectors))
            <div class="flex flex-wrap gap-2">
                @foreach($sectors as $i => $sector)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-700 px-3 py-1 text-sm text-slate-200 ring-1 ring-slate-600">
                    {{ $sector }}
                    <button type="button" wire:click="removeSector({{ $i }})"
                            class="text-slate-400 hover:text-red-400 transition-colors ml-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </span>
                @endforeach
            </div>
            @endif

            <div class="flex gap-2">
                <input type="text" wire:model.defer="newSector"
                       wire:keydown.enter.prevent="addSector"
                       placeholder="Ex: Tecnologia, Vendas, RH..."
                       class="input-field flex-1">
                <button type="button" wire:click="addSector"
                        class="btn-secondary shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Adicionar
                </button>
            </div>

            @if($sectorFeedback !== '')
            <p class="text-xs {{ $sectorFeedbackType === 'success' ? 'text-emerald-400' : 'text-red-400' }}">
                {{ $sectorFeedback }}
            </p>
            @endif
        </div>

        {{-- Configurações --}}
        <div class="card p-6 space-y-5">
            <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Configurações de Palpites</h2>

            <div>
                <label class="label">Bloquear palpites (minutos antes do jogo)</label>
                <input type="number" min="10" wire:model="prediction_lock_minutes"
                       class="input-field">
                <p class="mt-1 text-xs text-slate-600">Mínimo: 10 minutos</p>
                @error('prediction_lock_minutes') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer group">
                    <div class="relative">
                        <input type="checkbox" wire:model="allow_prediction_changes" class="sr-only peer">
                        <div class="h-5 w-9 rounded-full bg-slate-700 peer-checked:bg-emerald-600 transition-colors"></div>
                        <div class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-200">Permitir alteração de palpites</p>
                        <p class="text-xs text-slate-500">Membros podem editar palpites até o bloqueio</p>
                    </div>
                </label>

                <label class="flex items-center gap-3 cursor-pointer group">
                    <div class="relative">
                        <input type="checkbox" wire:model="allow_pending_member_predictions" class="sr-only peer">
                        <div class="h-5 w-9 rounded-full bg-slate-700 peer-checked:bg-emerald-600 transition-colors"></div>
                        <div class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-200">Palpites de membros pendentes</p>
                        <p class="text-xs text-slate-500">Membros aguardando aprovação podem palpitar</p>
                    </div>
                </label>
            </div>

            <div class="rounded-xl border border-slate-700/70 bg-pitch-900/40 p-4 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-200">Regras de Pontuação do Ranking</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Defina os pontos do seu grupo para cada tipo de acerto.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="label">Resultado + placar</label>
                        <input type="number" min="0" max="20" wire:model="points_exact_score" class="input-field">
                        @error('points_exact_score') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Resultado (vencedor/empate)</label>
                        <input type="number" min="0" max="20" wire:model="points_correct_result" class="input-field">
                        @error('points_correct_result') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Gols de um time</label>
                        <input type="number" min="0" max="20" wire:model="points_correct_goals" class="input-field">
                        <p class="mt-1 text-xs text-slate-600">Valor aplicado para mandante e visitante.</p>
                        @error('points_correct_goals') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-700/70 bg-pitch-900/40 p-4 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-200">Critérios de desempate</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Opcional. Apenas critérios com pontuação maior que zero aparecem aqui.
                    </p>
                </div>

                <div class="flex gap-2">
                    <select wire:model="newTieBreaker" class="select-field flex-1">
                        <option value="">Selecionar critério...</option>
                        @foreach($availableTieBreakers as $criterion)
                        <option value="{{ $criterion }}">{{ $tieBreakerLabels[$criterion] ?? $criterion }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="addTieBreaker" class="btn-secondary shrink-0">
                        Adicionar
                    </button>
                </div>

                @if(empty($tieBreakers))
                <div wire:transition class="rounded-lg border border-dashed border-slate-700 p-4 text-xs text-slate-500">
                    Sem desempate configurado. O ranking usará apenas pontuação total.
                </div>
                @else
                <div
                    wire:sort="reorderTieBreakers"
                    class="space-y-2"
                >
                    @foreach($tieBreakers as $index => $criterion)
                    <div wire:key="create-tie-breaker-{{ $criterion }}"
                         wire:sort:item="{{ $criterion }}"
                         wire:transition
                         class="sortable-card-item cursor-grab active:cursor-grabbing flex items-center justify-between gap-3 rounded-lg border border-slate-700 bg-slate-800/40 p-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="text-slate-500 hover:text-slate-300 transition-colors"
                                  title="Arraste para reordenar">
                                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M7 4a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm-1.5 7.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10-13.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm-1.5 7.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm1.5 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/>
                                </svg>
                            </span>
                            <span class="text-xs rounded-md bg-emerald-900/30 text-emerald-300 px-2 py-0.5">
                                Prioridade {{ $index + 1 }}
                            </span>
                            <span class="text-sm text-slate-200 truncate">
                                {{ $tieBreakerLabels[$criterion] ?? $criterion }}
                            </span>
                        </div>
                        <button type="button" wire:click="removeTieBreaker({{ $index }})"
                                class="text-slate-500 hover:text-red-400 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    @endforeach
                </div>
                @endif

                @error('tieBreakers') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                @error('tieBreakers.*') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('pools.index', ['competition' => $competition_code]) }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:loading.class="opacity-75">
                <svg wire:loading wire:target="save" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <svg wire:loading.remove wire:target="save" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Criar Bolão
            </button>
        </div>
    </form>
</div>

@script
<script>
    $wire.on('swal:toast', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;

        Swal.fire({
            toast: true,
            position: 'top-end',
            timer: 2200,
            timerProgressBar: true,
            showConfirmButton: false,
            icon: data?.icon ?? 'info',
            title: data?.title ?? 'Aviso',
            text: data?.text ?? ''
        });
    });
</script>
@endscript
