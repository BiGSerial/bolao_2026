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
$matchDate   = ($match->utc_date ?? $match->local_date)?->timezone('America/Sao_Paulo');

$rows     = $this->statsRows();
$hLineup  = $this->homeLineup();
$aLineup  = $this->awayLineup();
$hBench   = $this->homeBench();
$aBench   = $this->awayBench();
$bookings = $this->bookings();

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
    .mobile-swiper { display: block; }
    .desktop-grid  { display: none;  }
    @media (min-width: 768px) {
        .mobile-swiper { display: none; }
        .desktop-grid  { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 1rem; padding: 1rem 1rem 0; }
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
                {{ $match->homeTeam?->tla ?? 'Casa' }}
            </button>
            <button @click="current = 1"
                    class="flex-1 py-2 text-[11px] font-bold uppercase tracking-wider rounded-t-lg border-b-2 transition-all"
                    :class="current === 1 ? 'border-slate-300 text-slate-200' : 'border-slate-800 text-slate-600'">
                Stats
            </button>
            <button @click="current = 2"
                    class="flex-1 py-2 text-[11px] font-bold uppercase tracking-wider rounded-t-lg border-b-2 transition-all"
                    :class="current === 2 ? 'border-blue-400 text-blue-400' : 'border-slate-800 text-slate-600'">
                {{ $match->awayTeam?->tla ?? 'Visit.' }}
            </button>
        </div>

        {{-- Viewport --}}
        <div x-ref="viewport" class="overflow-hidden">
            <div class="flex will-change-transform select-none" style="width:300%"
                 :style="{ transform: 'translateX(' + offset + 'px)', transition: dragging ? 'none' : 'transform 0.32s cubic-bezier(0.25,0.46,0.45,0.94)' }">

                {{-- Panel 0: Home lineup --}}
                <div style="width:33.333%" class="pr-1">
                    <div class="card p-3">
                        <div class="flex items-center gap-2 mb-2">
                            @if($match->homeTeam?->crest)
                                <img src="{{ $match->homeTeam->crest }}" alt="" class="h-5 w-5 object-contain">
                            @endif
                            <p class="text-xs font-bold uppercase tracking-wider text-blue-400">{{ $match->homeTeam?->localized_name ?? 'Casa' }}</p>
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 mb-2">Titulares</p>
                        <div class="space-y-1">
                            @forelse($hLineup as $p)
                            <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-2.5 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black text-blue-300 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                                    <span class="text-xs font-semibold text-slate-200 truncate">{{ $p['name'] }}</span>
                                </div>
                            </div>
                            @empty
                            <p class="text-xs text-slate-600">Sem titulares disponíveis.</p>
                            @endforelse
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mt-4 mb-2">Reservas</p>
                        <div class="space-y-1">
                            @forelse($hBench as $p)
                            <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-2.5 py-1.5">
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
                        <div class="card p-10 text-center">
                            <div class="text-4xl mb-3">📊</div>
                            <p class="text-slate-300 font-semibold">Estatísticas indisponíveis</p>
                            <p class="text-sm text-slate-600 mt-1">{{ $this->statsUnavailableMessage() }}</p>
                        </div>
                    @else
                        <div class="card overflow-hidden">
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
                    <div class="card p-3">
                        <div class="flex items-center justify-end gap-2 mb-2">
                            <p class="text-xs font-bold uppercase tracking-wider text-rose-400 text-right">{{ $match->awayTeam?->localized_name ?? 'Visitante' }}</p>
                            @if($match->awayTeam?->crest)
                                <img src="{{ $match->awayTeam->crest }}" alt="" class="h-5 w-5 object-contain">
                            @endif
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 mb-2 text-right">Titulares</p>
                        <div class="space-y-1">
                            @forelse($aLineup as $p)
                            <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-2.5 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-slate-200 truncate text-right flex-1">{{ $p['name'] }}</span>
                                    <span class="text-[10px] font-black text-blue-300 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                                </div>
                            </div>
                            @empty
                            <p class="text-xs text-slate-600 text-right">Sem titulares disponíveis.</p>
                            @endforelse
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mt-4 mb-2 text-right">Reservas</p>
                        <div class="space-y-1">
                            @forelse($aBench as $p)
                            <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-2.5 py-1.5">
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
            <div class="card p-3">
                <div class="flex items-center gap-2 mb-2">
                    @if($match->homeTeam?->crest)
                        <img src="{{ $match->homeTeam->crest }}" alt="" class="h-5 w-5 object-contain">
                    @endif
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-400">{{ $match->homeTeam?->localized_name ?? 'Casa' }}</p>
                </div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 mb-2">Titulares</p>
                <div class="space-y-1">
                    @forelse($hLineup as $p)
                    <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-2.5 py-2">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black text-blue-300 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                            <span class="text-xs font-semibold text-slate-200 truncate">{{ $p['name'] }}</span>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-slate-600">Sem titulares disponíveis.</p>
                    @endforelse
                </div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mt-4 mb-2">Reservas</p>
                <div class="space-y-1">
                    @forelse($hBench as $p)
                    <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-2.5 py-1.5">
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
                <div class="card p-10 text-center">
                    <div class="text-4xl mb-3">📊</div>
                    <p class="text-slate-300 font-semibold">Estatísticas indisponíveis</p>
                    <p class="text-sm text-slate-600 mt-1">{{ $this->statsUnavailableMessage() }}</p>
                </div>
            @else
                <div class="card overflow-hidden">
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
            <div class="card p-3">
                <div class="flex items-center justify-end gap-2 mb-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-rose-400 text-right">{{ $match->awayTeam?->localized_name ?? 'Visitante' }}</p>
                    @if($match->awayTeam?->crest)
                        <img src="{{ $match->awayTeam->crest }}" alt="" class="h-5 w-5 object-contain">
                    @endif
                </div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 mb-2 text-right">Titulares</p>
                <div class="space-y-1">
                    @forelse($aLineup as $p)
                    <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-2.5 py-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-200 truncate text-right flex-1">{{ $p['name'] }}</span>
                            <span class="text-[10px] font-black text-blue-300 tabular-nums shrink-0">{{ $p['number'] ?? '—' }}</span>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-slate-600 text-right">Sem titulares disponíveis.</p>
                    @endforelse
                </div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mt-4 mb-2 text-right">Reservas</p>
                <div class="space-y-1">
                    @forelse($aBench as $p)
                    <div class="rounded-lg border border-blue-800/30 bg-blue-950/20 px-2.5 py-1.5">
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

</div>
