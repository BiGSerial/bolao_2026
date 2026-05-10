<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Bolão Copa') }}{{ isset($title) ? ' — '.$title : '' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>[x-cloak]{display:none!important}</style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased">
@php
    $authUser = Auth::user();
    $allCompetitions = (array) config('football-data.competitions', []);
    $currentCompetitionCode = strtoupper((string) request()->query('competition', session('competition', config('football-data.default_competition_code', 'WC'))));
    $allowedCompetitions = [];

    $competitionNameFallbacks = [
        'WC' => 'Copa do Mundo',
        'BSA' => 'Brasileirão Série A',
    ];

    foreach ($allCompetitions as $code => $settings) {
        $normalized = strtoupper((string) $code);
        $enabled = (bool) data_get($settings, 'enabled', false);
        if (! $enabled && $normalized !== 'WC' && !($authUser && (bool) $authUser->is_admin)) {
            continue;
        }
        if ($authUser && ! $authUser->canAccessCompetition($normalized)) {
            continue;
        }
        $configuredName = trim((string) data_get($settings, 'name', ''));
        $resolvedName = $configuredName !== '' ? $configuredName : ($competitionNameFallbacks[$normalized] ?? $normalized);
        if (strtoupper($resolvedName) === $normalized) {
            $resolvedName = $competitionNameFallbacks[$normalized] ?? $resolvedName;
        }

        $allowedCompetitions[$normalized] = [
            'name' => $resolvedName,
            'season' => (int) data_get($settings, 'season', 0),
            'enabled' => $enabled || $normalized === 'WC',
        ];
    }

    if (! isset($allowedCompetitions['WC'])) {
        $allowedCompetitions['WC'] = [
            'name' => 'Copa do Mundo',
            'season' => (int) config('football-data.competitions.WC.season', 2026),
            'enabled' => true,
        ];
    }

    if (! array_key_exists($currentCompetitionCode, $allowedCompetitions)) {
        $currentCompetitionCode = 'WC';
    }

    session(['competition' => $currentCompetitionCode]);
    $currentCompetition = $allowedCompetitions[$currentCompetitionCode];
    $canSwitchCompetition = $authUser && ((bool) $authUser->is_admin || (int) ($authUser->subscription_tier ?? 1) >= 2);
@endphp

