<div class="p-4 sm:p-6 lg:p-8 space-y-8 animate-fade-in">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">
                Olá, {{ explode(' ', Auth::user()->name)[0] }}! 👋
            </h1>
            <p class="text-sm text-slate-400 mt-1">Bem-vindo ao Bolão Copa do Mundo 2026</p>
        </div>
        <a href="{{ route('pools.create') }}" class="btn-primary hidden sm:inline-flex">
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
            <div class="card p-4 ring-1 ring-red-500/30 bg-gradient-to-br from-red-950/30 to-pitch-900">
                <div class="flex items-center justify-between mb-3">
                    <span class="badge-red text-xs">
                        {{ $match->status === 'PAUSED' ? 'Intervalo' : 'Ao Vivo' }}
                    </span>
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
                            {{ $match->homeTeam?->short_name ?? $match->homeTeam?->name ?? 'A definir' }}
                        </span>
                    </div>

                    <div class="flex flex-col items-center">
                        <div class="flex items-center gap-2">
                            <span class="score-display text-2xl">{{ $match->home_score_full_time ?? '?' }}</span>
                            <span class="text-slate-600 text-lg font-light">—</span>
                            <span class="score-display text-2xl">{{ $match->away_score_full_time ?? '?' }}</span>
                        </div>
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
                            {{ $match->awayTeam?->short_name ?? $match->awayTeam?->name ?? 'A definir' }}
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Próximos jogos --}}
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-base font-semibold text-white">Próximos Jogos</h2>

            @if($upcoming->isEmpty())
            <div class="card p-8 text-center">
                <p class="text-slate-500 text-sm">Nenhum jogo agendado no momento.</p>
            </div>
            @else
            <div class="space-y-2">
                @foreach($upcoming as $match)
                <div class="card-hover p-4">
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
                                {{ $match->homeTeam?->short_name ?? 'A definir' }}
                            </span>
                        </div>

                        <div class="flex flex-col items-center shrink-0">
                            <span class="text-xs font-semibold text-slate-400 bg-slate-800 rounded px-2 py-0.5">VS</span>
                            <span class="text-xs text-slate-500 mt-1">
                                {{ $match->local_date?->format('d/m H:i') ?? $match->utc_date->format('d/m H:i') }}
                            </span>
                        </div>

                        <div class="flex items-center gap-3 flex-1 justify-end">
                            <span class="text-sm font-medium text-slate-200">
                                {{ $match->awayTeam?->short_name ?? 'A definir' }}
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
                </div>
                @endforeach
            </div>
            @endif

            {{-- Últimos resultados --}}
            @if($recent->isNotEmpty())
            <h2 class="text-base font-semibold text-white pt-2">Resultados Recentes</h2>
            <div class="space-y-2">
                @foreach($recent as $match)
                <div class="card p-4 opacity-80">
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
                            <span class="text-sm text-slate-300">{{ $match->homeTeam?->short_name ?? 'A definir' }}</span>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-lg font-black text-white tabular-nums">{{ $match->home_score_full_time ?? 0 }}</span>
                            <span class="text-slate-600">—</span>
                            <span class="text-lg font-black text-white tabular-nums">{{ $match->away_score_full_time ?? 0 }}</span>
                        </div>

                        <div class="flex items-center gap-3 flex-1 justify-end">
                            <span class="text-sm text-slate-300">{{ $match->awayTeam?->short_name ?? 'A definir' }}</span>
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
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Meus Bolões --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-white">Meus Bolões</h2>
                <a href="{{ route('pools.index') }}" class="text-xs text-emerald-400 hover:text-emerald-300">Ver todos →</a>
            </div>

            @if($myMemberships->isEmpty())
            <div class="card p-6 text-center">
                <p class="text-slate-500 text-sm mb-4">Você ainda não participa de nenhum bolão.</p>
                <a href="{{ route('pools.create') }}" class="btn-primary btn-sm w-full justify-center">Criar Bolão</a>
            </div>
            @else
            <div class="space-y-3">
                @foreach($myMemberships as $membership)
                @php($ranking = $myRankings->get($membership->pool->id ?? 0))
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

                <a href="{{ route('pools.create') }}" class="btn-ghost w-full justify-center border border-dashed border-slate-700 hover:border-emerald-600">
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
