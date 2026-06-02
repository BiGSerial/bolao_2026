<div class="animate-fade-in"
     wire:poll.10s="refreshMatches"
     x-data="{
         touchStartX: 0,
         touchEndX: 0,
         swipe() {
             const diff = this.touchStartX - this.touchEndX;
             if (Math.abs(diff) < 50) return;
             const tabs = ['jogos', 'ranking', 'chat', 'resumo'];
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
            'chat' => 'Chat',
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

    <div class="mx-3 sm:mx-6 lg:mx-8 mt-3">
        <div class="inline-flex rounded-xl border border-white/10 bg-slate-900/60 p-1 shadow-sm">
            <button type="button"
                    wire:click="setTab('jogos')"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors {{ $activeTab === 'jogos' ? 'bg-amber-500/20 text-amber-300' : 'text-slate-400 hover:text-slate-200' }}">
                Palpites
            </button>
            <button type="button"
                    wire:click="setTab('ranking')"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors {{ $activeTab === 'ranking' ? 'bg-amber-500/20 text-amber-300' : 'text-slate-400 hover:text-slate-200' }}">
                Ranking
            </button>
            <button type="button"
                    wire:click="setTab('chat')"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors {{ $activeTab === 'chat' ? 'bg-amber-500/20 text-amber-300' : 'text-slate-400 hover:text-slate-200' }}">
                Chat
            </button>
            <button type="button"
                    wire:click="setTab('resumo')"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors {{ $activeTab === 'resumo' ? 'bg-amber-500/20 text-amber-300' : 'text-slate-400 hover:text-slate-200' }}">
                Meu resumo
            </button>
        </div>
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
                <span class="stat-label">{{ $isViewingOtherMember ? 'Palpites de '.($predictionTargetName ?? 'participante') : 'Meus palpites' }}</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-amber-400">{{ $pool->activeMembers()->count() }}</span>
                <span class="stat-label">Membros</span>
            </div>
        </div>

        @if($isViewingOtherMember)
        <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-sm text-amber-300">
            Você está visualizando os palpites de <span class="font-semibold">{{ $predictionTargetName ?? 'participante' }}</span> em modo somente leitura.
        </div>
        @endif

           {{-- Bulk prediction --}}
        @if($canEditPredictions)
           <div class="card p-4"
             x-data="{
                open: false,
                bh: '',
                ba: ''
             }"
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

                <div class="space-y-3">
                    <div>
                        <label class="label text-xs">Escopo de aplicação</label>
                        <select wire:model="bulkDelimiter" class="select-field">
                            @foreach($bulkDelimiters as $delimiter)
                            <option value="{{ $delimiter['value'] }}">{{ $delimiter['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-[1fr_auto_1fr] items-end gap-2">
                        <div class="min-w-0">
                            <label class="label text-xs">Mandante</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2"
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,2)"
                                   @input="bh = $event.target.value.replace(/[^0-9]/g,'').slice(0,2)"
                                   class="input-field w-full min-w-[56px] text-center tabular-nums">
                        </div>
                        <span class="pb-2.5 text-slate-600 text-lg">×</span>
                        <div class="min-w-0">
                            <label class="label text-xs">Visitante</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2"
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,2)"
                                   @input="ba = $event.target.value.replace(/[^0-9]/g,'').slice(0,2)"
                                   class="input-field w-full min-w-[56px] text-center tabular-nums">
                        </div>
                    </div>

                    <div>
                        <button @click="$wire.applyBulkPrediction(bh, ba)"
                                class="btn-secondary h-10 w-full sm:w-auto">
                            Aplicar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($canEditPredictions)
        <div class="flex justify-end" x-data="{
            async confirmReplication() {
                const result = await Swal.fire({
                    icon: 'question',
                    title: 'Replicar palpites?',
                    text: 'Seus palpites elegíveis serão replicados para os outros bolões em que você participa, respeitando as regras de cada bolão.',
                    showCancelButton: true,
                    confirmButtonText: 'Sim, replicar',
                    cancelButtonText: 'Cancelar'
                });

                if (result.isConfirmed) {
                    $wire.replicatePredictionsToOtherPools();
                }
            }
        }">
            <button type="button"
                    @click="confirmReplication()"
                    class="btn-ghost"
                    wire:loading.attr="disabled"
                    wire:target="replicatePredictionsToOtherPools">
                <svg wire:loading wire:target="replicatePredictionsToOtherPools" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <svg wire:loading.remove wire:target="replicatePredictionsToOtherPools" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5.5 15a7 7 0 0011 2.5M18.5 9a7 7 0 00-11-2.5"/>
                </svg>
                Replicar estes palpites para todos meus bolões
            </button>
        </div>
        @endif

        {{-- Groups --}}
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">
                {{ $isViewingOtherMember ? 'Palpites por grupo e rodada de '.($predictionTargetName ?? 'participante') : 'Meus palpites por grupo e rodada' }}
            </h2>
            <div class="flex flex-wrap items-center justify-end gap-3">
                @if($displayLeftRound !== null)
                <span class="text-xs text-slate-400">Esquerda: <span class="font-semibold text-slate-200">Rodada {{ $displayLeftRound }}</span></span>
                @endif

                @if($displayRightRound !== null)
                <span class="text-xs text-slate-400">Direita: <span class="font-semibold text-slate-200">Rodada {{ $displayRightRound }}</span></span>
                @endif

                <span class="text-xs text-slate-500">{{ $predictedCount }} preenchido(s)</span>
            </div>
        </div>

        @forelse($groupedMatches as $group => $matches)
        <div>
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">{{ $group }}</h2>
                    <div class="flex-1 h-px bg-slate-800"></div>
                    <span class="text-xs text-slate-600 whitespace-nowrap">{{ $matches->count() }} jogo{{ $matches->count() > 1 ? 's' : '' }}</span>
                </div>
                @if(($displayRightCandidates ?? collect())->isNotEmpty() || ($canMoveDisplayPrev ?? false) || ($canMoveDisplayNext ?? false))
                <div class="flex items-center justify-end gap-1.5">
                    <button type="button"
                            wire:click="previousDisplayRound"
                            @disabled(!($canMoveDisplayPrev ?? false))
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-800/60 text-slate-300 hover:bg-slate-700/60 hover:text-slate-100">
                        <i class="ti ti-chevron-left text-sm"></i>
                    </button>
                    <button type="button"
                            wire:click="nextDisplayRound"
                            @disabled(!($canMoveDisplayNext ?? false))
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-800/60 text-slate-300 hover:bg-slate-700/60 hover:text-slate-100">
                        <i class="ti ti-chevron-right text-sm"></i>
                    </button>
                </div>
                @endif
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
                    $matchDate  = $this->kickoffAtBrazil($match);
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
                                <img src="{{ $match->homeTeam->crest }}" alt="{{ $match->homeTeam->abbr3 }}"
                                     class="h-8 w-8 object-contain shrink-0 drop-shadow" loading="lazy">
                                @else
                                <div class="h-8 w-8 shrink-0 rounded-full bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-300">
                                    {{ $match->homeTeam?->abbr3 ?? '?' }}
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
                                <img src="{{ $match->awayTeam->crest }}" alt="{{ $match->awayTeam->abbr3 }}"
                                     class="h-8 w-8 object-contain shrink-0 drop-shadow" loading="lazy">
                                @else
                                <div class="h-8 w-8 shrink-0 rounded-full bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-300">
                                    {{ $match->awayTeam?->abbr3 ?? '?' }}
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
                                                @if($row->user_id)
                                                <a href="{{ route('pools.show', ['pool' => $pool->slug, 'tab' => 'jogos', 'member' => $row->user_id, 'competition' => $competitionCode]) }}"
                                                   class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-amber-400 hover:text-amber-300 transition-colors">
                                                    <i class="ti ti-eye text-sm"></i>
                                                    {{ $isMe ? 'Ver meus palpites' : 'Ver palpites' }}
                                                </a>
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
                                        @php $delta = (int) ($row->today_delta_points ?? 0); @endphp
                                        <div class="text-[11px] leading-none mt-1 {{ $delta > 0 ? 'text-emerald-300' : ($delta < 0 ? 'text-rose-300' : 'text-slate-600') }}">
                                            {{ $delta > 0 ? '+' : '' }}{{ $delta }}
                                        </div>
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

        <div class="card p-4 sm:p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Resultados e Pontuação</h2>

                <div class="flex items-center gap-2">
                    <button type="button"
                            wire:click="previousSummaryScope"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-800/60 text-slate-300 hover:bg-slate-700/60 hover:text-slate-100">
                        <i class="ti ti-chevron-left text-sm"></i>
                    </button>

                    <span class="rounded-md border border-slate-700/80 bg-slate-900/70 px-3 py-1.5 text-xs font-semibold text-slate-200">
                        {{ $summaryCurrentScopeLabel !== '' ? $summaryCurrentScopeLabel : 'Escopo' }}
                    </span>

                    <button type="button"
                            wire:click="nextSummaryScope"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-800/60 text-slate-300 hover:bg-slate-700/60 hover:text-slate-100">
                        <i class="ti ti-chevron-right text-sm"></i>
                    </button>
                </div>
            </div>

            @if($summaryMatches->isEmpty())
            <div class="rounded-lg border border-slate-800 bg-slate-900/50 px-4 py-8 text-center text-sm text-slate-500">
                Nenhum jogo encontrado neste {{ $summaryNavigationMode === 'groups' ? 'grupo' : 'recorte de rodada' }}.
            </div>
            @else
            <div class="space-y-2">
                @foreach($summaryMatches as $match)
                @php
                    $prediction = $predictions->get($match->id);
                    $canSeeThisPrediction = $predictionVisibility[$match->id] ?? true;
                    $matchDate = $this->kickoffAtBrazil($match);
                    $homeReal = $match->home_score_full_time;
                    $awayReal = $match->away_score_full_time;
                    $points = $this->pointsForSummaryMatch($match, $prediction);
                    $outcomeType = $this->summaryOutcomeType($match, $prediction);
                    $leftBarClass = match($outcomeType) {
                        'exact' => 'bg-emerald-400',
                        'result' => 'bg-sky-400',
                        'bonus' => 'bg-amber-400',
                        'error' => 'bg-red-400',
                        default => 'bg-slate-600',
                    };
                    $pointsBadgeClass = match($outcomeType) {
                        'exact' => 'bg-emerald-500/20 text-emerald-300',
                        'result' => 'bg-sky-500/20 text-sky-300',
                        'bonus' => 'bg-amber-500/20 text-amber-300',
                        'error' => 'bg-red-500/20 text-red-300',
                        default => 'bg-slate-700/70 text-slate-400',
                    };
                @endphp
                <div class="relative overflow-hidden rounded-xl border border-white/[0.08] bg-bolao-bg2/95 px-3 py-2 sm:px-4 sm:py-2.5">
                    <span class="absolute inset-y-0 left-0 w-1 {{ $leftBarClass }}"></span>

                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 sm:gap-3 pl-2">
                        <div class="flex min-w-0 items-center gap-2">
                            @if($match->homeTeam?->crest)
                            <img src="{{ $match->homeTeam->crest }}" alt="{{ $match->homeTeam?->abbr3 ?? 'Mandante' }}" class="h-7 w-7 shrink-0 object-contain drop-shadow">
                            @else
                            <div class="h-7 w-7 shrink-0 rounded-full bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-300">
                                {{ $match->homeTeam?->abbr3 ?? '?' }}
                            </div>
                            @endif
                            <p class="truncate text-base font-semibold text-slate-100">{{ $this->teamDisplayName($match->homeTeam) }}</p>
                        </div>

                        <div class="px-2 text-center leading-none">
                            <p class="text-4xl font-black tracking-tight text-bolao-accent tabular-nums">
                                {{ $homeReal !== null && $awayReal !== null ? ($homeReal.'-'.$awayReal) : '—' }}
                            </p>
                            <p class="mt-1 text-xs font-semibold tabular-nums {{ !$canSeeThisPrediction ? 'text-slate-500' : ($prediction ? 'text-slate-200' : 'text-slate-500') }}">
                                @if(!$canSeeThisPrediction)
                                    Oculto
                                @elseif($prediction)
                                    {{ $prediction->home_score }}-{{ $prediction->away_score }}
                                @else
                                    —
                                @endif
                            </p>
                        </div>

                        <div class="flex min-w-0 items-center justify-end gap-2">
                            <p class="truncate text-right text-base font-semibold text-slate-100">{{ $this->teamDisplayName($match->awayTeam) }}</p>
                            @if($match->awayTeam?->crest)
                            <img src="{{ $match->awayTeam->crest }}" alt="{{ $match->awayTeam?->abbr3 ?? 'Visitante' }}" class="h-7 w-7 shrink-0 object-contain drop-shadow">
                            @else
                            <div class="h-7 w-7 shrink-0 rounded-full bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-300">
                                {{ $match->awayTeam?->abbr3 ?? '?' }}
                            </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-1.5 flex items-center justify-between gap-2 pl-2">
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-slate-800/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                {{ $summaryNavigationMode === 'groups' ? ('Grupo '.($match->group_name ?: '—')) : ('Rodada '.($match->matchday ?? '—')) }}
                            </span>
                            <span class="text-[11px] text-slate-500">{{ $matchDate?->format('d/m H:i') ?? '—' }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-0.5 text-[11px] font-black {{ $points === null ? 'bg-slate-800/80 text-slate-500' : $pointsBadgeClass }}">
                                {{ $points === null ? '—' : ($points > 0 ? '+' : '').$points }}
                            </span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Tab: Chat --}}
    @if($activeTab === 'chat')
    <div class="px-4 sm:px-6 lg:px-8 py-4">
        <div class="mx-auto w-full max-w-3xl"
             x-data="poolChatWidget({
                poolId: {{ (int) $pool->id }},
                userId: {{ (int) auth()->id() }},
                userName: @js(auth()->user()?->public_name ?? 'Você')
             })"
             x-init="init()">

            {{-- Thread --}}
            <div class="wc-thread rounded-2xl overflow-hidden border border-white/8 flex flex-col" style="height:62vh">

                {{-- Header --}}
                <div class="wc-header flex items-center justify-between px-4 py-2.5 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="wc-group-avatar">
                            <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-100 leading-tight">Chat do bolão</p>
                            <template x-if="typingNames.length">
                                <div class="wc-typing-indicator">
                                    <span x-text="typingLabel()"></span>
                                    <span class="wc-typing-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                                </div>
                            </template>
                            <template x-if="!typingNames.length">
                                <p class="text-[11px] text-slate-500 leading-tight">Online</p>
                            </template>
                        </div>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-amber-300 transition-colors" @click="loadMessages()" title="Atualizar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                </div>

                {{-- Messages --}}
                <div class="wc-messages flex-1 overflow-y-auto px-3 py-3 space-y-1" x-ref="list">

                    <template x-if="loading">
                        <div class="flex justify-center pt-8">
                            <p class="text-xs text-slate-500">Carregando...</p>
                        </div>
                    </template>

                    <template x-if="!loading && messages.length === 0">
                        <div class="flex flex-col items-center justify-center h-full gap-3 pt-12">
                            <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <p class="text-sm text-slate-500">Sem mensagens ainda. Inicie a conversa!</p>
                        </div>
                    </template>

                    <template x-for="(item, idx) in messages" :key="item.id">
                        <div>
                            {{-- Separador de data quando necessário --}}
                            <template x-if="showDateSep(idx)">
                                <div class="flex items-center gap-2 my-3">
                                    <div class="flex-1 h-px bg-white/8"></div>
                                    <span class="text-[10px] text-slate-500 px-2 py-0.5 rounded-full border border-white/10 bg-slate-900/60"
                                          x-text="formatDate(item.created_at)"></span>
                                    <div class="flex-1 h-px bg-white/8"></div>
                                </div>
                            </template>

                            {{-- Bubble row --}}
                            <div class="flex items-end gap-2 mb-0.5"
                                 :class="isMine(item) ? 'justify-end' : 'justify-start'">

                                {{-- Avatar (outros, apenas na última mensagem do grupo) --}}
                                <template x-if="!isMine(item)">
                                    <div class="w-7 h-7 shrink-0 mb-0.5">
                                        <template x-if="isLastInGroup(idx)">
                                            <div class="wc-avatar w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                                                 :style="{ background: avatarBg(item.user?.name) }"
                                                 x-text="initials(item.user?.name)">
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Bubble --}}
                                <div class="relative max-w-[72%] group"
                                     :class="isMine(item) ? 'wc-bubble-me' : 'wc-bubble-other'">

                                    {{-- Nome (apenas 1ª mensagem do grupo, para outros) --}}
                                    <template x-if="!isMine(item) && isFirstInGroup(idx)">
                                        <p class="text-[11px] font-bold mb-1 leading-tight"
                                           :style="{ color: nameColor(item.user?.name) }"
                                           x-text="item.user?.name || '—'"></p>
                                    </template>

                                    {{-- Reply quote --}}
                                    <template x-if="item.reply_to">
                                        <div class="wc-reply mb-1.5 px-2 py-1 rounded-lg"
                                             :class="isMine(item) ? 'wc-reply-me' : 'wc-reply-other'">
                                            <p class="text-[10px] font-semibold mb-0.5"
                                               :style="{ color: nameColor(item.reply_to.user_name) }"
                                               x-text="item.reply_to.user_name || '—'"></p>
                                            <p class="text-[11px] opacity-80 truncate" x-text="item.reply_to.body"></p>
                                        </div>
                                    </template>

                                    {{-- Corpo + meta em linha --}}
                                    <div class="flex items-end gap-2 flex-wrap">
                                        <p class="wc-body text-[14px] leading-[1.4] whitespace-pre-wrap break-words flex-1"
                                           x-text="item.body"></p>

                                        {{-- Hora + ticks --}}
                                        <span class="wc-meta flex items-center gap-1 shrink-0 self-end ml-auto">
                                            <template x-if="item.edited_at">
                                                <span class="text-[10px] opacity-50 italic">editada</span>
                                            </template>
                                            <span class="text-[10px] opacity-60" x-text="formatTime(item.created_at)"></span>
                                            <template x-if="isMine(item)">
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 16 11" fill="none">
                                                    <template x-if="readState(item.id) === 'Lido'">
                                                        <g fill="#53bdeb">
                                                            <path d="M11.071.653a.75.75 0 0 1 .006 1.06l-6.463 6.6a.75.75 0 0 1-1.073-.007L1.076 5.567a.75.75 0 1 1 1.098-1.022l1.952 2.097L9.01 .66a.75.75 0 0 1 1.061-.007z"/>
                                                            <path d="M15.071.653a.75.75 0 0 1 .006 1.06l-6.463 6.6a.75.75 0 0 1-.134.103L9.617 9.3l.454-.488a.75.75 0 0 1 1.073.007l.006-.006 4.86-4.96a.75.75 0 0 1 1.061.8z"/>
                                                        </g>
                                                    </template>
                                                    <template x-if="readState(item.id) !== 'Lido'">
                                                        <g fill="currentColor" opacity="0.6">
                                                            <path d="M11.071.653a.75.75 0 0 1 .006 1.06l-6.463 6.6a.75.75 0 0 1-1.073-.007L1.076 5.567a.75.75 0 1 1 1.098-1.022l1.952 2.097L9.01 .66a.75.75 0 0 1 1.061-.007z"/>
                                                            <path d="M15.071.653a.75.75 0 0 1 .006 1.06l-6.463 6.6a.75.75 0 0 1-.134.103L9.617 9.3l.454-.488a.75.75 0 0 1 1.073.007l.006-.006 4.86-4.96a.75.75 0 0 1 1.061.8z"/>
                                                        </g>
                                                    </template>
                                                </svg>
                                            </template>
                                        </span>
                                    </div>

                                    {{-- Reactions --}}
                                    <template x-if="(item.reactions || []).length">
                                        <div class="wc-reactions-row">
                                            <template x-for="r in (item.reactions || [])" :key="r.emoji">
                                                <button type="button"
                                                        class="wc-reaction"
                                                        :class="(r.user_ids||[]).includes(userId) ? 'wc-reaction-active' : ''"
                                                        @click="toggleReaction(item.id, r.emoji)"
                                                        x-text="reactionLabel(r)">
                                                </button>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="item.audit?.edits?.length || item.audit?.deleted_body">
                                        <div class="wc-audit">
                                            <template x-if="item.audit?.deleted_body">
                                                <p class="wc-audit-line" x-text="'Apagada: ' + item.audit.deleted_body"></p>
                                            </template>
                                            <template x-for="(edit, auditIdx) in (item.audit?.edits || [])" :key="auditIdx">
                                                <p class="wc-audit-line" x-text="'Editada: &quot;' + edit.old_body + '&quot; -> &quot;' + edit.new_body + '&quot;'"></p>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- Context menu trigger (hover) --}}
                                    <div class="wc-ctx-btn absolute top-1"
                                         :class="isMine(item) ? '-left-7' : '-right-7'"
                                         @click="openCtx(item)">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                        </svg>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Context menu --}}
                <template x-if="ctxMsg">
                    <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center" @click="ctxMsg = null">
                        <div class="wc-ctx-menu rounded-2xl px-2 py-1.5 shadow-2xl min-w-[160px]" @click.stop>
                            <button class="wc-ctx-item" @click="replyTo = ctxMsg; ctxMsg = null">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                Responder
                            </button>
                            <button class="wc-ctx-item" @click="toggleReaction(ctxMsg.id, '👍'); ctxMsg = null">
                                <span class="text-base leading-none">👍</span> Reagir
                            </button>
                            <button class="wc-ctx-item" @click="copyMsg(ctxMsg); ctxMsg = null">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                Copiar
                            </button>
                            <template x-if="canEditMessage(ctxMsg)">
                                <button class="wc-ctx-item" @click="startEdit(ctxMsg); ctxMsg = null">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Editar
                                </button>
                            </template>
                            <template x-if="isMine(ctxMsg) && !ctxMsg?.deleted_at">
                                <button class="wc-ctx-item text-red-300" @click="deleteMessage(ctxMsg); ctxMsg = null">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    Apagar
                                </button>
                            </template>
                            <button class="wc-ctx-item text-slate-400" @click="ctxMsg = null">Cancelar</button>
                        </div>
                    </div>
                </template>

                {{-- Reply bar --}}
                <template x-if="replyTo">
                    <div class="wc-reply-bar px-4 py-2 flex items-center gap-3 shrink-0">
                        <div class="flex-1 min-w-0 border-l-2 border-amber-400 pl-3">
                            <p class="text-[11px] font-semibold text-amber-300" x-text="replyTo.user?.name || '—'"></p>
                            <p class="text-[12px] text-slate-300 truncate" x-text="replyTo.body"></p>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-200 shrink-0" @click="replyTo = null">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>

                <template x-if="editingMessageId">
                    <div class="wc-reply-bar px-4 py-2 flex items-center gap-3 shrink-0">
                        <div class="flex-1 min-w-0 border-l-2 border-emerald-400 pl-3">
                            <p class="text-[11px] font-semibold text-emerald-300">Editando mensagem</p>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-200 shrink-0" @click="cancelEdit()">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>

                {{-- Composer --}}
                <div class="wc-composer px-3 py-2.5 flex items-end gap-2 shrink-0">
                    <div class="flex-1 wc-input-wrap rounded-2xl px-4 py-2.5 flex items-end gap-2">
                        <textarea
                            x-model="draft"
                            rows="1"
                            maxlength="2000"
                            class="wc-textarea flex-1 text-sm text-slate-100 placeholder-slate-500 resize-none outline-none bg-transparent leading-[1.4] max-h-28"
                            :placeholder="editingMessageId ? 'Edite sua mensagem' : 'Digite uma mensagem'"
                            @input.debounce.300ms="touchTyping(); autoResize($event)"
                            @keydown.enter.exact.prevent="sendMessage()"
                            @keydown.shift.enter="$event.target.style.height = 'auto'; $event.target.style.height = $event.target.scrollHeight + 'px'"
                        ></textarea>
                    </div>
                    <button type="button"
                            class="wc-send shrink-0"
                            :class="draft.trim() ? 'wc-send-active' : 'wc-send-idle'"
                            :disabled="sending || !draft.trim()"
                            @click="sendMessage()">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>

            </div>{{-- /wc-thread --}}
        </div>
    </div>
    @endif

