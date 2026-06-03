<div class="p-4 sm:p-6 lg:p-8 space-y-6 animate-fade-in">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Bolões - {{ $competitionName }}</h1>
            <p class="text-sm text-slate-400 mt-1">Gerencie seus grupos de palpites</p>
        </div>
        <a href="{{ route('pools.create', ['competition' => $competition_code]) }}" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="hidden sm:inline">Criar Bolão</span>
            <span class="sm:hidden">Criar</span>
        </a>
    </div>

    @if(session('status'))
    <div class="alert-success">{{ session('status') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Minha participação --}}
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-base font-semibold text-white">Minha Participação</h2>
            @php
                $activeHomePoolId = (int) session('home_pool_'.strtoupper((string) $competition_code), 0);
            @endphp

            @if($myPools->isEmpty())
            <div class="card p-12 text-center">
                <div class="flex flex-col items-center gap-4">
                    <div class="h-16 w-16 rounded-2xl bg-slate-800 flex items-center justify-center text-3xl">
                        🏆
                    </div>
                    <div>
                        <p class="text-slate-300 font-medium">Você ainda não participa de nenhum bolão</p>
                        <p class="text-sm text-slate-500 mt-1">Crie um novo ou entre com código de convite</p>
                    </div>
                    <a href="{{ route('pools.create', ['competition' => $competition_code]) }}" class="btn-primary mt-2">Criar meu primeiro bolão</a>
                </div>
            </div>
            @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($myPools as $membership)
                @php
                $isActiveForAppView = $activeHomePoolId > 0 && (int) $membership->pool_id === $activeHomePoolId;
                $membersCount = (int) ($membership->pool_members_count ?? 0);
                $statusColor = match($membership->status) {
                    'active' => 'badge-green',
                    'pending' => 'badge-amber',
                    'inactive' => 'badge-slate',
                    default => 'badge-red',
                };
                $roleLabel = match($membership->role) {
                    'owner' => '👑 Dono',
                    'manager' => '🛡️ Gestor',
                    default => '🎯 Membro',
                };
                $statusLabel = match($membership->status) {
                    'active' => 'Ativo',
                    'pending' => 'Aguardando',
                    'inactive' => 'Suspenso',
                    'removed' => 'Removido',
                    'blocked' => 'Bloqueado',
                    default => ucfirst($membership->status),
                };
                $ranking = $myRankings->get($membership->pool_id);
                $rankingBadge = match(true) {
                    ($ranking?->position ?? 9999) === 1 => 'bg-amber-900/40 text-amber-300 ring-amber-500/30',
                    ($ranking?->position ?? 9999) === 2 => 'bg-slate-700/60 text-slate-200 ring-slate-500/30',
                    ($ranking?->position ?? 9999) === 3 => 'bg-orange-900/40 text-orange-300 ring-orange-500/30',
                    default => 'bg-blue-900/30 text-blue-300 ring-blue-500/30',
                };
                @endphp
                @php
                $pendingCount = (int) ($membership->pool->pending_members_count ?? 0);
                $isManager = in_array($membership->role, ['owner', 'manager']);
                @endphp
                <div class="card-hover border {{ $isActiveForAppView ? 'border-amber-400/90 bg-amber-500/5 shadow-[0_0_0_1px_rgba(251,191,36,0.18)]' : 'border-white/[0.07]' }} overflow-hidden"
                     x-data="{ copied: false, rulesOpen: false }">

                    <a href="{{ route('pools.show', $membership->pool->slug) }}"
                       class="p-4 block group">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold {{ $isActiveForAppView ? 'text-amber-300' : 'text-slate-100 group-hover:text-white' }} transition-colors truncate">
                                    {{ $membership->pool->name }}
                                </h3>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if($isManager && $pendingCount > 0)
                                <a href="{{ route('pools.members', $membership->pool->slug) }}"
                                   @click.stop
                                   class="inline-flex items-center gap-1 rounded-full bg-amber-500/20 border border-amber-500/40 px-2 py-0.5 text-[11px] font-semibold text-amber-300 hover:bg-amber-500/30 transition-colors">
                                    <span class="flex h-1.5 w-1.5 relative">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                                    </span>
                                    {{ $pendingCount }} pendente{{ $pendingCount !== 1 ? 's' : '' }}
                                </a>
                                @endif
                                <span class="{{ $statusColor }} shrink-0">{{ $statusLabel }}</span>
                            </div>
                        </div>

                        <div class="mt-1.5 flex items-center gap-3 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <i class="ti ti-ball-football text-sm"></i>
                                jogos
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <i class="ti ti-users text-sm"></i>
                                {{ $membersCount }} membro{{ $membersCount !== 1 ? 's' : '' }}
                            </span>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                @if($ranking)
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $rankingBadge }}">
                                    #{{ $ranking->position }}
                                </span>
                                <span class="text-sm text-slate-300">{{ $ranking->points_total }} pts</span>
                                @else
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 bg-slate-800 text-slate-400 ring-slate-600/40">
                                    Ranking pendente
                                </span>
                                @endif
                            </div>
                            <span class="text-xs text-amber-300 shrink-0">{{ $roleLabel }}</span>
                        </div>
                    </a>

                    <div class="px-4 pb-3 border-t border-slate-800 flex items-center justify-between">
                        @if($isManager)
                        <div class="flex items-center gap-2 pt-3">
                            <span class="text-xs text-slate-500">
                                <span class="text-slate-500">🔗</span>
                                <span class="font-mono text-emerald-400">{{ $membership->pool->invite_code }}</span>
                            </span>
                            <button type="button"
                                    @click.prevent.stop="navigator.clipboard.writeText('{{ $membership->pool->invite_code }}').then(() => { copied = true; setTimeout(() => copied = false, 1400); })"
                                    class="inline-flex items-center justify-center rounded-md p-1 text-emerald-400 hover:text-emerald-300 hover:bg-emerald-900/30 transition-colors"
                                    title="Copiar código de convite">
                                <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </div>
                        @else
                        <div class="pt-3"></div>
                        @endif
                        <button type="button"
                                @click.stop="rulesOpen = !rulesOpen"
                                class="mt-3 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-300 transition-colors">
                            <i class="ti ti-info-circle text-sm"></i>
                            <span x-text="rulesOpen ? 'Ocultar regras' : 'Ver regras'">Ver regras</span>
                            <i class="ti text-xs transition-transform duration-200" :class="rulesOpen ? 'ti-chevron-up' : 'ti-chevron-down'"></i>
                        </button>
                    </div>

                    {{-- Painel de regras expandível --}}
                    <div x-show="rulesOpen" x-cloak x-transition
                         class="border-t border-slate-800/60 bg-slate-900/50 px-4 py-3 space-y-3">

                        @if($membership->pool->description)
                        <p class="text-xs text-slate-400 leading-relaxed">{{ $membership->pool->description }}</p>
                        <div class="h-px bg-slate-800/60"></div>
                        @endif

                        {{-- Pontuação --}}
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-2">Pontuação</p>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="rounded-lg bg-slate-800/60 px-2 py-1.5 text-center">
                                    <p class="text-base font-black text-emerald-400">{{ $membership->pool->points_exact_score ?? 5 }}</p>
                                    <p class="text-[10px] text-slate-500">Exato</p>
                                </div>
                                <div class="rounded-lg bg-slate-800/60 px-2 py-1.5 text-center">
                                    <p class="text-base font-black text-sky-400">{{ $membership->pool->points_correct_result ?? 3 }}</p>
                                    <p class="text-[10px] text-slate-500">Resultado</p>
                                </div>
                                <div class="rounded-lg bg-slate-800/60 px-2 py-1.5 text-center">
                                    <p class="text-base font-black text-amber-400">{{ $membership->pool->points_correct_goals ?? 1 }}</p>
                                    <p class="text-[10px] text-slate-500">Gols</p>
                                </div>
                            </div>
                        </div>

                        {{-- Regras de palpite --}}
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-2">Regras de Palpite</p>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between rounded-lg bg-slate-800/50 px-2.5 py-1.5">
                                    <span class="text-xs text-slate-400">Palpite único (sem editar)</span>
                                    <span class="text-xs font-semibold {{ $membership->pool->closed_predictions ? 'text-amber-300' : 'text-slate-500' }}">
                                        {{ $membership->pool->closed_predictions ? '🔒 Sim' : '🔓 Não' }}
                                    </span>
                                </div>
                                @if(!$membership->pool->closed_predictions)
                                <div class="flex items-center justify-between rounded-lg bg-slate-800/50 px-2.5 py-1.5">
                                    <span class="text-xs text-slate-400">Edição de palpite</span>
                                    <span class="text-xs font-semibold {{ $membership->pool->allow_prediction_changes ? 'text-emerald-300' : 'text-slate-500' }}">
                                        {{ $membership->pool->allow_prediction_changes ? '✅ Sim' : '❌ Não' }}
                                    </span>
                                </div>
                                @endif
                                <div class="flex items-center justify-between rounded-lg bg-slate-800/50 px-2.5 py-1.5">
                                    <span class="text-xs text-slate-400">Pendentes palpitam</span>
                                    <span class="text-xs font-semibold {{ $membership->pool->allow_pending_member_predictions ? 'text-blue-300' : 'text-slate-500' }}">
                                        {{ $membership->pool->allow_pending_member_predictions ? '✅ Sim' : '❌ Não' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        @if($isManager)
                        <a href="{{ route('pools.members', $membership->pool->slug) }}"
                           class="flex items-center justify-between rounded-lg border border-amber-500/20 bg-amber-900/10 px-3 py-2 text-xs font-semibold text-amber-300 hover:bg-amber-900/20 transition-colors">
                            <span class="flex items-center gap-1.5">
                                <i class="ti ti-users text-sm"></i>
                                Gerenciar participantes
                                @if($pendingCount > 0)
                                <span class="rounded-full bg-amber-500 text-white text-[10px] font-bold px-1.5">{{ $pendingCount }}</span>
                                @endif
                            </span>
                            <i class="ti ti-chevron-right text-sm"></i>
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Sidebar: entrar por convite + bolões públicos --}}
        <div class="space-y-4">
            {{-- Entrar por convite --}}
            <div class="card p-5">
                <h2 class="text-base font-semibold text-white mb-4">Entrar por Convite</h2>
                <form wire:submit="joinByInviteCode" class="space-y-3">
                    <div>
                        <label class="label">Código de convite</label>
                        <input type="text"
                               maxlength="8"
                               wire:model.live.debounce.250ms="invite_code"
                               placeholder="XXXXXXXX"
                               class="input-field text-center text-lg font-mono tracking-widest uppercase"
                               autocomplete="off">
                        @error('invite_code')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @if($invite_pool_preview)
                    <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 space-y-1.5">
                        <p class="text-xs uppercase tracking-wider text-emerald-300">Bolão encontrado</p>
                        <p class="text-sm font-semibold text-white">{{ $invite_pool_preview['name'] }}</p>
                        <p class="text-xs text-slate-300">
                            Competição: {{ $invite_pool_preview['competition_name'] }} ({{ $invite_pool_preview['competition_code'] ?: '—' }})
                        </p>
                        <p class="text-xs text-slate-400">
                            Visibilidade: {{ $invite_pool_preview['visibility'] === 'public' ? 'Público' : ($invite_pool_preview['visibility'] === 'invite_only' ? 'Convite' : 'Privado') }}
                            · Status: {{ $invite_pool_preview['status'] === 'active' ? 'Ativo' : 'Inativo' }}
                        </p>
                    </div>
                    @endif

                    @if(!empty($invite_sectors))
                    <div>
                        <label class="label">Seu setor / departamento</label>
                        <select wire:model.live="invite_sector" class="select-field">
                            <option value="">Selecione seu setor</option>
                            @foreach($invite_sectors as $sector)
                            <option value="{{ $sector }}">{{ $sector }}</option>
                            @endforeach
                        </select>
                        @error('invite_sector')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    <button type="submit"
                            @disabled(! $this->canSubmitInviteRequest())
                            class="btn-primary w-full justify-center disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        Solicitar Entrada
                    </button>
                </form>
            </div>

            {{-- Bolões públicos --}}
            <div class="card p-5">
                <h2 class="text-base font-semibold text-white mb-4">Bolões Públicos</h2>

                @if($publicPools->isEmpty())
                <p class="text-sm text-slate-500 text-center py-4">
                    Nenhum bolão público disponível.
                </p>
                @else
                <div class="space-y-3">
                    @foreach($publicPools as $pool)
                    <div class="rounded-lg bg-pitch-800 border border-slate-700 p-3">
                        <p class="text-sm font-medium text-slate-200">{{ $pool->name }}</p>
                        @if($pool->description)
                        <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $pool->description }}</p>
                        @endif
                        <button type="button"
                                wire:click="requestPublicEntry({{ $pool->id }})"
                                wire:loading.attr="disabled"
                                wire:target="requestPublicEntry({{ $pool->id }})"
                                class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-emerald-400 hover:text-emerald-300 transition-colors disabled:opacity-60">
                            <span wire:loading.remove wire:target="requestPublicEntry({{ $pool->id }})">Solicitar entrada →</span>
                            <span wire:loading wire:target="requestPublicEntry({{ $pool->id }})">Enviando...</span>
                        </button>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
