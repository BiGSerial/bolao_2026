<div class="animate-fade-in"
     wire:poll.30s="refreshMatches"
     x-data="{
         touchStartX: 0,
         touchEndX: 0,
         swipe() {
             const diff = this.touchStartX - this.touchEndX;
             if (Math.abs(diff) < 50) return;
             const tabs = ['jogos', 'ranking', 'resumo'];
             const i = tabs.indexOf($wire.activeTab);
             if (diff > 0 && i < tabs.length - 1) $wire.setTab(tabs[i + 1]);
             else if (diff < 0 && i > 0) $wire.setTab(tabs[i - 1]);
         }
     }"
     @touchstart.passive="touchStartX = $event.changedTouches[0].screenX"
     @touchend.passive="touchEndX = $event.changedTouches[0].screenX; swipe()">

    @include('livewire.pools.partials.pool-header-nav', [
        'pool' => $pool,
        'activeItem' => $activeTab,
        'memberStatus' => $member->status,
        'memberRole' => $member->role,
        'myRanking' => $myRanking,
        'showBulkAction' => false,
        'showInstructionsToggle' => true,
    ])

    @php
        $competitionCode = strtoupper((string) request()->query('competition', session('competition', config('football-data.default_competition_code', 'WC'))));
        $tabLabel = match($activeTab) {
            'ranking' => 'Ranking',
            'resumo' => 'Resumo',
            default => 'Jogos',
        };
    @endphp
    <div class="mx-3 sm:mx-6 lg:mx-8 mt-2">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500">
            <a href="{{ route('dashboard', ['competition' => $competitionCode]) }}" class="hover:text-slate-300 transition-colors">Início</a>
            <i class="ti ti-chevron-right text-[10px] text-slate-600"></i>
            <a href="{{ route('pools.index', ['competition' => $competitionCode]) }}" class="hover:text-slate-300 transition-colors">Meus Bolões</a>
            <i class="ti ti-chevron-right text-[10px] text-slate-600"></i>
            <a href="{{ route('pools.show', ['pool' => $pool->slug, 'competition' => $competitionCode]) }}" class="hover:text-slate-300 transition-colors">{{ $pool->name }}</a>
            <i class="ti ti-chevron-right text-[10px] text-slate-600"></i>
            <span class="text-slate-300 font-medium">{{ $tabLabel }}</span>
        </nav>
    </div>

    @if($showInstructions && $pool->instructions)
    <div x-transition class="mx-3 sm:mx-6 lg:mx-8 mt-2 rounded-lg bg-blue-950/40 border border-blue-800/30 p-4">
        <p class="text-xs font-semibold text-blue-400 mb-2 uppercase tracking-wider">📋 Instruções / Regulamento</p>
        <p class="text-sm text-slate-300 whitespace-pre-line leading-relaxed">{{ $pool->instructions }}</p>
    </div>
    @endif


    @once
    <style>
        /* ── Ranking layout ── */
        .ranking-layout { display: flex; flex-direction: column; gap: 1rem; }
        .ranking-main { width: 100%; min-width: 0; }
        .ranking-side { width: 100%; }
        @media (min-width: 1024px) {
            .ranking-layout { flex-direction: row; align-items: flex-start; gap: 1.5rem; }
            .ranking-main { flex: 1 1 0%; min-width: 0; }
            .ranking-side { flex: 0 0 220px; width: 220px; }
        }


        /* ── Header stat pills ── */
        .pool-header-root {
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
            background: #13161b;
            backdrop-filter: blur(6px);
        }
        .pool-header-shell {
            padding: 0.35rem 0.75rem 0.3rem;
        }
        .pool-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .pool-name-group {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            min-width: 0;
            flex: 1 1 auto;
        }
        .pool-back-btn {
            width: 1.7rem;
            height: 1.7rem;
            border-radius: 0.4rem;
            border: 1px solid #2e2e2e;
            background: #171717;
            color: #999;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.15s;
        }
        .pool-back-btn:hover {
            color: #e8e8e0;
            border-color: #444;
            background: #202020;
        }
        .pool-name {
            color: #e8e8e0;
            font-size: 0.98rem;
            font-weight: 800;
            line-height: 1.02;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pool-competition-title {
            color: #7a8394;
            font-size: 0.62rem;
            font-weight: 600;
            line-height: 1.1;
            margin-bottom: 0.08rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .pool-status-dot {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            color: #4caf50;
            font-size: 0.6rem;
            font-weight: 500;
        }
        .pool-status-dot::before {
            content: '';
            width: 0.34rem;
            height: 0.34rem;
            border-radius: 9999px;
            background: #4caf50;
            flex-shrink: 0;
        }
        .pool-status-instructions {
            color: #666;
            transition: color 0.15s;
        }
        .pool-status-instructions:hover { color: #aaa; }
        .pool-stats-chips {
            display: flex;
            gap: 0.45rem;
            flex-shrink: 0;
        }
        .pool-stat-chip {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #232323;
            border: 1px solid #2e2e2e;
            border-radius: 0.45rem;
            padding: 0.3rem 0.55rem;
            min-width: 3.1rem;
        }
        .pool-stat-label {
            font-size: 0.52rem;
            color: #666;
            font-weight: 600;
            line-height: 1;
            margin-top: 0.1rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .pool-stat-value {
            font-size: 1rem;
            font-weight: 900;
            color: #f5a623;
            line-height: 1;
        }
        .pool-stat-value-points { color: #e8e8e0; }
        @media (min-width: 640px) {
            .pool-header-shell { padding: 0.55rem 1.5rem 0.5rem; }
            .pool-back-btn {
                width: 1.9rem;
                height: 1.9rem;
            }
            .pool-name { font-size: 1.35rem; }
            .pool-competition-title { font-size: 0.68rem; }
            .pool-status-dot { font-size: 0.72rem; }
            .pool-stat-chip {
                min-width: 3.65rem;
                padding: 0.35rem 0.8rem;
            }
            .pool-stat-label { font-size: 0.62rem; }
            .pool-stat-value { font-size: 1.2rem; }
        }

        /* ── Tabs: desktop vs mobile ── */
        .pool-tabs-desktop {
            display: flex;
            overflow-x: auto;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 0.1rem;
        }
        .pool-tabs-desktop::-webkit-scrollbar { display: none; }
        .pool-tabs-mobile { display: none; }
        .pool-header-root .pool-tabs-desktop {
            border-color: #2a2a2a;
        }
        .pool-header-root .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.38rem;
            padding: 0.6rem 1.1rem 0.58rem;
            color: #666;
            font-size: 13px;
            font-weight: 500;
            border-bottom-color: transparent;
            border-bottom-width: 2px;
            border-bottom-style: solid;
        }
        .pool-header-root .tab-btn:hover {
            color: #aaa;
        }
        .pool-header-root .tab-btn.active {
            color: #f5a623;
            border-bottom-color: #f5a623;
        }
        .pool-header-root .tab-btn::after {
            display: none !important;
            content: none !important;
        }
        .pool-header-root .tab-btn-right {
            margin-left: 1.25rem;
        }
        .tab-ico {
            width: 14px;
            height: 14px;
            opacity: 0.95;
            flex-shrink: 0;
        }
        .pool-tabs-bar {
            align-items: center;
        }
        .pool-bulk-top-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            height: 2rem;
            padding: 0 0.9rem;
            border-radius: 0.45rem;
            border: 1px solid rgba(255, 255, 255, 0.09);
            background: transparent;
            color: #a4adbc;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .pool-bulk-top-btn:hover {
            color: #e8e8e0;
            border-color: rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.03);
        }

        /* ── Mobile tab pills ── */
        .pool-tab-pill { display: none; }
        .pool-ticker-box {
            border-color: rgba(255, 255, 255, 0.07) !important;
            background: #13161b !important;
        }
        .pool-ticker-empty {
            border-color: rgba(255, 255, 255, 0.07) !important;
            background: #13161b !important;
            color: #7a8394 !important;
        }
        .pool-ticker .text-\[9px\].font-semibold {
            letter-spacing: 0.04em;
        }

        @media (max-width: 639px) {
            .pool-title-row {
                align-items: center;
                flex-wrap: nowrap;
                row-gap: 0;
            }
            .pool-name-group { min-width: 0; width: auto; flex: 1 1 auto; }
            .pool-stats-chips { margin-left: auto; }
            .pool-stat-chip {
                min-width: 2.8rem;
                padding: 0.24rem 0.45rem;
            }
            .pool-stat-value { font-size: 0.92rem; }
            .pool-stat-label { font-size: 0.48rem; }
            .pool-bulk-top-btn { display: none; }
            .pool-header-root .tab-btn {
                padding: 0.58rem 0.72rem 0.56rem;
                gap: 0;
            }
            .pool-header-root .tab-btn .tab-label {
                display: none;
            }
            .pool-header-root .tab-btn.tab-btn-right {
                margin-left: 0.55rem;
            }
            .pool-header-root .tab-ico {
                width: 15px;
                height: 15px;
            }
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
    <div class="p-4 sm:p-6 lg:p-8">
    <div class="mx-auto w-full max-w-7xl space-y-6">

        {{-- Stats bar --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="stat-card">
                <span class="stat-value">{{ $totalMatches }}</span>
                <span class="stat-label">Jogos</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-emerald-400">{{ $predictedCount }}</span>
                <span class="stat-label">Meus palpites</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-amber-400">{{ $pool->activeMembers()->count() }}</span>
                <span class="stat-label">Membros</span>
            </div>
        </div>

           {{-- Bulk prediction --}}
           @if($canEditPredictions)
           <div class="card p-4"
               x-data="{ open: false, bh: '', ba: '' }"
               x-on:toggle-bulk-bar.window="open = !open">
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
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2"
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,2)"
                                   @input="bh = $event.target.value.replace(/[^0-9]/g,'').slice(0,2)"
                                   class="input-field w-16 text-center tabular-nums">
                        </div>
                        <span class="pb-2.5 text-slate-600 text-lg">×</span>
                        <div>
                            <label class="label text-xs">Visitante</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2"
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,2)"
                                   @input="ba = $event.target.value.replace(/[^0-9]/g,'').slice(0,2)"
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
        @endif

        {{-- Groups --}}
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Meus palpites por grupo e rodada</h2>
            <div class="flex items-center gap-3">
                <button type="button"
                        wire:click="{{ $showAllRounds ? 'enableFocusRounds' : 'enableAllRounds' }}"
                        class="text-xs text-bolao-accent hover:text-amber-300 transition-colors">
                    {{ $showAllRounds ? 'Mostrar foco (atual + próxima)' : 'Ver todas' }}
                </button>
                <span class="text-xs text-slate-500">{{ $predictedCount }} preenchido(s)</span>
            </div>
        </div>

        @forelse($groupedMatches as $group => $matches)
        <div>
            <div class="flex items-center gap-3 mb-3">
                <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">{{ $group }}</h2>
                <div class="flex-1 h-px bg-slate-800"></div>
                <span class="text-xs text-slate-600">{{ $matches->count() }} jogo{{ $matches->count() > 1 ? 's' : '' }}</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
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
                    $isLive     = in_array($match->status, ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'], true);
                    $isFinished = $match->status === 'FINISHED';
                    $isPreMatch = $match->status === 'PRE_MATCH';
                    $isLocked   = in_array($predStatus, ['bloqueado', 'calculado', 'finalizado', 'inelegivel']);
                    $matchDate  = ($match->utc_date ?? $match->local_date)?->timezone('America/Sao_Paulo');
                    $liveMinute = $liveMinutes[$match->id] ?? null;
                    $statusLabel = $statusLabels[$match->id] ?? '';
                    if (in_array($match->status, ['TIMED', 'SCHEDULED'], true) && $matchDate) {
                        if ($matchDate->isToday()) {
                            $statusLabel = 'Hoje';
                        } elseif ($matchDate->isTomorrow()) {
                            $statusLabel = 'Amanhã';
                        }
                    }

                    $cardRingClass = match(true) {
                        $isLive && $match->status === 'IN_PLAY'             => 'ring-1 ring-red-500/30',
                        $isLive && $match->status === 'PAUSED'              => 'ring-1 ring-amber-500/30',
                        $isLive && $match->status === 'EXTRA_TIME'          => 'ring-1 ring-orange-500/30',
                        $isLive && $match->status === 'PENALTY_SHOOTOUT'    => 'ring-1 ring-purple-500/30',
                        $isPreMatch                                          => 'ring-1 ring-blue-500/20',
                        default => '',
                    };

                    [$statusColor, $pingOuter, $pingInner, $showPing] = match($match->status) {
                        'IN_PLAY'          => ['text-red-400',    'bg-red-400',    'bg-red-500',    true],
                        'PAUSED'           => ['text-amber-400',  'bg-amber-400',  'bg-amber-500',  false],
                        'EXTRA_TIME'       => ['text-orange-400', 'bg-orange-400', 'bg-orange-500', true],
                        'PENALTY_SHOOTOUT' => ['text-purple-400', 'bg-purple-400', 'bg-purple-500', true],
                        'PRE_MATCH'        => ['text-blue-400',   '',              '',              false],
                        'FINISHED'         => ['text-slate-500',  '',              '',              false],
                        default            => ['text-slate-500',  '',              '',              false],
                    };

                    $liveBadgeClass = match($match->status) {
                        'IN_PLAY'          => 'badge-red',
                        'PAUSED'           => 'badge-amber',
                        'EXTRA_TIME'       => 'badge-orange',
                        'PENALTY_SHOOTOUT' => 'badge-purple',
                        default            => 'badge-red',
                    };
                @endphp
                <div class="card {{ $cardRingClass }} overflow-hidden h-full flex flex-col">
                    {{-- Match status bar --}}
                    <div class="flex items-center justify-between px-3 pt-2.5 pb-1">
                        <div class="flex items-center gap-2">
                            @if($isLive)
                            <span class="flex h-2 w-2 relative">
                                @if($showPing)
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $pingOuter }} opacity-75"></span>
                                @endif
                                <span class="relative inline-flex h-2 w-2 rounded-full {{ $pingInner }}"></span>
                            </span>
                            <span class="text-xs font-semibold {{ $statusColor }}">
                                {{ $statusLabel }}@if($liveMinute !== null) · {{ $liveMinute }}'@endif
                            </span>
                            @elseif($isPreMatch)
                            <span class="text-xs font-semibold {{ $statusColor }}">{{ $statusLabel }}</span>
                            @elseif($isFinished)
                            <span class="text-xs {{ $statusColor }}">✓ {{ $statusLabel }}</span>
                            @else
                            <span class="text-xs {{ $statusColor }}">{{ $statusLabel }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] text-slate-600">
                                {{ $matchDate?->format('d/m H:i') }}
                            </span>
                            <a href="{{ route('matches.show', ['match' => $match->id]) }}"
                               class="text-[11px] text-emerald-400 hover:text-emerald-300 transition-colors">
                                Ver jogo
                            </a>
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
                                @if($isLive)
                                <span class="{{ $liveBadgeClass }} text-[10px] mt-1 inline-flex items-center gap-1.5">
                                    @if($showPing)
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $pingOuter }} opacity-75"></span>
                                        <span class="relative inline-flex h-2 w-2 rounded-full {{ $pingInner }}"></span>
                                    </span>
                                    @endif
                                    {{ $statusLabel }}@if($liveMinute !== null) · {{ $liveMinute }}'@endif
                                </span>
                                @endif
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
                    @if($canEditPredictions && (!$isLocked || $prediction))
                    @php
                        $initH = $prediction ? (string) $prediction->home_score : '';
                        $initA = $prediction ? (string) $prediction->away_score : '';
                        $hasPred = $prediction ? 'true' : 'false';
                    @endphp
                    <div class="border-t border-slate-800 px-3 py-2 bg-slate-900/30"
                         x-data="{
                             hasPred: {{ $hasPred }},
                             savedH: '{{ $initH }}',
                             savedA: '{{ $initA }}',
                             curH: '{{ $initH }}',
                             curA: '{{ $initA }}',
                             get dirty() { return this.curH !== this.savedH || this.curA !== this.savedA; },
                             get showSave() { return this.dirty || !this.hasPred; },
                             get showSaved() { return !this.dirty && this.hasPred; }
                         }"
                         @prediction-saved.window="
                             if ($event.detail.matchId === {{ $match->id }}) {
                                 savedH = curH; savedA = curA; hasPred = true;
                             }
                         ">
                        <div class="flex items-center gap-2">
                            <form wire:submit="savePrediction({{ $match->id }})" class="flex w-full items-center gap-2 whitespace-nowrap">
                                <span class="text-xs text-slate-500 shrink-0">Meu palpite:</span>
                                <div class="flex flex-1 items-center justify-center gap-1.5 min-w-[96px]">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2"
                                           oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,2)"
                                           @input="curH = $event.target.value.replace(/[^0-9]/g,'').slice(0,2)"
                                           wire:model.live="scores.{{ $match->id }}.home"
                                           @if($isLocked) disabled @endif
                                           class="score-input w-10 text-center rounded-md text-xs font-bold tabular-nums
                                                  {{ $isLocked
                                                     ? 'bg-slate-800/50 border-slate-700 text-slate-400 cursor-not-allowed'
                                                     : 'bg-pitch-800 border-slate-700 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500' }}
                                                  border py-1 transition-colors">
                                    <span class="text-slate-600 font-bold text-xs">×</span>
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2"
                                           oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,2)"
                                           @input="curA = $event.target.value.replace(/[^0-9]/g,'').slice(0,2)"
                                           wire:model.live="scores.{{ $match->id }}.away"
                                           @if($isLocked) disabled @endif
                                           class="score-input w-10 text-center rounded-md text-xs font-bold tabular-nums
                                                  {{ $isLocked
                                                     ? 'bg-slate-800/50 border-slate-700 text-slate-400 cursor-not-allowed'
                                                     : 'bg-pitch-800 border-slate-700 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500' }}
                                                  border py-1 transition-colors">
                                </div>
                                @if(!$isLocked)
                                {{-- Indicador: já salvo --}}
                                <div x-show="showSaved"
                                     @if(!$prediction) style="display:none" @endif
                                     class="flex items-center gap-1 text-emerald-400 text-xs ml-auto shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span>Salvo</span>
                                </div>
                                {{-- Botão salvar: aparece quando alterado ou sem palpite --}}
                                <button type="submit"
                                        x-show="showSave"
                                        @if($prediction) style="display:none" @endif
                                        class="btn-primary !px-2.5 !py-1 text-xs ml-auto"
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
                    @elseif(!$canEditPredictions)
                    @php
                        $canSeeThisPrediction = $predictionVisibility[$match->id] ?? true;
                    @endphp
                    <div class="border-t border-slate-800 px-3 py-2 bg-slate-900/30">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-slate-500 shrink-0">Palpite do participante:</span>

                            @if(!$canSeeThisPrediction)
                            <span class="text-xs text-slate-500">Oculto até fechamento do palpite</span>
                            @elseif($prediction)
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center min-w-[38px] rounded-md border border-slate-700 bg-slate-800/70 px-2 py-1 text-xs font-bold tabular-nums text-slate-100">
                                    {{ $prediction->home_score }}
                                </span>
                                <span class="text-slate-600 font-bold text-xs">×</span>
                                <span class="inline-flex items-center justify-center min-w-[38px] rounded-md border border-slate-700 bg-slate-800/70 px-2 py-1 text-xs font-bold tabular-nums text-slate-100">
                                    {{ $prediction->away_score }}
                                </span>
                            </div>
                            @elseif($isLocked)
                            <span class="text-xs text-slate-500">Sem palpite</span>
                            @else
                            <span class="text-xs text-slate-500">Aguardando fechamento</span>
                            @endif
                        </div>

                        @if($prediction && $predStatus === 'calculado')
                        <div class="mt-1.5 flex items-center justify-end gap-1">
                            <span class="text-xs text-slate-500">Pontos:</span>
                            <span class="text-sm font-bold text-amber-400">{{ $prediction->points }}</span>
                        </div>
                        @endif
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
    </div>
    @endif

    {{-- Tab: Ranking --}}
    @if($activeTab === 'ranking')
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="ranking-layout">
            <div class="ranking-main">
                <div class="card w-full overflow-hidden">
                    @if($rankings->isEmpty())
                    <div class="px-4 py-3 border-b border-slate-800 bg-blue-900/10 text-xs text-blue-300">
                        Ranking provisório: todos empatados na mesma posição até o primeiro cálculo oficial.
                    </div>
                    @endif
                    <div class="w-full overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-slate-800">
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Participante</th>
                                    @if($rankingColumns['exact_scores'])
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase hidden sm:table-cell">Exatos</th>
                                    @endif
                                    @if($rankingColumns['correct_results'])
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase hidden sm:table-cell">Resultados</th>
                                    @endif
                                    @if($rankingColumns['correct_goals'])
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase hidden sm:table-cell">Gols</th>
                                    @endif
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase hidden md:table-cell">Palpites</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Pts</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                @foreach($rankingRows as $row)
                                @php
                                    $isMe = $row->user_id === Auth::id();
                                    $medal = match($row->position) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => null };
                                    $name = $row->user?->public_name ?? '—';
                                    $fullName = trim((string) ($row->user?->name ?? ''));
                                    $parts = array_values(array_filter(preg_split('/\s+/u', $fullName) ?: []));
                                    $initials = $parts === [] ? mb_substr($name, 0, 2) : (
                                        mb_substr($parts[0] ?? '', 0, 1) .
                                        mb_substr($parts[1] ?? ($parts[0] ?? ''), 0, 1)
                                    );
                                    $goalsHits = (int) ($row->correct_home_goals ?? 0) + (int) ($row->correct_away_goals ?? 0);
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
                                                {{ mb_strtoupper($initials) }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium {{ $isMe ? 'text-emerald-400' : 'text-slate-200' }}">
                                                    {{ $name }}
                                                    @if($isMe) <span class="text-xs text-slate-500">(você)</span> @endif
                                                </p>
                                                @if($row->user?->area)
                                                <p class="text-xs text-slate-600">{{ $row->user->area }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    @if($rankingColumns['exact_scores'])
                                    <td class="px-4 py-3.5 text-center text-sm text-slate-400 hidden sm:table-cell">
                                        {{ $row->exact_scores }}
                                    </td>
                                    @endif
                                    @if($rankingColumns['correct_results'])
                                    <td class="px-4 py-3.5 text-center text-sm text-slate-400 hidden sm:table-cell">
                                        {{ $row->correct_results }}
                                    </td>
                                    @endif
                                    @if($rankingColumns['correct_goals'])
                                    <td class="px-4 py-3.5 text-center text-sm text-slate-400 hidden sm:table-cell">
                                        {{ $goalsHits }}
                                    </td>
                                    @endif
                                    <td class="px-4 py-3.5 text-center text-sm text-slate-500 hidden md:table-cell">
                                        {{ $row->predictions_counted }}
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <span class="text-xl font-black {{ $isMe ? 'text-emerald-400' : 'text-white' }}">
                                            {{ $row->points_total }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="ranking-side">
                <div class="card p-4 space-y-3 lg:sticky lg:top-20">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Critérios do Bolão</h3>
                    <div class="space-y-2 text-xs">
                        @if($rankingColumns['exact_scores'])
                        <div class="flex items-center justify-between rounded-lg bg-slate-800/60 px-2.5 py-2">
                            <span class="text-slate-400">Exato</span>
                            <span class="text-emerald-300 font-semibold">{{ $pool->points_exact_score ?? 5 }} pts</span>
                        </div>
                        @endif
                        @if($rankingColumns['correct_results'])
                        <div class="flex items-center justify-between rounded-lg bg-slate-800/60 px-2.5 py-2">
                            <span class="text-slate-400">Resultado</span>
                            <span class="text-emerald-300 font-semibold">{{ $pool->points_correct_result ?? 3 }} pts</span>
                        </div>
                        @endif
                        @if($rankingColumns['correct_goals'])
                        <div class="flex items-center justify-between rounded-lg bg-slate-800/60 px-2.5 py-2">
                            <span class="text-slate-400">Gols time</span>
                            <span class="text-emerald-300 font-semibold">{{ $pool->points_correct_goals ?? 1 }} pts</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
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