</div>

@once
<script>
    function poolChatWidget({ poolId, userId, userName }) {
        return {
            poolId, userId, userName,
            loading: true, sending: false,
            draft: '', replyTo: null, ctxMsg: null, editingMessageId: null,
            messages: [], typingUsers: {}, participants: [], readsMap: {},
            typingExpiresAt: {},
            typingTimeout: null, channel: null,

            get typingNames() { return Object.values(this.typingUsers); },

            typingLabel() {
                const names = this.typingNames;
                if (names.length === 1) return `${names[0]} está digitando`;
                if (names.length === 2) return `${names[0]} e ${names[1]} estão digitando`;
                return `${names.length} pessoas estão digitando`;
            },

            async init() {
                await Promise.all([this.loadMessages(), this.loadParticipants()]);
                this.bindRealtime();
            },

            async loadMessages() {
                this.loading = true;
                try {
                    const res = await window.axios.get(`/api/v1/pools/${this.poolId}/chat/messages`);
                    this.messages = Array.isArray(res?.data?.data?.items) ? res.data.data.items : [];
                    this.readsMap = (res?.data?.data?.reads_map && typeof res.data.data.reads_map === 'object')
                        ? res.data.data.reads_map : {};
                    this.$nextTick(() => this.scrollBottom());
                    await this.markRead();
                } finally { this.loading = false; }
            },

            async loadParticipants() {
                try {
                    const res = await window.axios.get(`/api/v1/pools/${this.poolId}/chat/participants`);
                    this.participants = Array.isArray(res?.data?.data?.items) ? res.data.data.items : [];
                } catch (_) { this.participants = []; }
            },

            bindRealtime() {
                if (!window.Echo) return;
                this.channel = window.Echo.private(`pool-chat.${this.poolId}`)
                    .listen('.PoolChatMessageCreated', async (payload) => {
                        const msg = payload?.message;
                        if (!msg) return;
                        this.upsertMessage(msg);
                        this.$nextTick(() => this.scrollBottom());
                        await this.markRead();
                    })
                    .listen('.PoolChatReactionChanged', (payload) => {
                        const id = Number(payload?.message_id || 0);
                        const idx = this.messages.findIndex((m) => Number(m.id) === id);
                        if (idx >= 0) this.messages[idx].reactions = Array.isArray(payload?.reactions) ? payload.reactions : [];
                    })
                    .listen('.PoolChatTypingChanged', (payload) => {
                        const uid = Number(payload?.user_id || 0);
                        if (!uid || uid === this.userId) return;
                        if (payload?.typing) {
                            const expiresAt = Date.now() + 5000;
                            this.typingExpiresAt = { ...this.typingExpiresAt, [uid]: expiresAt };
                            this.typingUsers = { ...this.typingUsers, [uid]: payload?.user_name || 'Alguém' };
                            setTimeout(() => {
                                if (Number(this.typingExpiresAt?.[uid] || 0) !== expiresAt) return;
                                const nextExpires = { ...this.typingExpiresAt };
                                delete nextExpires[uid];
                                const next = { ...this.typingUsers };
                                delete next[uid];
                                this.typingExpiresAt = nextExpires;
                                this.typingUsers = next;
                            }, 5000);
                        } else {
                            const nextExpires = { ...this.typingExpiresAt };
                            delete nextExpires[uid];
                            const next = { ...this.typingUsers };
                            delete next[uid];
                            this.typingExpiresAt = nextExpires;
                            this.typingUsers = next;
                        }
                    })
                    .listen('.PoolChatReadUpdated', (payload) => {
                        const uid = Number(payload?.user_id || 0);
                        const last = Number(payload?.last_read_message_id || 0);
                        if (uid && last) this.readsMap[uid] = last;
                    });
            },

            async sendMessage() {
                const body = String(this.draft || '').trim();
                if (!body || this.sending) return;
                this.sending = true;
                try {
                    if (this.editingMessageId) {
                        const res = await window.axios.patch(`/api/v1/pools/${this.poolId}/chat/messages/${this.editingMessageId}`, { body });
                        const updated = res?.data?.data?.message;
                        if (updated?.id) this.upsertMessage(updated);
                        this.draft = '';
                        this.editingMessageId = null;
                        this.replyTo = null;
                        await this.setTyping(false);
                        return;
                    }

                    const payload = { body };
                    if (this.replyTo?.id) payload.reply_to_message_id = this.replyTo.id;
                    const res = await window.axios.post(`/api/v1/pools/${this.poolId}/chat/messages`, payload);
                    const created = res?.data?.data?.message;
                    if (created?.id && !this.messages.some((m) => Number(m.id) === Number(created.id))) {
                        this.messages.push(created);
                        this.$nextTick(() => this.scrollBottom());
                    }
                    this.draft = '';
                    this.replyTo = null;
                    await this.setTyping(false);
                } finally { this.sending = false; }
            },

            upsertMessage(message) {
                const idx = this.messages.findIndex((m) => Number(m.id) === Number(message.id));
                if (idx >= 0) {
                    this.messages[idx] = { ...this.messages[idx], ...message };
                    this.messages = [...this.messages];
                    return;
                }
                this.messages.push(message);
            },

            async toggleReaction(messageId, emoji) {
                await window.axios.post(`/api/v1/pools/${this.poolId}/chat/messages/${messageId}/reactions`, { emoji });
            },

            reactionLabel(reaction) {
                return `${reaction?.emoji || ''} ${Number(reaction?.count || 0)}`;
            },

            openCtx(item) { this.ctxMsg = item; },

            canEditMessage(item) {
                return !!item && this.isMine(item) && !item.deleted_at && item.can_edit !== false;
            },

            startEdit(item) {
                if (!this.canEditMessage(item)) return;
                this.editingMessageId = Number(item.id || 0) || null;
                this.draft = String(item.body || '');
                this.replyTo = null;
            },

            cancelEdit() {
                this.editingMessageId = null;
                this.draft = '';
            },

            async deleteMessage(item) {
                if (!item?.id || !this.isMine(item) || item.deleted_at) return;
                await window.axios.delete(`/api/v1/pools/${this.poolId}/chat/messages/${item.id}`);
            },

            async copyMsg(item) {
                try { await navigator.clipboard.writeText(item.body || ''); } catch (_) {}
            },

            touchTyping() {
                this.setTyping(true);
                if (this.typingTimeout) clearTimeout(this.typingTimeout);
                this.typingTimeout = setTimeout(() => this.setTyping(false), 1500);
            },

            autoResize(e) {
                const el = e.target;
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 112) + 'px';
            },

            isMine(item) { return Number(item.user?.id || 0) === Number(this.userId || 0); },

            isFirstInGroup(idx) {
                if (idx === 0) return true;
                const cur = this.messages[idx];
                const prev = this.messages[idx - 1];
                return Number(cur.user?.id) !== Number(prev.user?.id) || this.minuteDiff(prev, cur) > 2;
            },

            isLastInGroup(idx) {
                if (idx === this.messages.length - 1) return true;
                const cur = this.messages[idx];
                const next = this.messages[idx + 1];
                return Number(cur.user?.id) !== Number(next.user?.id) || this.minuteDiff(cur, next) > 2;
            },

            minuteDiff(a, b) {
                const ta = a.created_at ? new Date(a.created_at).getTime() : 0;
                const tb = b.created_at ? new Date(b.created_at).getTime() : 0;
                return Math.abs(tb - ta) / 60000;
            },

            showDateSep(idx) {
                if (idx === 0) return true;
                const a = this.messages[idx - 1]?.created_at;
                const b = this.messages[idx]?.created_at;
                if (!a || !b) return false;
                return new Date(a).toDateString() !== new Date(b).toDateString();
            },

            readState(messageId) {
                const ownId = Number(this.userId || 0);
                const vals = Object.entries(this.readsMap || {})
                    .filter(([uid]) => Number(uid) !== ownId)
                    .map(([, last]) => Number(last || 0));
                return vals.some((last) => last >= Number(messageId || 0)) ? 'Lido' : 'Enviado';
            },

            async setTyping(typing) {
                try { await window.axios.post(`/api/v1/pools/${this.poolId}/chat/typing`, { typing: !!typing }); } catch (_) {}
            },

            async markRead() {
                const lastId = this.messages.length ? this.messages[this.messages.length - 1].id : null;
                if (!lastId) return;
                try { await window.axios.post(`/api/v1/pools/${this.poolId}/chat/read`, { last_read_message_id: lastId }); } catch (_) {}
            },

            formatTime(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                return Number.isNaN(d.getTime()) ? '' : d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            },

            formatDate(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return '';
                const today = new Date();
                if (d.toDateString() === today.toDateString()) return 'Hoje';
                const yest = new Date(today); yest.setDate(today.getDate() - 1);
                if (d.toDateString() === yest.toDateString()) return 'Ontem';
                return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
            },

            hashStr(s) {
                let h = 0;
                for (let i = 0; i < s.length; i++) h = ((h << 5) - h) + s.charCodeAt(i);
                return Math.abs(h);
            },

            avatarBg(name) {
                const p = ['#1d4ed8','#0f766e','#7c3aed','#b45309','#be123c','#0369a1'];
                return p[this.hashStr(String(name||'')) % p.length];
            },

            nameColor(name) {
                const p = ['#60a5fa','#34d399','#a78bfa','#f59e0b','#f472b6','#22d3ee'];
                return p[this.hashStr(String(name||'')) % p.length];
            },

            initials(name) {
                const n = String(name||'').trim();
                if (!n) return '?';
                return n.split(/\s+/).slice(0,2).map(p=>p.charAt(0).toUpperCase()).join('');
            },

            scrollBottom() {
                const el = this.$refs.list;
                if (el) el.scrollTop = el.scrollHeight;
            },
        };
    }
