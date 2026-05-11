<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'BolaoFC') }}{{ isset($title) ? ' — '.$title : '' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=barlow:400,500,600&family=barlow-condensed:600,700,800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.34.0/dist/tabler-icons.min.css">

    <style>[x-cloak]{display:none!important}</style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased">
@php
    $authUser = Auth::user();

    /* Avatar initials (up to 2 chars) */
    $initials = collect(explode(' ', $authUser->name))
        ->filter()
        ->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))
        ->take(2)
        ->implode('');

    /* Competition resolver */
    $allCompetitions       = (array) config('football-data.competitions', []);
    $currentCompetitionCode = strtoupper((string) request()->query('competition', session('competition', config('football-data.default_competition_code', 'WC'))));
    $allowedCompetitions   = [];
    $competitionNameFallbacks = ['WC' => 'Copa do Mundo', 'BSA' => 'Brasileirão Série A'];

    foreach ($allCompetitions as $code => $settings) {
        $normalized   = strtoupper((string) $code);
        $enabled      = (bool) data_get($settings, 'enabled', false);
        if (!$enabled && $normalized !== 'WC' && !($authUser && (bool) $authUser->is_admin)) continue;
        if ($authUser && !$authUser->canAccessCompetition($normalized)) continue;
        $configuredName = trim((string) data_get($settings, 'name', ''));
        $resolvedName   = $configuredName !== '' ? $configuredName : ($competitionNameFallbacks[$normalized] ?? $normalized);
        if (strtoupper($resolvedName) === $normalized) $resolvedName = $competitionNameFallbacks[$normalized] ?? $resolvedName;
        $allowedCompetitions[$normalized] = ['name' => $resolvedName, 'season' => (int) data_get($settings, 'season', 0), 'enabled' => $enabled || $normalized === 'WC'];
    }
    if (!isset($allowedCompetitions['WC'])) {
        $allowedCompetitions['WC'] = ['name' => 'Copa do Mundo', 'season' => (int) config('football-data.competitions.WC.season', 2026), 'enabled' => true];
    }
    if (!array_key_exists($currentCompetitionCode, $allowedCompetitions)) $currentCompetitionCode = 'WC';
    session(['competition' => $currentCompetitionCode]);
    $currentCompetition    = $allowedCompetitions[$currentCompetitionCode];
    $canSwitchCompetition  = $authUser && count($allowedCompetitions) > 1;

    /* Management & pending */
    $managedPoolIds = $authUser->poolMemberships()
        ->whereIn('role', ['owner', 'manager'])
        ->where('status', 'active')
        ->pluck('pool_id');
    $isManagedPool  = $managedPoolIds->isNotEmpty();
    $pendingTotal   = $isManagedPool
        ? \App\Models\PoolMember::where('status', 'pending')->whereIn('pool_id', $managedPoolIds)->count()
        : 0;

    /* Helpers for active nav styling */
    $sbActive   = 'flex items-center h-11 pl-5 gap-[14px] text-bolao-accent border-l-[3px] border-bolao-accent bg-bolao-accent/[0.08] font-medium transition-all';
    $sbInactive = 'flex items-center h-11 pl-[19px] gap-[14px] text-bolao-muted border-l-[3px] border-transparent hover:bg-bolao-bg3 hover:text-slate-200 font-medium transition-all';
    $sbAdmin    = fn($isActive) => $isActive
        ? 'flex items-center h-11 pl-5 gap-[14px] text-amber-400 border-l-[3px] border-amber-400 bg-amber-400/[0.08] font-medium transition-all'
        : 'flex items-center h-11 pl-[19px] gap-[14px] text-bolao-muted border-l-[3px] border-transparent hover:bg-bolao-bg3 hover:text-slate-200 font-medium transition-all';
@endphp

{{-- ═══════════════════════════════════════════════════
     ROOT x-data — sidebar overlay + competition/user menus
     ════════════════════════════════════════════════ --}}
