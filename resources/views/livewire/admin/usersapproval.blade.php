<div class="p-4 sm:p-6 lg:p-8 space-y-6 animate-fade-in">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-white">Usuários do Sistema</h1>
        <p class="text-sm text-slate-400 mt-1">Gerencie aprovações e permissões de todos os usuários.</p>
    </div>

    @if(session('status'))
    <div class="alert-success">{{ session('status') }}</div>
    @endif

    {{-- Contadores por status --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach([
            ['pending',   'Aguardando', 'text-amber-400',  'border-amber-800/30 bg-amber-900/10'],
            ['active',    'Ativos',     'text-emerald-400','border-emerald-800/30 bg-emerald-900/10'],
            ['suspended', 'Suspensos',  'text-slate-400',  'border-slate-700/30 bg-slate-800/20'],
            ['rejected',  'Rejeitados', 'text-red-400',    'border-red-800/30 bg-red-900/10'],
            ['banned',    'Banidos',    'text-rose-400',   'border-rose-800/30 bg-rose-900/10'],
        ] as [$status, $label, $color, $bg])
        <button wire:click="$set('filterStatus', '{{ $filterStatus === $status ? '' : $status }}')"
                class="card border p-4 text-left transition-all
                       {{ $filterStatus === $status ? $bg : 'hover:border-slate-600' }}">
            <div class="text-2xl font-black {{ $color }}">{{ $counts[$status] ?? 0 }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ $label }}</div>
        </button>
        @endforeach
    </div>

    {{-- Filtros --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-48">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Buscar por nome ou e-mail..."
                   class="input-field pl-9 py-2 text-sm">
        </div>
        @if($filterStatus)
        <button wire:click="$set('filterStatus', '')"
                class="btn-ghost btn-sm border border-slate-700">
            Limpar filtro ✕
        </button>
        @endif
    </div>

    {{-- Tabela --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-slate-800">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Usuário</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden md:table-cell">E-mail</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden lg:table-cell">Apelido</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase hidden lg:table-cell">Cadastro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Admin</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($users as $user)
                    @php
                        $isSelf = $user->id === Auth::id();
                        $statusBadge = match($user->status) {
                            'active'    => 'badge-green',
                            'pending'   => 'badge-amber',
                            'suspended' => 'badge-slate',
                            'rejected'  => 'badge-red',
                            'banned'    => 'badge-red',
                            default     => 'badge-slate',
                        };
                        $statusLabel = match($user->status) {
                            'active'    => 'Ativo',
                            'pending'   => 'Aguardando',
                            'suspended' => 'Suspenso',
                            'rejected'  => 'Rejeitado',
                            'banned'    => 'Banido',
                            default     => ucfirst($user->status),
                        };
                    @endphp
                    <tr class="hover:bg-slate-800/20 transition-colors {{ $isSelf ? 'opacity-60' : '' }}"
                        wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white uppercase
                                            {{ $user->is_admin ? 'bg-amber-700' : 'bg-slate-700' }}">
                                    {{ mb_substr($user->name, 0, 2) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-200">
                                        {{ $user->name }}
                                        @if($isSelf)
                                        <span class="text-xs text-slate-500">(você)</span>
                                        @endif
                                    </p>
                                    @if($user->area)
                                    <p class="text-xs text-slate-500">{{ $user->area }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-sm text-slate-400 hidden md:table-cell">{{ $user->email }}</td>
                        <td class="px-4 py-3.5 text-sm text-slate-400 hidden lg:table-cell">{{ $user->display_name ?? '—' }}</td>
                        <td class="px-4 py-3.5 text-xs text-slate-500 hidden lg:table-cell">
                            {{ $user->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="{{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-4 py-3.5">
                            @if($isSelf)
                            <span class="badge-amber">Admin</span>
                            @else
                            <button wire:click="toggleAdmin({{ $user->id }})"
                                    class="text-xs font-medium transition-colors
                                           {{ $user->is_admin ? 'text-amber-400 hover:text-amber-300' : 'text-slate-600 hover:text-slate-300' }}">
                                {{ $user->is_admin ? '★ Admin' : '☆ Não' }}
                            </button>
                            @endif
                        </td>
                        <td class="px-4 py-3.5">
                            @if(! $isSelf)
                            <div class="flex flex-wrap gap-1.5">
                                @if($user->status !== 'active')
                                <button wire:click="approve({{ $user->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="approve({{ $user->id }})"
                                        class="inline-flex items-center rounded-md bg-emerald-700/30 px-2 py-1 text-xs font-medium text-emerald-400 ring-1 ring-emerald-500/30 hover:bg-emerald-700/50 transition-colors disabled:opacity-60">
                                    <span wire:loading.remove wire:target="approve({{ $user->id }})">Aprovar</span>
                                    <span wire:loading wire:target="approve({{ $user->id }})">Aprovando...</span>
                                </button>
                                @endif
                                @if($user->status === 'active')
                                <button wire:click="suspend({{ $user->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="suspend({{ $user->id }})"
                                        class="inline-flex items-center rounded-md bg-amber-700/30 px-2 py-1 text-xs font-medium text-amber-400 ring-1 ring-amber-500/30 hover:bg-amber-700/50 transition-colors disabled:opacity-60">
                                    <span wire:loading.remove wire:target="suspend({{ $user->id }})">Suspender</span>
                                    <span wire:loading wire:target="suspend({{ $user->id }})">Suspendendo...</span>
                                </button>
                                @endif
                                @if($user->status !== 'banned')
                                <button wire:click="ban({{ $user->id }})"
                                        wire:confirm="Banir {{ addslashes($user->name) }} da plataforma?"
                                        wire:loading.attr="disabled"
                                        wire:target="ban({{ $user->id }})"
                                        class="inline-flex items-center rounded-md bg-rose-700/30 px-2 py-1 text-xs font-medium text-rose-300 ring-1 ring-rose-500/30 hover:bg-rose-700/50 transition-colors disabled:opacity-60">
                                    <span wire:loading.remove wire:target="ban({{ $user->id }})">Banir</span>
                                    <span wire:loading wire:target="ban({{ $user->id }})">Banindo...</span>
                                </button>
                                @endif
                                @if(! in_array($user->status, ['rejected']))
                                <button wire:click="reject({{ $user->id }})"
                                        wire:confirm="Rejeitar {{ addslashes($user->name) }}?"
                                        wire:loading.attr="disabled"
                                        wire:target="reject({{ $user->id }})"
                                        class="inline-flex items-center rounded-md bg-red-700/30 px-2 py-1 text-xs font-medium text-red-400 ring-1 ring-red-500/30 hover:bg-red-700/50 transition-colors disabled:opacity-60">
                                    <span wire:loading.remove wire:target="reject({{ $user->id }})">Rejeitar</span>
                                    <span wire:loading wire:target="reject({{ $user->id }})">Rejeitando...</span>
                                </button>
                                @endif
                                <button wire:click="resendTemporaryPassword({{ $user->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="resendTemporaryPassword({{ $user->id }})"
                                        class="inline-flex items-center rounded-md bg-blue-700/30 px-2 py-1 text-xs font-medium text-blue-300 ring-1 ring-blue-500/30 hover:bg-blue-700/50 transition-colors disabled:opacity-60">
                                    <span wire:loading.remove wire:target="resendTemporaryPassword({{ $user->id }})">Reenviar senha</span>
                                    <span wire:loading wire:target="resendTemporaryPassword({{ $user->id }})">Enviando...</span>
                                </button>
                                <button wire:click="destroyUserData({{ $user->id }})"
                                        wire:confirm="Excluir todos os dados de {{ addslashes($user->name) }}? Esta ação é irreversível."
                                        wire:loading.attr="disabled"
                                        wire:target="destroyUserData({{ $user->id }})"
                                        class="inline-flex items-center rounded-md bg-red-800/40 px-2 py-1 text-xs font-medium text-red-300 ring-1 ring-red-600/40 hover:bg-red-800/60 transition-colors disabled:opacity-60">
                                    <span wire:loading.remove wire:target="destroyUserData({{ $user->id }})">Remover dados</span>
                                    <span wire:loading wire:target="destroyUserData({{ $user->id }})">Removendo...</span>
                                </button>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">
                            Nenhum usuário encontrado.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="px-4 py-3 border-t border-slate-800">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>

@script
<script>
    $wire.on('swal:alert', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        Swal.fire({
            icon: data?.icon ?? 'info',
            title: data?.title ?? 'Aviso',
            text: data?.text ?? '',
            confirmButtonText: 'Entendi'
        });
    });
</script>
@endscript
