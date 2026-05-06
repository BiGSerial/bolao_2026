<div class="flex h-full min-h-screen animate-fade-in" x-data="{ mobilePanelOpen: false }">

    {{-- ===== Painel esquerdo: lista de bolões ===== --}}
    <aside class="hidden lg:flex w-72 shrink-0 flex-col border-r border-slate-800 bg-pitch-900">

        <div class="px-5 py-4 border-b border-slate-800">
            <h1 class="text-base font-bold text-white">Gestão de Bolões</h1>
            <p class="text-xs text-slate-500 mt-0.5">Seus bolões ativos</p>
        </div>

        <nav class="flex-1 overflow-y-auto p-2 space-y-1">
            @forelse($pools as $pool)
            @php
                $isSelected = $selectedPoolId === $pool->id;
                $hasPending = ($pool->pending_count ?? 0) > 0;
            @endphp
            <button wire:click="selectPool({{ $pool->id }})"
                    class="w-full text-left rounded-lg px-3 py-3 transition-colors
                           {{ $isSelected
                              ? 'bg-emerald-600/20 ring-1 ring-emerald-500/30'
                              : 'hover:bg-slate-800' }}">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-medium {{ $isSelected ? 'text-emerald-400' : 'text-slate-300' }} leading-tight">
                        {{ $pool->name }}
                    </p>
                    @if($hasPending)
                    <span class="shrink-0 flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-black px-1">
                        {{ $pool->pending_count }}
                    </span>
                    @endif
                </div>
                <div class="flex items-center gap-2 mt-1.5">
                    <span class="text-xs text-slate-500">{{ $pool->active_count }} ativos</span>
                    <span class="text-slate-700">·</span>
                    <span class="text-xs text-slate-500">{{ $pool->total_members }} total</span>
                </div>
            </button>
            @empty
            <div class="px-3 py-6 text-center">
                <p class="text-sm text-slate-500">Nenhum bolão ativo.</p>
                <a href="{{ route('pools.create') }}" class="text-xs text-emerald-400 hover:text-emerald-300 mt-2 block">
                    Criar bolão →
                </a>
            </div>
            @endforelse
        </nav>
    </aside>

    {{-- ===== Conteúdo principal ===== --}}
    <div class="flex-1 flex flex-col min-w-0">

        @if(! $selectedPool)
        <div class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <div class="text-5xl mb-4">🏆</div>
                <p class="text-slate-400">Selecione um bolão para gerenciar</p>
            </div>
        </div>
        @else

        {{-- Header do bolão selecionado --}}
        <div class="sticky top-0 z-10 border-b border-slate-800 bg-pitch-900/95 backdrop-blur px-4 sm:px-6 py-4">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-3 min-w-0">
                    {{-- Mobile: seletor de bolão --}}
                    <button @click="mobilePanelOpen = true"
                            class="lg:hidden flex items-center justify-center h-8 w-8 rounded-lg bg-slate-800 text-slate-400 hover:text-slate-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-white truncate">{{ $selectedPool->name }}</h2>
                        <p class="text-xs text-slate-400 flex items-center gap-2 flex-wrap">
                            <span>Código: <span class="font-mono text-emerald-400">{{ $selectedPool->invite_code }}</span></span>
                            <span class="text-slate-700">·</span>
                            <span>{{ match($selectedPool->visibility) { 'public' => '🌐 Público', 'invite_only' => '🔗 Convite', default => '🔒 Privado' } }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0 flex-wrap">
                    <a href="{{ route('pools.show', $selectedPool->slug) }}"
                       class="btn-ghost btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Ver bolão
                    </a>
                    <a href="{{ route('pools.settings', $selectedPool->slug) }}"
                       class="btn-secondary btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Configurações
                    </a>
                </div>
            </div>

            {{-- Stats rápidos --}}
            <div class="flex items-center gap-4 mt-3 flex-wrap">
                @foreach([
                    ['all', 'Todos', $statusCounts->sum(), 'text-slate-300'],
                    ['active', 'Ativos', $statusCounts->get('active', 0), 'text-emerald-400'],
                    ['pending', 'Aguardando', $statusCounts->get('pending', 0), 'text-amber-400'],
                    ['inactive', 'Suspensos', $statusCounts->get('inactive', 0), 'text-slate-500'],
                    ['blocked', 'Bloqueados', $statusCounts->get('blocked', 0), 'text-red-400'],
                ] as [$status, $label, $count, $color])
                @if($count > 0 || $status === 'all')
                <button wire:click="$set('filterStatus', '{{ $filterStatus === $status || ($status === 'all' && $filterStatus === '') ? '' : $status }}')"
                        class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors
                               {{ ($filterStatus === $status || ($status === 'all' && $filterStatus === ''))
                                  ? 'bg-slate-700 text-white'
                                  : 'text-slate-500 hover:text-slate-300 hover:bg-slate-800' }}">
                    <span class="{{ $color }} font-bold">{{ $count }}</span>
                    {{ $label }}
                </button>
                @endif
                @endforeach
            </div>
        </div>

        {{-- Busca --}}
        <div class="px-4 sm:px-6 py-3 border-b border-slate-800">
            <div class="relative max-w-sm">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Buscar por nome ou e-mail..."
                       class="input-field pl-9 py-1.5 text-sm">
            </div>
        </div>

        @if(session('error'))
        <div class="mx-4 sm:mx-6 mt-4"><div class="alert-error">{{ session('error') }}</div></div>
        @endif

        {{-- Tabela de membros --}}
        <div class="flex-1 overflow-auto p-4 sm:p-6">
            @if($members->isEmpty())
            <div class="card p-12 text-center">
                <p class="text-slate-500 text-sm">
                    @if($search || $filterStatus)
                        Nenhum participante encontrado com esses filtros.
                    @else
                        Nenhum participante neste bolão ainda.
                    @endif
                </p>
            </div>
            @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-slate-800">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Participante</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden md:table-cell">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden lg:table-cell">Telefone</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Setor</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Papel</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @foreach($members as $member)
                            @php
                                $statusBadge = match($member->status) {
                                    'active'   => 'badge-green',
                                    'pending'  => 'badge-amber',
                                    'inactive' => 'badge-slate',
                                    'blocked'  => 'badge-red',
                                    default    => 'badge-slate',
                                };
                                $statusLabel = match($member->status) {
                                    'active'   => 'Ativo',
                                    'pending'  => 'Aguardando',
                                    'inactive' => 'Suspenso',
                                    'removed'  => 'Removido',
                                    'blocked'  => 'Bloqueado',
                                    default    => ucfirst($member->status),
                                };
                                $roleLabel = match($member->role) {
                                    'owner'   => '👑 Dono',
                                    'manager' => '🛡️ Gestor',
                                    default   => '🎯 Membro',
                                };
                            @endphp
                            <tr class="hover:bg-slate-800/20 transition-colors" wire:key="mgr-{{ $member->id }}">
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white uppercase
                                                    {{ $member->role === 'owner' ? 'bg-amber-700' : ($member->role === 'manager' ? 'bg-blue-700' : 'bg-slate-700') }}">
                                            {{ mb_substr($member->user?->name ?? '?', 0, 2) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-200">{{ $member->user?->name ?? '—' }}</p>
                                            @if($member->user?->area)
                                            <p class="text-xs text-slate-500">{{ $member->user->area }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-sm text-slate-400 hidden md:table-cell">
                                    {{ $member->user?->email ?? '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-sm text-slate-400 hidden lg:table-cell">
                                    {{ $member->user?->phone ?? '—' }}
                                </td>
                                <td class="px-4 py-3.5">
                                    @if($selectedPool->sectors && count($selectedPool->sectors) > 0)
                                    <select wire:change="updateSector({{ $member->id }}, $event.target.value)"
                                            class="text-xs rounded-lg bg-pitch-800 border-slate-700 text-slate-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 py-1">
                                        <option value="">— Sem setor</option>
                                        @foreach($selectedPool->sectors as $sector)
                                        <option value="{{ $sector }}" {{ $member->sector === $sector ? 'selected' : '' }}>{{ $sector }}</option>
                                        @endforeach
                                    </select>
                                    @else
                                    <span class="text-xs text-slate-600">{{ $member->sector ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="text-xs text-slate-400">{{ $roleLabel }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="{{ $statusBadge }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex flex-wrap gap-1.5">
                                        @if($member->status !== 'active')
                                        <button wire:click="activateMember({{ $member->id }})"
                                                class="inline-flex items-center rounded-md bg-emerald-700/30 px-2 py-1 text-xs font-medium text-emerald-400 ring-1 ring-emerald-500/30 hover:bg-emerald-700/50 transition-colors">
                                            Liberar
                                        </button>
                                        @endif
                                        @if($member->status === 'active')
                                        <button wire:click="deactivateMember({{ $member->id }})"
                                                class="inline-flex items-center rounded-md bg-amber-700/30 px-2 py-1 text-xs font-medium text-amber-400 ring-1 ring-amber-500/30 hover:bg-amber-700/50 transition-colors">
                                            Suspender
                                        </button>
                                        @endif
                                        @if($member->role !== 'owner')
                                            @if($member->role === 'member')
                                            <button wire:click="promoteManager({{ $member->id }})"
                                                    class="inline-flex items-center rounded-md bg-blue-700/30 px-2 py-1 text-xs font-medium text-blue-400 ring-1 ring-blue-500/30 hover:bg-blue-700/50 transition-colors">
                                                → Gestor
                                            </button>
                                            @else
                                            <button wire:click="demoteMember({{ $member->id }})"
                                                    class="inline-flex items-center rounded-md bg-slate-700/50 px-2 py-1 text-xs font-medium text-slate-400 ring-1 ring-slate-600/30 hover:bg-slate-600/50 transition-colors">
                                                → Membro
                                            </button>
                                            @endif
                                            <button wire:click="removeMember({{ $member->id }})"
                                                    onclick="confirm('Confirma remoção de {{ addslashes($member->user?->name ?? '') }}?') || event.stopImmediatePropagation()"
                                                    class="inline-flex items-center rounded-md bg-slate-700/30 px-2 py-1 text-xs font-medium text-slate-400 ring-1 ring-slate-600/30 hover:bg-slate-600/50 transition-colors">
                                                Remover
                                            </button>
                                            @if($member->status !== 'blocked')
                                            <button wire:click="blockMember({{ $member->id }})"
                                                    onclick="confirm('Confirma bloqueio de {{ addslashes($member->user?->name ?? '') }}?') || event.stopImmediatePropagation()"
                                                    class="inline-flex items-center rounded-md bg-red-700/30 px-2 py-1 text-xs font-medium text-red-400 ring-1 ring-red-500/30 hover:bg-red-700/50 transition-colors">
                                                Bloquear
                                            </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-800 text-xs text-slate-600">
                    {{ $members->count() }} participante{{ $members->count() !== 1 ? 's' : '' }} exibido{{ $members->count() !== 1 ? 's' : '' }}
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- ===== Mobile: painel de seleção de bolão ===== --}}
    <div x-show="mobilePanelOpen" x-cloak
         class="fixed inset-0 z-50 lg:hidden flex">
        <div class="absolute inset-0 bg-black/60" @click="mobilePanelOpen = false"></div>
        <aside class="relative w-72 flex flex-col bg-pitch-900 border-r border-slate-800 h-full overflow-y-auto">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800">
                <h2 class="text-sm font-bold text-white">Meus Bolões</h2>
                <button @click="mobilePanelOpen = false" class="text-slate-400 hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <nav class="p-2 space-y-1">
                @foreach($pools as $pool)
                <button wire:click="selectPool({{ $pool->id }}); $nextTick(() => { mobilePanelOpen = false })"
                        @click="mobilePanelOpen = false"
                        class="w-full text-left rounded-lg px-3 py-3 transition-colors
                               {{ $selectedPoolId === $pool->id ? 'bg-emerald-600/20 ring-1 ring-emerald-500/30' : 'hover:bg-slate-800' }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-medium {{ $selectedPoolId === $pool->id ? 'text-emerald-400' : 'text-slate-300' }}">
                            {{ $pool->name }}
                        </p>
                        @if(($pool->pending_count ?? 0) > 0)
                        <span class="shrink-0 flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-black px-1">
                            {{ $pool->pending_count }}
                        </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ $pool->active_count }} ativos · {{ $pool->total_members }} total</p>
                </button>
                @endforeach
            </nav>
        </aside>
    </div>

</div>