<div x-data="{ sidebar: false, userMenu: false, compMenu: false }" class="h-screen flex flex-col overflow-hidden md:flex-row">

    {{-- ╔══════════════════════════════════════════╗
         ║  SIDEBAR  (hidden on mobile → overlay)  ║
         ║  Icon-only 64px tablet, 220px desktop   ║
         ╚══════════════════════════════════════════╝ --}}
    <aside class="bolao-sidebar hidden md:flex flex-col bg-bolao-bg2 border-r border-white/[0.07]">

        {{-- Logo / Competition --}}
        <div class="flex h-14 shrink-0 items-center gap-2.5 px-[18px] border-b border-white/[0.07] overflow-hidden">
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-black font-bc font-extrabold text-sm"
                 style="background:linear-gradient(135deg,#f5a623,#e8390d)">B</div>
            <div class="sb-label min-w-0" x-data="{ compMenu: false }">
                <div class="font-bc font-extrabold text-[19px] leading-none text-white">
                    Bolão<span class="text-bolao-accent">FC</span>
                </div>
                @if($canSwitchCompetition)
                <div class="relative mt-1">
                    <button type="button" @click="compMenu = !compMenu"
                            class="inline-flex items-center gap-1 rounded border border-bolao-accent/30 bg-bolao-accent/[0.08] px-2 py-0.5 text-[10px] font-semibold text-bolao-accent hover:bg-bolao-accent/[0.15] transition-colors">
                        <span class="truncate max-w-[110px]">{{ $currentCompetition['name'] }} {{ $currentCompetition['season'] }}</span>
                        <i class="ti ti-chevron-down text-[10px]"></i>
                    </button>
                    <div x-show="compMenu" x-cloak @click.outside="compMenu = false"
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute left-0 z-50 mt-1.5 w-60 rounded-xl border border-white/[0.07] bg-bolao-bg3 shadow-2xl py-1.5">
                        @foreach($allowedCompetitions as $code => $competition)
                        <a href="{{ ($competition['enabled'] || $authUser->is_admin) ? route('dashboard', ['competition' => $code]) : '#' }}"
                           @click="compMenu = false"
                           class="mx-1 flex items-center justify-between rounded-lg px-3 py-2 text-xs {{ $currentCompetitionCode === $code ? 'text-bolao-accent bg-bolao-accent/10' : 'text-slate-300 hover:bg-bolao-bg4 hover:text-white' }}">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="font-semibold truncate">{{ $competition['name'] }}</span>
                                <span class="shrink-0 text-bolao-muted2">{{ $code }}</span>
                                @if(!($competition['enabled'] ?? false))
                                <span class="rounded bg-amber-500/20 px-1 py-0.5 text-[9px] font-bold text-amber-300">OFF</span>
                                @endif
                            </span>
                            @if($currentCompetitionCode === $code)
                            <i class="ti ti-check text-bolao-accent text-xs"></i>
                            @endif
                        </a>
                        @endforeach
                    </div>
                </div>
                @else
                <p class="text-[11px] text-bolao-accent font-semibold leading-none mt-0.5">
                    {{ $currentCompetition['name'] }} {{ $currentCompetition['season'] }}
                </p>
                @endif
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 space-y-0.5">

            <div class="px-5 mb-1.5 overflow-hidden">
                <span class="sb-label text-[10px] font-bold uppercase tracking-widest text-bolao-muted2">Principal</span>
            </div>

            <a href="{{ route('dashboard') }}"
               class="{{ request()->routeIs('dashboard') ? $sbActive : $sbInactive }}">
                <i class="ti ti-home text-xl shrink-0"></i>
                <span class="sb-label text-sm">Dashboard</span>
            </a>

            <a href="{{ route('pools.index', ['competition' => $currentCompetitionCode]) }}"
               class="{{ request()->routeIs('pools.*') ? $sbActive : $sbInactive }}">
                <i class="ti ti-trophy text-xl shrink-0"></i>
                <span class="sb-label text-sm">Meus Bolões</span>
            </a>

            @if($isManagedPool)
            <a href="{{ route('management.pools') }}"
               class="{{ request()->routeIs('management.*') ? $sbActive : $sbInactive }}">
                <i class="ti ti-layout-dashboard text-xl shrink-0"></i>
                <span class="sb-label text-sm">Gestão</span>
                @if($pendingTotal > 0)
                <span class="ml-auto mr-4 sb-label flex h-5 min-w-5 items-center justify-center rounded-full bg-bolao-accent text-[10px] font-bold text-black px-1">
                    {{ $pendingTotal }}
                </span>
                @endif
            </a>
            @endif

            @if($authUser->is_admin)
            <div class="px-5 pt-4 pb-1.5 overflow-hidden">
                <span class="sb-label text-[10px] font-bold uppercase tracking-widest text-bolao-muted2">Admin</span>
            </div>

            <a href="{{ route('admin.users.approval') }}"
               class="{{ $sbAdmin(request()->routeIs('admin.users.*')) }}">
                <i class="ti ti-users text-xl shrink-0"></i>
                <span class="sb-label text-sm">Usuários</span>
            </a>
            <a href="{{ route('admin.pools.control') }}"
               class="{{ $sbAdmin(request()->routeIs('admin.pools.*')) }}">
                <i class="ti ti-tournament text-xl shrink-0"></i>
                <span class="sb-label text-sm">Grupos</span>
            </a>
            <a href="{{ route('admin.api.sync') }}"
               class="{{ $sbAdmin(request()->routeIs('admin.api.*')) }}">
                <i class="ti ti-refresh text-xl shrink-0"></i>
                <span class="sb-label text-sm">Sync API</span>
            </a>
            <a href="{{ route('admin.matches.manual-correction') }}"
               class="{{ $sbAdmin(request()->routeIs('admin.matches.*')) }}">
                <i class="ti ti-pencil text-xl shrink-0"></i>
                <span class="sb-label text-sm">Correção Manual</span>
            </a>
            <a href="{{ route('admin.legal.index') }}"
               class="{{ $sbAdmin(request()->routeIs('admin.legal.*')) }}">
                <i class="ti ti-file-text text-xl shrink-0"></i>
                <span class="sb-label text-sm">Jurídico</span>
            </a>
            @endif
        </nav>

        {{-- User footer --}}
        <div class="shrink-0 border-t border-white/[0.07] p-2" x-data="{ userMenu: false }">
            <button @click="userMenu = !userMenu"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 hover:bg-bolao-bg3 transition-colors overflow-hidden">
                <div class="bolao-avatar w-8 h-8 shrink-0 text-xs">{{ $initials }}</div>
                <div class="sb-label flex-1 min-w-0 text-left">
                    <p class="text-sm font-semibold text-slate-200 truncate">{{ $authUser->name }}</p>
                    <p class="text-[11px] text-bolao-muted truncate">{{ $authUser->area ?: $authUser->email }}</p>
                </div>
                <i class="ti ti-chevron-up sb-label text-bolao-muted text-sm shrink-0 transition-transform duration-200"
                   :class="userMenu ? 'rotate-180' : 'rotate-0'"></i>
            </button>

            <div x-show="userMenu" x-cloak
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-1 rounded-xl bg-bolao-bg3 border border-white/[0.07] py-1 shadow-xl">
                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-bolao-bg4 transition-colors rounded-lg mx-1">
                    <i class="ti ti-user-circle text-base"></i> Meu Perfil
                </a>
                <a href="{{ route('legal.terms') }}"
                   class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-bolao-bg4 transition-colors rounded-lg mx-1">
                    <i class="ti ti-file-text text-base"></i> Termos de Uso
                </a>
                <a href="{{ route('legal.privacy-policy') }}"
                   class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-bolao-bg4 transition-colors rounded-lg mx-1">
                    <i class="ti ti-shield text-base"></i> Privacidade
                </a>
                <a href="{{ route('about') }}"
                   class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-bolao-bg4 transition-colors rounded-lg mx-1">
                    <i class="ti ti-info-circle text-base"></i> Sobre
                </a>
                <div class="my-1 h-px bg-white/[0.06] mx-2"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-2.5 px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-bolao-bg4 transition-colors rounded-lg mx-1">
                        <i class="ti ti-logout text-base"></i> Sair
                    </button>
                </form>
            </div>

            <div class="sb-label px-3 pt-2 pb-1 text-center">
                <p class="text-[10px] text-bolao-muted2 leading-snug">
                    &copy; {{ date('Y') }} VixForge &middot; v{{ config('app.version') }}
                </p>
            </div>
        </div>
    </aside>

    {{-- ╔═══════════════════════════════════════════╗
         ║  CONTENT COLUMN                          ║
         ╚═══════════════════════════════════════════╝ --}}
    <div class="flex flex-1 flex-col min-h-0 min-w-0">

        {{-- Mobile Header (visible only on mobile) --}}
        <header class="flex md:hidden h-14 shrink-0 items-center justify-between px-4 bg-bolao-bg border-b border-white/[0.07] z-30">
            <button @click="sidebar = true"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-bolao-muted hover:text-slate-200 hover:bg-bolao-bg3 transition-colors">
                <i class="ti ti-menu-2 text-xl"></i>
            </button>
            <div class="font-bc font-extrabold text-[20px] leading-none text-white select-none">
                Bolão<span class="text-bolao-accent">FC</span>
            </div>
            <a href="{{ route('profile.edit') }}"
               class="bolao-avatar w-9 h-9 text-[13px] hover:ring-2 hover:ring-bolao-accent/50 transition-all">
                {{ $initials }}
            </a>
        </header>

        {{-- Desktop Header (visible on tablet+) --}}
        <header class="hidden md:flex h-14 shrink-0 items-center justify-between px-6 bg-bolao-bg border-b border-white/[0.07] z-30 sticky top-0">
            <div class="flex items-center gap-3 min-w-0">
                <span class="font-bc font-bold text-lg text-slate-200 truncate">
                    @hasSection('page-heading')
                        @yield('page-heading')
                    @else
                        {{ $currentCompetition['name'] }} {{ $currentCompetition['season'] }}
                    @endif
                </span>
                @stack('page-title')
            </div>
            <div class="flex items-center gap-2">
                @stack('header-actions')
                <a href="{{ route('profile.edit') }}"
                   class="bolao-avatar w-8 h-8 text-xs hover:ring-2 hover:ring-bolao-accent/50 transition-all">
                    {{ $initials }}
                </a>
            </div>
        </header>

        {{-- Main slot --}}
        <main class="flex-1 overflow-y-auto bolao-page-enter">
            {{ $slot }}
        </main>

        {{-- Mobile Tab Bar --}}
        <nav class="flex md:hidden shrink-0 items-start pt-2 bg-bolao-bg2 border-t border-white/[0.07] bolao-tabbar" style="height:68px">
            <a href="{{ route('dashboard') }}"
               class="flex flex-1 flex-col items-center gap-1 px-1 py-1 cursor-pointer transition-colors {{ request()->routeIs('dashboard') ? 'text-bolao-accent' : 'text-bolao-muted2 hover:text-bolao-muted' }}">
                <i class="ti ti-home text-[22px]"></i>
                <span class="text-[10px] font-bold uppercase tracking-wide leading-none">Início</span>
            </a>

            <a href="{{ route('pools.index', ['competition' => $currentCompetitionCode]) }}"
               class="flex flex-1 flex-col items-center gap-1 px-1 py-1 cursor-pointer transition-colors {{ request()->routeIs('pools.*') ? 'text-bolao-accent' : 'text-bolao-muted2 hover:text-bolao-muted' }}">
                <i class="ti ti-trophy text-[22px]"></i>
                <span class="text-[10px] font-bold uppercase tracking-wide leading-none">Bolões</span>
            </a>

            @if($isManagedPool)
            <a href="{{ route('management.pools') }}"
               class="flex flex-1 flex-col items-center gap-1 px-1 py-1 cursor-pointer transition-colors relative {{ request()->routeIs('management.*') ? 'text-bolao-accent' : 'text-bolao-muted2 hover:text-bolao-muted' }}">
                <span class="relative">
                    <i class="ti ti-layout-dashboard text-[22px]"></i>
                    @if($pendingTotal > 0)
                    <span class="absolute -top-1 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-bolao-accent text-[9px] font-bold text-black px-0.5">{{ $pendingTotal }}</span>
                    @endif
                </span>
                <span class="text-[10px] font-bold uppercase tracking-wide leading-none">Gestão</span>
            </a>
            @elseif($authUser->is_admin)
            <a href="{{ route('admin.users.approval') }}"
               class="flex flex-1 flex-col items-center gap-1 px-1 py-1 cursor-pointer transition-colors {{ request()->routeIs('admin.*') ? 'text-amber-400' : 'text-bolao-muted2 hover:text-bolao-muted' }}">
                <i class="ti ti-shield text-[22px]"></i>
                <span class="text-[10px] font-bold uppercase tracking-wide leading-none">Admin</span>
            </a>
            @endif

            <a href="{{ route('profile.edit') }}"
               class="flex flex-1 flex-col items-center gap-1 px-1 py-1 cursor-pointer transition-colors {{ request()->routeIs('profile.*') ? 'text-bolao-accent' : 'text-bolao-muted2 hover:text-bolao-muted' }}">
                <i class="ti ti-user-circle text-[22px]"></i>
                <span class="text-[10px] font-bold uppercase tracking-wide leading-none">Perfil</span>
            </a>
        </nav>
    </div>

    {{-- ╔══════════════════════════════════════════╗
         ║  RIGHT PANEL (1100 px+)                 ║
         ╚══════════════════════════════════════════╝ --}}
    <aside class="bolao-right-panel overflow-y-auto border-l border-white/[0.07] bg-bolao-bg p-4">
        @stack('right-panel')
    </aside>

