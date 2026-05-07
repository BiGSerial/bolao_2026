<x-app-layout>
<div class="p-4 sm:p-6 lg:p-8 max-w-2xl mx-auto space-y-5 animate-fade-in">

    {{-- Page header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('dashboard') }}"
           class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-400 hover:text-white transition-all group shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-white leading-tight">Meu Perfil</h1>
            <p class="text-sm text-slate-500 mt-0.5">Gerencie suas informações pessoais e segurança da conta</p>
        </div>
    </div>

    {{-- Avatar / identity card --}}
    <div class="card p-5">
        <div class="flex items-center gap-4">
            <div class="shrink-0 flex h-14 w-14 items-center justify-center rounded-2xl
                        bg-gradient-to-br from-emerald-500 to-emerald-700
                        text-xl font-black text-white uppercase
                        shadow-lg shadow-emerald-900/50 select-none">
                {{ mb_substr($user->name, 0, 2) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-lg font-bold text-white truncate">{{ $user->name }}</p>
                <p class="text-sm text-slate-400 truncate">{{ $user->email }}</p>
                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    @if($user->area)
                        <span class="badge-green">{{ $user->area }}</span>
                    @endif
                    @if($user->is_admin)
                        <span class="badge-amber">Administrador</span>
                    @endif
                    @if($user->email_verified_at)
                        <span class="badge badge-blue">E-mail verificado</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Sections --}}
    @include('profile.partials.update-profile-information-form')
    @include('profile.partials.update-password-form')
    @include('profile.partials.delete-user-form')

</div>
</x-app-layout>
