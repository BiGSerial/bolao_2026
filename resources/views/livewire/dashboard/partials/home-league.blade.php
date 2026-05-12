{{-- ═══════════════════════════════════════════════════════════════
     LEAGUE HOME  —  Points-based (BSA, etc.)
     ═══════════════════════════════════════════════════════════════ --}}

{{-- ── Pool Selector ─────────────────────────────────────────── --}}
@if($myMembershipsForComp->count() > 1)
<div class="flex items-center gap-2 overflow-x-auto px-4 md:px-6 pt-4 pb-0 scrollbar-none">
    @foreach($myMembershipsForComp as $m)
    @php $rank = $myRankings->get($m->pool_id); @endphp
    <button wire:click="selectPool({{ $m->pool_id }})"
            class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all
                   {{ $selectedPool?->id === $m->pool_id
                       ? 'bg-bolao-accent text-black'
                       : 'bg-bolao-bg3 text-bolao-muted border border-white/[0.07] hover:bg-bolao-bg4 hover:text-slate-200' }}">
        {{ $m->pool->name }}
        @if($rank)
        <span class="{{ $selectedPool?->id === $m->pool_id ? 'text-black/60' : 'text-bolao-muted2' }}">
            {{ $rank->points_total }}pts
        </span>
        @endif
    </button>
    @endforeach
    @if($selectedPool)
    <button wire:click="selectPool(null)"
            class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-bolao-bg3 text-bolao-muted2 border border-white/[0.07] hover:bg-bolao-bg4">
        <i class="ti ti-x text-xs"></i>
    </button>
    @endif
</div>
@endif

{{-- ── Live matches ──────────────────────────────────────────── --}}
@if($live->isNotEmpty())
<section class="px-4 md:px-6 pt-5">
    <div class="flex items-center gap-2 mb-3">
        <span class="live-dot"></span>
        <h2 class="font-bc font-bold text-base uppercase tracking-wide text-white">Ao Vivo</h2>
        <span class="pts-chip bg-bolao-red/10 text-bolao-red">{{ $live->count() }}</span>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($live as $match)
        @php
            $href    = $matchLinks[$match->id] ?? null;
            $minute  = $liveMinutes[$match->id] ?? null;
            $extraMin = data_get($match->raw_payload, 'injury_time');
            [$liveLabel, $showPing] = match($match->status) {
                'IN_PLAY'          => ['Ao Vivo', true],
                'PAUSED'           => ['Intervalo', false],
                'EXTRA_TIME'       => ['Prorrogação', true],
                'PENALTY_SHOOTOUT' => ['Pênaltis', true],
                default            => ['Pré-Jogo', true],
            };
        @endphp
        @if($href)<a href="{{ $href }}" class="bg-bolao-bg2 border border-white/[0.07] rounded-xl p-4 relative overflow-hidden hover:border-white/[0.13] transition-colors">
        @else<div class="bg-bolao-bg2 border border-white/[0.07] rounded-xl p-4 relative overflow-hidden">
        @endif
            <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-bolao-accent to-bolao-accent2"></div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] text-bolao-muted">Rodada {{ $match->matchday }}</span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-bolao-red">
                    @if($showPing)<span class="live-dot mr-0.5"></span>@endif
                    {{ $liveLabel }}
                    @if(in_array($match->status, ['IN_PLAY','EXTRA_TIME','PENALTY_SHOOTOUT']) && $minute !== null)
                        · {{ $minute }}'@if($extraMin)+{{ $extraMin }}@endif
                    @endif
                </span>
            </div>
            <div class="flex items-center justify-between gap-2">
                <div class="flex flex-col items-center gap-1.5 flex-1">
                    <x-match-team-logo :team="$match->homeTeam" size="md" />
                    <span class="text-xs font-semibold text-slate-200 text-center leading-tight truncate w-full">
                        {{ $match->homeTeam?->short_name ?? $match->homeTeam?->name ?? 'A definir' }}
                    </span>
                </div>
                <div class="font-bc font-extrabold text-3xl text-white leading-none tracking-widest shrink-0 min-w-[56px] text-center">
                    {{ $match->home_score_full_time ?? '?' }}–{{ $match->away_score_full_time ?? '?' }}
                </div>
                <div class="flex flex-col items-center gap-1.5 flex-1">
                    <x-match-team-logo :team="$match->awayTeam" size="md" />
                    <span class="text-xs font-semibold text-slate-200 text-center leading-tight truncate w-full">
                        {{ $match->awayTeam?->short_name ?? $match->awayTeam?->name ?? 'A definir' }}
                    </span>
                </div>
            </div>
        @if($href)</a>@else</div>@endif
        @endforeach
    </div>
