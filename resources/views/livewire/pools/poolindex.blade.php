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
                <a href="{{ route('pools.show', $membership->pool->slug) }}"
                   class="card-hover p-4 block group"
                   x-data="{ copied: false }">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-slate-100 group-hover:text-white transition-colors truncate">
                                {{ $membership->pool->name }}
                            </h3>
                            @if($ranking)
                            <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $rankingBadge }}">
                                Ranking #{{ $ranking->position }} · {{ $ranking->points_total }} pts
                            </span>
                            @else
                            <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 bg-slate-800 text-slate-400 ring-slate-600/40">
                                Ranking pendente
                            </span>
                            @endif
                        </div>
                        <span class="{{ $statusColor }} shrink-0">{{ $statusLabel }}</span>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs text-slate-500">{{ $roleLabel }}</span>
                        @if($membership->pool->status === 'active')
                        <span class="text-slate-700">·</span>
                        <span class="text-xs text-slate-500">
                            {{ match($membership->pool->visibility) {
                                'public' => '🌐 Público',
                                'invite_only' => '🔗 Convite',
                                default => '🔒 Privado',
                            } }}
                        </span>
                        @endif
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-800 flex items-center justify-between">
                        @if(in_array($membership->role, ['owner', 'manager']))
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">
                                Código: <span class="font-mono text-emerald-400">{{ $membership->pool->invite_code }}</span>
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
                        <span class="text-xs text-slate-600">—</span>
                        @endif
                        <svg class="w-4 h-4 text-slate-600 group-hover:text-emerald-400 transition-colors"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>
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
                               wire:model="invite_code"
                               placeholder="XXXXXXXX"
                               class="input-field text-center text-lg font-mono tracking-widest uppercase"
                               autocomplete="off">
                        @error('invite_code')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @if(!empty($invite_sectors))
                    <div>
                        <label class="label">Seu setor / departamento</label>
                        <select wire:model="invite_sector" class="select-field">
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

                    <button type="submit" class="btn-primary w-full justify-center">
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
