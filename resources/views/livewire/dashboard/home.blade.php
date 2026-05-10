<div class="p-4 sm:p-6 lg:p-8 space-y-8 animate-fade-in"
     @if($live->isNotEmpty()) wire:poll.10s @endif>

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">
                Olá, {{ explode(' ', Auth::user()->name)[0] }}! 👋
            </h1>
            <p class="text-sm text-slate-400 mt-1">Bem-vindo ao Bolão Copa do Mundo 2026</p>
        </div>
        <a href="{{ route('pools.create', ['competition' => $currentCompetitionCode]) }}" class="btn-primary hidden sm:inline-flex">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Criar Bolão
        </a>
    </div>

    {{-- Jogos ao vivo --}}
    @if($live->isNotEmpty())
    <section>
        <div class="flex items-center gap-2 mb-4">
            <span class="flex h-2.5 w-2.5 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
            </span>
            <h2 class="text-base font-semibold text-white">Ao Vivo</h2>
            <span class="badge-red">{{ $live->count() }} jogo{{ $live->count() > 1 ? 's' : '' }}</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($live as $match)
            @php
                $matchHref  = $matchLinks[$match->id] ?? null;
                $minute     = $liveMinutes[$match->id] ?? null;
                $extraMin   = data_get($match->raw_payload, 'injury_time', data_get($match->raw_payload, 'extra'));
                $showMinute = in_array($match->status, ['IN_PLAY', 'EXTRA_TIME', 'PENALTY_SHOOTOUT']);

                [$liveLabel, $badgeClass, $pingOuter, $pingInner, $showPing] = match($match->status) {
                    'IN_PLAY'          => ['Ao Vivo',     'badge-red',    'bg-red-400',     'bg-red-500',     true],
                    'PAUSED'           => ['Intervalo',   'badge-amber',  'bg-amber-400',   'bg-amber-500',   false],
                    'EXTRA_TIME'       => ['Prorrogação', 'badge-orange', 'bg-orange-400',  'bg-orange-500',  true],
                    'PENALTY_SHOOTOUT' => ['Pênaltis',    'badge-purple', 'bg-purple-400',  'bg-purple-500',  true],
                    'PRE_MATCH'        => ['Pré-Jogo',    'badge-green',  'bg-emerald-400', 'bg-emerald-500', true],
                    default            => ['Ao Vivo',     'badge-red',    'bg-red-400',     'bg-red-500',     true],
                };
            @endphp
            @if($matchHref)
            <a href="{{ $matchHref }}" class="card p-4 ring-1 ring-red-500/30 bg-gradient-to-br from-red-950/30 to-pitch-900 block hover:ring-emerald-500/40 transition">
            @else
            <div class="card p-4 ring-1 ring-red-500/30 bg-gradient-to-br from-red-950/30 to-pitch-900">
            @endif
                <div class="flex items-center justify-end mb-3">
                    <span class="text-xs text-slate-500">{{ $match->group_name }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex flex-col items-center gap-1.5 flex-1">
                        @if($match->homeTeam?->crest)
                        <img src="{{ $match->homeTeam->crest }}" alt="{{ $match->homeTeam->name }}"
                             class="h-10 w-10 object-contain drop-shadow" loading="lazy">
                        @else
                        <div class="h-10 w-10 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-300">
                            {{ $match->homeTeam?->tla ?? '?' }}
                        </div>
                        @endif
                        <span class="text-xs font-medium text-slate-300 text-center leading-tight">
                            {{ $match->homeTeam?->localized_name ?? 'A definir' }}
                        </span>
                    </div>

                    <div class="flex flex-col items-center">
                        <div class="flex items-center gap-2">
                            <span class="score-display text-2xl">{{ $match->home_score_full_time ?? '?' }}</span>
                            <span class="text-slate-600 text-lg font-light">—</span>
                            <span class="score-display text-2xl">{{ $match->away_score_full_time ?? '?' }}</span>
                        </div>
                        <span class="{{ $badgeClass }} text-xs mt-2 inline-flex items-center gap-1.5">
                            @if($showPing)
                                <span class="relative flex h-2 w-2 shrink-0">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $pingOuter }} opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full {{ $pingInner }}"></span>
                                </span>
                            @endif
                            {{ $liveLabel }}
                            @if($showMinute && $minute !== null)
                                · {{ $minute }}'@if($extraMin)+{{ $extraMin }}@endif
                            @endif
                        </span>
                    </div>

                    <div class="flex flex-col items-center gap-1.5 flex-1">
                        @if($match->awayTeam?->crest)
                        <img src="{{ $match->awayTeam->crest }}" alt="{{ $match->awayTeam->name }}"
                             class="h-10 w-10 object-contain drop-shadow" loading="lazy">
                        @else
                        <div class="h-10 w-10 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-300">
                            {{ $match->awayTeam?->tla ?? '?' }}
                        </div>
                        @endif
                        <span class="text-xs font-medium text-slate-300 text-center leading-tight">
                            {{ $match->awayTeam?->localized_name ?? 'A definir' }}
                        </span>
                    </div>
                </div>
            @if($matchHref)
            </a>
            @else
            </div>
            @endif
            @endforeach
        </div>
    </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Próximos jogos --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="grid gap-4 xl:grid-cols-2">
                <div class="space-y-4">
                    <h2 class="text-base font-semibold text-white">Próximos Jogos</h2>

                    @if($upcoming->isEmpty())
                    <div class="card p-8 text-center">
                        <p class="text-slate-500 text-sm">Nenhum jogo agendado no momento.</p>
                    </div>
                    @else
                    <div class="space-y-2">
                        @foreach($upcoming as $match)
                        @php
                            $matchHref = $matchLinks[$match->id] ?? null;
                        @endphp
                        @if($matchHref)
                        <a href="{{ $matchHref }}" class="card-hover p-4 block">
                        @else
                        <div class="card-hover p-4">
                        @endif
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3 flex-1">
                                    @if($match->homeTeam?->crest)
                                    <img src="{{ $match->homeTeam->crest }}" alt="{{ $match->homeTeam->tla }}"
                                         class="h-8 w-8 object-contain" loading="lazy">
                                    @else
                                    <div class="h-8 w-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-400">
                                        {{ $match->homeTeam?->tla ?? '?' }}
                                    </div>
                                    @endif
                                    <span class="text-sm font-medium text-slate-200">
                                        {{ $match->homeTeam?->localized_name ?? 'A definir' }}
                                    </span>
                                </div>

                                <div class="flex flex-col items-center shrink-0">
                                    <span class="text-xs font-semibold text-slate-400 bg-slate-800 rounded px-2 py-0.5">VS</span>
                                    <span class="text-xs text-slate-500 mt-1">
                                        {{ $match->utc_date?->timezone('America/Sao_Paulo')->format('d/m H:i') }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-3 flex-1 justify-end">
                                    <span class="text-sm font-medium text-slate-200">
                                        {{ $match->awayTeam?->localized_name ?? 'A definir' }}
                                    </span>
                                    @if($match->awayTeam?->crest)
                                    <img src="{{ $match->awayTeam->crest }}" alt="{{ $match->awayTeam->tla }}"
                                         class="h-8 w-8 object-contain" loading="lazy">
                                    @else
                                    <div class="h-8 w-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-400">
                                        {{ $match->awayTeam?->tla ?? '?' }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @if($match->group_name)
                            <div class="mt-2 flex justify-center">
                                <span class="text-xs text-slate-600">{{ $match->group_name }}</span>
                            </div>
                            @endif
                        @if($matchHref)
                        </a>
                        @else
                        </div>
                        @endif
                        @endforeach
                    </div>
                    @endif
                </div>

                <div class="space-y-4">
                    <h2 class="text-base font-semibold text-white">Classificação · {{ $currentCompetitionName }}</h2>
                    @if($groupStandings->isEmpty())
                    <div class="card p-8 text-center">
                        <p class="text-slate-500 text-sm">Classificação indisponível no momento.</p>
                    </div>
                    @else
                    <div class="space-y-3 max-h-[32rem] overflow-y-auto pr-1">
                        @foreach($groupStandings as $standing)
                        @php
                            $isWorldCupGroupView = $currentCompetitionCode === 'WC'
                                && !empty($standing->group_name)
                                && $standing->group_name !== 'Classificação Geral';

                            $orderedRows = $standing->rows->sort(function ($a, $b) {
                                $aPosition = $a->position ?? PHP_INT_MAX;
                                $bPosition = $b->position ?? PHP_INT_MAX;
                                if ($aPosition !== $bPosition) {
                                    return $aPosition <=> $bPosition;
                                }

                                $criteria = [
                                    ['points', true],
                                    ['goal_difference', true],
                                    ['goals_for', true],
                                ];

                                foreach ($criteria as [$field, $desc]) {
                                    $aValue = (int) ($a->{$field} ?? 0);
                                    $bValue = (int) ($b->{$field} ?? 0);
                                    if ($aValue !== $bValue) {
                                        return $desc ? ($bValue <=> $aValue) : ($aValue <=> $bValue);
                                    }
                                }

                                $aName = mb_strtolower((string) ($a->team?->localized_name ?? ''));
                                $bName = mb_strtolower((string) ($b->team?->localized_name ?? ''));

                                return $aName <=> $bName;
                            })->values();

                            if ($isWorldCupGroupView) {
                                $orderedRows = $orderedRows->take(4)->values();
                            }
                        @endphp
                        <div class="card p-0 overflow-hidden">
                            <div class="px-3 py-2 border-b border-slate-800 bg-slate-900/40 flex items-center justify-between">
                                <div class="text-xs font-semibold text-slate-300 uppercase tracking-wide">
                                    {{ $standing->group_name }}
                                </div>
                                <div class="text-[10px] text-slate-500 text-right">
                                    <div>{{ $standingsCriteriaLabel }}</div>
                                    @if($isWorldCupGroupView)
                                    <div class="text-emerald-400">1º e 2º classificam</div>
                                    @endif
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-xs">
                                    <thead>
                                        <tr class="text-[10px] text-slate-500 border-b border-slate-800">
                                            <th class="px-2 py-2 text-center font-medium">#</th>
                                            <th class="px-2 py-2 text-left font-medium">Time</th>
                                            <th class="px-2 py-2 text-right font-medium">PJ</th>
                                            <th class="px-2 py-2 text-right font-medium">V</th>
                                            <th class="px-2 py-2 text-right font-medium">E</th>
                                            <th class="px-2 py-2 text-right font-medium">D</th>
                                            <th class="px-2 py-2 text-right font-medium">GP</th>
                                            <th class="px-2 py-2 text-right font-medium">GC</th>
                                            <th class="px-2 py-2 text-right font-medium">SG</th>
                                            <th class="px-2 py-2 text-right font-semibold text-slate-300">PTS</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/60">
                                        @foreach($orderedRows as $row)
                                        @php
                                            $teamDisplayName = $row->team?->localized_name ?? 'Equipe';
                                            $displayPosition = $loop->iteration;
                                            $isClassified = $isWorldCupGroupView && $displayPosition <= 2;
                                        @endphp
                                        <tr class="hover:bg-slate-800/30 transition-colors">
                                            <td class="px-2 py-2 text-center font-semibold {{ $isClassified ? 'text-emerald-400' : 'text-slate-500' }}">
                                                {{ $isWorldCupGroupView ? $displayPosition : ($row->position ?? '—') }}
                                            </td>
                                            <td class="px-2 py-2 text-slate-200 truncate max-w-[9rem]" title="{{ $row->team?->name }}">
                                                {{ $teamDisplayName }}
                                            </td>
                                            <td class="px-2 py-2 text-right text-slate-500">{{ $row->played_games }}</td>
                                            <td class="px-2 py-2 text-right text-slate-500">{{ $row->won }}</td>
                                            <td class="px-2 py-2 text-right text-slate-500">{{ $row->draw }}</td>
                                            <td class="px-2 py-2 text-right text-slate-500">{{ $row->lost }}</td>
                                            <td class="px-2 py-2 text-right text-slate-500">{{ $row->goals_for }}</td>
                                            <td class="px-2 py-2 text-right text-slate-500">{{ $row->goals_against }}</td>
                                            <td class="px-2 py-2 text-right {{ (int) $row->goal_difference > 0 ? 'text-emerald-400' : ((int) $row->goal_difference < 0 ? 'text-red-400' : 'text-slate-500') }}">
                                                {{ (int) $row->goal_difference > 0 ? '+' : '' }}{{ $row->goal_difference }}
                                            </td>
                                            <td class="px-2 py-2 text-right font-bold text-white">{{ $row->points }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- Últimos resultados --}}
            @if($recent->isNotEmpty())
            <h2 class="text-base font-semibold text-white pt-2">Resultados Recentes</h2>
            <div class="space-y-2">
                @foreach($recent as $match)
                @php
                    $matchHref = $matchLinks[$match->id] ?? null;
                @endphp
                @if($matchHref)
                <a href="{{ $matchHref }}" class="card p-4 opacity-80 block hover:opacity-100 transition">
                @else
                <div class="card p-4 opacity-80">
                @endif
                    @php
                        $kickoff = $match->local_date ?? $match->utc_date;
                        $venue = data_get($match->detail?->payload, 'venue')
                            ?? data_get($match->detail?->payload, 'match.venue')
                            ?? data_get($match->detail?->payload, 'area.name')
                            ?? data_get($match->raw_payload, 'venue')
                            ?? data_get($match->raw_payload, 'area.name');
                    @endphp
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 flex-1">
                            @if($match->homeTeam?->crest)
                            <img src="{{ $match->homeTeam->crest }}" alt="{{ $match->homeTeam->tla }}"
                                 class="h-7 w-7 object-contain" loading="lazy">
                            @else
                            <div class="h-7 w-7 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-400">
                                {{ $match->homeTeam?->tla ?? '?' }}
                            </div>
                            @endif
                            <span class="text-sm text-slate-300">{{ $match->homeTeam?->localized_name ?? 'A definir' }}</span>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-lg font-black text-white tabular-nums">{{ $match->home_score_full_time ?? 0 }}</span>
                            <span class="text-slate-600">—</span>
                            <span class="text-lg font-black text-white tabular-nums">{{ $match->away_score_full_time ?? 0 }}</span>
                        </div>

                        <div class="flex items-center gap-3 flex-1 justify-end">
                            <span class="text-sm text-slate-300">{{ $match->awayTeam?->localized_name ?? 'A definir' }}</span>
                            @if($match->awayTeam?->crest)
                            <img src="{{ $match->awayTeam->crest }}" alt="{{ $match->awayTeam->tla }}"
                                 class="h-7 w-7 object-contain" loading="lazy">
                            @else
                            <div class="h-7 w-7 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-400">
                                {{ $match->awayTeam?->tla ?? '?' }}
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-3 text-xs text-slate-500">
                        <span>{{ $kickoff?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</span>
                        @if($venue)
                        <span class="truncate text-right" title="{{ $venue }}">{{ $venue }}</span>
                        @endif
                    </div>
                @if($matchHref)
                </a>
                @else
                </div>
                @endif
                @endforeach
            </div>
            @endif
        </div>

        {{-- Meus Bolões --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-white">Meus Bolões</h2>
                <a href="{{ route('pools.index', ['competition' => $currentCompetitionCode]) }}" class="text-xs text-emerald-400 hover:text-emerald-300">Ver todos →</a>
            </div>

            @if($myMemberships->isEmpty())
            <div class="card p-6 text-center">
                <p class="text-slate-500 text-sm mb-4">Você ainda não participa de nenhum bolão.</p>
                <a href="{{ route('pools.create', ['competition' => $currentCompetitionCode]) }}" class="btn-primary btn-sm w-full justify-center">Criar Bolão</a>
            </div>
            @else
            <div class="space-y-3">
                @foreach($myMemberships as $membership)
                @php
                    $ranking = $myRankings->get($membership->pool->id ?? 0);
                @endphp
                <a href="{{ route('pools.show', $membership->pool->slug) }}"
                   class="card-hover p-4 flex items-center justify-between gap-3 block">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-900/40 text-emerald-400 text-xs font-bold">
                            #{{ $ranking?->position ?? '—' }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-200 truncate">{{ $membership->pool->name }}</p>
                            @if($ranking)
                            <p class="text-xs text-slate-500">{{ $ranking->points_total }} pts</p>
                            @else
                            <p class="text-xs text-slate-600">Sem ranking ainda</p>
                            @endif
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                @endforeach

                <a href="{{ route('pools.create', ['competition' => $currentCompetitionCode]) }}" class="btn-ghost w-full justify-center border border-dashed border-slate-700 hover:border-emerald-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Criar novo bolão
                </a>
            </div>
            @endif
        </div>
    </div>
</div>