</section>
@endif

{{-- ── My pool position ─────────────────────────────────────── --}}
@if($selectedPool && $selectedPoolRanking)
<section class="px-4 md:px-6 pt-5">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-bc font-bold text-[18px] uppercase tracking-[0.5px] text-white">Minha Posição</h2>
        <a href="{{ route('pools.show', $selectedPool->slug) }}"
           class="text-[13px] font-medium text-bolao-accent hover:text-bolao-accent2 transition-colors">
            Ranking →
        </a>
    </div>
    <a href="{{ route('pools.show', $selectedPool->slug) }}"
       class="flex items-center gap-[14px] bg-bolao-bg3 border border-bolao-accent rounded-xl px-4 py-[14px] hover:border-bolao-accent2 transition-colors">
        <div>
            <div class="text-[12px] text-bolao-muted mb-0.5">Classificação</div>
            <div class="font-bc font-extrabold text-[36px] leading-none text-bolao-accent">
                {{ $selectedPoolRanking->position }}<sup class="text-[14px]">°</sup>
            </div>
        </div>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-[15px] text-white leading-tight truncate">{{ auth()->user()->public_name }}</div>
            <div class="text-[13px] text-bolao-muted mt-0.5">{{ $selectedPoolMemberCount }} participante{{ $selectedPoolMemberCount !== 1 ? 's' : '' }}</div>
        </div>
        <div class="text-right">
            <div class="font-bc font-extrabold text-[28px] leading-none text-bolao-accent">{{ $selectedPoolRanking->points_total }}</div>
            <div class="text-[11px] text-bolao-muted mt-0.5">pts</div>
        </div>
    </a>
</section>
@elseif($myMembershipsForComp->isEmpty())
<section class="px-4 md:px-6 pt-5">
    <div class="bg-bolao-bg2 border border-white/[0.07] rounded-xl px-4 py-5 text-center">
        <i class="ti ti-trophy text-3xl text-bolao-muted2 mb-2 block"></i>
        <p class="text-sm text-bolao-muted mb-3">Você ainda não participa de nenhum bolão.</p>
        <a href="{{ route('pools.create', ['competition' => $currentCompetitionCode]) }}"
           class="btn-primary btn-sm">
            <i class="ti ti-plus text-sm"></i> Criar Bolão
        </a>
    </div>
</section>
@endif

