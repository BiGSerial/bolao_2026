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
        <h2 class="font-bc font-bold text-sm uppercase tracking-wide text-white">Ao Vivo</h2>
        <span class="pts-chip bg-bolao-red/10 text-bolao-red">{{ $live->count() }}</span>
    </div>

    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
        @foreach($live->take(4) as $match)
        @php
            $href    = route('matches.show', ['match' => $match->id]);
            $minute  = $liveMinutes[$match->id] ?? null;
            $extraMin = data_get($match->raw_payload, 'injury_time');
            $poolRows = $livePoolPredictions[$match->id] ?? collect();
            $liveLabel = match($match->status) {
                'IN_PLAY'          => 'Ao Vivo',
                'PAUSED'           => 'Intervalo',
                'EXTRA_TIME'       => 'Prorrogação',
                'PENALTY_SHOOTOUT' => 'Pênaltis',
                default            => 'Pré-Jogo',
            };
        @endphp
        <a href="{{ $href }}" x-data="{ liveCardTab: 'pools' }" class="bg-bolao-bg2 border border-white/[0.07] rounded-xl px-4 py-4 min-h-[138px] relative overflow-hidden hover:border-white/[0.13] transition-colors">
            <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-bolao-accent to-bolao-accent2"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] text-bolao-muted">Rodada {{ $match->matchday }}</span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-bolao-red">
                    <span class="live-dot mr-0.5"></span>
                    {{ $liveLabel }}
                    @if(in_array($match->status, ['IN_PLAY','EXTRA_TIME','PENALTY_SHOOTOUT']) && $minute !== null)
                        · {{ $minute }}'@if($extraMin)+{{ $extraMin }}@endif
                    @endif
                </span>
            </div>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <x-match-team-logo :team="$match->homeTeam" size="sm" />
                    <span class="text-xs font-semibold text-slate-200 truncate">
                        {{ $match->homeTeam?->localized_name ?? $match->homeTeam?->short_name ?? $match->homeTeam?->name ?? 'A definir' }}
                    </span>
                </div>
                <div class="font-bc font-extrabold text-2xl text-white leading-none tracking-widest shrink-0 min-w-[72px] text-center px-1">
                    {{ $match->home_score_full_time ?? '?' }}–{{ $match->away_score_full_time ?? '?' }}
                </div>
                <div class="flex items-center gap-2 flex-1 min-w-0 justify-end">
                    <span class="text-xs font-semibold text-slate-200 truncate text-right">
                        {{ $match->awayTeam?->localized_name ?? $match->awayTeam?->short_name ?? $match->awayTeam?->name ?? 'A definir' }}
                    </span>
                    <x-match-team-logo :team="$match->awayTeam" size="sm" />
                </div>
            </div>
            <div class="mt-3 border-t border-white/[0.07] pt-2">
                <div class="mb-2 inline-flex w-full rounded-md border border-white/[0.07] bg-bolao-bg3/70 p-0.5">
                    <button type="button"
                            @click.prevent.stop="liveCardTab = 'pools'"
                            :class="liveCardTab === 'pools' ? 'bg-bolao-accent text-black' : 'text-bolao-muted hover:text-slate-200'"
                            class="flex-1 rounded px-2 py-1 text-[10px] font-bold uppercase tracking-wide transition-colors">
                        Meus bolões
                    </button>
                    <button type="button"
                            @click.prevent.stop="liveCardTab = 'ranking'"
                            :class="liveCardTab === 'ranking' ? 'bg-bolao-accent text-black' : 'text-bolao-muted hover:text-slate-200'"
                            class="flex-1 rounded px-2 py-1 text-[10px] font-bold uppercase tracking-wide transition-colors">
                        Ranking ao vivo
                    </button>
                </div>

                <div x-show="liveCardTab === 'pools'" class="space-y-1.5">
                    @forelse($poolRows as $row)
                    <div class="flex items-center justify-between gap-2 rounded-md border border-white/[0.08] bg-bolao-bg3/70 px-2 py-1">
                        <div class="min-w-0 text-[10px] text-slate-300 truncate">
                            <span class="font-semibold text-slate-200">{{ $row['pool_name'] }}</span>
                            <span class="text-bolao-muted"> · </span>
                            <span class="font-bc font-bold text-[11px] text-bolao-accent">{{ $row['prediction'] }}</span>
                        </div>
                        <span class="shrink-0 inline-flex items-center justify-center min-w-[42px] rounded-md border border-amber-400/35 bg-amber-400/15 px-1.5 py-0.5 font-bc font-extrabold text-[11px] text-amber-300">
                            {{ is_numeric($row['points']) ? ((int) $row['points']).' pts' : '—' }}
                        </span>
                    </div>
                    @empty
                    <div class="text-[10px] text-bolao-muted">
                        Sem palpites seus neste jogo.
                    </div>
                    @endforelse
                </div>

                <div x-show="liveCardTab === 'ranking'" class="space-y-2">
                    @php $rankingRows = $liveRankingPredictions[$match->id] ?? collect(); @endphp
                    @if($selectedPool && $rankingRows->isNotEmpty())
                    <div class="rounded-md border border-white/[0.08] bg-bolao-bg3/70 px-2 py-1.5">
                        <div class="flex items-center gap-1 px-1 text-[8px] uppercase tracking-wide text-bolao-muted2 mb-0.5">
                            <span class="w-[26px] shrink-0"></span>
                            <span class="min-w-0 flex-1 text-[10px] normal-case tracking-normal font-semibold text-slate-200 truncate">{{ $selectedPool->name }}</span>
                            <span class="w-[52px] shrink-0 text-center">Palpite</span>
                            <span class="w-[30px] shrink-0 text-right">Pts</span>
                        </div>
                        <div class="space-y-1">
                            @foreach($rankingRows as $row)
                            <div class="flex items-center gap-1 rounded-md px-1 py-0.5 text-[10px] whitespace-nowrap {{ !empty($row['is_me']) ? 'bg-amber-400/10 text-amber-300 font-semibold' : 'text-slate-300' }}">
                                <span class="w-[26px] shrink-0 font-bc font-bold">{{ (int) ($row['position'] ?? 0) }}º</span>
                                <span class="min-w-0 flex-1 truncate">{{ $row['name'] ?? 'Participante' }}@if(!empty($row['is_me'])) ★@endif</span>
                                <span class="w-[52px] shrink-0 font-bc font-bold text-bolao-accent text-center">{{ $row['prediction'] ?? 's/p' }}</span>
                                <span class="w-[30px] shrink-0 text-right font-bc font-bold text-amber-300">{{ is_numeric($row['points'] ?? null) ? (int) $row['points'] : '—' }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @else
                    <div class="text-[10px] text-bolao-muted">
                        Ranking ao vivo indisponível.
                    </div>
                    @endif
                </div>
            </div>
        </a>
        @endforeach
    </div>
</section>
@endif

{{-- ── Hero: próximo jogo ────────────────────────────────────── --}}
@if($heroMatch)
@php
    $isHeroLive = false;
    $kickoff    = $heroMatch->kickoffAtBrazil();
    $timeLabel  = $kickoff
        ? ($kickoff->isToday()    ? 'Hoje · '.$kickoff->format('H:i')
        : ($kickoff->isTomorrow() ? 'Amanhã · '.$kickoff->format('H:i')
                                  : $kickoff->format('d/m · H:i')))
        : '';
    $roundLabel = $heroMatch->matchday ? 'Rodada '.$heroMatch->matchday : 'Próximo Jogo';
@endphp
<section class="px-4 md:px-6 pt-5">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-bc font-bold text-base uppercase tracking-wide text-white">Próximo Jogo</h2>
        <a href="{{ route('pools.index', ['competition' => $currentCompetitionCode]) }}"
           class="text-xs text-bolao-accent hover:text-bolao-accent2 transition-colors">Ver todos →</a>
    </div>

    <div class="bg-bolao-bg2 border border-white/[0.13] rounded-xl overflow-hidden relative">
        <div class="absolute top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-bolao-accent to-bolao-accent2"></div>

        {{-- Meta row --}}
        <div class="flex items-center justify-between px-4 pt-4 pb-2">
            <span class="text-[11px] font-bold uppercase tracking-widest text-bolao-muted">{{ $roundLabel }}</span>
            <span class="flex items-center gap-1.5 text-xs text-bolao-muted">
                @if($isHeroLive)
                    <span class="live-dot"></span> Ao Vivo
                @else
                    <i class="ti ti-clock text-xs"></i> {{ $timeLabel }}
                @endif
            </span>
        </div>

        {{-- Teams --}}
        <div class="flex items-center justify-between gap-3 px-4 py-4">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-match-team-logo :team="$heroMatch->homeTeam" size="xl" />
                <div class="text-center">
                    <p class="font-bc font-bold text-base uppercase tracking-wide text-white leading-tight">
                        {{ $heroMatch->homeTeam?->localized_name ?? 'A definir' }}
                    </p>
                    @if($heroMatch->homeTeam?->abbr3)
                    <p class="text-[10px] text-bolao-muted mt-0.5">{{ $heroMatch->homeTeam->abbr3 }}</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-col items-center gap-1 min-w-[64px]">
                @if($isHeroLive)
                <div class="font-bc font-extrabold text-4xl text-white leading-none tracking-widest">
                    {{ $heroMatch->home_score_full_time ?? 0 }}–{{ $heroMatch->away_score_full_time ?? 0 }}
                </div>
                @else
                <div class="font-bc font-extrabold text-3xl text-bolao-muted2 leading-none tracking-widest">×</div>
                @endif
                <span class="text-[10px] text-bolao-muted2 uppercase tracking-widest">
                    {{ $isHeroLive ? 'ao vivo' : 'vs' }}
                </span>
            </div>
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-match-team-logo :team="$heroMatch->awayTeam" size="xl" />
                <div class="text-center">
                    <p class="font-bc font-bold text-base uppercase tracking-wide text-white leading-tight">
                        {{ $heroMatch->awayTeam?->localized_name ?? 'A definir' }}
                    </p>
                    @if($heroMatch->awayTeam?->abbr3)
                    <p class="text-[10px] text-bolao-muted mt-0.5">{{ $heroMatch->awayTeam->abbr3 }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Prediction area --}}
        <div class="border-t border-white/[0.07] px-4 py-3">
            @if($heroCanPredict && $selectedPool)
            <div x-data="{
                    h: {{ (int) ($heroPrediction?->home_score ?? 0) }},
                    a: {{ (int) ($heroPrediction?->away_score ?? 0) }},
                    lastSavedH: {{ (int) ($heroPrediction?->home_score ?? 0) }},
                    lastSavedA: {{ (int) ($heroPrediction?->away_score ?? 0) }},
                    saving: false,
                    errorMsg: '',
                    get saved() { return this.h === this.lastSavedH && this.a === this.lastSavedA; },
                    async save() {
                        this.saving = true;
                        this.errorMsg = '';

                        const toScore = (value) => {
                            const n = parseInt(value, 10);
                            return Number.isFinite(n) ? Math.max(0, Math.min(20, n)) : 0;
                        };

                        const home = toScore(this.h);
                        const away = toScore(this.a);

                        this.h = home;
                        this.a = away;

                        try {
                            const r = await this.$wire.saveHeroPrediction({{ $heroMatch->id }}, home, away);
                            if (r?.ok) {
                                this.lastSavedH = home;
                                this.lastSavedA = away;
                            } else {
                                this.errorMsg = r?.msg || 'Erro ao salvar.';
                            }
                        } catch (e) {
                            this.errorMsg = 'Falha ao salvar. Tente novamente.';
                        } finally {
                            this.saving = false;
                        }
                    }
                 }"
                 class="flex items-center gap-3 flex-wrap sm:flex-nowrap">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-white" x-text="saved ? '✓ Palpite salvo' : 'Seu palpite'"></p>
                    @if($heroLockTime)
                    <p class="text-[10px] text-bolao-muted mt-0.5">Encerra {{ $heroLockTime->format('d/m H:i') }}</p>
                    @endif
                    <p x-show="errorMsg" class="text-[10px] text-bolao-red mt-0.5" x-text="errorMsg"></p>
                </div>
                <div class="flex items-center gap-2">
                    <input x-model.number="h" type="number" min="0" max="20"
                           class="w-11 h-11 bg-bolao-bg4 border border-white/[0.07] rounded-lg text-center font-bc font-bold text-xl text-white focus:border-bolao-accent focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                    <span class="font-bc font-bold text-lg text-bolao-muted2">×</span>
                    <input x-model.number="a" type="number" min="0" max="20"
                           class="w-11 h-11 bg-bolao-bg4 border border-white/[0.07] rounded-lg text-center font-bc font-bold text-xl text-white focus:border-bolao-accent focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                </div>
                <button @click="save()" :disabled="saving"
                        class="h-11 px-4 bg-bolao-accent hover:bg-bolao-accent2 text-black font-bc font-bold text-sm uppercase tracking-wide rounded-lg transition-colors disabled:opacity-50 flex-shrink-0">
                    <span x-show="saving"><i class="ti ti-loader-2 animate-spin text-sm"></i></span>
                    <span x-show="!saving" x-text="saved ? '✓ Salvo' : 'Salvar'"></span>
                </button>
            </div>

            @elseif($heroPrediction && $selectedPool)
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-white">Seu palpite</p>
                    <p class="text-[10px] text-bolao-muted mt-0.5">
                        @if($heroMatch->isPredictionLockedFor($selectedPool))
                            <i class="ti ti-lock text-xs"></i> Bloqueado
                        @else
                            <i class="ti ti-check text-xs text-bolao-green"></i> Registrado
                        @endif
                    </p>
                </div>
                <div class="font-bc font-extrabold text-2xl text-bolao-accent tracking-widest">
                    {{ $heroPrediction->home_score }} × {{ $heroPrediction->away_score }}
                </div>
            </div>

            @elseif($selectedPool)
            <div class="flex items-center justify-between">
                            @if($heroLockedForPool)
                            <p class="text-xs text-bolao-muted">Janela de palpite encerrada para este bolão.</p>
                            @elseif($heroStageMismatch)
                            <p class="text-xs text-bolao-muted">Palpite não disponível para esta fase.</p>
                            @else
                            <p class="text-xs text-bolao-muted">Palpite indisponível no momento.</p>
                            @endif
                <a href="{{ route('pools.show', $selectedPool->slug) }}"
                   class="text-xs text-bolao-accent hover:text-bolao-accent2 font-semibold">Ver bolão →</a>
            </div>

            @else
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-bolao-muted flex-1">
                    @if($myMembershipsForComp->isEmpty())
                        Participe de um bolão para enviar seu palpite.
                    @else
                        Selecione um bolão acima para enviar seu palpite.
                    @endif
                </p>
                @if($myMembershipsForComp->isEmpty())
                <a href="{{ route('pools.index', ['competition' => $currentCompetitionCode]) }}"
                   class="flex-shrink-0 h-9 px-4 bg-bolao-accent hover:bg-bolao-accent2 text-black font-bc font-bold text-xs uppercase tracking-wide rounded-lg transition-colors inline-flex items-center">
                    Participar
                </a>
                @endif
            </div>
            @endif
        </div>
    </div>
