<div class="flex h-full min-h-screen animate-fade-in"
    x-data="{
        managementMobileMenu: false,
        memberActionMenuOpen: false,
        desktopMemberMenuOpen: false,
        desktopMenuPosition: { top: 0, left: 0, width: 360 },
        selectedMember: null,
        openMemberActionMenu(member) {
            this.selectedMember = member;
            this.memberActionMenuOpen = true;
        },
        closeMemberActionMenu() {
            this.memberActionMenuOpen = false;
            this.selectedMember = null;
        },
        openDesktopMemberMenu(member, event) {
            this.selectedMember = member;
            const rect = event.currentTarget.getBoundingClientRect();
            const menuWidth = 360;
            const estimatedMenuHeight = 340;
            const viewportPadding = 12;
            const gap = 8;

            let left = rect.right - menuWidth;
            left = Math.max(viewportPadding, Math.min(left, window.innerWidth - menuWidth - viewportPadding));

            let top = rect.bottom + gap;
            if (top + estimatedMenuHeight > window.innerHeight - viewportPadding) {
                top = Math.max(viewportPadding, rect.top - estimatedMenuHeight - gap);
            }

            this.desktopMenuPosition = { top, left, width: menuWidth };
            this.desktopMemberMenuOpen = true;
        },
        closeDesktopMemberMenu() {
            this.desktopMemberMenuOpen = false;
            this.selectedMember = null;
        }
    }"
    @open-management-mobile-menu.window="managementMobileMenu = true"
    @toggle-management-mobile-menu.window="managementMobileMenu = !managementMobileMenu">

    {{-- ===== Conteúdo principal ===== --}}
    <div class="flex-1 flex flex-col min-w-0">

        @if(! $selectedPool)
        <div class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <div class="text-5xl mb-4">🏆</div>
                <p class="text-bolao-muted">Selecione um bolão para gerenciar</p>
            </div>
        </div>
        @else

        {{-- Header do bolão selecionado --}}
        <div class="sticky top-0 z-10 border-b border-white/[0.07] bg-bolao-bg2/95 backdrop-blur px-4 sm:px-6 py-4">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-white truncate">{{ $selectedPool->name }}</h2>
                        <p class="text-xs text-slate-400 flex items-center gap-2 flex-wrap">
                            <span>Código: <span class="font-mono text-bolao-accent">{{ $selectedPool->invite_code }}</span></span>
                            <span class="text-bolao-muted2">·</span>
                            <span>{{ strtoupper((string) ($selectedPool->competition?->code ?? '—')) }} {{ $selectedPool->season?->year ?? '—' }}</span>
                            <span class="text-bolao-muted2">·</span>
                            <span>{{ match($selectedPool->visibility) { 'public' => '🌐 Público', 'invite_only' => '🔗 Convite', default => '🔒 Privado' } }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex w-full items-center gap-2 sm:w-auto sm:shrink-0 flex-wrap">
                    <a href="{{ route('pools.show', $selectedPool->slug) }}"
                       class="btn-ghost btn-sm flex-1 sm:flex-none justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Ver bolão
                    </a>
                    <a href="{{ route('pools.settings', $selectedPool->slug) }}"
                       class="btn-secondary btn-sm flex-1 sm:flex-none justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Configurações
                    </a>
                </div>
            </div>

            {{-- Stats rápidos --}}
            <div class="mt-3 flex items-center gap-2 overflow-x-auto no-scrollbar md:flex-wrap">
                @foreach([
                    ['all', 'Todos', $statusCounts->sum(), 'text-slate-300'],
                    ['active', 'Ativos', $statusCounts->get('active', 0), 'text-emerald-400'],
                    ['pending', 'Aguardando', $statusCounts->get('pending', 0), 'text-amber-400'],
                    ['inactive', 'Suspensos', $statusCounts->get('inactive', 0), 'text-slate-500'],
                    ['blocked', 'Bloqueados', $statusCounts->get('blocked', 0), 'text-red-400'],
                ] as [$status, $label, $count, $color])
                @if($count > 0 || $status === 'all')
                <button wire:click="$set('filterStatus', '{{ $filterStatus === $status || ($status === 'all' && $filterStatus === '') ? '' : $status }}')"
                    class="shrink-0 whitespace-nowrap flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors
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
        <div class="px-4 sm:px-6 py-3 border-b border-white/[0.07]">
            <div class="relative w-full md:max-w-sm">
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
            @php
                $pendingMembers = $members->where('status', 'pending')->values();
            @endphp
            @if($pendingMembers->isNotEmpty())
            <div class="card mb-4 overflow-hidden border-amber-500/30">
                <div class="px-4 py-3 border-b border-amber-500/20 bg-amber-500/5 flex items-center justify-between">
                    <p class="text-sm font-semibold text-amber-300">Pendentes de aprovação</p>
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-400 text-[10px] font-bold text-black px-1">
                        {{ $pendingMembers->count() }}
                    </span>
                </div>
                <div class="divide-y divide-white/[0.06]">
                    @foreach($pendingMembers as $pendingMember)
                    <div class="px-4 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-200 truncate">{{ $pendingMember->user?->name ?? '—' }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ $pendingMember->user?->email ?? '—' }}</p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button wire:click="activateMember({{ $pendingMember->id }})"
                                    class="inline-flex items-center rounded-md bg-emerald-700/30 px-2 py-1 text-xs font-medium text-emerald-400 ring-1 ring-emerald-500/30 hover:bg-emerald-700/50 transition-colors">
                                Aprovar
                            </button>
                            <button wire:click="blockMember({{ $pendingMember->id }})"
                                    onclick="confirm('Confirma bloqueio de {{ addslashes($pendingMember->user?->name ?? '') }}?') || event.stopImmediatePropagation()"
                                    class="inline-flex items-center rounded-md bg-red-700/30 px-2 py-1 text-xs font-medium text-red-400 ring-1 ring-red-500/30 hover:bg-red-700/50 transition-colors">
                                Bloquear
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            @php
                $canTransferOwnership = $selectedPool->members()
                    ->where('user_id', auth()->id())
                    ->where('role', 'owner')
                    ->exists();
            @endphp
            <div class="card overflow-hidden">
                <div class="md:hidden divide-y divide-white/[0.06]">
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
                            'owner'   => 'Dono',
                            'manager' => 'Gestor',
                            default   => 'Membro',
                        };
                    @endphp
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-100 truncate">{{ $member->user?->name ?? '—' }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $member->user?->email ?? '—' }}</p>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="{{ $statusBadge }}">{{ $statusLabel }}</span>
                                <button type="button"
                                        @click="openMemberActionMenu({
                                            id: {{ $member->id }},
                                            name: @js($member->user?->name ?? '—'),
                                            email: @js($member->user?->email ?? '—'),
                                            role: @js($member->role),
                                            status: @js($member->status),
                                            canViewPredictions: @js($member->status === 'active'),
                                            canTransfer: @js($canTransferOwnership && $member->role !== 'owner'),
                                            canRemove: @js($member->role !== 'owner'),
                                            showUrl: @js($member->status === 'active'
                                                ? route('pools.show', ['pool' => $selectedPool->slug, 'tab' => 'jogos', 'member' => $member->user_id, 'competition' => request()->query('competition', session('competition'))])
                                                : null)
                                        })"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-white/[0.08] bg-bolao-bg3/40 text-slate-400 hover:text-slate-200 hover:bg-bolao-bg3">
                                    <i class="ti ti-dots-vertical text-base"></i>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-md bg-bolao-bg3/40 px-2 py-1.5">
                                <p class="text-[10px] uppercase tracking-wide text-slate-500">Papel</p>
                                <p class="text-slate-300">{{ $roleLabel }}</p>
                            </div>
                            <div class="rounded-md bg-bolao-bg3/40 px-2 py-1.5">
                                <p class="text-[10px] uppercase tracking-wide text-slate-500">Setor</p>
                                @if($selectedPool->sectors && count($selectedPool->sectors) > 0)
                                <select wire:change="updateSector({{ $member->id }}, $event.target.value)"
                                        class="mt-1 w-full text-xs rounded-md bg-pitch-800 border-slate-700 text-slate-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 py-1">
                                    <option value="">— Sem setor</option>
                                    @foreach($selectedPool->sectors as $sector)
                                    <option value="{{ $sector }}" {{ $member->sector === $sector ? 'selected' : '' }}>{{ $sector }}</option>
                                    @endforeach
                                </select>
                                @else
                                <p class="text-slate-400">{{ $member->sector ?? '—' }}</p>
                                @endif
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-500">Toque nos 3 pontos para gerenciar este participante</p>
                    </div>
                    @endforeach
                </div>

                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-white/[0.07]">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Participante</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden md:table-cell">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden lg:table-cell">Apelido</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Setor</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Papel</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.06]">
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
                            <tr class="hover:bg-bolao-bg3/30 transition-colors" wire:key="mgr-{{ $member->id }}">
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
                                    {{ $member->user?->display_name ?? '—' }}
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
                                    <span class="text-xs text-bolao-muted">{{ $roleLabel }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="{{ $statusBadge }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <button type="button"
                                            @click="openDesktopMemberMenu({
                                                id: {{ $member->id }},
                                                name: @js($member->user?->name ?? '—'),
                                                email: @js($member->user?->email ?? '—'),
                                                role: @js($member->role),
                                                status: @js($member->status),
                                                canViewPredictions: @js($member->status === 'active'),
                                                canTransfer: @js($canTransferOwnership && $member->role !== 'owner'),
                                                canRemove: @js($member->role !== 'owner'),
                                                showUrl: @js($member->status === 'active'
                                                    ? route('pools.show', ['pool' => $selectedPool->slug, 'tab' => 'jogos', 'member' => $member->user_id, 'competition' => request()->query('competition', session('competition'))])
                                                    : null)
                                            }, $event)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-800/60 text-slate-400 hover:text-slate-200 hover:bg-slate-700/60">
                                        <i class="ti ti-dots-vertical text-base"></i>
                                    </button>
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

    <div x-cloak class="fixed inset-0 z-[85] pointer-events-none md:hidden">
        <div x-show="memberActionMenuOpen"
             x-transition:enter="transition ease-out duration-180" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black/70 pointer-events-auto"
             @click="closeMemberActionMenu()"></div>

        <div x-show="memberActionMenuOpen"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-10"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-10"
             class="absolute inset-x-3 bottom-[calc(68px+env(safe-area-inset-bottom,0px)+10px)] rounded-2xl border border-white/10 bg-bolao-bg2 shadow-2xl pointer-events-auto overflow-hidden md:hidden">

            <div class="border-b border-white/[0.07] px-4 py-3">
                <p class="text-lg font-semibold text-slate-100">Gerenciar membro</p>
                <p class="text-sm text-slate-500">Escolha uma ação para este participante</p>

                <div class="mt-3 flex items-center gap-3 rounded-xl bg-bolao-bg px-3 py-2.5">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-700 text-sm font-bold uppercase text-slate-300"
                         x-text="(selectedMember?.name || '?').split(' ').map(w => w[0]).join('').slice(0,2)"></div>
                    <div class="min-w-0">
                        <p class="truncate text-xl font-semibold leading-tight text-slate-100" x-text="selectedMember?.name || 'Participante'"></p>
                        <p class="truncate text-sm text-slate-500" x-text="selectedMember?.email || ''"></p>
                    </div>
                </div>
            </div>

            <div class="p-2.5 space-y-1.5">
                <button
                   :disabled="!(selectedMember && selectedMember.canViewPredictions && selectedMember.showUrl)"
                   @click="if (selectedMember && selectedMember.canViewPredictions && selectedMember.showUrl) { window.location.href = selectedMember.showUrl; closeMemberActionMenu(); }"
                   :class="selectedMember && selectedMember.canViewPredictions && selectedMember.showUrl ? 'hover:bg-slate-700/35' : 'opacity-50 cursor-not-allowed'"
                   class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-800/70 text-slate-400">
                        <i class="ti ti-trophy text-lg"></i>
                    </span>
                    <span class="flex-1">
                        <span class="block text-xl font-semibold leading-tight text-slate-100">Ver palpites</span>
                        <span x-show="selectedMember && selectedMember.canViewPredictions" class="block text-sm text-slate-500">Visualizar todos os palpites deste membro</span>
                        <span x-show="selectedMember && !selectedMember.canViewPredictions" class="block text-sm text-slate-500">Disponível apenas para membro ativo no bolão</span>
                    </span>
                    <i class="ti ti-chevron-right text-base text-slate-600"></i>
                </button>

                <button
                        :disabled="!(selectedMember && selectedMember.canTransfer)"
                        @click="if (selectedMember && selectedMember.canTransfer && confirm('Confirmar transferencia de propriedade?')) { $wire.transferOwnership(selectedMember.id); closeMemberActionMenu(); }"
                        :class="selectedMember && selectedMember.canTransfer ? 'hover:bg-amber-500/10' : 'opacity-50 cursor-not-allowed'"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/20 text-amber-300">
                        <i class="ti ti-crown text-lg"></i>
                    </span>
                    <span class="flex-1">
                        <span class="block text-xl font-semibold leading-tight text-amber-300">Transferir propriedade</span>
                        <span x-show="selectedMember && selectedMember.canTransfer" class="block text-sm text-slate-500">Este membro passa a ser o dono</span>
                        <span x-show="selectedMember && !selectedMember.canTransfer" class="block text-sm text-slate-500">Indisponível para o dono atual</span>
                    </span>
                    <i class="ti ti-chevron-right text-base text-slate-600"></i>
                </button>

                <button
                        :disabled="!(selectedMember && selectedMember.canRemove)"
                        @click="if (selectedMember && selectedMember.canRemove && confirm('Confirma remoção?')) { $wire.removeMember(selectedMember.id); closeMemberActionMenu(); }"
                        :class="selectedMember && selectedMember.canRemove ? 'hover:bg-red-500/10' : 'opacity-50 cursor-not-allowed'"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-red-600/20 text-red-400">
                        <i class="ti ti-user-minus text-lg"></i>
                    </span>
                    <span class="flex-1">
                        <span class="block text-xl font-semibold leading-tight text-red-400">Remover do bolão</span>
                        <span x-show="selectedMember && selectedMember.canRemove" class="block text-sm text-slate-500">O membro perde acesso e palpites</span>
                        <span x-show="selectedMember && !selectedMember.canRemove" class="block text-sm text-slate-500">Não é possível remover o dono</span>
                    </span>
                    <i class="ti ti-chevron-right text-base text-slate-600"></i>
                </button>
            </div>
        </div>
    </div>

    <div x-cloak class="fixed inset-0 z-[84] pointer-events-none hidden md:block" @keydown.escape.window="closeDesktopMemberMenu()">
        <div x-show="desktopMemberMenuOpen"
             x-transition:enter="transition ease-out duration-120" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 pointer-events-auto"
             @click="closeDesktopMemberMenu()"></div>

        <div x-show="desktopMemberMenuOpen"
             x-transition:enter="transition ease-out duration-140" x-transition:enter-start="opacity-0 -translate-y-1 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
             :style="`top:${desktopMenuPosition.top}px; left:${desktopMenuPosition.left}px; width:${desktopMenuPosition.width}px;`"
             class="fixed z-[85] overflow-hidden rounded-2xl border border-white/10 bg-bolao-bg2 shadow-2xl pointer-events-auto"
             @click.stop>
            <div class="border-b border-white/[0.07] px-4 py-3">
                <p class="text-sm font-semibold text-slate-200">Gerenciar membro</p>

                <div class="mt-2.5 flex items-center gap-3 rounded-xl bg-bolao-bg px-3 py-2.5">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-700 text-xs font-bold uppercase text-slate-300"
                         x-text="(selectedMember && selectedMember.name ? selectedMember.name : '?').split(' ').map(w => w[0]).join('').slice(0,2)"></div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold leading-tight text-slate-100" x-text="selectedMember && selectedMember.name ? selectedMember.name : 'Participante'"></p>
                        <p class="truncate text-xs text-slate-500" x-text="selectedMember && selectedMember.email ? selectedMember.email : ''"></p>
                    </div>
                </div>
            </div>

            <div class="p-2 space-y-1">
                <button
                   :disabled="!(selectedMember && selectedMember.canViewPredictions && selectedMember.showUrl)"
                   @click="if (selectedMember && selectedMember.canViewPredictions && selectedMember.showUrl) { window.location.href = selectedMember.showUrl; closeDesktopMemberMenu(); }"
                   :class="selectedMember && selectedMember.canViewPredictions && selectedMember.showUrl ? 'hover:bg-slate-700/35' : 'opacity-50 cursor-not-allowed'"
                   class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-800/70 text-slate-400">
                        <i class="ti ti-trophy text-base"></i>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block truncate text-sm font-semibold leading-tight text-slate-100">Ver palpites</span>
                        <span x-show="selectedMember && selectedMember.canViewPredictions" class="block truncate text-xs text-slate-500">Visualizar palpites deste membro</span>
                        <span x-show="selectedMember && !selectedMember.canViewPredictions" class="block truncate text-xs text-slate-500">Disponível apenas para membro ativo no bolão</span>
                    </span>
                    <i class="ti ti-chevron-right text-sm text-slate-600"></i>
                </button>

                <button
                        :disabled="!(selectedMember && selectedMember.canTransfer)"
                        @click="if (selectedMember && selectedMember.canTransfer && confirm('Confirmar transferencia de propriedade?')) { $wire.transferOwnership(selectedMember.id); closeDesktopMemberMenu(); }"
                        :class="selectedMember && selectedMember.canTransfer ? 'hover:bg-amber-500/10' : 'opacity-50 cursor-not-allowed'"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/20 text-amber-300">
                        <i class="ti ti-crown text-base"></i>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block truncate text-sm font-semibold leading-tight text-amber-300">Transferir propriedade</span>
                        <span x-show="selectedMember && selectedMember.canTransfer" class="block truncate text-xs text-slate-500">Este membro passa a ser o dono</span>
                        <span x-show="selectedMember && !selectedMember.canTransfer" class="block truncate text-xs text-slate-500">Indisponível para o dono atual</span>
                    </span>
                    <i class="ti ti-chevron-right text-sm text-slate-600"></i>
                </button>

                <button
                        :disabled="!(selectedMember && selectedMember.canRemove)"
                        @click="if (selectedMember && selectedMember.canRemove && confirm('Confirma remoção?')) { $wire.removeMember(selectedMember.id); closeDesktopMemberMenu(); }"
                        :class="selectedMember && selectedMember.canRemove ? 'hover:bg-red-500/10' : 'opacity-50 cursor-not-allowed'"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-600/20 text-red-400">
                        <i class="ti ti-user-minus text-base"></i>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block truncate text-sm font-semibold leading-tight text-red-400">Remover do bolão</span>
                        <span x-show="selectedMember && selectedMember.canRemove" class="block truncate text-xs text-slate-500">O membro perde acesso e palpites</span>
                        <span x-show="selectedMember && !selectedMember.canRemove" class="block truncate text-xs text-slate-500">Não é possível remover o dono</span>
                    </span>
                    <i class="ti ti-chevron-right text-sm text-slate-600"></i>
                </button>
            </div>
        </div>
    </div>

        <div x-cloak class="fixed inset-0 z-[70] pointer-events-none md:hidden">
           <div x-show="managementMobileMenu"
               x-transition:enter="transition ease-out duration-180" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
               x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
               class="absolute inset-0 bg-black/70 pointer-events-auto"
               @click="managementMobileMenu = false"></div>

           <div x-show="managementMobileMenu"
               class="absolute inset-x-0 top-0 bottom-[calc(68px+env(safe-area-inset-bottom,0px))] overflow-hidden border-y border-white/10 bg-bolao-bg2 shadow-2xl pointer-events-auto flex flex-col"
               x-transition:enter="transition ease-out duration-320"
               x-transition:enter-start="opacity-0 translate-y-20"
               x-transition:enter-end="opacity-100 translate-y-0"
               x-transition:leave="transition ease-in duration-240"
             x-transition:leave-start="opacity-100 translate-y-0"
               x-transition:leave-end="opacity-0 translate-y-20">
            <div class="flex items-center justify-between border-b border-white/[0.07] px-4 py-3">
                <div>
                    <p class="text-sm font-semibold text-slate-200">Selecionar bolao</p>
                    <p class="text-xs text-bolao-muted">Troque rapidamente entre suas gestoes</p>
                </div>
                <button type="button" @click="managementMobileMenu = false"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-bolao-muted hover:bg-bolao-bg3 hover:text-slate-200">
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-4">
                @php
                    $isSystemAdmin = (bool) (auth()->user()?->is_admin ?? false);
                @endphp

                <section class="space-y-2">
                    <p class="px-1 text-[10px] font-bold uppercase tracking-widest text-bolao-muted2">Gestao Bolao</p>
                    @forelse($groupedPools as $groupKey => $groupPools)
                    @php
                        [$compCode, $compName, $compSeason] = array_pad(explode('|', (string) $groupKey), 3, '—');
                        $hasSelectedInGroup = $groupPools->contains(fn ($pool) => $selectedPoolId === $pool->id);
                    @endphp
                    <div class="rounded-xl border border-white/[0.08] bg-bolao-bg"
                         x-data="{ open: {{ $hasSelectedInGroup ? 'true' : 'false' }} }">
                        <button type="button"
                                @click="open = !open"
                                class="flex w-full items-center gap-2 px-3 py-2.5 text-left">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-bolao-accent truncate">{{ $compCode }} {{ $compSeason }}</p>
                                <p class="text-[11px] text-bolao-muted truncate">{{ $compName }}</p>
                            </div>
                            <i class="ti ti-chevron-down ml-auto text-[11px] text-slate-500 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                        </button>

                        <div x-show="open" x-collapse class="space-y-1 px-2 pb-2">
                            @foreach($groupPools as $pool)
                            @php
                                $isSelected = $selectedPoolId === $pool->id;
                            @endphp
                            <a href="{{ route('management.pools', ['competition' => $compCode, 'pool' => $pool->id]) }}"
                               @click="managementMobileMenu = false"
                               class="flex items-center gap-2 rounded-lg border px-3 py-2.5 transition-colors {{ $isSelected ? 'border-amber-400/80 bg-amber-500/10' : 'border-transparent bg-bolao-bg3/40 text-slate-300' }}">
                                <i class="ti ti-trophy text-sm {{ $isSelected ? 'text-amber-300' : 'text-slate-500' }}"></i>
                                <div class="min-w-0">
                                    <p class="truncate text-sm {{ $isSelected ? 'font-semibold text-amber-300' : 'text-slate-200' }}">{{ $pool->name }}</p>
                                    <p class="text-[11px] text-bolao-muted">{{ $pool->active_count }} ativos · {{ $pool->total_members }} total</p>
                                </div>
                                @if((int) ($pool->pending_count ?? 0) > 0)
                                <span class="ml-auto inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-400 px-1 text-[10px] font-bold text-black">
                                    {{ (int) $pool->pending_count }}
                                </span>
                                @endif
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @empty
                    <div class="rounded-xl border border-white/[0.08] bg-bolao-bg px-4 py-6 text-center">
                        <p class="text-sm text-slate-400">Nenhum bolao ativo para gerenciar.</p>
                    </div>
                    @endforelse
                </section>

                @if($isSystemAdmin)
                <section class="space-y-2">
                    <p class="px-1 text-[10px] font-bold uppercase tracking-widest text-bolao-muted2">Gestao Aplicativo</p>

                    <a href="{{ route('admin.users.approval') }}"
                       @click="managementMobileMenu = false"
                       class="flex items-center gap-2 rounded-xl border border-amber-400/20 bg-amber-500/10 px-3 py-2.5 text-sm font-medium text-amber-300 transition-colors hover:bg-amber-500/15">
                        <i class="ti ti-users text-base"></i>
                        <span>Usuarios</span>
                    </a>

                    <a href="{{ route('admin.pools.control') }}"
                       @click="managementMobileMenu = false"
                       class="flex items-center gap-2 rounded-xl border border-white/[0.08] bg-bolao-bg px-3 py-2.5 text-sm text-slate-300 transition-colors hover:bg-bolao-bg3/60 hover:text-slate-100">
                        <i class="ti ti-tournament text-base"></i>
                        <span>Grupos</span>
                    </a>

                    <a href="{{ route('admin.teams.canonical') }}"
                       @click="managementMobileMenu = false"
                       class="flex items-center gap-2 rounded-xl border border-white/[0.08] bg-bolao-bg px-3 py-2.5 text-sm text-slate-300 transition-colors hover:bg-bolao-bg3/60 hover:text-slate-100">
                        <i class="ti ti-shield text-base"></i>
                        <span>Times</span>
                    </a>

                    <a href="{{ route('admin.api.sync') }}"
                       @click="managementMobileMenu = false"
                       class="flex items-center gap-2 rounded-xl border border-white/[0.08] bg-bolao-bg px-3 py-2.5 text-sm text-slate-300 transition-colors hover:bg-bolao-bg3/60 hover:text-slate-100">
                        <i class="ti ti-refresh text-base"></i>
                        <span>Sync API</span>
                    </a>

                    <a href="{{ route('admin.matches.manual-correction') }}"
                       @click="managementMobileMenu = false"
                       class="flex items-center gap-2 rounded-xl border border-white/[0.08] bg-bolao-bg px-3 py-2.5 text-sm text-slate-300 transition-colors hover:bg-bolao-bg3/60 hover:text-slate-100">
                        <i class="ti ti-pencil text-base"></i>
                        <span>Correcao Manual</span>
                    </a>

                    <a href="{{ route('admin.legal.index') }}"
                       @click="managementMobileMenu = false"
                       class="flex items-center gap-2 rounded-xl border border-white/[0.08] bg-bolao-bg px-3 py-2.5 text-sm text-slate-300 transition-colors hover:bg-bolao-bg3/60 hover:text-slate-100">
                        <i class="ti ti-file-text text-base"></i>
                        <span>Juridico</span>
                    </a>
                </section>
                @endif
            </div>
        </div>
    </div>

</div>
