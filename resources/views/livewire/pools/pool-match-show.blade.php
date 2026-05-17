@php
$isLive     = in_array($match->status, ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT']);
$isPreMatch = $match->status === 'PRE_MATCH';
$isFinished = $match->status === 'FINISHED';
$hasScore   = $isLive || $isFinished;

$statusLabel = match($match->status) {
    'IN_PLAY'   => 'Ao Vivo',
    'PAUSED'    => 'Intervalo',
    'FINISHED'  => 'Encerrado',
    'TIMED', 'SCHEDULED' => 'Agendado',
    default     => ucfirst(strtolower($match->status ?? '')),
};

$homeScore = $match->home_score_full_time ?? 0;
$awayScore = $match->away_score_full_time ?? 0;

$liveMinute = $isLive ? data_get($match->raw_payload, 'minute') : null;
$matchDate  = ($match->utc_date ?? $match->local_date)?->timezone('America/Sao_Paulo');
if (in_array($match->status, ['TIMED', 'SCHEDULED'], true) && $matchDate) {
    if ($matchDate->isToday()) {
        $statusLabel = 'Hoje';
    } elseif ($matchDate->isTomorrow()) {
        $statusLabel = 'Amanhã';
    }
}

$rows     = $this->statsRows();
$hLineup  = $this->homeLineup();
$aLineup  = $this->awayLineup();
$hBench   = $this->homeBench();
$aBench   = $this->awayBench();
$bookings = $this->bookings();
$goalEvents = $this->goalEvents();

$normalizePlayer = static fn (?string $name): string => mb_strtolower(trim((string) $name));
$toTokens = static function (?string $name): array {
    $clean = mb_strtolower(trim((string) $name));
    $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $clean);
    $parts = array_values(array_filter(preg_split('/\s+/u', (string) $clean)));
    return $parts ?: [];
};
$buildGoalScorerList = static function (array $events, callable $normalizePlayer, callable $toTokens, string $side): array {
    $rows = [];
    foreach ($events as $g) {
        if (!empty($g['is_disallowed'])) {
            continue;
        }
        $isSide = $side === 'home' ? !empty($g['is_home']) : !empty($g['is_away']);
        if (!$isSide) {
            continue;
        }
        $playerRaw = (string) ($g['player_name'] ?? '');
        $normalized = $normalizePlayer($playerRaw);
        if ($normalized === '') {
            continue;
        }
        $tokens = $toTokens($playerRaw);
        $rows[] = [
            'normalized' => $normalized,
            'tokens' => $tokens,
            'first_initial' => $tokens !== [] ? mb_substr($tokens[0], 0, 1) : '',
            'last_name' => $tokens !== [] ? end($tokens) : '',
        ];
    }
    return $rows;
};
$countGoalsForLineupPlayer = static function (?string $lineupName, array $scorers, callable $normalizePlayer, callable $toTokens): int {
    $name = $normalizePlayer($lineupName);
    if ($name === '') {
        return 0;
    }
    $tokens = $toTokens((string) $lineupName);
    $firstInitial = $tokens !== [] ? mb_substr($tokens[0], 0, 1) : '';
    $lastName = $tokens !== [] ? end($tokens) : '';
    $count = 0;
    foreach ($scorers as $scorer) {
        if (($scorer['normalized'] ?? '') === $name) {
            $count++;
            continue;
        }
        $scorerLast = (string) ($scorer['last_name'] ?? '');
        $scorerInitial = (string) ($scorer['first_initial'] ?? '');
        if ($lastName !== '' && $scorerLast !== '' && $lastName === $scorerLast) {
            if ($firstInitial === '' || $scorerInitial === '' || $firstInitial === $scorerInitial) {
                $count++;
            }
        }
    }
    return $count;
};

$homeGoalScorers = $buildGoalScorerList($goalEvents, $normalizePlayer, $toTokens, 'home');
$awayGoalScorers = $buildGoalScorerList($goalEvents, $normalizePlayer, $toTokens, 'away');

// Calculate percentage bar widths for each stat row
$bar = function (string $h, string $a): array {
    $isPct = str_contains($h, '%') || str_contains($a, '%');
    if ($isPct) {
        $hv = (float) str_replace('%', '', $h);
        $av = (float) str_replace('%', '', $a);
        return ['h' => max(0, min(100, $hv)), 'ok' => ($hv + $av) > 0];
    }
    $hv    = is_numeric($h) ? (int) $h : 0;
    $av    = is_numeric($a) ? (int) $a : 0;
    $total = $hv + $av;
    if ($total === 0) return ['h' => 50, 'ok' => false];
    return ['h' => round($hv / $total * 100), 'ok' => true];
};