</section>
@endif

@if(!$heroMatch)
<section class="px-4 md:px-6 pt-5">
    <div class="bg-bolao-bg2 border border-white/[0.07] rounded-xl px-4 py-5 text-center">
        <p class="font-bc font-bold text-sm uppercase tracking-wide text-bolao-muted2">Sem próximo jogo</p>
        <p class="text-xs text-bolao-muted mt-1">Aguardando programação da próxima rodada.</p>
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
@elseif($selectedPool)
<section class="px-4 md:px-6 pt-5">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-bc font-bold text-[18px] uppercase tracking-[0.5px] text-white">Minha Posição</h2>
        <a href="{{ route('pools.show', $selectedPool->slug) }}"
           class="text-[13px] font-medium text-bolao-accent hover:text-bolao-accent2 transition-colors">
            Ranking →
        </a>
    </div>
    <a href="{{ route('pools.show', $selectedPool->slug) }}"
       class="flex items-center gap-[14px] bg-bolao-bg3 border border-white/[0.12] rounded-xl px-4 py-[14px] hover:border-bolao-accent2 transition-colors">
        <div>
            <div class="text-[12px] text-bolao-muted mb-0.5">Classificação</div>
            <div class="font-bc font-extrabold text-[28px] leading-none text-bolao-muted2">—</div>
        </div>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-[15px] text-white leading-tight truncate">{{ auth()->user()->public_name }}</div>
            <div class="text-[13px] text-bolao-muted mt-0.5">Aguardando cálculo do ranking</div>
        </div>
        <div class="text-right">
            <div class="font-bc font-extrabold text-[24px] leading-none text-bolao-muted2">—</div>
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
                $kickoff = $match->kickoffAtBrazil();
                $label   = $kickoff
                    ? ($kickoff->isToday() ? 'Hoje · '.$kickoff->format('H:i') : $kickoff->format('d/m · H:i'))
                    : '';
                // Check if user has prediction for this match (any pool)
                $hasPred  = $upcomingPredictions->has($match->id);
            @endphp
            @if($href)<a href="{{ $href }}" class="bg-bolao-bg2 border border-white/[0.07] rounded-lg px-3 py-2.5 flex items-center gap-2 hover:border-white/[0.13] transition-colors">
            @else<div class="bg-bolao-bg2 border border-white/[0.07] rounded-lg px-3 py-2.5 flex items-center gap-2">
            @endif
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <x-match-team-logo :team="$match->homeTeam" size="sm" />
                    <span class="text-sm font-medium text-slate-200 truncate">
                        {{ $match->homeTeam?->localized_name ?? $match->homeTeam?->short_name ?? $match->homeTeam?->name ?? 'A definir' }}
                    </span>
                </div>
                <div class="flex flex-col items-center shrink-0 min-w-[64px]">
                    <span class="font-bc font-bold text-xs text-bolao-muted2 bg-bolao-bg4 rounded px-2 py-0.5">VS</span>
                    <span class="text-[10px] text-bolao-muted mt-0.5">{{ $label }}</span>
                </div>
                <div class="flex items-center gap-2 flex-1 min-w-0 justify-end">
                    <span class="text-sm font-medium text-slate-200 truncate text-right">
                        {{ $match->awayTeam?->localized_name ?? $match->awayTeam?->short_name ?? $match->awayTeam?->name ?? 'A definir' }}
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
                <table class="min-w-full text-[11px] sm:text-xs">
                    <colgroup>
                        <col class="w-[28px] sm:w-[40px]">
                        <col>
                        <col class="w-[30px] sm:w-[52px]">
                        <col class="w-[30px] sm:w-[52px]">
                        <col class="w-[30px] sm:w-[52px]">
                        <col class="w-[30px] sm:w-[52px]">
                        <col class="w-[30px] sm:w-[52px]">
                        <col class="w-[32px] sm:w-[56px]">
                    </colgroup>
                    <thead class="sticky top-0 z-10 bg-bolao-bg2">
                        <tr class="border-b border-white/[0.07] text-[10px] text-bolao-muted uppercase tracking-widest">
                            <th class="px-1.5 sm:px-3 py-2 text-center w-6">#</th>
                            <th class="px-1 sm:px-2 py-2 text-left">Time</th>
                            <th class="px-1 sm:px-2 py-2 text-right">PJ</th>
                            <th class="px-1 sm:px-2 py-2 text-right">V</th>
                            <th class="px-1 sm:px-2 py-2 text-right">E</th>
                            <th class="px-1 sm:px-2 py-2 text-right">D</th>
                            <th class="px-1 sm:px-2 py-2 text-right">SG</th>
                            <th class="px-1 sm:px-2 py-2 text-right font-semibold text-slate-300">PTS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                    @foreach($standingRows as $row)
                    @php
                        $pos = $loop->iteration;
                        $sg  = (int) ($row->goal_difference ?? 0);
                    @endphp
                    <tr class="hover:bg-bolao-bg3 transition-colors">
                        <td class="px-1.5 sm:px-3 py-2 text-center font-bc font-bold
                                   {{ $pos <= 4 ? 'text-bolao-green' : ($pos <= 6 ? 'text-bolao-blue' : ($pos > ($standingRows->count() - 4) ? 'text-bolao-red' : 'text-bolao-muted')) }}">
                            {{ $row->position ?? $pos }}
                        </td>
                        <td class="px-1 sm:px-2 py-2">
                            <div class="flex items-center gap-2">
                                <x-match-team-logo :team="$row->team" size="xs" />
                                <span class="text-[12px] sm:text-[13px] font-medium text-slate-200 truncate max-w-[4.5rem] sm:max-w-[6rem]" title="{{ $row->team?->localized_name ?? $row->team?->name }}">
                                    {{ $row->team?->localized_name ?? '—' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-1 sm:px-2 py-2 text-right text-bolao-muted">{{ $row->played_games }}</td>
                        <td class="px-1 sm:px-2 py-2 text-right text-bolao-muted">{{ $row->won }}</td>
                        <td class="px-1 sm:px-2 py-2 text-right text-bolao-muted">{{ $row->draw }}</td>
                        <td class="px-1 sm:px-2 py-2 text-right text-bolao-muted">{{ $row->lost }}</td>
                        <td class="px-1 sm:px-2 py-2 text-right
                                   {{ $sg > 0 ? 'text-bolao-green' : ($sg < 0 ? 'text-bolao-red' : 'text-bolao-muted') }}">
                            {{ $sg > 0 ? '+'.$sg : $sg }}
                        </td>
                        <td class="px-1 sm:px-2 py-2 text-right font-bc font-bold text-white">{{ $row->points }}</td>
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
            @php
                $kickoff = $match->kickoffAtBrazil();
                $metaDate = $kickoff ? $kickoff->format('d/m · H:i') : 'Data indefinida';
                $stageClass = \App\Livewire\Dashboard\Home::stageBadgeClass($match->stage ?? '');
                $stageLabel = \App\Livewire\Dashboard\Home::stageLabel($match->stage ?? '');
            @endphp
            <div class="w-full min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <x-match-team-logo :team="$match->homeTeam" size="sm" />
                        <span class="text-sm font-medium text-slate-200 truncate">
                            {{ $match->homeTeam?->localized_name ?? $match->homeTeam?->short_name ?? $match->homeTeam?->name ?? 'A definir' }}
                        </span>
                    </div>
                    <div class="font-bc font-extrabold text-xl text-white shrink-0 tracking-wider px-1">
                        {{ $match->home_score_full_time ?? 0 }}–{{ $match->away_score_full_time ?? 0 }}
                    </div>
                    <div class="flex items-center gap-2 min-w-0 flex-1 justify-end">
                        <span class="text-sm font-medium text-slate-200 truncate text-right">
                            {{ $match->awayTeam?->localized_name ?? $match->awayTeam?->short_name ?? $match->awayTeam?->name ?? 'A definir' }}
                        </span>
                        <x-match-team-logo :team="$match->awayTeam" size="sm" />
                    </div>
                </div>
                <div class="mt-1.5 flex items-center justify-between gap-2">
                    <span class="text-[10px] text-bolao-muted flex items-center gap-1.5 truncate">
                        <span class="{{ $stageClass }} phase-badge text-[8px] px-1 py-0">{{ $stageLabel }}</span>
                        <span>{{ $metaDate }}</span>
                    </span>
                    @if($chipClass)
                    <span class="pts-chip {{ $chipClass }} flex-shrink-0">{{ $chipLabel }}</span>
                    @endif
                </div>
            </div>
        @if($href)</a>@else</div>@endif
        @endforeach
    </div>
</section>
@endif

{{-- ── Right panel data — re-renderizado pelo Livewire, lido por JS ── --}}
<div id="rp-live-data" class="hidden" aria-hidden="true">
@if($live->isNotEmpty())
<div class="rp-widget">
    <div class="rp-widget-header">
        <span>Jogos Ao Vivo</span>
    </div>
    <div class="rp-widget-body divide-y divide-white/[0.04]">
        @foreach($live->take(3) as $match)
        @php
            $minute = $liveMinutes[$match->id] ?? null;
            $stats = $liveMatchStats[$match->id] ?? [];
        @endphp
        <a href="{{ route('matches.show', ['match' => $match->id]) }}" class="block py-2 hover:bg-white/[0.02] -mx-2 px-2 rounded-md transition-colors">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-slate-300 truncate">{{ $match->homeTeam?->localized_name ?? $match->homeTeam?->short_name ?? $match->homeTeam?->abbr3 ?? '?' }}</span>
                <span class="font-bc font-extrabold text-xl text-white">{{ $match->home_score_full_time ?? 0 }}–{{ $match->away_score_full_time ?? 0 }}</span>
                <span class="text-xs text-slate-300 truncate text-right">{{ $match->awayTeam?->localized_name ?? $match->awayTeam?->short_name ?? $match->awayTeam?->abbr3 ?? '?' }}</span>
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
@if($selectedPool && $selectedPoolTopRankings->isNotEmpty())
<div class="rp-widget">
    <div class="rp-widget-header">
        <span>Ranking ao Vivo</span>
        <a href="{{ route('pools.show', $selectedPool->slug) }}"
           class="text-bolao-accent hover:text-bolao-accent2 transition-colors normal-case tracking-normal text-[11px] font-semibold">
            ver →
        </a>
    </div>
    <div class="rp-widget-body divide-y divide-white/[0.04]">
        @foreach($selectedPoolTopRankings as $row)
        @php
            $isMe = (int) ($row->user_id ?? 0) === (int) auth()->id();
            $publicName = $row->user?->display_name ?: $row->user?->name ?: 'Participante';
            $rankPos = (int) ($row->position ?? ($loop->index + 1));
        @endphp
        <div class="rp-rank-row py-2 flex items-center justify-between gap-2 {{ $isMe ? 'bg-amber-400/10 -mx-2 px-2 rounded-md' : '' }}"
             data-rank-key="pool-{{ $selectedPool->id }}-user-{{ $row->user_id }}"
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
        @foreach($upcoming->filter(fn($m) => $m->matchday === $nextMatchday)->take(2) as $match)
        <div class="py-2 first:pt-1">
            <p class="font-bc font-bold text-xs text-slate-200">
                {{ $match->homeTeam?->abbr3 ?? '?' }}
                <span class="text-bolao-muted2 font-normal text-[10px] mx-1">vs</span>
                {{ $match->awayTeam?->abbr3 ?? '?' }}
            </p>
            <p class="text-[10px] text-bolao-muted mt-0.5">
                {{ $match->kickoffAtBrazil()?->format('d/m · H:i') }}
                @if($selectedPool)
                @php $rp = $upcomingPredictions->get($match->id); @endphp
                · @if($rp)<span class="text-bolao-accent">✓</span>@else<span class="text-bolao-red">sem palpite</span>@endif
                @endif
            </p>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($selectedPool)
@php
    $statsBase = is_array($selectedPoolRealtimeStats ?? null)
        ? $selectedPoolRealtimeStats
        : [
            'points_total' => (int) ($selectedPoolRanking?->points_total ?? 0),
            'exact_scores' => (int) ($selectedPoolRanking?->exact_scores ?? 0),
            'correct_results' => (int) ($selectedPoolRanking?->correct_results ?? 0),
            'correct_home_goals' => (int) ($selectedPoolRanking?->correct_home_goals ?? 0),
            'correct_away_goals' => (int) ($selectedPoolRanking?->correct_away_goals ?? 0),
            'predictions_counted' => (int) ($selectedPoolRanking?->predictions_counted ?? 0),
        ];
    $statsPoints = (int) ($statsBase['points_total'] ?? 0);
    $statsExact = (int) ($statsBase['exact_scores'] ?? 0);
    $statsCorrect = (int) ($statsBase['correct_results'] ?? 0);
    $statsGoals = (int) ($statsBase['correct_home_goals'] ?? 0) + (int) ($statsBase['correct_away_goals'] ?? 0);
    $statsCounted = (int) ($statsBase['predictions_counted'] ?? 0);
    $statsMisses = max(0, $statsCounted - $statsCorrect);
    $statsAccuracy = $statsCounted > 0 ? (int) round(($statsCorrect / $statsCounted) * 100) : 0;

    $statsRows = [];
    if ((int) ($selectedPool->points_exact_score ?? 0) > 0) {
        $statsRows[] = [
            'label' => 'Exatos (x'.(int) ($selectedPool->points_exact_score ?? 0).')',
            'value' => $statsExact,
            'valueClass' => 'text-bolao-green',
        ];
    }
    if ((int) ($selectedPool->points_correct_result ?? 0) > 0) {
        $statsRows[] = [
            'label' => 'Vencedor (x'.(int) ($selectedPool->points_correct_result ?? 0).')',
            'value' => $statsCorrect,
            'valueClass' => 'text-bolao-blue',
        ];
    }
    if ((int) ($selectedPool->points_correct_goals ?? 0) > 0) {
        $statsRows[] = [
            'label' => 'Gols (x'.(int) ($selectedPool->points_correct_goals ?? 0).')',
            'value' => $statsGoals,
            'valueClass' => 'text-bolao-accent',
        ];
    }
@endphp
<div class="rp-widget">
    <div class="rp-widget-header">
        <span>Suas Stats</span>
    </div>
    <div class="rp-widget-body py-3">
        <div class="space-y-1 text-xs">
            <div class="flex items-center justify-between border-b border-white/[0.07] py-1.5">
                <span class="text-bolao-muted">Pontos totais</span>
                <span class="font-bc font-extrabold text-2xl leading-none text-bolao-accent">{{ $statsPoints }}</span>
            </div>
            @foreach($statsRows as $row)
            <div class="flex items-center justify-between border-b border-white/[0.07] py-1.5">
                <span class="text-bolao-muted">{{ $row['label'] }}</span>
                <span class="font-bc font-bold text-2xl leading-none {{ $row['valueClass'] }}">{{ $row['value'] }}</span>
            </div>
            @endforeach
            <div class="flex items-center justify-between border-b border-white/[0.07] py-1.5">
                <span class="text-bolao-muted">Erros</span>
                <span class="font-bc font-bold text-2xl leading-none text-bolao-red">{{ $statsMisses }}</span>
            </div>
            <div class="flex items-center justify-between pt-1">
                <span class="text-bolao-muted">Aproveitamento</span>
                <span class="font-bc font-extrabold text-3xl leading-none text-white">{{ $statsAccuracy }}%</span>
            </div>
        </div>
    </div>
</div>

<div class="rp-widget">
    @php
        $inviteLandingUrl = route('pools.index', ['competition' => $currentCompetitionCode]);
        $inviteShareText = "Participe do meu bolao no BolaoVF!\n\n";
        $inviteShareText .= "Bolao: {$selectedPool->name}\n";
        $inviteShareText .= "Codigo de convite: {$selectedPool->invite_code}\n\n";
        $inviteShareText .= "Acesse e entre agora: {$inviteLandingUrl}\n\n";
        $inviteShareText .= "BolaoVF - palpites ao vivo, ranking em tempo real e disputa entre amigos.";
    @endphp
    <div class="rp-widget-body text-center py-4" x-data="{ copied: false, inviteText: @js($inviteShareText) }">
        <div class="text-3xl mb-2">🎉</div>
        <p class="font-bc font-bold text-3xl leading-none text-white mb-2">Convide amigos!</p>
        <p class="text-xs text-bolao-muted mb-4">Quanto mais gente, mais emoção no bolão.</p>
        <button
            @click.prevent.stop="navigator.clipboard.writeText(inviteText).then(() => { copied = true; setTimeout(() => copied = false, 1600); })"
            class="w-full h-10 bg-bolao-accent hover:bg-bolao-accent2 text-black font-bc font-bold text-sm uppercase tracking-wide rounded-lg transition-colors inline-flex items-center justify-center"
            type="button"
            :class="copied ? 'bg-bolao-green text-white hover:bg-bolao-green' : ''">
            <span x-show="!copied">Copiar Link</span>
            <span x-show="copied" x-cloak>Copiado!</span>
        </button>
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
</div>