</script>
<style>
/* ── WhatsApp-style chat ─────────────────────────────────────── */
.wc-thread {
    background: #0b141a;
    box-shadow: 0 8px 32px rgba(0,0,0,.45);
}
.wc-header {
    background: #1f2c34;
    border-bottom: 1px solid rgba(255,255,255,.06);
}
.wc-group-avatar {
    width: 36px; height: 36px;
    border-radius: 999px;
    background: #2a3942;
    display: flex; align-items: center; justify-content: center;
}
.wc-messages {
    background:
        radial-gradient(ellipse at 20% 30%, rgba(0,92,75,.08) 0%, transparent 60%),
        #0b141a;
}
/* Bubbles */
.wc-bubble-me {
    background: #005c4b;
    border-radius: 18px 18px 4px 18px;
    padding: 7px 12px 6px;
    color: #e9edef;
    box-shadow: 0 1px 2px rgba(0,0,0,.35);
    position: relative;
}
.wc-bubble-other {
    background: #202c33;
    border-radius: 18px 18px 18px 4px;
    padding: 7px 12px 6px;
    color: #e9edef;
    box-shadow: 0 1px 2px rgba(0,0,0,.35);
    position: relative;
}
.wc-body { color: #e9edef; }
.wc-meta { color: rgba(233,237,239,.6); line-height: 1; }
/* Reply quote */
.wc-reply-me {
    background: rgba(0,0,0,.2);
    border-left: 3px solid #00a884;
}
.wc-reply-other {
    background: rgba(0,0,0,.2);
    border-left: 3px solid #53bdeb;
}
/* Reactions */
.wc-reactions-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
}
.wc-reaction {
    display: inline-flex; align-items: baseline; gap: 2px;
    font-size: 14px; line-height: 1;
    padding: 0;
    border: 0;
    background: transparent;
    color: rgba(233,237,239,.85);
    font-weight: 800;
    transition: color .1s, opacity .1s;
}
.wc-reaction-active { color: #53bdeb; }
.wc-audit {
    margin-top: 6px;
    border-top: 1px solid rgba(255,255,255,.08);
    padding-top: 5px;
}
.wc-audit-line {
    color: #fbbf24;
    font-size: 10px;
    line-height: 1.35;
    opacity: .9;
}
.wc-typing-indicator {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: #8fb7aa;
    line-height: 1.15;
}
.wc-typing-dots {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    transform: translateY(1px);
}
.wc-typing-dots i {
    width: 3px;
    height: 3px;
    border-radius: 999px;
    background: currentColor;
    animation: wcTypingDot 1.05s infinite ease-in-out;
}
.wc-typing-dots i:nth-child(2) { animation-delay: .16s; }
.wc-typing-dots i:nth-child(3) { animation-delay: .32s; }
@keyframes wcTypingDot {
    0%, 70%, 100% { opacity: .35; transform: translateY(0); }
    35% { opacity: 1; transform: translateY(-3px); }
}
/* Context menu button */
.wc-ctx-btn {
    display: none;
    cursor: pointer;
    color: rgba(233,237,239,.55);
    padding: 2px;
    transition: color .1s;
}
.wc-bubble-me:hover .wc-ctx-btn,
.wc-bubble-other:hover .wc-ctx-btn { display: block; }
.wc-ctx-btn:hover { color: rgba(233,237,239,.9); }
/* Context menu */
.wc-ctx-menu {
    background: #233138;
    border: 1px solid rgba(255,255,255,.1);
}
.wc-ctx-item {
    display: flex; align-items: center; gap-x: 8px; gap: 8px;
    width: 100%; padding: 9px 12px;
    font-size: 13px; color: #e9edef;
    border-radius: 8px;
    transition: background .1s;
}
.wc-ctx-item:hover { background: rgba(255,255,255,.06); }
/* Reply bar */
.wc-reply-bar {
    background: #1f2c34;
    border-top: 1px solid rgba(255,255,255,.06);
}
/* Composer */
.wc-composer {
    background: #1f2c34;
    border-top: 1px solid rgba(255,255,255,.06);
}
.wc-input-wrap {
    background: #2a3942;
    border: 1px solid rgba(255,255,255,.08);
    min-height: 44px;
}
.wc-textarea {
    color: #e9edef;
    font-size: 14px;
    line-height: 1.4;
    min-height: 24px;
}
.wc-textarea::placeholder { color: #8696a0; }
.wc-send {
    width: 44px; height: 44px; border-radius: 999px;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, color .15s, transform .1s;
    flex-shrink: 0;
}
.wc-send:active { transform: scale(.93); }
.wc-send-active {
    background: #00a884; color: #fff;
    box-shadow: 0 2px 8px rgba(0,168,132,.35);
}
.wc-send-idle {
    background: #2a3942; color: #8696a0;
}
</style>
@endonce
