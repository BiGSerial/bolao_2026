@php
$isLive     = in_array($match->status, ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT']);
$isPreMatch = $match->status === 'PRE_MATCH';
$isFinished = $match->status === 'FINISHED';
$hasScore   = $isLive || $isFinished;

$statusLabel = match($match->status) {
    'IN_PLAY'          => 'Ao Vivo',
    'PAUSED'           => 'Intervalo',
    'EXTRA_TIME'       => 'Prorrogação',
    'PENALTY_SHOOTOUT' => 'Pênaltis',
    'FINISHED'         => 'Encerrado',
    'TIMED'            => 'Agendado',
    'SCHEDULED'        => 'Agendado',
    'PRE_MATCH'        => 'Pré-Jogo',
    'SUSPENDED'        => 'Suspenso',
    'POSTPONED'        => 'Adiado',
    'CANCELLED'        => 'Cancelado',
    'AWARDED'          => 'Decidido',
    default            => ucfirst(strtolower($match->status ?? '')),
};

$homeScore = $match->home_score_full_time ?? 0;
$awayScore = $match->away_score_full_time ?? 0;

$liveMinute  = $this->resolveLiveMinute();
$extraMinute = $isLive ? data_get($match->raw_payload, 'injury_time', data_get($match->raw_payload, 'extra')) : null;
$matchDate   = $match->kickoffAtBrazil();
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
$apiEvents = (array) data_get($match->detail?->payload, '_api_football.events', []);
$sidebarPoolContext = $this->sidebarPoolContext();
$sidebarLiveContext = $this->sidebarLiveContext();
$sidebarPool = $sidebarPoolContext['pool'] ?? null;
$sidebarTopRankings = $sidebarPoolContext['top_rankings'] ?? collect();
$sidebarLiveMatches = $sidebarLiveContext['live'] ?? collect();
$sidebarLiveMinutes = $sidebarLiveContext['liveMinutes'] ?? [];
$sidebarLiveMatchStats = $sidebarLiveContext['liveMatchStats'] ?? [];

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
        if (!empty($g['is_disallowed'])) continue;
        $isSide = $side === 'home' ? !empty($g['is_home']) : !empty($g['is_away']);
        if (!$isSide) continue;
        $playerRaw = (string) ($g['player_name'] ?? '');
        $normalized = $normalizePlayer($playerRaw);
        if ($normalized === '') continue;
        $tokens = $toTokens($playerRaw);
        $rows[] = [
            'normalized' => $normalized,
            'first_initial' => $tokens !== [] ? mb_substr($tokens[0], 0, 1) : '',
            'last_name' => $tokens !== [] ? end($tokens) : '',
        ];
    }
    return $rows;
};
$countGoalsForLineupPlayer = static function (?string $lineupName, array $scorers, callable $normalizePlayer, callable $toTokens): int {
    $name = $normalizePlayer($lineupName);
    if ($name === '') return 0;
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
            if ($firstInitial === '' || $scorerInitial === '' || $firstInitial === $scorerInitial) $count++;
        }
    }
    return $count;
};
$homeGoalScorers = $buildGoalScorerList($goalEvents, $normalizePlayer, $toTokens, 'home');
$awayGoalScorers = $buildGoalScorerList($goalEvents, $normalizePlayer, $toTokens, 'away');
$buildCardList = static function (array $bookings, array $apiEvents, callable $normalizePlayer, callable $toTokens, string $side) use ($match): array {
    $rows = [];

    foreach ($bookings as $b) {
        $teamName = (string) data_get($b, 'team.name', data_get($b, 'teamName', ''));
        $isSide = $side === 'home'
            ? $teamName === (string) ($match->homeTeam?->name ?? '')
            : $teamName === (string) ($match->awayTeam?->name ?? '');
        if (! $isSide) continue;

        $playerRaw = (string) data_get($b, 'player.name', data_get($b, 'playerName', data_get($b, 'player', '')));
        $cardRaw = mb_strtolower((string) data_get($b, 'card', data_get($b, 'type', data_get($b, 'detail', ''))));
        if ($playerRaw === '' || $cardRaw === '') continue;

        $normalized = $normalizePlayer($playerRaw);
        $tokens = $toTokens($playerRaw);
        $rows[] = [
            'normalized' => $normalized,
            'first_initial' => $tokens !== [] ? mb_substr($tokens[0], 0, 1) : '',
            'last_name' => $tokens !== [] ? end($tokens) : '',
            'is_red' => str_contains($cardRaw, 'red'),
            'is_yellow' => str_contains($cardRaw, 'yellow'),
        ];
    }

    foreach ($apiEvents as $event) {
        $type = mb_strtolower((string) data_get($event, 'type', ''));
        $detail = mb_strtolower((string) data_get($event, 'detail', ''));
        if (! str_contains($type, 'card') && ! str_contains($detail, 'card') && !str_contains($detail, 'yellow') && !str_contains($detail, 'red')) {
            continue;
        }
        $teamName = (string) data_get($event, 'team.name', '');
        $isSide = $side === 'home'
            ? $teamName === (string) ($match->homeTeam?->name ?? '')
            : $teamName === (string) ($match->awayTeam?->name ?? '');
        if (! $isSide) continue;

        $playerRaw = (string) data_get($event, 'player.name', '');
        if ($playerRaw === '') continue;
        $normalized = $normalizePlayer($playerRaw);
        $tokens = $toTokens($playerRaw);
        $rows[] = [
            'normalized' => $normalized,
            'first_initial' => $tokens !== [] ? mb_substr($tokens[0], 0, 1) : '',
            'last_name' => $tokens !== [] ? end($tokens) : '',
            'is_red' => str_contains($detail, 'red'),
            'is_yellow' => str_contains($detail, 'yellow'),
        ];
    }

    return $rows;
};
$countCardsForLineupPlayer = static function (?string $lineupName, array $cards, callable $normalizePlayer, callable $toTokens): array {
    $name = $normalizePlayer($lineupName);
    if ($name === '') return ['yellow' => 0, 'red' => 0];
    $tokens = $toTokens((string) $lineupName);
    $firstInitial = $tokens !== [] ? mb_substr($tokens[0], 0, 1) : '';
    $lastName = $tokens !== [] ? end($tokens) : '';
    $yellow = 0;
    $red = 0;
    foreach ($cards as $card) {
        $isMatch = false;
        if (($card['normalized'] ?? '') === $name) {
            $isMatch = true;
        } else {
            $cardLast = (string) ($card['last_name'] ?? '');
            $cardInitial = (string) ($card['first_initial'] ?? '');
            if ($lastName !== '' && $cardLast !== '' && $lastName === $cardLast) {
                if ($firstInitial === '' || $cardInitial === '' || $firstInitial === $cardInitial) {
                    $isMatch = true;
                }
            }
        }
        if (! $isMatch) continue;
        if (!empty($card['is_red'])) $red++;
        elseif (!empty($card['is_yellow'])) $yellow++;
    }
    return ['yellow' => $yellow, 'red' => $red];
};
$homeCards = $buildCardList($bookings, $apiEvents, $normalizePlayer, $toTokens, 'home');
$awayCards = $buildCardList($bookings, $apiEvents, $normalizePlayer, $toTokens, 'away');

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
        background: #13161b;
    }
    .match-panel-flat {
        background: #13161b !important;
    }
    .match-panel-accent {
        position: relative;
        overflow: hidden;
    }
    .match-panel-accent::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, #f5a623, #e8930d);
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
    .mobile-swiper { display: block; }
    .desktop-grid  { display: none;  }
    .lineup-sub-out {
        background: rgba(15, 23, 42, 0.22) !important;
        border-color: rgba(51, 65, 85, 0.45) !important;
    }
    .lineup-sub-out .lineup-dim {
        opacity: .52;
    }
    .sub-arrow-icon {
        width: 14px;
        height: 14px;
        display: inline-block;
        filter: drop-shadow(0 0 4px rgba(0, 0, 0, .45));
        flex-shrink: 0;
    }
    @media (min-width: 768px) {
        .mobile-swiper { display: none; }
        .desktop-grid  { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; padding: 1rem 1rem 0; }
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
        <a href="{{ route('dashboard') }}"
           class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors group">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-800/80 group-hover:bg-slate-700 border border-slate-700/50 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </span>
            <span class="font-medium">Voltar ao dashboard</span>
        </a>
    </div>

    {{-- ════════════════════════════════
         MATCH HERO
    ════════════════════════════════ --}}
    <div class="mx-4 mb-3 card rounded-xl overflow-hidden hero-gradient relative">

        {{-- Subtle top accent line --}}
        <div class="absolute top-0 inset-x-0 h-[2px] bg-gradient-to-r from-bolao-accent to-bolao-accent2"></div>
        <div class="absolute bottom-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>

        {{-- Group · Date --}}
        <div class="flex items-center justify-center gap-2 pt-4 pb-2">
            @if($match->group_name)
            <span class="inline-flex items-center rounded-full border border-slate-700/60 bg-slate-800/60 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-widest text-slate-400">
                {{ $match->group_name }}
            </span>
            <span class="h-1 w-1 rounded-full bg-slate-700"></span>
            @endif
            <span class="text-[11px] text-slate-500">
                {{ $matchDate?->format('d/m/Y · H:i') }} (Brasília)
            </span>
        </div>

        {{-- Teams + Score --}}
        <div class="flex items-center justify-between px-4 sm:px-8 pb-5 pt-1 gap-3">

            {{-- Home Team --}}
            <div class="flex flex-1 flex-col items-center gap-2.5 min-w-0">
                <div class="relative">
                    @if($match->homeTeam?->crest)
                        <img src="{{ $match->homeTeam->crest }}"
                             alt="{{ $match->homeTeam?->localized_name ?? $match->homeTeam->name }}"
                             class="h-16 w-16 sm:h-20 sm:w-20 object-contain drop-shadow-xl transition-transform duration-300 hover:scale-105">
                    @else
                        <div class="h-16 w-16 sm:h-20 sm:w-20 rounded-2xl bg-blue-900/30 border border-blue-700/30 flex items-center justify-center text-xl font-black text-blue-300">
                            {{ $match->homeTeam?->abbr3 ?? '?' }}
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
                    @if($match->status === 'IN_PLAY')
                        {{-- Ao Vivo --}}
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                        </span>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-red-400">
                            AO VIVO
                            @if($liveMinute !== null)
                                <span class="text-red-300/80 font-black tabular-nums"> · {{ $liveMinute }}'@if($extraMinute)+{{ $extraMinute }}@endif</span>
                            @endif
                        </span>

                    @elseif($match->status === 'PAUSED')
                        {{-- Intervalo --}}
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                        </span>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-amber-400">Intervalo</span>

                    @elseif($match->status === 'EXTRA_TIME')
                        {{-- Prorrogação --}}
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-orange-500"></span>
                        </span>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-orange-400">
                            Prorrogação
                            @if($liveMinute !== null)
                                <span class="text-orange-300/80 font-black tabular-nums"> · {{ $liveMinute }}'@if($extraMinute)+{{ $extraMinute }}@endif</span>
                            @endif
                        </span>

                    @elseif($match->status === 'PENALTY_SHOOTOUT')
                        {{-- Pênaltis --}}
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-purple-500"></span>
                        </span>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-purple-400">Pênaltis</span>

                    @elseif($isPreMatch)
                        {{-- Pré-Jogo --}}
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-emerald-400">Pré-Jogo</span>

                    @elseif($isFinished)
                        {{-- Encerrado --}}
                        <span class="inline-flex items-center rounded-full border border-slate-700/50 bg-slate-800/60 px-3 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                            Encerrado
                        </span>

                    @elseif(in_array($match->status, ['SUSPENDED', 'POSTPONED', 'CANCELLED']))
                        {{-- Suspenso / Adiado / Cancelado --}}
                        <span class="inline-flex items-center rounded-full border border-red-800/40 bg-red-950/30 px-3 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-red-400">
                            {{ $statusLabel }}
                        </span>

                    @else
                        {{-- Agendado / outros --}}
                        <span class="inline-flex items-center rounded-full border border-blue-700/30 bg-blue-900/20 px-3 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-blue-400">
                            {{ $statusLabel }}
                            @if($matchDate) · {{ $matchDate->format('H:i') }} @endif
                        </span>
                    @endif
                </div>
            </div>

            {{-- Away Team --}}
            <div class="flex flex-1 flex-col items-center gap-2.5 min-w-0">
                <div class="relative">
                    @if($match->awayTeam?->crest)
                        <img src="{{ $match->awayTeam->crest }}"
                             alt="{{ $match->awayTeam?->localized_name ?? $match->awayTeam->name }}"
                             class="h-16 w-16 sm:h-20 sm:w-20 object-contain drop-shadow-xl transition-transform duration-300 hover:scale-105">
                    @else
                        <div class="h-16 w-16 sm:h-20 sm:w-20 rounded-2xl bg-rose-900/30 border border-rose-700/30 flex items-center justify-center text-xl font-black text-rose-300">
                            {{ $match->awayTeam?->abbr3 ?? '?' }}
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

    @if(!empty($goalEvents))
    <div class="mx-4 mb-3 card match-panel-flat match-panel-accent rounded-xl overflow-hidden border border-slate-700/40">
        <div class="px-4 py-2.5 border-b border-slate-800/60">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-300">Gols da Partida</p>
        </div>
        <div class="divide-y divide-slate-800/50">
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
            <div class="flex items-center gap-3 px-4 py-3 {{ !empty($g['is_disallowed']) ? 'opacity-70' : '' }}">
                <span class="w-12 shrink-0 text-sm font-black tabular-nums text-emerald-300 text-right">
                    {{ $goalMinute }}'@if($goalExtra)+{{ $goalExtra }}@endif
                </span>
                <span class="h-6 w-6 shrink-0 flex items-center justify-center overflow-hidden">
                    @if(!empty($g['is_home']) && $match->homeTeam?->crest)
                        <img src="{{ $match->homeTeam->crest }}" alt="" class="h-6 w-6 object-contain">
                    @elseif(!empty($g['is_away']) && $match->awayTeam?->crest)
                        <img src="{{ $match->awayTeam->crest }}" alt="" class="h-6 w-6 object-contain">
                    @else
                        <span class="text-[10px] leading-none">🏳️</span>
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <p class="text-sm font-semibold text-slate-100 truncate {{ !empty($g['is_disallowed']) ? 'line-through decoration-red-400/80' : '' }}">{{ $g['player_name'] }}</p>
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
        </div>
    </div>
    @endif

    {{-- ════════════════════════════════
         MOBILE SWIPER  (hidden on md+)
    ════════════════════════════════ --}}
    <div class="mobile-swiper px-4 pt-4"
         x-data="{
             current: 1,
             startX: 0, startY: 0,
             dragX: 0,
             dragging: false,
             lockAxis: null,
             get offset() {
                 const w = this.$refs.viewport.offsetWidth;
                 const base = -this.current * w;
                 const d    = this.dragging ? this.dragX : 0;
                 const v    = base + d;
                 const min  = -2 * w, max = 0;
                 if (v > max) return max + (v - max) * 0.25;
                 if (v < min) return min + (v - min) * 0.25;
                 return v;
             },
             start(e) {
                 this.startX   = e.touches[0].clientX;
                 this.startY   = e.touches[0].clientY;
                 this.dragging = true;
                 this.dragX    = 0;
                 this.lockAxis = null;
             },
             move(e) {
                 if (!this.dragging) return;
                 const dx = e.touches[0].clientX - this.startX;
                 const dy = e.touches[0].clientY - this.startY;
                 if (!this.lockAxis) {
                     if (Math.abs(dx) > 8)      this.lockAxis = 'x';
                     else if (Math.abs(dy) > 8) { this.lockAxis = 'y'; this.dragging = false; return; }
                 }
                 if (this.lockAxis === 'x') { e.preventDefault(); this.dragX = dx; }
             },
             end() {
                 if      (this.dragX < -50 && this.current < 2) this.current++;
                 else if (this.dragX >  50 && this.current > 0) this.current--;
                 this.dragging = false; this.dragX = 0;
             }
         }"
         x-init="$el.addEventListener('touchmove', e => move(e), { passive: false })"
         @touchstart="start($event)"
         @touchend="end()">

        {{-- Tab bar --}}
        <div class="flex mb-3 gap-1">
            <button @click="current = 0"
                    class="flex-1 py-2 text-[11px] font-bold uppercase tracking-wider rounded-t-lg border-b-2 transition-all"
                    :class="current === 0 ? 'border-blue-400 text-blue-400' : 'border-slate-800 text-slate-600'">
                {{ $match->homeTeam?->abbr3 ?? 'Casa' }}
            </button>
            <button @click="current = 1"
                    class="flex-1 py-2 text-[11px] font-bold uppercase tracking-wider rounded-t-lg border-b-2 transition-all"
                    :class="current === 1 ? 'border-slate-300 text-slate-200' : 'border-slate-800 text-slate-600'">
                Stats
            </button>
            <button @click="current = 2"
                    class="flex-1 py-2 text-[11px] font-bold uppercase tracking-wider rounded-t-lg border-b-2 transition-all"
                    :class="current === 2 ? 'border-blue-400 text-blue-400' : 'border-slate-800 text-slate-600'">
                {{ $match->awayTeam?->abbr3 ?? 'Visit.' }}
            </button>
        </div>

        {{-- Viewport --}}
        <div x-ref="viewport" class="overflow-hidden">
            <div class="flex will-change-transform select-none" style="width:300%"
                 :style="{ transform: 'translateX(' + offset + 'px)', transition: dragging ? 'none' : 'transform 0.32s cubic-bezier(0.25,0.46,0.45,0.94)' }">

                {{-- Panel 0: Home lineup --}}
                <div style="width:33.333%" class="pr-1">
                    <div class="card match-panel-flat match-panel-accent p-4">
                        <div class="flex items-center gap-2 mb-2">
                            @if($match->homeTeam?->crest)
                                <img src="{{ $match->homeTeam->crest }}" alt="" class="h-5 w-5 object-contain">
                            @endif
                            <p class="text-xs font-bold uppercase tracking-wider text-blue-400">{{ $match->homeTeam?->localized_name ?? 'Casa' }}</p>
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 mb-2">Titulares</p>
                        <div class="space-y-1">
                            @forelse($hLineup as $p)
                            @php
                                $hGoals = $countGoalsForLineupPlayer($p['name'] ?? '', $homeGoalScorers, $normalizePlayer, $toTokens);
                                $hCardCount = $countCardsForLineupPlayer($p['name'] ?? '', $homeCards, $normalizePlayer, $toTokens);
                            @endphp
                            <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-3 py-2.5 {{ !empty($p['sub_out']) ? 'lineup-sub-out ml-3 mt-1' : '' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="lineup-dim text-[10px] font-black text-blue-300 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                                        <span class="lineup-dim text-xs font-semibold text-slate-200 truncate">{{ $p['name'] }}</span>
                                        <span class="inline-flex items-center gap-0.5 shrink-0">
                                            @for($c=0;$c<($hCardCount['yellow'] ?? 0);$c++)<span class="card-rect bg-amber-400" style="--card-shadow: rgba(251,191,36,.4)"></span>@endfor
                                            @for($c=0;$c<($hCardCount['red'] ?? 0);$c++)<span class="card-rect bg-red-500" style="--card-shadow: rgba(239,68,68,.4)"></span>@endfor
                                            @if($hGoals > 0)
                                                @for($g=0;$g<$hGoals;$g++)<span class="text-[11px] leading-none">⚽</span>@endfor
                                            @endif
                                        </span>
                                    </div>
                                    <span class="shrink-0">
                                        @if(!empty($p['sub_in']))<svg viewBox="0 0 24 24" class="sub-arrow-icon" aria-hidden="true"><path fill="#22c55e" d="M12 3l8 8h-5v10H9V11H4z"/></svg>@endif
                                        @if(!empty($p['sub_out']))<svg viewBox="0 0 24 24" class="sub-arrow-icon" aria-hidden="true"><path fill="#ef4444" d="M12 21l-8-8h5V3h6v10h5z"/></svg>@endif
                                    </span>
                                </div>
                            </div>
                            @empty
                            <p class="text-xs text-slate-600">Sem titulares disponíveis.</p>
                            @endforelse
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mt-4 mb-2">Reservas</p>
                        <div class="space-y-1">
                            @forelse($hBench as $p)
                            <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-[9px] font-semibold text-slate-500 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                                    <span class="text-[11px] text-slate-400 truncate">{{ $p['name'] }}</span>
                                </div>
                            </div>
                            @empty
                            <p class="text-xs text-slate-700">Sem reservas disponíveis.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Panel 1: Stats --}}
                <div style="width:33.333%" class="px-1">
                    @if(collect($rows)->every(fn($r) => $r['home'] === '-' && $r['away'] === '-'))
                        <div class="card match-panel-flat match-panel-accent p-10 text-center">
                            <div class="text-4xl mb-3">📊</div>
                            <p class="text-slate-300 font-semibold">Estatísticas indisponíveis</p>
                            <p class="text-sm text-slate-600 mt-1">{{ $this->statsUnavailableMessage() }}</p>
                        </div>
                    @else
                        <div class="card match-panel-flat match-panel-accent overflow-hidden">
                            @foreach($rows as $row)
                            @php $b = $bar($row['home'], $row['away']); @endphp
                            <div class="px-3 py-3 {{ !$loop->last ? 'border-b border-slate-800/50' : '' }}">
                                <div class="flex items-center justify-between mb-2 gap-2">
                                    <span class="text-sm font-black tabular-nums {{ $b['ok'] && $b['h'] >= 50 ? 'text-blue-300' : 'text-slate-400' }}">
                                        {{ $row['home'] === '-' ? '—' : $row['home'] }}
                                    </span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 text-center px-1">
                                        {{ $row['label'] }}
                                    </span>
                                    <span class="text-sm font-black tabular-nums {{ $b['ok'] && (100 - $b['h']) >= 50 ? 'text-rose-300' : 'text-slate-400' }}">
                                        {{ $row['away'] === '-' ? '—' : $row['away'] }}
                                    </span>
                                </div>
                                @if($b['ok'])
                                <div class="relative flex h-1.5 overflow-hidden rounded-full bg-rose-500/20">
                                    <div class="stat-bar-fill absolute inset-y-0 left-0 rounded-l-full bg-gradient-to-r from-blue-600 to-blue-400"
                                         :style="{ width: (barsReady ? '{{ $b['h'] }}' : '0') + '%' }"></div>
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

                {{-- Panel 2: Away lineup --}}
                <div style="width:33.333%" class="pl-1">
                    <div class="card match-panel-flat match-panel-accent p-4">
                        <div class="flex items-center justify-end gap-2 mb-2">
                            <p class="text-xs font-bold uppercase tracking-wider text-rose-400 text-right">{{ $match->awayTeam?->localized_name ?? 'Visitante' }}</p>
                            @if($match->awayTeam?->crest)
                                <img src="{{ $match->awayTeam->crest }}" alt="" class="h-5 w-5 object-contain">
                            @endif
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 mb-2 text-right">Titulares</p>
                        <div class="space-y-1">
                            @forelse($aLineup as $p)
                            @php
                                $aGoals = $countGoalsForLineupPlayer($p['name'] ?? '', $awayGoalScorers, $normalizePlayer, $toTokens);
                                $aCardCount = $countCardsForLineupPlayer($p['name'] ?? '', $awayCards, $normalizePlayer, $toTokens);
                            @endphp
                            <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-3 py-2.5 {{ !empty($p['sub_out']) ? 'lineup-sub-out mt-1' : '' }}"
                                 @if(!empty($p['sub_out'])) style="margin-right: 0.75rem; width: calc(100% - 0.75rem);" @endif>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="w-4 shrink-0 inline-flex items-center justify-center">
                                        @if(!empty($p['sub_in']))<svg viewBox="0 0 24 24" class="sub-arrow-icon" aria-hidden="true"><path fill="#22c55e" d="M12 3l8 8h-5v10H9V11H4z"/></svg>@endif
                                        @if(!empty($p['sub_out']))<svg viewBox="0 0 24 24" class="sub-arrow-icon" aria-hidden="true"><path fill="#ef4444" d="M12 21l-8-8h5V3h6v10h5z"/></svg>@endif
                                    </span>
                                    <div class="flex items-center gap-2 min-w-0 justify-end flex-1">
                                        <span class="inline-flex items-center gap-0.5 shrink-0">
                                            @for($c=0;$c<($aCardCount['yellow'] ?? 0);$c++)<span class="card-rect bg-amber-400" style="--card-shadow: rgba(251,191,36,.4)"></span>@endfor
                                            @for($c=0;$c<($aCardCount['red'] ?? 0);$c++)<span class="card-rect bg-red-500" style="--card-shadow: rgba(239,68,68,.4)"></span>@endfor
                                            @if($aGoals > 0)
                                                @for($g=0;$g<$aGoals;$g++)<span class="text-[11px] leading-none">⚽</span>@endfor
                                            @endif
                                        </span>
                                        <span class="lineup-dim text-xs font-semibold text-slate-200 truncate text-right max-w-[11rem]">{{ $p['name'] }}</span>
                                        <span class="lineup-dim text-[10px] font-black text-blue-300 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <p class="text-xs text-slate-600 text-right">Sem titulares disponíveis.</p>
                            @endforelse
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mt-4 mb-2 text-right">Reservas</p>
                        <div class="space-y-1">
                            @forelse($aBench as $p)
                            <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] text-slate-400 truncate text-right flex-1">{{ $p['name'] }}</span>
                                    <span class="text-[9px] font-semibold text-slate-500 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                                </div>
                            </div>
                            @empty
                            <p class="text-xs text-slate-700 text-right">Sem reservas disponíveis.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>{{-- /track --}}
        </div>{{-- /viewport --}}

        {{-- Dot indicators --}}
        <div class="flex items-center justify-center gap-2 mt-3">
            <button @click="current = 0" class="h-1.5 rounded-full transition-all duration-300"
                    :class="current === 0 ? 'w-5 bg-blue-400' : 'w-1.5 bg-slate-700'"></button>
            <button @click="current = 1" class="h-1.5 rounded-full transition-all duration-300"
                    :class="current === 1 ? 'w-5 bg-slate-300' : 'w-1.5 bg-slate-700'"></button>
            <button @click="current = 2" class="h-1.5 rounded-full transition-all duration-300"
                    :class="current === 2 ? 'w-5 bg-blue-400' : 'w-1.5 bg-slate-700'"></button>
        </div>

    </div>{{-- /mobile swiper --}}

    {{-- ════════════════════════════════
         DESKTOP GRID  (hidden below md)
    ════════════════════════════════ --}}
    <div class="desktop-grid">

        <section class="space-y-3">
            <div class="card match-panel-flat match-panel-accent p-4">
                <div class="flex items-center gap-2 mb-2">
                    @if($match->homeTeam?->crest)
                        <img src="{{ $match->homeTeam->crest }}" alt="" class="h-5 w-5 object-contain">
                    @endif
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-400">{{ $match->homeTeam?->localized_name ?? 'Casa' }}</p>
                </div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 mb-2">Titulares</p>
                <div class="space-y-1">
                    @forelse($hLineup as $p)
                    @php
                        $hGoals = $countGoalsForLineupPlayer($p['name'] ?? '', $homeGoalScorers, $normalizePlayer, $toTokens);
                        $hCardCount = $countCardsForLineupPlayer($p['name'] ?? '', $homeCards, $normalizePlayer, $toTokens);
                    @endphp
                    <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-3 py-2.5 {{ !empty($p['sub_out']) ? 'lineup-sub-out ml-3 mt-1' : '' }}">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="lineup-dim text-[10px] font-black text-blue-300 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                                <span class="lineup-dim text-xs font-semibold text-slate-200 truncate">{{ $p['name'] }}</span>
                                <span class="inline-flex items-center gap-0.5 shrink-0">
                                    @for($c=0;$c<($hCardCount['yellow'] ?? 0);$c++)<span class="card-rect bg-amber-400" style="--card-shadow: rgba(251,191,36,.4)"></span>@endfor
                                    @for($c=0;$c<($hCardCount['red'] ?? 0);$c++)<span class="card-rect bg-red-500" style="--card-shadow: rgba(239,68,68,.4)"></span>@endfor
                                    @if($hGoals > 0)
                                        @for($g=0;$g<$hGoals;$g++)<span class="text-[11px] leading-none">⚽</span>@endfor
                                    @endif
                                </span>
                            </div>
                            <span class="shrink-0">
                                @if(!empty($p['sub_in']))<svg viewBox="0 0 24 24" class="sub-arrow-icon" aria-hidden="true"><path fill="#22c55e" d="M12 3l8 8h-5v10H9V11H4z"/></svg>@endif
                                @if(!empty($p['sub_out']))<svg viewBox="0 0 24 24" class="sub-arrow-icon" aria-hidden="true"><path fill="#ef4444" d="M12 21l-8-8h5V3h6v10h5z"/></svg>@endif
                            </span>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-slate-600">Sem titulares disponíveis.</p>
                    @endforelse
                </div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mt-4 mb-2">Reservas</p>
                <div class="space-y-1">
                    @forelse($hBench as $p)
                    <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-semibold text-slate-500 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                            <span class="text-[11px] text-slate-400 truncate">{{ $p['name'] }}</span>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-slate-700">Sem reservas disponíveis.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="space-y-3">
            @if(collect($rows)->every(fn($r) => $r['home'] === '-' && $r['away'] === '-'))
                <div class="card match-panel-flat match-panel-accent p-10 text-center">
                    <div class="text-4xl mb-3">📊</div>
                    <p class="text-slate-300 font-semibold">Estatísticas indisponíveis</p>
                    <p class="text-sm text-slate-600 mt-1">{{ $this->statsUnavailableMessage() }}</p>
                </div>
            @else
                <div class="card match-panel-flat match-panel-accent overflow-hidden">
                    @foreach($rows as $row)
                    @php $b = $bar($row['home'], $row['away']); @endphp
                    <div class="px-3 py-3 {{ !$loop->last ? 'border-b border-slate-800/50' : '' }}">
                        <div class="flex items-center justify-between mb-2 gap-2">
                            <span class="text-sm font-black tabular-nums {{ $b['ok'] && $b['h'] >= 50 ? 'text-blue-300' : 'text-slate-400' }}">
                                {{ $row['home'] === '-' ? '—' : $row['home'] }}
                            </span>
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 text-center px-1">
                                {{ $row['label'] }}
                            </span>
                            <span class="text-sm font-black tabular-nums {{ $b['ok'] && (100 - $b['h']) >= 50 ? 'text-rose-300' : 'text-slate-400' }}">
                                {{ $row['away'] === '-' ? '—' : $row['away'] }}
                            </span>
                        </div>
                        @if($b['ok'])
                        <div class="relative flex h-1.5 overflow-hidden rounded-full bg-rose-500/20">
                            <div class="stat-bar-fill absolute inset-y-0 left-0 rounded-l-full bg-gradient-to-r from-blue-600 to-blue-400"
                                 :style="{ width: (barsReady ? '{{ $b['h'] }}' : '0') + '%' }"></div>
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
        </section>

        <section class="space-y-3">
            <div class="card match-panel-flat match-panel-accent p-4">
                <div class="flex items-center justify-end gap-2 mb-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-rose-400 text-right">{{ $match->awayTeam?->localized_name ?? 'Visitante' }}</p>
                    @if($match->awayTeam?->crest)
                        <img src="{{ $match->awayTeam->crest }}" alt="" class="h-5 w-5 object-contain">
                    @endif
                </div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 mb-2 text-right">Titulares</p>
                <div class="space-y-1">
                    @forelse($aLineup as $p)
                    @php
                        $aGoals = $countGoalsForLineupPlayer($p['name'] ?? '', $awayGoalScorers, $normalizePlayer, $toTokens);
                        $aCardCount = $countCardsForLineupPlayer($p['name'] ?? '', $awayCards, $normalizePlayer, $toTokens);
                    @endphp
                    <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-3 py-2.5 {{ !empty($p['sub_out']) ? 'lineup-sub-out mt-1' : '' }}"
                         @if(!empty($p['sub_out'])) style="margin-right: 0.75rem; width: calc(100% - 0.75rem);" @endif>
                        <div class="flex items-center justify-between gap-2">
                            <span class="w-4 shrink-0 inline-flex items-center justify-center">
                                @if(!empty($p['sub_in']))<svg viewBox="0 0 24 24" class="sub-arrow-icon" aria-hidden="true"><path fill="#22c55e" d="M12 3l8 8h-5v10H9V11H4z"/></svg>@endif
                                @if(!empty($p['sub_out']))<svg viewBox="0 0 24 24" class="sub-arrow-icon" aria-hidden="true"><path fill="#ef4444" d="M12 21l-8-8h5V3h6v10h5z"/></svg>@endif
                            </span>
                            <div class="flex items-center gap-2 min-w-0 justify-end flex-1">
                                <span class="inline-flex items-center gap-0.5 shrink-0">
                                    @for($c=0;$c<($aCardCount['yellow'] ?? 0);$c++)<span class="card-rect bg-amber-400" style="--card-shadow: rgba(251,191,36,.4)"></span>@endfor
                                    @for($c=0;$c<($aCardCount['red'] ?? 0);$c++)<span class="card-rect bg-red-500" style="--card-shadow: rgba(239,68,68,.4)"></span>@endfor
                                    @if($aGoals > 0)
                                        @for($g=0;$g<$aGoals;$g++)<span class="text-[11px] leading-none">⚽</span>@endfor
                                    @endif
                                </span>
                                <span class="lineup-dim text-xs font-semibold text-slate-200 truncate text-right max-w-[11rem]">{{ $p['name'] }}</span>
                                <span class="lineup-dim text-[10px] font-black text-blue-300 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-slate-600 text-right">Sem titulares disponíveis.</p>
                    @endforelse
                </div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mt-4 mb-2 text-right">Reservas</p>
                <div class="space-y-1">
                    @forelse($aBench as $p)
                    <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] text-slate-400 truncate text-right flex-1">{{ $p['name'] }}</span>
                            <span class="text-[9px] font-semibold text-slate-500 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-slate-700 text-right">Sem reservas disponíveis.</p>
                    @endforelse
                </div>
            </div>
        </section>

    </div>{{-- /desktop-grid --}}

    {{-- ── Right panel data — re-renderizado pelo Livewire, lido por JS ── --}}
    <div id="rp-live-data" class="hidden" aria-hidden="true">
        @if($sidebarLiveMatches->isNotEmpty())
        <div class="rp-widget">
            <div class="rp-widget-header">
                <span>Jogos Ao Vivo</span>
            </div>
            <div class="rp-widget-body divide-y divide-white/[0.04]">
                @foreach($sidebarLiveMatches as $liveMatch)
                @php
                    $minute = $sidebarLiveMinutes[$liveMatch->id] ?? null;
                    $stats = $sidebarLiveMatchStats[$liveMatch->id] ?? [];
                @endphp
                <a href="{{ route('matches.show', ['match' => $liveMatch->id]) }}" class="block py-2 hover:bg-white/[0.02] -mx-2 px-2 rounded-md transition-colors">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-slate-300 truncate">{{ $liveMatch->homeTeam?->localized_name ?? $liveMatch->homeTeam?->short_name ?? $liveMatch->homeTeam?->abbr3 ?? '?' }}</span>
                        <span class="font-bc font-extrabold text-xl text-white">{{ $liveMatch->home_score_full_time ?? 0 }}–{{ $liveMatch->away_score_full_time ?? 0 }}</span>
                        <span class="text-xs text-slate-300 truncate text-right">{{ $liveMatch->awayTeam?->localized_name ?? $liveMatch->awayTeam?->short_name ?? $liveMatch->awayTeam?->abbr3 ?? '?' }}</span>
                    </div>
                    <div class="mt-1 flex items-center justify-between text-[10px] text-bolao-muted">
                        <span class="text-bolao-red">{{ $minute ? $minute . "'" : 'AO VIVO' }}</span>
                        <span>
                            @if(isset($stats['shots_home'], $stats['shots_away']) && $stats['shots_home'] !== null)
                                Finalizações {{ $stats['shots_home'] }}-{{ $stats['shots_away'] }}
                            @elseif(isset($stats['poss_home'], $stats['poss_away']) && $stats['poss_home'] !== null)
                                Posse {{ $stats['poss_home'] }}%-{{ $stats['poss_away'] }}%
                            @else
                                {{ $minute ? 'Parcial sem estatísticas da API' : 'Ao vivo sem estatísticas da API' }}
                            @endif
                        </span>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        @if($sidebarPool && $sidebarTopRankings->isNotEmpty())
        <div class="rp-widget">
            <div class="rp-widget-header">
                <span>Ranking ao Vivo</span>
                <a href="{{ route('pools.show', $sidebarPool->slug) }}"
                   class="text-bolao-accent hover:text-bolao-accent2 transition-colors normal-case tracking-normal text-[11px] font-semibold">
                    ver →
                </a>
            </div>
            <div class="rp-widget-body divide-y divide-white/[0.04]">
                @foreach($sidebarTopRankings as $row)
                @php
                    $isMe = (int) ($row->user_id ?? 0) === (int) auth()->id();
                    $publicName = $row->user?->display_name ?: $row->user?->name ?: 'Participante';
                    $rankPos = (int) ($row->position ?? ($loop->index + 1));
                @endphp
                <div class="rp-rank-row py-2 flex items-center justify-between gap-2 {{ $isMe ? 'bg-amber-400/10 -mx-2 px-2 rounded-md' : '' }}"
                     data-rank-key="pool-{{ $sidebarPool->id }}-user-{{ $row->user_id }}"
                     data-rank-pos="{{ $rankPos }}">
                    <div class="min-w-0 flex items-center gap-2">
                        <span class="w-5 text-center font-bc font-extrabold text-xs {{ $isMe ? 'text-amber-300' : 'text-bolao-muted2' }}">{{ $rankPos }}º</span>
                        <span class="text-xs truncate {{ $isMe ? 'text-white font-semibold' : 'text-slate-300' }}">{{ $publicName }}@if($isMe) ★@endif</span>
                    </div>
                    <span class="shrink-0 inline-flex items-center justify-center min-w-[38px] rounded-md border border-amber-400/35 bg-amber-400/15 px-1.5 py-0.5 font-bc font-extrabold text-[11px] text-amber-300">
                        {{ (int) ($row->points_total ?? 0) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

</div>