{{-- ── Main grid: próximos + classificação ─────────────────── --}}
<div class="grid gap-5 px-4 md:px-6 pt-5 pb-4 lg:grid-cols-2 items-stretch">

    {{-- ── Próximos jogos ─── --}}
    @if($upcoming->isNotEmpty())
    <div class="flex flex-col h-full">
        @php
            $nextMatchday = $upcoming->first()?->matchday;
            $matchdayMatches = $upcoming->filter(fn($m) => $m->matchday === $nextMatchday)->take(10);
        @endphp
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bc font-bold text-base uppercase tracking-wide text-white">
                Rodada {{ $nextMatchday ?? '' }}
            </h2>
            <a href="{{ route('pools.index', ['competition' => $currentCompetitionCode]) }}"
               class="text-xs text-bolao-accent hover:text-bolao-accent2 transition-colors">
                Ver todos →
            </a>
        </div>
        <div class="flex flex-col gap-1.5 h-full">
            @foreach($matchdayMatches as $match)
            @php
                $href    = $matchLinks[$match->id] ?? null;
                $kickoff = $match->utc_date?->timezone('America/Sao_Paulo');
                $label   = $kickoff
                    ? ($kickoff->isToday() ? 'Hoje · '.$kickoff->format('H:i') : $kickoff->format('d/m · H:i'))
                    : '';
                // Check if user has prediction for this match (any pool)
                $hasPred  = $recentPredictions->has($match->id);
            @endphp
            @if($href)<a href="{{ $href }}" class="bg-bolao-bg2 border border-white/[0.07] rounded-lg px-3 py-2.5 flex items-center gap-2 hover:border-white/[0.13] transition-colors">
            @else<div class="bg-bolao-bg2 border border-white/[0.07] rounded-lg px-3 py-2.5 flex items-center gap-2">
            @endif
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <x-match-team-logo :team="$match->homeTeam" size="sm" />
                    <span class="text-sm font-medium text-slate-200 truncate">
                        {{ $match->homeTeam?->short_name ?? $match->homeTeam?->name ?? 'A definir' }}
                    </span>
                </div>
                <div class="flex flex-col items-center shrink-0 min-w-[64px]">
                    <span class="font-bc font-bold text-xs text-bolao-muted2 bg-bolao-bg4 rounded px-2 py-0.5">VS</span>
                    <span class="text-[10px] text-bolao-muted mt-0.5">{{ $label }}</span>
                </div>
                <div class="flex items-center gap-2 flex-1 min-w-0 justify-end">
                    <span class="text-sm font-medium text-slate-200 truncate text-right">
                        {{ $match->awayTeam?->short_name ?? $match->awayTeam?->name ?? 'A definir' }}
                    </span>
                    <x-match-team-logo :team="$match->awayTeam" size="sm" />
                </div>
                @if($selectedPool)
                <span class="flex-shrink-0 w-1.5 h-1.5 rounded-full ml-1
                             {{ $hasPred ? 'bg-bolao-green' : 'bg-bolao-muted2' }}"></span>
                @endif
            @if($href)</a>@else</div>@endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Classificação ─── --}}
    @if($groupStandings->isNotEmpty())
    <div class="flex flex-col h-full">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bc font-bold text-base uppercase tracking-wide text-white">Classificação</h2>
            <span class="text-[10px] text-bolao-muted uppercase tracking-widest">{{ $standingsCriteriaLabel }}</span>
        </div>
        @php
            $standingRows = $groupStandings->first()?->rows ?? collect();
        @endphp
        <div class="bg-bolao-bg2 border border-white/[0.07] rounded-xl overflow-hidden h-full flex flex-col">
            <div class="max-h-[456px] overflow-y-auto">
                <table class="min-w-full text-xs">
                    <colgroup>
                        <col class="w-[40px]">
                        <col>
                        <col class="w-[44px] sm:w-[56px]">
                        <col class="w-[44px] sm:w-[56px]">
                        <col class="w-[44px] sm:w-[56px]">
                        <col class="w-[44px] sm:w-[56px]">
                        <col class="w-[44px] md:w-[56px]">
                        <col class="w-[44px] sm:w-[56px]">
                    </colgroup>
                    <thead class="sticky top-0 z-10 bg-bolao-bg2">
                        <tr class="border-b border-white/[0.07] text-[10px] text-bolao-muted uppercase tracking-widest">
                            <th class="px-3 py-2 text-center w-6">#</th>
                            <th class="px-2 py-2 text-left">Time</th>
                            <th class="px-2 py-2 text-right hidden sm:table-cell">PJ</th>
                            <th class="px-2 py-2 text-right hidden sm:table-cell">V</th>
                            <th class="px-2 py-2 text-right hidden sm:table-cell">E</th>
                            <th class="px-2 py-2 text-right hidden sm:table-cell">D</th>
                            <th class="px-2 py-2 text-right hidden md:table-cell">SG</th>
                            <th class="px-2 py-2 text-right font-semibold text-slate-300">PTS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                    @foreach($standingRows as $row)
                    @php
                        $pos = $loop->iteration;
                        $sg  = (int) ($row->goal_difference ?? 0);
                    @endphp
                    <tr class="hover:bg-bolao-bg3 transition-colors">
                        <td class="px-3 py-2 text-center font-bc font-bold
                                   {{ $pos <= 4 ? 'text-bolao-green' : ($pos <= 6 ? 'text-bolao-blue' : ($pos > ($standingRows->count() - 4) ? 'text-bolao-red' : 'text-bolao-muted')) }}">
                            {{ $row->position ?? $pos }}
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-2">
                                <x-match-team-logo :team="$row->team" size="xs" />
                                <span class="text-[13px] font-medium text-slate-200 truncate max-w-[6rem]" title="{{ $row->team?->name }}">
                                    {{ $row->team?->localized_name ?? '—' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-2 py-2 text-right text-bolao-muted hidden sm:table-cell">{{ $row->played_games }}</td>
                        <td class="px-2 py-2 text-right text-bolao-muted hidden sm:table-cell">{{ $row->won }}</td>
                        <td class="px-2 py-2 text-right text-bolao-muted hidden sm:table-cell">{{ $row->draw }}</td>
                        <td class="px-2 py-2 text-right text-bolao-muted hidden sm:table-cell">{{ $row->lost }}</td>
                        <td class="px-2 py-2 text-right hidden md:table-cell
                                   {{ $sg > 0 ? 'text-bolao-green' : ($sg < 0 ? 'text-bolao-red' : 'text-bolao-muted') }}">
                            {{ $sg > 0 ? '+'.$sg : $sg }}
                        </td>
                        <td class="px-2 py-2 text-right font-bc font-bold text-white">{{ $row->points }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="sticky bottom-0 z-10 bg-bolao-bg2">
                        <tr class="border-t border-white/[0.04]">
                            <td colspan="8" class="px-3 py-1 text-center">
                                <span class="text-[9px] text-bolao-muted uppercase tracking-widest">{{ $standingRows->count() }} times</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-bolao-muted">
            <span><span class="text-bolao-green font-bold">●</span> Libertadores (G4)</span>
            <span><span class="text-bolao-blue font-bold">●</span> Pré-Libertadores (G6)</span>
            <span><span class="text-bolao-red font-bold">●</span> Rebaixamento (Z4)</span>
        </div>
    </div>
    @endif

</div>

{{-- ── Últimos resultados ────────────────────────────────────── --}}
@if($recent->isNotEmpty())
<section class="px-4 md:px-6 pb-4">
    <h2 class="font-bc font-bold text-base uppercase tracking-wide text-white mb-3">Resultados Recentes</h2>
    <div class="flex flex-col gap-1.5">
        @foreach($recent as $match)
        @php
            $href = $matchLinks[$match->id] ?? null;
            $pred = $recentPredictions->get($match->id);
            $chipClass = null; $chipLabel = null;
            if ($pred && $selectedPool) {
                if ($pred->calculated_at !== null) {
                    $pts      = (int) $pred->points;
                    $exactPts = $selectedPool->points_exact_score ?? 5;
                    if ($pts >= $exactPts) { $chipClass = 'pts-exact'; $chipLabel = "+{$pts}"; }
                    elseif ($pts > 0)      { $chipClass = 'pts-winner'; $chipLabel = "+{$pts}"; }
                    else                   { $chipClass = 'pts-miss'; $chipLabel = '0'; }
                } else {
                    $chipClass = 'pts-pending'; $chipLabel = $pred->home_score.'×'.$pred->away_score;
                }
            }
        @endphp
        @php
            $borderClass = match($chipClass) {
                'pts-exact'   => 'border-l-[3px] border-l-bolao-green',
                'pts-winner'  => 'border-l-[3px] border-l-bolao-blue',
                'pts-miss'    => 'border-l-[3px] border-l-bolao-red',
                'pts-pending' => 'border-l-[3px] border-l-bolao-accent',
                default       => '',
            };
        @endphp
        @if($href)<a href="{{ $href }}" class="bg-bolao-bg2 border border-white/[0.07] rounded-lg px-3 py-2.5 flex items-center gap-2 hover:border-white/[0.13] transition-colors {{ $borderClass }}">
        @else<div class="bg-bolao-bg2 border border-white/[0.07] rounded-lg px-3 py-2.5 flex items-center gap-2 {{ $borderClass }}">
        @endif
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <x-match-team-logo :team="$match->homeTeam" size="sm" />
                <span class="text-sm font-medium text-slate-200 truncate">
                    {{ $match->homeTeam?->short_name ?? $match->homeTeam?->name ?? 'A definir' }}
                </span>
            </div>
            <div class="font-bc font-extrabold text-xl text-white shrink-0 tracking-wider">
                {{ $match->home_score_full_time ?? 0 }}–{{ $match->away_score_full_time ?? 0 }}
            </div>
            <div class="flex items-center gap-2 flex-1 min-w-0 justify-end">
                <span class="text-sm font-medium text-slate-200 truncate text-right">
                    {{ $match->awayTeam?->short_name ?? $match->awayTeam?->name ?? 'A definir' }}
                </span>
                <x-match-team-logo :team="$match->awayTeam" size="sm" />
            </div>
            @if($chipClass)
            <span class="pts-chip {{ $chipClass }} flex-shrink-0 ml-1">{{ $chipLabel }}</span>
            @endif
        @if($href)</a>@else</div>@endif
        @endforeach
    </div>
</section>
@endif

{{-- ── Right panel push ─────────────────────────────────────── --}}
@push('right-panel')
@if($selectedPool)
<div class="rp-widget">
    <div class="rp-widget-header">
        <span>Top Ranking</span>
        <a href="{{ route('pools.show', $selectedPool->slug) }}"
           class="text-bolao-accent hover:text-bolao-accent2 transition-colors normal-case tracking-normal text-[11px] font-semibold">
            ver tudo →
        </a>
    </div>
    <div class="rp-widget-body divide-y divide-white/[0.04]">
        @forelse($selectedPoolTopRankings as $entry)
        @php
            $isMe = (int) $entry->user_id === (int) auth()->id();
            $publicName = trim((string) ($entry->user?->display_name ?: $entry->user?->name ?: 'Participante'));
            $initials = strtoupper(substr(preg_replace('/\s+.*$/', '', $publicName), 0, 2));
        @endphp
        <div class="py-2 flex items-center gap-2 {{ $isMe ? 'bg-bolao-accent/15 -mx-2 px-2 rounded-md' : '' }}">
            <span class="w-4 text-center font-bc font-extrabold text-base {{ $isMe ? 'text-bolao-accent' : 'text-slate-200' }}">{{ $entry->position ?? '—' }}</span>
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-bolao-bg4 text-[9px] font-bold text-bolao-accent2">{{ $initials ?: 'PL' }}</span>
            <span class="min-w-0 flex-1 truncate text-xs font-semibold {{ $isMe ? 'text-white' : 'text-slate-200' }}">{{ $isMe ? 'Você' : $publicName }}</span>
            <span class="font-bc font-extrabold text-xl {{ $isMe ? 'text-bolao-accent' : 'text-white' }}">{{ (int) $entry->points_total }}</span>
        </div>
        @empty
        <div class="py-2 text-xs text-bolao-muted">Ranking ainda não disponível.</div>
        @endforelse
    </div>
</div>
@endif

@if($upcoming->isNotEmpty())
@php $nextMatchday = $upcoming->first()?->matchday; @endphp
<div class="rp-widget">
    <div class="rp-widget-header">
        <span>Rodada {{ $nextMatchday }}</span>
        <a href="{{ route('pools.index', ['competition' => $currentCompetitionCode]) }}"
           class="text-bolao-accent hover:text-bolao-accent2 transition-colors normal-case tracking-normal text-[11px] font-semibold">
            todos →
        </a>
    </div>
    <div class="rp-widget-body divide-y divide-white/[0.04]">
        @foreach($upcoming->filter(fn($m) => $m->matchday === $nextMatchday)->take(4) as $match)
        <div class="py-2 first:pt-1">
            <p class="font-bc font-bold text-xs text-slate-200">
                {{ $match->homeTeam?->tla ?? '?' }}
                <span class="text-bolao-muted2 font-normal text-[10px] mx-1">vs</span>
                {{ $match->awayTeam?->tla ?? '?' }}
            </p>
            <p class="text-[10px] text-bolao-muted mt-0.5">
                {{ $match->utc_date?->timezone('America/Sao_Paulo')->format('d/m · H:i') }}
                @if($selectedPool)
                @php $rp = $recentPredictions->get($match->id); @endphp
                · @if($rp)<span class="text-bolao-accent">✓</span>@else<span class="text-bolao-red">sem palpite</span>@endif
                @endif
            </p>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($myMembershipsForComp->isEmpty())
<div class="rp-widget">
    <div class="rp-widget-body text-center py-4">
        <i class="ti ti-tournament text-3xl text-bolao-muted2 mb-2 block"></i>
        <p class="font-bc font-bold text-sm text-white mb-1">Crie seu bolão!</p>
        <p class="text-xs text-bolao-muted mb-3">Convide amigos e dispute quem acerta mais.</p>
        <a href="{{ route('pools.create', ['competition' => $currentCompetitionCode]) }}"
           class="w-full h-9 bg-bolao-accent hover:bg-bolao-accent2 text-black font-bc font-bold text-xs uppercase tracking-wide rounded-lg transition-colors inline-flex items-center justify-center">
            Criar Bolão
        </a>
    </div>
</div>
@endif
@endpush