<div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: false }">

    {{-- Overlay mobile --}}
    <div x-show="sidebarOpen" x-cloak
         class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
         @click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col bg-pitch-900 border-r border-slate-800
                  transition-transform duration-300 ease-in-out
                  lg:static lg:inset-auto lg:z-auto lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

        {{-- Logo --}}
        <div class="flex h-16 shrink-0 items-center gap-3 px-5 border-b border-slate-800">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white text-lg shadow-lg shadow-emerald-900/50 select-none">
                ⚽
            </div>
            <div class="min-w-0" x-data="{ openCompetition: false }">
                <p class="text-sm font-bold text-white leading-none">Bolão</p>
                @if($canSwitchCompetition)
                <div class="relative mt-1">
                    <button type="button"
                            @click="openCompetition = !openCompetition"
                            class="inline-flex w-full items-center justify-between gap-2 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-2 py-1 text-xs text-emerald-300 font-semibold leading-none hover:bg-emerald-500/20 transition-colors">
                        <span class="truncate">{{ $currentCompetition['name'] }} {{ $currentCompetition['season'] }}</span>
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="openCompetition" x-cloak @click.outside="openCompetition = false"
                         class="absolute left-0 z-50 mt-2 w-60 rounded-xl border border-slate-700 bg-pitch-800 shadow-2xl py-1.5">
                        @foreach($allowedCompetitions as $code => $competition)
                        <a href="{{ ($competition['enabled'] || ($authUser && (bool) $authUser->is_admin)) ? route('pools.index', ['competition' => $code]) : '#' }}"
                           @click="openCompetition = false; sidebarOpen = false"
                           class="mx-1 flex items-center justify-between rounded-lg px-3 py-2 text-xs {{ $currentCompetitionCode === $code ? 'text-emerald-300 bg-emerald-600/15' : 'text-slate-300 hover:bg-slate-700/60 hover:text-white' }}">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="font-semibold truncate">{{ $competition['name'] }}</span>
                                <span class="shrink-0 text-slate-500">{{ $code }}</span>
                                <span class="shrink-0 text-slate-400">{{ $competition['season'] }}</span>
                                @if(!($competition['enabled'] ?? false))
                                <span class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-amber-300">OFF</span>
                                @endif
                            </span>
                            @if($currentCompetitionCode === $code)
                            <span class="text-emerald-300">✓</span>
                            @endif
                        </a>
                        @endforeach
                    </div>
                </div>
                @else
                <p class="text-xs text-emerald-400 font-semibold leading-none mt-0.5">Copa do Mundo {{ $allowedCompetitions['WC']['season'] }}</p>
                @endif
            </div>
        </div>

        {{-- Nav links --}}
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
            <p class="px-3 mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Principal</p>

            <a href="{{ route('dashboard') }}"
               @click="sidebarOpen = false"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                      {{ request()->routeIs('dashboard') ? 'bg-emerald-600/20 text-emerald-400 ring-1 ring-emerald-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>

            <a href="{{ route('pools.index', ['competition' => $currentCompetitionCode]) }}"
               @click="sidebarOpen = false"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                      {{ request()->routeIs('pools.*') ? 'bg-emerald-600/20 text-emerald-400 ring-1 ring-emerald-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Meus Bolões
            </a>

            @php
                $isManagedPool = Auth::user()->poolMemberships()
                    ->whereIn('role', ['owner', 'manager'])
                    ->where('status', 'active')
                    ->exists();
            @endphp
            @if($isManagedPool)
            <a href="{{ route('management.pools') }}"
               @click="sidebarOpen = false"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                      {{ request()->routeIs('management.*') ? 'bg-emerald-600/20 text-emerald-400 ring-1 ring-emerald-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                </svg>
                Gestão
                @php
                    $pendingTotal = \App\Models\PoolMember::query()
                        ->where('status', 'pending')
                        ->whereIn('pool_id', Auth::user()->poolMemberships()
                            ->whereIn('role', ['owner', 'manager'])
                            ->where('status', 'active')
                            ->pluck('pool_id'))
                        ->count();
                @endphp
                @if($pendingTotal > 0)
                <span class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-black px-1">
                    {{ $pendingTotal }}
                </span>
                @endif
            </a>
            @endif

            @if(Auth::user()->is_admin)
            <div class="pt-4">
                <p class="px-3 mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Administração</p>

                <a href="{{ route('admin.users.approval') }}"
                   @click="sidebarOpen = false"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                          {{ request()->routeIs('admin.users.*') ? 'bg-amber-500/20 text-amber-400 ring-1 ring-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Usuários
                </a>

                <a href="{{ route('admin.pools.control') }}"
                   @click="sidebarOpen = false"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                          {{ request()->routeIs('admin.pools.*') ? 'bg-amber-500/20 text-amber-400 ring-1 ring-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 7h18M3 12h18M3 17h18"/>
                    </svg>
                    Grupos
                </a>

                <a href="{{ route('admin.api.sync') }}"
                   @click="sidebarOpen = false"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                          {{ request()->routeIs('admin.api.*') ? 'bg-amber-500/20 text-amber-400 ring-1 ring-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Sync API
                </a>

                <a href="{{ route('admin.matches.manual-correction') }}"
                   @click="sidebarOpen = false"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                          {{ request()->routeIs('admin.matches.*') ? 'bg-amber-500/20 text-amber-400 ring-1 ring-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Correção Manual
                </a>

                <a href="{{ route('admin.legal.index') }}"
                   @click="sidebarOpen = false"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                          {{ request()->routeIs('admin.legal.*') ? 'bg-amber-500/20 text-amber-400 ring-1 ring-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Jurídico
                </a>
            </div>
            @endif
        </nav>

        {{-- User menu --}}
        <div class="shrink-0 border-t border-slate-800 p-3" x-data="{ open: false }">
            <button @click="open = !open"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-800 transition-colors group">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-600 to-emerald-800 text-xs font-bold text-white uppercase">
                    {{ mb_substr(Auth::user()->name, 0, 2) }}
                </div>
                <div class="flex-1 min-w-0 text-left">
                    <p class="text-sm font-medium text-slate-200 truncate">{{ Auth::user()->name }}</p>
                    @if(Auth::user()->area)
                    <p class="text-xs text-slate-500 truncate">{{ Auth::user()->area }}</p>
                    @else
                    <p class="text-xs text-slate-500 truncate">{{ Auth::user()->email }}</p>
                    @endif
                </div>
                <svg class="w-4 h-4 text-slate-500 shrink-0 transition-transform duration-200"
                     :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-1 rounded-lg bg-pitch-800 border border-slate-700 py-1 shadow-xl">
                <a href="{{ route('profile.edit') }}"
                   @click="sidebarOpen = false"
                   class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-700/60 transition-colors rounded-md mx-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Meu Perfil
                </a>
                <a href="{{ route('legal.terms') }}"
                   class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-700/60 transition-colors rounded-md mx-1">
                    Termos de Uso
                </a>
                <a href="{{ route('legal.privacy-policy') }}"
                   class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-700/60 transition-colors rounded-md mx-1">
                    Política de Privacidade
                </a>
                <a href="{{ route('about') }}"
                   class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-700/60 transition-colors rounded-md mx-1">
                    Sobre
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-2.5 px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-slate-700/60 transition-colors rounded-md mx-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Sair
                    </button>
                </form>
            </div>
        </div>

        {{-- Copyright --}}
        <div class="shrink-0 px-4 pb-3 pt-1 text-center">
            <p class="text-xs text-slate-500 leading-snug mb-2">
                Plataforma recreativa de organização de palpites esportivos entre usuários.
            </p>
            <div class="mb-2 flex flex-wrap items-center justify-center gap-2 text-xs">
                <a href="{{ route('legal.terms') }}" class="text-emerald-400 hover:text-emerald-300 transition-colors">Termos</a>
                <a href="{{ route('legal.privacy-policy') }}" class="text-emerald-400 hover:text-emerald-300 transition-colors">Privacidade</a>
                <a href="{{ route('about') }}" class="text-emerald-400 hover:text-emerald-300 transition-colors">Sobre</a>
            </div>
            <p class="text-xs text-slate-600 leading-snug">
                &copy; {{ date('Y') }} VixForge Sistemas<br>
                <span class="text-slate-700">Versão {{ config('app.version') }}</span>
            </p>
        </div>
    </aside>

    {{-- Main area --}}
    <div class="flex flex-1 flex-col min-h-0 min-w-0">
        {{-- Mobile topbar --}}
        <div class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-800 bg-pitch-900/95 backdrop-blur px-4 lg:hidden">
            <button @click="sidebarOpen = !sidebarOpen"
                    class="rounded-lg p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <span class="text-sm font-bold text-white">⚽ Bolão {{ $currentCompetition['name'] }} {{ $currentCompetition['season'] }}</span>
        </div>

        <main class="flex-1 overflow-y-auto">
            {{ $slot }}
        </main>
    </div>
</div>

@livewireScripts
@stack('scripts')
</body>
</html>
