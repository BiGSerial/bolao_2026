<div class="animate-fade-in" wire:poll.30s="refreshMatches">

    {{-- Pool Header --}}
    <div class="border-b border-slate-800 bg-pitch-900/50 backdrop-blur-sm sticky top-0 z-20">
        <div class="px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('pools.index') }}"
                       class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-slate-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div class="min-w-0">
                        <h1 class="text-lg font-bold text-white truncate">{{ $pool->name }}</h1>
                        <p class="text-xs text-slate-400 flex items-center gap-2">
                            <span>{{ ucfirst($member->status) }}</span>
                            @if($pool->instructions)
                            <button wire:click="$toggle('showInstructions')" class="text-emerald-400 hover:text-emerald-300 transition-colors">
                                📋 Instruções
                            </button>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if($myRanking)
                    <div class="hidden sm:flex flex-col items-center rounded-lg bg-slate-800 px-3 py-1.5">
                        <span class="text-xs text-slate-500">Minha posição</span>
                        <span class="text-lg font-black text-white">#{{ $myRanking->position ?? '—' }}</span>
                    </div>
                    <div class="hidden sm:flex flex-col items-center rounded-lg bg-slate-800 px-3 py-1.5">
                        <span class="text-xs text-slate-500">Pontos</span>
                        <span class="text-lg font-black text-emerald-400">{{ $myRanking->points_total }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Instructions panel --}}
            @if($showInstructions && $pool->instructions)
            <div x-transition class="mt-3 rounded-lg bg-blue-950/40 border border-blue-800/30 p-4">
                <p class="text-xs font-semibold text-blue-400 mb-2 uppercase tracking-wider">📋 Instruções / Regulamento</p>
                <p class="text-sm text-slate-300 whitespace-pre-line leading-relaxed">{{ $pool->instructions }}</p>
            </div>
            @endif

            {{-- Tabs --}}
            <div class="flex gap-6 mt-3 border-b border-slate-800 -mb-4">
                @foreach([
                    ['jogos', '⚽ Jogos', null],
                    ['ranking', '🏆 Ranking', null],
                    ['resumo', '📊 Resumo', null],
                ] as [$tab, $label, $count])
                <button wire:click="setTab('{{ $tab }}')"
                        class="tab-btn {{ $activeTab === $tab ? 'active' : '' }}">
                    {{ $label }}
                </button>
                @endforeach

                <a href="{{ route('pools.members', $pool->slug) }}"
                   class="tab-btn ml-auto hidden sm:block">
                    👥 Participantes
                </a>
                @if(in_array($member->role, ['owner', 'manager']))
                <a href="{{ route('pools.settings', $pool->slug) }}" class="tab-btn">
                    ⚙️ Config
                </a>
                @endif
            </div>

            {{-- Live scores ticker --}}
            <div class="mt-4 pb-1"
                 x-data="{
                    animated: false,
                    init() {
                        const track = this.$refs.track;
                        if (!track) return;
                        this.$nextTick(() => {
                            this.animated = track.scrollWidth > track.clientWidth;
                        });
                    }
                 }">
                @if($nearestTickerMatches->isNotEmpty())
                @php
                    $duplicateTickerItems = $nearestTickerMatches->count() > 3;
                @endphp
                <div class="relative overflow-hidden rounded-lg border border-slate-800 bg-slate-900/40">
                    <div class="absolute left-0 top-0 bottom-0 w-8 bg-gradient-to-r from-pitch-900/90 to-transparent pointer-events-none z-10"></div>
                    <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-pitch-900/90 to-transparent pointer-events-none z-10"></div>

                    <div class="px-2 py-2"
                         :class="animated ? 'ticker-mask' : ''">
                        <div class="flex items-center gap-2"
                             x-ref="track"
                             :class="animated ? 'ticker-track' : ''">
                            @foreach($nearestTickerMatches as $matchTicker)
                            @php
                                $isLiveTicker = in_array($matchTicker->status, ['IN_PLAY', 'PAUSED'], true);
                                $isFinishedTicker = $matchTicker->status === 'FINISHED';
                                $statusBadgeClass = 'bg-slate-900/40 text-slate-500 border-slate-700/40';
                                $statusLabel = $isLiveTicker ? 'Ao vivo' : ($isFinishedTicker ? 'Encerrado' : 'Agendado');
                            @endphp
                            <div class="shrink-0 w-[240px] rounded-md border border-slate-700 bg-pitch-900/70 px-2.5 py-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                        @if($matchTicker->homeTeam?->crest)
                                        <img src="{{ $matchTicker->homeTeam->crest }}" alt="{{ $matchTicker->homeTeam->tla }}" class="h-6 w-6 object-contain shrink-0" loading="lazy">
                                        @endif
                                    </div>

                                    <span class="text-[12px] font-bold text-slate-300">vs</span>

                                    <div class="flex items-center gap-1.5 min-w-0 flex-1 justify-end">
                                        @if($matchTicker->awayTeam?->crest)
                                        <img src="{{ $matchTicker->awayTeam->crest }}" alt="{{ $matchTicker->awayTeam->tla }}" class="h-6 w-6 object-contain shrink-0" loading="lazy">
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-1 flex items-center justify-between">
                                    <div class="text-[10px] font-medium text-slate-500">
                                        {{ ($matchTicker->local_date ?? $matchTicker->utc_date)?->timezone('America/Sao_Paulo')->format('d/m H:i') }}
                                    </div>

                                    <div class="flex items-center gap-2">
                                        @if($isLiveTicker || $isFinishedTicker)
                                        <span class="text-[14px] font-black text-white tabular-nums leading-none">
                                            {{ $matchTicker->home_score_full_time ?? 0 }} x {{ $matchTicker->away_score_full_time ?? 0 }}
                                        </span>
                                        @endif
                                        <span class="inline-flex items-center rounded-full border px-1.5 py-0 text-[9px] font-normal tracking-normal uppercase {{ $statusBadgeClass }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            @endforeach

                            @if($duplicateTickerItems)
                                @foreach($nearestTickerMatches as $matchTicker)
                                @php
                                    $isLiveTicker = in_array($matchTicker->status, ['IN_PLAY', 'PAUSED'], true);
                                    $isFinishedTicker = $matchTicker->status === 'FINISHED';
                                    $statusBadgeClass = 'bg-slate-900/40 text-slate-500 border-slate-700/40';
                                    $statusLabel = $isLiveTicker ? 'Ao vivo' : ($isFinishedTicker ? 'Encerrado' : 'Agendado');
                                @endphp
                                <div class="shrink-0 w-[240px] rounded-md border border-slate-700 bg-pitch-900/70 px-2.5 py-1.5">
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                            @if($matchTicker->homeTeam?->crest)
                                            <img src="{{ $matchTicker->homeTeam->crest }}" alt="{{ $matchTicker->homeTeam->tla }}" class="h-6 w-6 object-contain shrink-0" loading="lazy">
                                            @endif
                                        </div>

                                        <span class="text-[12px] font-bold text-slate-300">vs</span>

                                        <div class="flex items-center gap-1.5 min-w-0 flex-1 justify-end">
                                            @if($matchTicker->awayTeam?->crest)
                                            <img src="{{ $matchTicker->awayTeam->crest }}" alt="{{ $matchTicker->awayTeam->tla }}" class="h-6 w-6 object-contain shrink-0" loading="lazy">
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-1 flex items-center justify-between">
                                        <div class="text-[10px] font-medium text-slate-500">
                                            {{ ($matchTicker->local_date ?? $matchTicker->utc_date)?->timezone('America/Sao_Paulo')->format('d/m H:i') }}
                                        </div>

                                        <div class="flex items-center gap-2">
                                            @if($isLiveTicker || $isFinishedTicker)
                                            <span class="text-[14px] font-black text-white tabular-nums leading-none">
                                                {{ $matchTicker->home_score_full_time ?? 0 }} x {{ $matchTicker->away_score_full_time ?? 0 }}
                                            </span>
                                            @endif
                                            <span class="inline-flex items-center rounded-full border px-1.5 py-0 text-[9px] font-normal tracking-normal uppercase {{ $statusBadgeClass }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
                @else
                <div class="rounded-lg border border-slate-800 bg-slate-900/30 px-3 py-2 text-xs text-slate-500">
                    Sem jogos da primeira rodada no momento.
                </div>
                @endif
            </div>
        </div>
    </div>

    @once
    <style>
        .ticker-mask {
            mask-image: linear-gradient(to right, transparent 0, black 16px, black calc(100% - 16px), transparent 100%);
            -webkit-mask-image: linear-gradient(to right, transparent 0, black 16px, black calc(100% - 16px), transparent 100%);
        }
        .ticker-track {
            width: max-content;
            animation: live-score-ticker 28s linear infinite;
        }
        @keyframes live-score-ticker {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }
    </style>
    @endonce

    {{-- Flash messages --}}
    @if(session('status'))
    <div class="mx-4 sm:mx-6 lg:mx-8 mt-4">
        <div class="alert-success">{{ session('status') }}</div>
    </div>
    @endif

    {{-- Tab: Jogos e Palpites --}}
    @if($activeTab === 'jogos')
    <div class="p-4 sm:p-6 lg:p-8 space-y-6">

        {{-- Stats bar --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="stat-card">
                <span class="stat-value">{{ $totalMatches }}</span>
                <span class="stat-label">Jogos</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-emerald-400">{{ $predictedCount }}</span>
                <span class="stat-label">Palpites</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-amber-400">{{ $pool->activeMembers()->count() }}</span>
                <span class="stat-label">Membros</span>
            </div>
        </div>

        {{-- Bulk prediction --}}
        <div class="card p-4" x-data="{ open: false, bh: '', ba: '' }">
            <button @click="open = !open"
                    class="flex w-full items-center justify-between text-sm font-medium text-slate-300 hover:text-white transition-colors">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Palpite em Massa
                </span>
                <svg class="w-4 h-4 text-slate-500 transition-transform duration-200" :class="open && 'rotate-180'"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-cloak x-transition class="mt-4 space-y-3">
                <p class="text-xs text-slate-500">Aplica o mesmo palpite para todos os jogos não bloqueados.</p>

                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-0">
                        <label class="label text-xs">Grupo (opcional)</label>
                        <select wire:model="bulkGroup" class="select-field">
                            <option value="">Todos os jogos</option>
                            @foreach($groupedMatches->keys() as $group)
                            <option value="{{ $group }}">{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <div>
                            <label class="label text-xs">Mandante</label>
                            <input type="number" min="0" max="30" x-model="bh"
                                   class="input-field w-16 text-center tabular-nums">
                        </div>
                        <span class="pb-2.5 text-slate-600 text-lg">×</span>
                        <div>
                            <label class="label text-xs">Visitante</label>
                            <input type="number" min="0" max="30" x-model="ba"
                                   class="input-field w-16 text-center tabular-nums">
                        </div>
                    </div>

                    <button @click="$wire.applyBulkPrediction(bh, ba)"
                            class="btn-secondary shrink-0">
                        Aplicar
                    </button>
                </div>
            </div>
        </div>

        {{-- Groups --}}
        @forelse($groupedMatches as $group => $matches)
        <div>
            <div class="flex items-center gap-3 mb-3">
                <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">{{ $group }}</h2>
                <div class="flex-1 h-px bg-slate-800"></div>
                <span class="text-xs text-slate-600">{{ $matches->count() }} jogo{{ $matches->count() > 1 ? 's' : '' }}</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                @foreach($matches->groupBy('matchday') as $matchday => $matchdayMatches)
                <div class="card p-3">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                            Rodada {{ $matchday ?: '—' }}
                        </span>
                        <div class="h-px flex-1 bg-slate-800/70"></div>
                    </div>

                    <div class="space-y-3">
                    @foreach($matchdayMatches as $match)
                @php
                    $prediction = $predictions->get($match->id);
                    $predStatus = $predictionStatuses[$match->id];
                    $isLive = in_array($match->status, ['IN_PLAY', 'PAUSED']);
                    $isFinished = $match->status === 'FINISHED';
                    $isLocked = in_array($predStatus, ['bloqueado', 'calculado', 'finalizado', 'inelegivel']);
                @endphp
                <div class="card {{ $isLive ? 'ring-1 ring-red-500/30' : '' }} overflow-hidden h-full flex flex-col">
                    {{-- Match status bar --}}
                    <div class="flex items-center justify-between px-3 pt-2.5 pb-1">
                        <div class="flex items-center gap-2">
                            @if($isLive)
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                            </span>
                            <span class="text-xs font-semibold text-red-400">{{ $statusLabels[$match->id] }}</span>
                            @elseif($isFinished)
                            <span class="text-xs text-slate-600">✓ {{ $statusLabels[$match->id] }}</span>
                            @else
                            <span class="text-xs text-slate-500">{{ $statusLabels[$match->id] }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] text-slate-600">
                                {{ $match->local_date?->format('d/m H:i') ?? $match->utc_date->format('d/m H:i') }}
                            </span>
                            @php
                            $badgeClass = match($predStatus) {
                                'aberto' => 'badge-green',
                                'calculado' => 'badge-amber',
                                'bloqueado' => 'badge-slate',
                                'sem_palpite' => 'badge-red',
                                default => 'badge-slate',
                            };
                            $badgeLabel = match($predStatus) {
                                'aberto' => 'Aberto',
                                'calculado' => $prediction ? $prediction->points.'pts' : 'Calculado',
                                'bloqueado' => 'Bloqueado',
                                'sem_palpite' => 'Sem palpite',
                                'finalizado' => 'Aguardando',
                                'inelegivel' => 'Inelegível',
                                default => ucfirst($predStatus),
                            };
                            @endphp
                            <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
                        </div>
                    </div>

                    {{-- Match scoreboard --}}
                    <div class="px-3 py-2 flex-1">
                        <div class="flex items-center gap-2">

                            {{-- Home team --}}
                            <div class="flex flex-1 items-center gap-2 min-w-0">
                                @if($match->homeTeam?->crest)
                                <img src="{{ $match->homeTeam->crest }}" alt="{{ $match->homeTeam->tla }}"
                                     class="h-8 w-8 object-contain shrink-0 drop-shadow" loading="lazy">
                                @else
                                <div class="h-8 w-8 shrink-0 rounded-full bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-300">
                                    {{ $match->homeTeam?->tla ?? '?' }}
                                </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-slate-100 truncate">
                                        {{ $this->teamDisplayName($match->homeTeam) }}
                                    </p>
                                    @if($isLive || $isFinished)
                                    <p class="text-lg font-black text-white tabular-nums leading-none">
                                        {{ $match->home_score_full_time ?? 0 }}
                                    </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Center --}}
                            <div class="flex flex-col items-center gap-1 shrink-0 px-1">
                                @if($isLive || $isFinished)
                                <div class="text-xs text-slate-600">PLACAR</div>
                                @else
                                <div class="text-xs font-bold text-slate-600 bg-slate-800 rounded px-2 py-0.5">VS</div>
                                @endif
                            </div>

                            {{-- Away team --}}
                            <div class="flex flex-1 items-center gap-2 min-w-0 flex-row-reverse">
                                @if($match->awayTeam?->crest)
                                <img src="{{ $match->awayTeam->crest }}" alt="{{ $match->awayTeam->tla }}"
                                     class="h-8 w-8 object-contain shrink-0 drop-shadow" loading="lazy">
                                @else
                                <div class="h-8 w-8 shrink-0 rounded-full bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-300">
                                    {{ $match->awayTeam?->tla ?? '?' }}
                                </div>
                                @endif
                                <div class="min-w-0 text-right">
                                    <p class="text-[13px] font-semibold text-slate-100 truncate">
                                        {{ $this->teamDisplayName($match->awayTeam) }}
                                    </p>
                                    @if($isLive || $isFinished)
                                    <p class="text-lg font-black text-white tabular-nums leading-none">
                                        {{ $match->away_score_full_time ?? 0 }}
                                    </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Prediction input --}}
                    @if(!$isLocked || $prediction)
                    <div class="border-t border-slate-800 px-3 py-2 bg-slate-900/30">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs text-slate-500 shrink-0">Meu palpite:</span>
                            <form wire:submit="savePrediction({{ $match->id }})" class="flex items-center gap-1.5 flex-1 min-w-[170px]">
                                <input type="number" min="0" max="30"
                                       wire:model="scores.{{ $match->id }}.home"
                                       @if($isLocked) disabled @endif
                                       class="w-10 text-center rounded-md text-xs font-bold tabular-nums
                                              {{ $isLocked
                                                 ? 'bg-slate-800/50 border-slate-700 text-slate-400 cursor-not-allowed'
                                                 : 'bg-pitch-800 border-slate-700 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500' }}
                                              border py-1 transition-colors">
                                <span class="text-slate-600 font-bold text-xs">×</span>
                                <input type="number" min="0" max="30"
                                       wire:model="scores.{{ $match->id }}.away"
                                       @if($isLocked) disabled @endif
                                       class="w-10 text-center rounded-md text-xs font-bold tabular-nums
                                              {{ $isLocked
                                                 ? 'bg-slate-800/50 border-slate-700 text-slate-400 cursor-not-allowed'
                                                 : 'bg-pitch-800 border-slate-700 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500' }}
                                              border py-1 transition-colors">
                                @if(!$isLocked)
                                <button type="submit"
                                        class="btn-primary !px-2.5 !py-1 text-xs"
                                        wire:loading.attr="disabled" wire:target="savePrediction({{ $match->id }})">
                                    <svg wire:loading wire:target="savePrediction({{ $match->id }})" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg wire:loading.remove wire:target="savePrediction({{ $match->id }})" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Salvar
                                </button>
                                @endif
                            </form>

                            @if($prediction && $predStatus === 'calculado')
                            <div class="flex items-center gap-1 shrink-0">
                                <span class="text-xs text-slate-500">Pontos:</span>
                                <span class="text-sm font-bold text-amber-400">{{ $prediction->points }}</span>
                            </div>
                            @endif
                        </div>
                        @error('scores.'.$match->id)
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif
                </div>
                    @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @empty
        <div class="card p-12 text-center">
            <p class="text-slate-500">Nenhum jogo disponível para esta fase.</p>
        </div>
        @endforelse
    </div>
    @endif

    {{-- Tab: Ranking --}}
    @if($activeTab === 'ranking')
    <div class="p-4 sm:p-6 lg:p-8">
        @if($rankings->isEmpty())
        <div class="card p-12 text-center">
            <div class="flex flex-col items-center gap-4">
                <div class="text-4xl">🏆</div>
                <p class="text-slate-400">O ranking ainda não foi calculado.</p>
                <p class="text-sm text-slate-600">Os pontos são calculados automaticamente após cada jogo.</p>
            </div>
        </div>
        @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-800">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Participante</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Pts</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase hidden sm:table-cell">Exatos</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase hidden sm:table-cell">Resultados</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase hidden md:table-cell">Palpites</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach($rankings as $row)
                        @php
                            $isMe = $row->user_id === Auth::id();
                            $medal = match($row->position) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => null };
                        @endphp
                        <tr class="{{ $isMe ? 'bg-emerald-900/10 ring-1 ring-inset ring-emerald-700/20' : 'hover:bg-slate-800/30' }} transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-1.5">
                                    @if($medal)
                                    <span class="text-base">{{ $medal }}</span>
                                    @else
                                    <span class="text-sm font-semibold text-slate-500">{{ $row->position }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 shrink-0 rounded-full flex items-center justify-center text-xs font-bold uppercase
                                                {{ $isMe ? 'bg-emerald-700 text-white' : 'bg-slate-700 text-slate-300' }}">
                                        {{ mb_substr($row->user?->name ?? '?', 0, 2) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium {{ $isMe ? 'text-emerald-400' : 'text-slate-200' }}">
                                            {{ $row->user?->name ?? '—' }}
                                            @if($isMe) <span class="text-xs text-slate-500">(você)</span> @endif
                                        </p>
                                        @if($row->user?->area)
                                        <p class="text-xs text-slate-600">{{ $row->user->area }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <span class="text-base font-bold {{ $isMe ? 'text-emerald-400' : 'text-white' }}">
                                    {{ $row->points_total }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right text-sm text-slate-400 hidden sm:table-cell">
                                {{ $row->exact_scores }}
                            </td>
                            <td class="px-4 py-3.5 text-right text-sm text-slate-400 hidden sm:table-cell">
                                {{ $row->correct_results }}
                            </td>
                            <td class="px-4 py-3.5 text-right text-sm text-slate-500 hidden md:table-cell">
                                {{ $row->predictions_counted }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Tab: Resumo --}}
    @if($activeTab === 'resumo')
    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="stat-card">
                <span class="stat-value">{{ $totalMatches }}</span>
                <span class="stat-label">Total de Jogos</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-emerald-400">{{ $predictedCount }}</span>
                <span class="stat-label">Meus Palpites</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-amber-400">{{ $pool->activeMembers()->count() }}</span>
                <span class="stat-label">Membros Ativos</span>
            </div>
            <div class="stat-card">
                <span class="stat-value">{{ $myRanking?->position ?? '—' }}</span>
                <span class="stat-label">Minha Posição</span>
            </div>
        </div>

        @if($myRanking)
        <div class="card p-6">
            <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Meu Desempenho</h2>
            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                <div class="flex flex-col gap-1 rounded-lg bg-slate-800 p-4">
                    <span class="text-2xl font-black text-amber-400">{{ $myRanking->points_total }}</span>
                    <span class="text-xs text-slate-500">Pontos Totais</span>
                </div>
                <div class="flex flex-col gap-1 rounded-lg bg-slate-800 p-4">
                    <span class="text-2xl font-black text-emerald-400">{{ $myRanking->exact_scores }}</span>
                    <span class="text-xs text-slate-500">Placares Exatos (5pts)</span>
                </div>
                <div class="flex flex-col gap-1 rounded-lg bg-slate-800 p-4">
                    <span class="text-2xl font-black text-blue-400">{{ $myRanking->correct_results }}</span>
                    <span class="text-xs text-slate-500">Resultados Corretos (3pts)</span>
                </div>
                <div class="flex flex-col gap-1 rounded-lg bg-slate-800 p-4">
                    <span class="text-2xl font-black text-slate-300">{{ $myRanking->predictions_counted }}</span>
                    <span class="text-xs text-slate-500">Palpites Válidos</span>
                </div>
            </div>
        </div>
        @endif

        @if($pool->description || $pool->instructions)
        <div class="card p-6 space-y-4">
            @if($pool->description)
            <div>
                <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-2">Descrição</h2>
                <p class="text-sm text-slate-300 leading-relaxed">{{ $pool->description }}</p>
            </div>
            @endif
            @if($pool->instructions)
            <div>
                <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-2">📋 Instruções</h2>
                <p class="text-sm text-slate-300 whitespace-pre-line leading-relaxed">{{ $pool->instructions }}</p>
            </div>
            @endif
        </div>
        @endif

        @if(in_array($member->role, ['owner', 'manager']))
        <div class="card p-5 flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-slate-200">Código de Convite</p>
                <p class="text-xs text-slate-500 mt-0.5">Compartilhe para novos participantes entrarem</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="font-mono text-xl font-bold text-emerald-400 tracking-widest">{{ $pool->invite_code }}</span>
            </div>
        </div>
        @endif
    </div>
    @endif

</div>