</div>{{-- end root flex --}}

{{-- ╔══════════════════════════════════════════════╗
     ║  MOBILE SIDEBAR OVERLAY                     ║
     ╚══════════════════════════════════════════════╝ --}}
<div x-show="sidebar" x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="md:hidden fixed inset-0 z-50 bg-black/70"
     @click="sidebar = false" aria-hidden="true"></div>

<aside x-show="sidebar" x-cloak
       x-transition:enter="transition ease-out duration-250" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
       class="md:hidden fixed inset-y-0 left-0 z-[60] w-72 flex flex-col bg-bolao-bg2 border-r border-white/[0.07] shadow-2xl">

    {{-- Overlay header --}}
    <div class="flex h-14 shrink-0 items-center justify-between px-4 border-b border-white/[0.07]">
        <div class="font-bc font-extrabold text-[20px] leading-none text-white">
            Bolão<span class="text-bolao-accent">FC</span>
        </div>
        <button @click="sidebar = false"
                class="flex h-9 w-9 items-center justify-center rounded-lg text-bolao-muted hover:text-slate-200 hover:bg-bolao-bg3 transition-colors">
            <i class="ti ti-x text-xl"></i>
        </button>
    </div>

    {{-- Competition switcher (mobile overlay) --}}
    @if($canSwitchCompetition)
    <div class="px-4 py-2.5 border-b border-white/[0.07]" x-data="{ compMenuMobile: false }">
        <button type="button" @click="compMenuMobile = !compMenuMobile"
                class="inline-flex w-full items-center justify-between gap-1 rounded-lg border border-bolao-accent/30 bg-bolao-accent/[0.08] px-3 py-2 text-xs font-semibold text-bolao-accent hover:bg-bolao-accent/[0.15] transition-colors">
            <span class="truncate">{{ $currentCompetition['name'] }} {{ $currentCompetition['season'] }}</span>
            <i class="ti ti-chevron-down text-[11px] shrink-0 transition-transform" :class="compMenuMobile ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="compMenuMobile" x-cloak
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             class="mt-1.5 rounded-xl border border-white/[0.07] bg-bolao-bg3 py-1.5 shadow-2xl">
            @foreach($allowedCompetitions as $code => $competition)
            <a href="{{ ($competition['enabled'] || $authUser->is_admin) ? route('dashboard', ['competition' => $code]) : '#' }}"
               @click="sidebar = false"
               class="mx-1 flex items-center justify-between rounded-lg px-3 py-2 text-xs {{ $currentCompetitionCode === $code ? 'text-bolao-accent bg-bolao-accent/10' : 'text-slate-300 hover:bg-bolao-bg4 hover:text-white' }}">
                <span class="flex items-center gap-2 min-w-0">
                    <span class="font-semibold truncate">{{ $competition['name'] }}</span>
                    <span class="shrink-0 text-bolao-muted2">{{ $code }}</span>
                    @if(!($competition['enabled'] ?? false))
                    <span class="rounded bg-amber-500/20 px-1 py-0.5 text-[9px] font-bold text-amber-300">OFF</span>
                    @endif
                </span>
                @if($currentCompetitionCode === $code)
                <i class="ti ti-check text-bolao-accent text-xs"></i>
                @endif
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Overlay nav --}}
    <nav class="flex-1 overflow-y-auto py-3 space-y-0.5">
        <p class="px-5 mb-1.5 text-[10px] font-bold uppercase tracking-widest text-bolao-muted2">Principal</p>

        <a href="{{ route('dashboard') }}" @click="sidebar = false"
           class="{{ request()->routeIs('dashboard') ? $sbActive : $sbInactive }}">
            <i class="ti ti-home text-xl shrink-0"></i>
            <span class="text-sm">Dashboard</span>
        </a>
        <a href="{{ route('pools.index', ['competition' => $currentCompetitionCode]) }}" @click="sidebar = false"
           class="{{ request()->routeIs('pools.*') ? $sbActive : $sbInactive }}">
            <i class="ti ti-trophy text-xl shrink-0"></i>
            <span class="text-sm">Meus Bolões</span>
        </a>
        @if($isManagedPool)
        <a href="{{ route('management.pools') }}" @click="sidebar = false"
           class="{{ request()->routeIs('management.*') ? $sbActive : $sbInactive }}">
            <i class="ti ti-layout-dashboard text-xl shrink-0"></i>
            <span class="text-sm">Gestão</span>
            @if($pendingTotal > 0)
            <span class="ml-auto mr-4 flex h-5 min-w-5 items-center justify-center rounded-full bg-bolao-accent text-[10px] font-bold text-black px-1">{{ $pendingTotal }}</span>
            @endif
        </a>
        @endif

        @if($authUser->is_admin)
        <p class="px-5 pt-4 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-bolao-muted2">Admin</p>
        <a href="{{ route('admin.users.approval') }}" @click="sidebar = false"
           class="{{ $sbAdmin(request()->routeIs('admin.users.*')) }}">
            <i class="ti ti-users text-xl shrink-0"></i><span class="text-sm">Usuários</span>
        </a>
        <a href="{{ route('admin.pools.control') }}" @click="sidebar = false"
           class="{{ $sbAdmin(request()->routeIs('admin.pools.*')) }}">
            <i class="ti ti-tournament text-xl shrink-0"></i><span class="text-sm">Grupos</span>
        </a>
        <a href="{{ route('admin.api.sync') }}" @click="sidebar = false"
           class="{{ $sbAdmin(request()->routeIs('admin.api.*')) }}">
            <i class="ti ti-refresh text-xl shrink-0"></i><span class="text-sm">Sync API</span>
        </a>
        <a href="{{ route('admin.matches.manual-correction') }}" @click="sidebar = false"
           class="{{ $sbAdmin(request()->routeIs('admin.matches.*')) }}">
            <i class="ti ti-pencil text-xl shrink-0"></i><span class="text-sm">Correção Manual</span>
        </a>
        <a href="{{ route('admin.legal.index') }}" @click="sidebar = false"
           class="{{ $sbAdmin(request()->routeIs('admin.legal.*')) }}">
            <i class="ti ti-file-text text-xl shrink-0"></i><span class="text-sm">Jurídico</span>
        </a>
        @endif
    </nav>

    {{-- Overlay user footer --}}
    <div class="shrink-0 border-t border-white/[0.07] p-3 space-y-1">
        <div class="flex items-center gap-3 px-2 py-1.5">
            <div class="bolao-avatar w-9 h-9 text-sm shrink-0">{{ $initials }}</div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-200 truncate">{{ $authUser->name }}</p>
                <p class="text-xs text-bolao-muted truncate">{{ $authUser->area ?: $authUser->email }}</p>
            </div>
        </div>
        <a href="{{ route('profile.edit') }}" @click="sidebar = false"
           class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-bolao-bg3 rounded-lg transition-colors">
            <i class="ti ti-user-circle text-base"></i> Meu Perfil
        </a>
        <a href="{{ route('about') }}" @click="sidebar = false"
           class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-bolao-bg3 rounded-lg transition-colors">
            <i class="ti ti-info-circle text-base"></i> Sobre
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-bolao-bg3 rounded-lg transition-colors">
                <i class="ti ti-logout text-base"></i> Sair
            </button>
        </form>
        <p class="px-3 text-[10px] text-bolao-muted2 text-center pb-1">
            &copy; {{ date('Y') }} VixForge &middot; v{{ config('app.version') }}
        </p>
    </div>
</aside>

@livewireScripts
@stack('scripts')
</body>
</html>