$posAbbr = fn(string $pos): string => match (strtolower(trim($pos))) {
    'goalkeeper' => 'GK',
    'defender'   => 'DEF',
    'midfielder' => 'MEI',
    'forward'    => 'ATA',
    default      => strtoupper(substr(trim($pos), 0, 3)),
};

$cardInfo = function (string $card): array {
    $lower = strtolower($card);
    if (str_contains($lower, 'yellow') && str_contains($lower, 'red')) {
        return ['label' => '2° Amarelo', 'color' => 'text-orange-400', 'bg' => 'bg-orange-400', 'shadow' => 'shadow-orange-500/40'];
    }
    if (str_contains($lower, 'red')) {
        return ['label' => 'Vermelho', 'color' => 'text-red-400', 'bg' => 'bg-red-500', 'shadow' => 'shadow-red-500/40'];
    }
    return ['label' => 'Amarelo', 'color' => 'text-amber-400', 'bg' => 'bg-amber-400', 'shadow' => 'shadow-amber-400/40'];
};
@endphp

@once
<style>
    .hero-gradient {
        background:
            radial-gradient(ellipse 80% 60% at 15% 50%, rgba(59,130,246,.12) 0%, transparent 65%),
            radial-gradient(ellipse 80% 60% at 85% 50%, rgba(244,63,94,.12) 0%, transparent 65%),
            radial-gradient(ellipse 100% 80% at 50% 0%,  rgba(15,30,60,.8)   0%, transparent 80%);
    }
    .stat-bar-fill {
        transition: width 1s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .tab-content-enter { opacity: 0; transform: translateY(6px); }
    .tab-content-active { transition: opacity .18s ease, transform .18s ease; }
    .tab-content-done   { opacity: 1; transform: none; }
    .score-glow {
        text-shadow: 0 0 40px rgba(255,255,255,.15);
    }
    .card-rect {
        border-radius: 3px;
        display: inline-block;
        width: 14px;
        height: 18px;
        box-shadow: 0 2px 8px var(--card-shadow, rgba(0,0,0,.5));
    }
    .lineup-sub-flash {
        animation: lineupSubFlash 1.15s ease;
        border-color: rgba(251, 191, 36, 0.45) !important;
        background: rgba(251, 191, 36, 0.08) !important;
    }
    @keyframes lineupSubFlash {
        0% { background: rgba(251, 191, 36, 0.28); }
        100% { background: rgba(251, 191, 36, 0.08); }
    }
</style>
@endonce

<div class="animate-fade-in pb-8"
     @if($isLive || $isPreMatch) wire:poll.10s="refreshLiveData" @endif
     x-data="{
         tab: 'stats',
         barsReady: false,
         switchTab(t) {
             this.tab = t;
             if (t === 'stats') {
                 this.barsReady = false;
                 this.$nextTick(() => setTimeout(() => this.barsReady = true, 80));
             }
         },
         init() { setTimeout(() => this.barsReady = true, 350) }
     }">

    {{-- ── Back button ── --}}
    <div class="px-4 pt-4 pb-3">
        <a href="{{ route('pools.show', $pool->slug) }}"
           class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors group">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-800/80 group-hover:bg-slate-700 border border-slate-700/50 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </span>
            <span class="font-medium">{{ $pool->name }}</span>
        </a>
    </div>

    {{-- ════════════════════════════════
         MATCH HERO
    ════════════════════════════════ --}}
    <div class="mx-4 mb-1 rounded-2xl border border-slate-700/40 overflow-hidden hero-gradient relative">

        {{-- Subtle top accent line --}}
        <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-blue-500/40 to-transparent"></div>
        <div class="absolute bottom-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-rose-500/30 to-transparent"></div>

        {{-- Group · Date --}}
        <div class="flex items-center justify-center gap-2 pt-4 pb-2">
            @if($match->group_name)
            <span class="inline-flex items-center rounded-full border border-slate-700/60 bg-slate-800/60 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-widest text-slate-400">
                {{ $match->group_name }}
            </span>
            <span class="h-1 w-1 rounded-full bg-slate-700"></span>
            @endif
            <span class="text-[11px] text-slate-500">
                {{ $matchDate?->format('d/m/Y · H:i') }}
            </span>
        </div>

        {{-- Teams + Score --}}
        <div class="flex items-center justify-between px-4 sm:px-8 pb-5 pt-1 gap-3">

            {{-- Home Team --}}
            <div class="flex flex-1 flex-col items-center gap-2.5 min-w-0">
                <div class="relative">
                    @if($match->homeTeam?->crest)
                        <img src="{{ $match->homeTeam->crest }}"
                             alt="{{ $match->homeTeam->name }}"
                             class="h-16 w-16 sm:h-20 sm:w-20 object-contain drop-shadow-xl transition-transform duration-300 hover:scale-105">
                    @else
                        <div class="h-16 w-16 sm:h-20 sm:w-20 rounded-2xl bg-blue-900/30 border border-blue-700/30 flex items-center justify-center text-xl font-black text-blue-300">
                            {{ $match->homeTeam?->tla ?? '?' }}
                        </div>
                    @endif
                    @if($isLive && $homeScore > $awayScore)
                        <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 h-1 w-8 rounded-full bg-blue-400/60 blur-sm"></div>
                    @endif
                </div>
                <div class="text-center min-w-0 px-1">
                    <p class="text-[13px] sm:text-sm font-bold text-slate-100 truncate leading-tight">
                        {{ $match->homeTeam?->localized_name ?? '—' }}
                    </p>
                    <span class="text-[10px] font-semibold uppercase tracking-widest text-blue-400/70">Casa</span>
                </div>
            </div>

            {{-- Score Center --}}
            <div class="flex flex-col items-center gap-1.5 shrink-0 px-1">
                @if($hasScore)
                    <div class="flex items-center gap-2 sm:gap-3">
                        <span class="text-5xl sm:text-6xl font-black text-white tabular-nums tracking-tight score-glow
                                     {{ $homeScore > $awayScore ? 'text-white' : 'text-slate-400' }}">
                            {{ $homeScore }}
                        </span>
                        <span class="text-lg font-light text-slate-600 mt-1">–</span>
                        <span class="text-5xl sm:text-6xl font-black text-white tabular-nums tracking-tight score-glow
                                     {{ $awayScore > $homeScore ? 'text-white' : 'text-slate-400' }}">
                            {{ $awayScore }}
                        </span>
                    </div>
                @else
                    <div class="px-4 py-2 rounded-xl bg-slate-800/60 border border-slate-700/40">
                        <span class="text-xl font-black text-slate-600 tracking-widest">VS</span>
                    </div>
                @endif

                {{-- Status badge --}}
                <div class="flex items-center gap-1.5 mt-0.5">
                    @if($isLive)
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                        </span>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-red-400">
                            {{ $statusLabel }}@if($liveMinute !== null)<span class="text-red-300/80 font-black tabular-nums"> · {{ $liveMinute }}'</span>@endif
                        </span>
                    @elseif($isFinished)
                        <span class="inline-flex items-center rounded-full border border-slate-700/50 bg-slate-800/60 px-3 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                            {{ $statusLabel }}
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full border border-blue-700/30 bg-blue-900/20 px-3 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-blue-400">
                            {{ $statusLabel }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Away Team --}}
            <div class="flex flex-1 flex-col items-center gap-2.5 min-w-0">
                <div class="relative">
                    @if($match->awayTeam?->crest)
                        <img src="{{ $match->awayTeam->crest }}"
                             alt="{{ $match->awayTeam->name }}"
                             class="h-16 w-16 sm:h-20 sm:w-20 object-contain drop-shadow-xl transition-transform duration-300 hover:scale-105">
                    @else
                        <div class="h-16 w-16 sm:h-20 sm:w-20 rounded-2xl bg-rose-900/30 border border-rose-700/30 flex items-center justify-center text-xl font-black text-rose-300">
                            {{ $match->awayTeam?->tla ?? '?' }}
                        </div>
                    @endif
                    @if($isLive && $awayScore > $homeScore)
                        <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 h-1 w-8 rounded-full bg-rose-400/60 blur-sm"></div>
                    @endif
                </div>
                <div class="text-center min-w-0 px-1">
                    <p class="text-[13px] sm:text-sm font-bold text-slate-100 truncate leading-tight">
                        {{ $match->awayTeam?->localized_name ?? '—' }}
                    </p>
                    <span class="text-[10px] font-semibold uppercase tracking-widest text-rose-400/70">Visitante</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════
         TAB NAVIGATION
    ════════════════════════════════ --}}
    <div class="sticky top-0 z-20 bg-pitch-950/95 backdrop-blur-md border-b border-slate-800/80 px-4 mt-4">
        <div class="flex -mb-px">
            @foreach([
                ['stats',  'Estatísticas'],
                ['lineup', 'Escalação'],
                ['events', 'Eventos'],
            ] as [$t, $label])
            <button @click="switchTab('{{ $t }}')"
                    :class="tab === '{{ $t }}'
                        ? 'text-emerald-400 border-b-2 border-emerald-500'
                        : 'text-slate-500 hover:text-slate-300 border-b-2 border-transparent'"
                    class="flex-1 py-3 text-sm font-semibold transition-colors duration-150 text-center">
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- ════════════════════════════════
         TAB CONTENT
    ════════════════════════════════ --}}
    <div class="px-4 pt-4">

        {{-- ──────────────────────────
             TAB: ESTATÍSTICAS
        ────────────────────────── --}}
        <div x-show="tab === 'stats'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-1.5"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="space-y-3">

            @if(collect($rows)->every(fn($r) => $r['home'] === '-' && $r['away'] === '-'))
                <div class="card p-12 text-center">
                    <div class="text-5xl mb-4">📊</div>
                    <p class="text-slate-300 font-semibold">Estatísticas indisponíveis</p>
                    <p class="text-sm text-slate-600 mt-1">{{ $this->statsUnavailableMessage() }}</p>
                </div>
            @else

            {{-- Team color legend --}}
            <div class="flex items-center justify-between px-1 mb-1">
                <div class="flex items-center gap-2">
                    @if($match->homeTeam?->crest)
                        <img src="{{ $match->homeTeam->crest }}" alt="" class="h-5 w-5 object-contain">
                    @endif
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-wide">
                        {{ $match->homeTeam?->tla ?? 'Casa' }}
                    </span>
                </div>
                <span class="text-[9px] uppercase tracking-[0.15em] font-medium text-slate-600">Comparativo</span>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-rose-400 uppercase tracking-wide">
                        {{ $match->awayTeam?->tla ?? 'Visitante' }}
                    </span>
                    @if($match->awayTeam?->crest)
                        <img src="{{ $match->awayTeam->crest }}" alt="" class="h-5 w-5 object-contain">
                    @endif
                </div>
            </div>

            {{-- Stat rows --}}
            <div class="card overflow-hidden">
                @foreach($rows as $idx => $row)
                @php $b = $bar($row['home'], $row['away']); @endphp
                <div class="px-4 py-3.5 {{ !$loop->last ? 'border-b border-slate-800/50' : '' }}">
                    {{-- Label + Values --}}
                    <div class="flex items-center justify-between mb-2.5 gap-2">
                        <span class="text-base font-black tabular-nums leading-none
                            {{ $b['ok'] && $b['h'] >= 50 ? 'text-blue-300' : 'text-slate-400' }}">
                            {{ $row['home'] === '-' ? '—' : $row['home'] }}
                        </span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 text-center leading-tight px-2">
                            {{ $row['label'] }}
                        </span>
                        <span class="text-base font-black tabular-nums leading-none
                            {{ $b['ok'] && (100 - $b['h']) >= 50 ? 'text-rose-300' : 'text-slate-400' }}">
                            {{ $row['away'] === '-' ? '—' : $row['away'] }}
                        </span>
                    </div>

                    {{-- Bar --}}
                    @if($b['ok'])
                    <div class="relative flex h-1.5 overflow-hidden rounded-full bg-rose-500/20">
                        <div class="stat-bar-fill absolute inset-y-0 left-0 rounded-l-full
                                    bg-gradient-to-r from-blue-600 to-blue-400"
                             :style="{ width: (barsReady ? '{{ $b['h'] }}' : '0') + '%' }">
                        </div>
                        {{-- Gap divider --}}
                        @if($b['h'] > 2 && $b['h'] < 98)
                        <div class="absolute inset-y-0 w-px bg-pitch-900"
                             :style="{ left: (barsReady ? '{{ $b['h'] }}' : '0') + '%' }"></div>
                        @endif
                    </div>
                    @else
                    <div class="h-1.5 rounded-full bg-slate-800/40"></div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ──────────────────────────
             TAB: ESCALAÇÃO
        ────────────────────────── --}}
        <div x-show="tab === 'lineup'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-1.5"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-data="{ lineupTab: 'starters' }"
             class="space-y-3">

            {{-- Starters / Bench toggle --}}
            <div class="flex rounded-xl bg-slate-800/50 p-1 border border-slate-700/30">
                <button @click="lineupTab = 'starters'"
                        :class="lineupTab === 'starters' ? 'bg-pitch-900 text-slate-100 shadow-sm border border-slate-700/50' : 'text-slate-500 hover:text-slate-300'"
                        class="flex-1 py-2 text-xs font-semibold rounded-lg transition-all duration-150 text-center">
                    Titulares
                </button>
                <button @click="lineupTab = 'bench'"
                        :class="lineupTab === 'bench' ? 'bg-pitch-900 text-slate-100 shadow-sm border border-slate-700/50' : 'text-slate-500 hover:text-slate-300'"
                        class="flex-1 py-2 text-xs font-semibold rounded-lg transition-all duration-150 text-center">
                    Reservas
                </button>
            </div>

            {{-- Column headers --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="flex items-center gap-2 px-1">
                    @if($match->homeTeam?->crest)
                        <img src="{{ $match->homeTeam->crest }}" alt="" class="h-5 w-5 object-contain shrink-0">
                    @endif
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-wide truncate">
                        {{ $match->homeTeam?->tla ?? 'Casa' }}
                    </span>
                </div>
                <div class="flex items-center gap-2 px-1 justify-end">
                    <span class="text-xs font-bold text-rose-400 uppercase tracking-wide truncate">
                        {{ $match->awayTeam?->tla ?? 'Visitante' }}
                    </span>
                    @if($match->awayTeam?->crest)
                        <img src="{{ $match->awayTeam->crest }}" alt="" class="h-5 w-5 object-contain shrink-0">
                    @endif
                </div>
            </div>

            {{-- Starters --}}
            <div x-show="lineupTab === 'starters'">
                @if(empty($hLineup) && empty($aLineup))
                    <div class="card p-10 text-center">
                        <div class="text-4xl mb-3">👕</div>
                        <p class="text-slate-400 font-medium">Escalação indisponível</p>
                        <p class="text-sm text-slate-600 mt-1">Os dados serão publicados próximo ao início do jogo.</p>
                    </div>
                @else
                @php $maxL = max(count($hLineup), count($aLineup)); @endphp
                <div class="card overflow-hidden">
                    @for($i = 0; $i < $maxL; $i++)
                    @php $hp = $hLineup[$i] ?? null; $ap = $aLineup[$i] ?? null; @endphp
                    <div class="grid grid-cols-2 {{ !($i + 1 === $maxL) ? 'border-b border-slate-800/50' : '' }}">

                        {{-- Home player --}}
                        <div class="flex items-center gap-2 px-3 py-3 border-r border-slate-800/50 min-w-0 {{ !empty($hp['sub_in']) ? 'lineup-sub-flash' : '' }}">
                            @if($hp)
                            @php
                                $hGoals = $countGoalsForLineupPlayer($hp['name'] ?? '', $homeGoalScorers, $normalizePlayer, $toTokens);
                            @endphp
                            <div class="shrink-0 h-6 w-6 rounded-md bg-blue-950/60 border border-blue-800/40
                                        flex items-center justify-center text-[10px] font-black text-blue-300 tabular-nums">
                                {{ $hp['number'] ?? '?' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-[12px] font-semibold text-slate-200 truncate leading-tight">{{ $hp['name'] }}</p>
                                    @if($hGoals > 0)
                                        <span class="inline-flex items-center gap-0.5 shrink-0" aria-label="Gols do jogador">
                                            @for($g = 0; $g < $hGoals; $g++)
                                                <span class="text-[11px] leading-none">⚽</span>
                                            @endfor
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="inline-block text-[9px] font-bold uppercase tracking-wider text-blue-500 leading-tight">
                                        {{ $posAbbr($hp['position']) }}
                                    </span>
                                    @if(!empty($hp['sub_in']))
                                        <span class="text-[9px] font-bold uppercase tracking-wide text-amber-300">ENTROU</span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>

                        {{-- Away player --}}
                        <div class="flex items-center gap-2 px-3 py-3 min-w-0 flex-row-reverse {{ !empty($ap['sub_in']) ? 'lineup-sub-flash' : '' }}">
                            @if($ap)
                            @php
                                $aGoals = $countGoalsForLineupPlayer($ap['name'] ?? '', $awayGoalScorers, $normalizePlayer, $toTokens);
                            @endphp
                            <div class="shrink-0 h-6 w-6 rounded-md bg-rose-950/60 border border-rose-800/40
                                        flex items-center justify-center text-[10px] font-black text-rose-300 tabular-nums">
                                {{ $ap['number'] ?? '?' }}
                            </div>
                            <div class="min-w-0 flex-1 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if($aGoals > 0)
                                        <span class="inline-flex items-center gap-0.5 shrink-0" aria-label="Gols do jogador">
                                            @for($g = 0; $g < $aGoals; $g++)
                                                <span class="text-[11px] leading-none">⚽</span>
                                            @endfor
                                        </span>
                                    @endif
                                    <p class="text-[12px] font-semibold text-slate-200 truncate leading-tight">{{ $ap['name'] }}</p>
                                </div>
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(!empty($ap['sub_in']))
                                        <span class="text-[9px] font-bold uppercase tracking-wide text-amber-300">ENTROU</span>
                                    @endif
                                    <span class="inline-block text-[9px] font-bold uppercase tracking-wider text-rose-500 leading-tight">
                                        {{ $posAbbr($ap['position']) }}
                                    </span>
                                </div>
                            </div>
                            @endif
                        </div>

                    </div>
                    @endfor
                </div>
                @endif
            </div>

            {{-- Bench --}}
            <div x-show="lineupTab === 'bench'">
                @if(empty($hBench) && empty($aBench))
                    <div class="card p-10 text-center">
                        <p class="text-slate-500 text-sm">Reservas não disponíveis.</p>
                    </div>
                @else
                @php $maxB = max(count($hBench), count($aBench)); @endphp
                <div class="card overflow-hidden">
                    @for($i = 0; $i < $maxB; $i++)
                    @php $hp = $hBench[$i] ?? null; $ap = $aBench[$i] ?? null; @endphp
                    <div class="grid grid-cols-2 {{ !($i + 1 === $maxB) ? 'border-b border-slate-800/50' : '' }}">
                        <div class="flex items-center gap-2 px-3 py-2.5 border-r border-slate-800/50 min-w-0">
                            @if($hp)
                            <span class="shrink-0 h-5 w-5 rounded bg-slate-800 flex items-center justify-center text-[9px] font-bold text-slate-500 tabular-nums">
                                {{ $hp['number'] ?? '?' }}
                            </span>
                            <p class="text-[11px] text-slate-500 truncate">{{ $hp['name'] }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2.5 min-w-0 flex-row-reverse">
                            @if($ap)
                            <span class="shrink-0 h-5 w-5 rounded bg-slate-800 flex items-center justify-center text-[9px] font-bold text-slate-500 tabular-nums">
                                {{ $ap['number'] ?? '?' }}
                            </span>
                            <p class="text-[11px] text-slate-500 truncate text-right">{{ $ap['name'] }}</p>
                            @endif
                        </div>
                    </div>
                    @endfor
                </div>
                @endif
            </div>
        </div>

        {{-- ──────────────────────────
             TAB: EVENTOS
        ────────────────────────── --}}
        <div x-show="tab === 'events'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-1.5"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="space-y-3">

            @if(empty($bookings) && empty($goalEvents))
                <div class="card p-12 text-center">
                    <div class="text-5xl mb-4">🟨</div>
                    <p class="text-slate-300 font-semibold">Sem ocorrências</p>
                    <p class="text-sm text-slate-600 mt-1">Cartões e eventos serão exibidos aqui durante a partida.</p>
                </div>
            @else
            <div class="card overflow-hidden divide-y divide-slate-800/50">
                @foreach($goalEvents as $g)
                @php
                    $goalLabel = (string) ($g['detail'] ?? 'Goal');
                    $goalLabelLower = strtolower($goalLabel);
                    $goalBadge = !empty($g['is_disallowed'])
                        ? 'Anulado'
                        : (str_contains($goalLabelLower, 'penalty')
                            ? 'Pênalti'
                            : (str_contains($goalLabelLower, 'own') ? 'Gol Contra' : 'Gol'));
                    $goalMinute = $g['minute'] ?? '?';
                    $goalExtra = $g['extra_minute'] ?? null;
                    $goalTeamClass = $g['is_home'] ? 'text-blue-400' : (($g['is_away'] ?? false) ? 'text-rose-400' : 'text-slate-400');
                @endphp
                <div class="flex items-center gap-3 px-4 py-3.5 {{ !empty($g['is_disallowed']) ? 'bg-red-500/[0.08] opacity-75' : 'bg-emerald-500/[0.06]' }}">
                    <div class="w-9 shrink-0 text-right">
                        <span class="text-sm font-black tabular-nums text-emerald-300 leading-none">
                            {{ $goalMinute }}'@if($goalExtra)+{{ $goalExtra }}@endif
                        </span>
                    </div>
                    <div class="shrink-0 w-px h-8 bg-slate-800/80"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 min-w-0">
                            @if(!empty($g['is_home']) && $match->homeTeam?->crest)
                                <img src="{{ $match->homeTeam->crest }}" alt="" class="h-3.5 w-3.5 object-contain shrink-0">
                            @elseif(!empty($g['is_away']) && $match->awayTeam?->crest)
                                <img src="{{ $match->awayTeam->crest }}" alt="" class="h-3.5 w-3.5 object-contain shrink-0">
                            @endif
                            <p class="text-sm font-semibold text-slate-100 truncate leading-tight {{ !empty($g['is_disallowed']) ? 'line-through decoration-red-400/80' : '' }}">{{ $g['player_name'] }}</p>
                        </div>
                        <p class="text-[11px] {{ $goalTeamClass }} truncate">
                            {{ $g['team_name'] ?: 'Time não identificado' }}
                            @if(!empty($g['assist_name']))
                                · Assist: {{ $g['assist_name'] }}
                            @endif
                        </p>
                    </div>
                    <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide {{ !empty($g['is_disallowed']) ? 'text-red-300' : 'text-emerald-300' }}">{{ $goalBadge }}</span>
                </div>
                @endforeach

                @foreach($bookings as $b)
                @php
                    $cardType = data_get($b, 'card', 'YELLOW_CARD');
                    $ci       = $cardInfo($cardType);
                    $teamName = data_get($b, 'team.name', '');
                    $isHome   = $teamName === ($match->homeTeam?->name ?? '__none__');
                @endphp
                <div class="flex items-center gap-3 px-4 py-3.5">

                    {{-- Minute --}}
                    <div class="w-9 shrink-0 text-right">
                        <span class="text-sm font-black tabular-nums text-slate-300 leading-none">
                            {{ data_get($b, 'minute', '?') }}'
                        </span>
                    </div>

                    {{-- Divider line --}}
                    <div class="shrink-0 w-px h-8 bg-slate-800/80"></div>

                    {{-- Card icon --}}
                    <div class="shrink-0">
                        @php $lower = strtolower($cardType); @endphp
                        @if(str_contains($lower, 'yellow') && str_contains($lower, 'red'))
                            {{-- Yellow-Red: two overlapping cards --}}
                            <div class="relative w-5 h-6">
                                <div class="card-rect absolute top-0 left-1 bg-red-500" style="--card-shadow: rgba(239,68,68,.5)"></div>
                                <div class="card-rect absolute top-0 left-0 bg-amber-400" style="--card-shadow: rgba(251,191,36,.5)"></div>
                            </div>
                        @elseif(str_contains($lower, 'red'))
                            <div class="card-rect bg-red-500" style="--card-shadow: rgba(239,68,68,.5)"></div>
                        @else
                            <div class="card-rect bg-amber-400" style="--card-shadow: rgba(251,191,36,.5)"></div>
                        @endif
                    </div>

                    {{-- Player + team info --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-100 truncate leading-tight">
                            {{ data_get($b, 'player.name', 'Jogador') }}
                        </p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            @if($isHome && $match->homeTeam?->crest)
                                <img src="{{ $match->homeTeam->crest }}" alt="" class="h-3.5 w-3.5 object-contain shrink-0">
                            @elseif(!$isHome && $match->awayTeam?->crest)
                                <img src="{{ $match->awayTeam->crest }}" alt="" class="h-3.5 w-3.5 object-contain shrink-0">
                            @endif
                            <p class="text-[11px] font-medium {{ $isHome ? 'text-blue-400' : 'text-rose-400' }} truncate">
                                {{ $teamName ?: '—' }}
                            </p>
                        </div>
                    </div>

                    {{-- Card type label --}}
                    <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide {{ $ci['color'] }}">
                        {{ $ci['label'] }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>{{-- /px-4 pt-4 --}}

</div>
