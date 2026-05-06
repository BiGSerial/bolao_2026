<div class="p-4 sm:p-6 lg:p-8 animate-fade-in">

    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('pools.show', $pool->slug) }}"
           class="flex items-center justify-center h-9 w-9 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-slate-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-white">Participantes</h1>
            <p class="text-sm text-slate-400 mt-0.5">{{ $pool->name }}</p>
        </div>
    </div>

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
                            {{ $member->user?->phone ?? '—' }}
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
                            <div class="flex flex-wrap gap-1.5">
                                @if($member->status !== 'active')
                                <button wire:click="activateMember({{ $member->id }})"
                                        wire:loading.attr="disabled"
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
                                        onclick="confirm('Confirma remoção?') || event.stopImmediatePropagation()"
                                        class="inline-flex items-center rounded-md bg-slate-700/30 px-2 py-1 text-xs font-medium text-slate-400 ring-1 ring-slate-600/30 hover:bg-slate-600/50 transition-colors">
                                    Remover
                                </button>
                                @if($member->status !== 'blocked')
                                <button wire:click="blockMember({{ $member->id }})"
                                        onclick="confirm('Confirma bloqueio?') || event.stopImmediatePropagation()"
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
    </div>
    @endif
</div>
