<div class="animate-fade-in"
     x-data="{
        mobileMemberMenuOpen: false,
        selectedMember: null,
        openMobileMemberMenu(member) {
            this.selectedMember = member;
            this.mobileMemberMenuOpen = true;
        },
        closeMobileMemberMenu() {
            this.mobileMemberMenuOpen = false;
            this.selectedMember = null;
        }
     }">
    @php
        $currentMember = $pool->members()->where('user_id', auth()->id())->first();
        $memberStatus = (string) ($currentMember->status ?? 'active');
        $memberRole = (string) ($currentMember->role ?? 'member');
        $myRanking = \App\Models\PoolRanking::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', auth()->id())
            ->first();
    @endphp

    @include('livewire.pools.partials.pool-header-nav', [
        'pool' => $pool,
        'activeItem' => 'participantes',
        'memberStatus' => $memberStatus,
        'memberRole' => $memberRole,
        'myRanking' => $myRanking,
    ])

<div class="p-4 sm:p-6 lg:p-8">

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        @foreach([
            ['active', 'Ativos', 'text-emerald-400', 'bg-emerald-900/20 border-emerald-800/30'],
            ['pending', 'Aguardando', 'text-amber-400', 'bg-amber-900/20 border-amber-800/30'],
            ['inactive', 'Suspensos', 'text-slate-400', 'bg-slate-800/40 border-slate-700/30'],
            ['blocked', 'Bloqueados', 'text-red-400', 'bg-red-900/20 border-red-800/30'],
        ] as [$status, $label, $textColor, $bgClass])
        <button wire:click="$set('filterStatus', '{{ $filterStatus === $status ? '' : $status }}')"
                class="card border p-3 text-left transition-colors
                       {{ $filterStatus === $status ? $bgClass : '' }}
                       hover:border-slate-600">
            <div class="text-xl font-black {{ $textColor }}">{{ $counts[$status] ?? 0 }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ $label }}</div>
        </button>
        @endforeach
    </div>

    <div class="card p-4 mb-6">
        <h2 class="text-sm font-semibold text-slate-300 mb-3">Convidar por e-mail</h2>
        <form wire:submit="sendInvite" class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <label class="label">E-mail do convidado</label>
                <input type="email" wire:model.blur="invite_email" placeholder="email@dominio.com" class="input-field">
                @error('invite_email') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">Setor (opcional)</label>
                @if($pool->sectors && count($pool->sectors) > 0)
                <select wire:model="invite_sector" class="select-field">
                    <option value="">Sem setor</option>
                    @foreach($pool->sectors as $sector)
                    <option value="{{ $sector }}">{{ $sector }}</option>
                    @endforeach
                </select>
                @else
                <input type="text" wire:model.blur="invite_sector" placeholder="Opcional" class="input-field">
                @endif
            </div>

            <div class="md:col-span-3">
                <button type="submit" class="btn-primary">
                    Enviar convite
                </button>
            </div>
        </form>
    </div>

    @if(session('status'))
    <div class="alert-success mb-4">{{ session('status') }}</div>
    @endif
    @if(session('error'))
    <div class="alert-error mb-4">{{ session('error') }}</div>
    @endif

    @if($filterStatus)
    <div class="flex items-center gap-2 mb-4">
        <span class="text-sm text-slate-400">Filtrando por: <strong class="text-white">{{ $filterStatus }}</strong></span>
        <button wire:click="$set('filterStatus', '')" class="text-xs text-slate-500 hover:text-slate-300 underline">limpar</button>
    </div>
    @endif

    {{-- Members list --}}
    @if($members->isEmpty())
    <div class="card p-12 text-center">
        <p class="text-slate-500">Nenhum participante encontrado.</p>
    </div>
    @else
    <div class="card overflow-hidden">
        <div class="md:hidden divide-y divide-slate-800/60">
            @foreach($members as $member)
            @php
            $statusBadge = match($member->status) {
                'active' => 'badge-green',
                'pending' => 'badge-amber',
                'inactive' => 'badge-slate',
                'blocked' => 'badge-red',
                default => 'badge-slate',
            };
            $statusLabel = match($member->status) {
                'active' => 'Ativo',
                'pending' => 'Aguardando',
                'inactive' => 'Suspenso',
                'removed' => 'Removido',
                'blocked' => 'Bloqueado',
                default => ucfirst($member->status),
            };
            $roleLabel = match($member->role) {
                'owner' => 'Dono',
                'manager' => 'Gestor',
                default => 'Membro',
            };
            @endphp
            <div class="p-3.5">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full
                                {{ $member->role === 'owner' ? 'bg-amber-700' : ($member->role === 'manager' ? 'bg-blue-700' : 'bg-slate-700') }}
                                text-sm font-bold text-white uppercase">
                        {{ mb_substr($member->user?->name ?? '?', 0, 2) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-base font-semibold text-slate-100">{{ $member->user?->name ?? '—' }}</p>
                        <p class="truncate text-sm text-slate-500">{{ $member->user?->email ?? '—' }}</p>
                    </div>

                    <button type="button"
                            @click="openMobileMemberMenu({
                                id: {{ $member->id }},
                                name: @js($member->user?->name ?? '—'),
                                email: @js($member->user?->email ?? '—'),
                                role: @js($member->role),
                                status: @js($member->status)
                            })"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-white/[0.08] bg-bolao-bg3/40 text-slate-400 hover:text-slate-200 hover:bg-bolao-bg3">
                        <i class="ti ti-dots-vertical text-lg"></i>
                    </button>
                </div>

                <div class="mt-2 flex items-center gap-2">
                    <span class="{{ $statusBadge }}">{{ $statusLabel }}</span>
                    <span class="inline-flex items-center rounded-md bg-slate-800/70 px-2 py-0.5 text-xs text-slate-300">{{ $roleLabel }}</span>
                    @if($member->sector)
                    <span class="inline-flex items-center rounded-md bg-slate-800/70 px-2 py-0.5 text-xs text-slate-400">{{ $member->sector }}</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-slate-800">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Participante</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden md:table-cell">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden lg:table-cell">Apelido</th>
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
                        'active' => 'badge-green',
                        'pending' => 'badge-amber',
                        'inactive' => 'badge-slate',
                        'blocked' => 'badge-red',
                        default => 'badge-slate',
                    };
                    $statusLabel = match($member->status) {
                        'active' => 'Ativo',
                        'pending' => 'Aguardando',
                        'inactive' => 'Suspenso',
                        'removed' => 'Removido',
                        'blocked' => 'Bloqueado',
                        default => ucfirst($member->status),
                    };
                    $roleLabel = match($member->role) {
                        'owner' => '👑 Dono',
                        'manager' => '🛡️ Gestor',
                        default => '🎯 Membro',
                    };
                    @endphp
                    <tr class="hover:bg-slate-800/20 transition-colors group" wire:key="member-{{ $member->id }}">
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                                            {{ $member->role === 'owner' ? 'bg-amber-700' : ($member->role === 'manager' ? 'bg-blue-700' : 'bg-slate-700') }}
                                            text-xs font-bold text-white uppercase">
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
                            @if($pool->sectors && count($pool->sectors) > 0)
                            <select wire:change="updateSector({{ $member->id }}, $event.target.value)"
                                    class="text-xs rounded-lg bg-pitch-800 border-slate-700 text-slate-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 py-1">
                                <option value="">— Sem setor</option>
                                @foreach($pool->sectors as $sector)
                                <option value="{{ $sector }}" {{ $member->sector === $sector ? 'selected' : '' }}>
                                    {{ $sector }}
                                </option>
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
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                <button type="button"
                                        @click="open = !open"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-800/60 text-slate-400 hover:text-slate-200 hover:bg-slate-700/60">
                                    <i class="ti ti-dots-vertical text-base"></i>
                                </button>

                                <div x-show="open" x-cloak
                                     x-transition:enter="transition ease-out duration-120"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     class="absolute right-0 z-20 mt-2 w-48 origin-top-right rounded-xl border border-slate-700 bg-slate-900 p-1.5 shadow-2xl">

                                    @if($member->status !== 'active')
                                    <button wire:click="activateMember({{ $member->id }})"
                                            @click="open = false"
                                            class="flex w-full items-center rounded-lg px-3 py-2 text-left text-xs font-medium text-emerald-300 hover:bg-emerald-500/10">
                                        Liberar
                                    </button>
                                    @endif

                                    @if($member->status === 'active')
                                    <button wire:click="deactivateMember({{ $member->id }})"
                                            @click="open = false"
                                            class="flex w-full items-center rounded-lg px-3 py-2 text-left text-xs font-medium text-amber-300 hover:bg-amber-500/10">
                                        Suspender
                                    </button>
                                    @endif

                                    @if($member->role !== 'owner')
                                        @if($member->role === 'member')
                                        <button wire:click="promoteManager({{ $member->id }})"
                                                @click="open = false"
                                                class="flex w-full items-center rounded-lg px-3 py-2 text-left text-xs font-medium text-blue-300 hover:bg-blue-500/10">
                                            Tornar gestor
                                        </button>
                                        @else
                                        <button wire:click="demoteMember({{ $member->id }})"
                                                @click="open = false"
                                                class="flex w-full items-center rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-300 hover:bg-slate-700/60">
                                            Tornar membro
                                        </button>
                                        @endif

                                        <button wire:click="removeMember({{ $member->id }})"
                                                onclick="confirm('Confirma remoção?') || event.stopImmediatePropagation()"
                                                @click="open = false"
                                                class="flex w-full items-center rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-300 hover:bg-slate-700/60">
                                            Remover
                                        </button>

                                        @if($member->status !== 'blocked')
                                        <button wire:click="blockMember({{ $member->id }})"
                                                onclick="confirm('Confirma bloqueio?') || event.stopImmediatePropagation()"
                                                @click="open = false"
                                                class="flex w-full items-center rounded-lg px-3 py-2 text-left text-xs font-medium text-red-300 hover:bg-red-500/10">
                                            Bloquear
                                        </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div x-cloak class="fixed inset-0 z-[80] pointer-events-none md:hidden">
        <div x-show="mobileMemberMenuOpen"
             x-transition:enter="transition ease-out duration-180" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black/70 pointer-events-auto"
             @click="closeMobileMemberMenu()"></div>

        <div x-show="mobileMemberMenuOpen"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-10"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-10"
             class="absolute inset-x-3 bottom-[calc(68px+env(safe-area-inset-bottom,0px)+10px)] rounded-2xl border border-white/10 bg-bolao-bg2 shadow-2xl pointer-events-auto overflow-hidden">

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
                <button x-show="selectedMember && selectedMember.status !== 'active'"
                        @click="$wire.activateMember(selectedMember.id); closeMobileMemberMenu()"
                        class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-base text-emerald-300 hover:bg-emerald-500/10">
                    <span>Liberar membro</span>
                    <i class="ti ti-chevron-right text-sm text-slate-500"></i>
                </button>

                <button x-show="selectedMember && selectedMember.status === 'active'"
                        @click="$wire.deactivateMember(selectedMember.id); closeMobileMemberMenu()"
                        class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-base text-amber-300 hover:bg-amber-500/10">
                    <span>Suspender membro</span>
                    <i class="ti ti-chevron-right text-sm text-slate-500"></i>
                </button>

                <button x-show="selectedMember && selectedMember.role === 'member'"
                        @click="$wire.promoteManager(selectedMember.id); closeMobileMemberMenu()"
                        class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-base text-blue-300 hover:bg-blue-500/10">
                    <span>Tornar gestor</span>
                    <i class="ti ti-chevron-right text-sm text-slate-500"></i>
                </button>

                <button x-show="selectedMember && selectedMember.role === 'manager'"
                        @click="$wire.demoteMember(selectedMember.id); closeMobileMemberMenu()"
                        class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-base text-slate-300 hover:bg-slate-700/60">
                    <span>Tornar membro</span>
                    <i class="ti ti-chevron-right text-sm text-slate-500"></i>
                </button>

                <button x-show="selectedMember && selectedMember.role !== 'owner'"
                        @click="if (confirm('Confirma remoção?')) { $wire.removeMember(selectedMember.id); closeMobileMemberMenu(); }"
                        class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-base text-slate-300 hover:bg-slate-700/60">
                    <span>Remover do bolão</span>
                    <i class="ti ti-chevron-right text-sm text-slate-500"></i>
                </button>

                <button x-show="selectedMember && selectedMember.role !== 'owner' && selectedMember.status !== 'blocked'"
                        @click="if (confirm('Confirma bloqueio?')) { $wire.blockMember(selectedMember.id); closeMobileMemberMenu(); }"
                        class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-base text-red-400 hover:bg-red-500/10">
                    <span>Bloquear membro</span>
                    <i class="ti ti-chevron-right text-sm text-slate-500"></i>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
</div>
