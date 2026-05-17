<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials.base-head', [
        'title' => config('app.name', 'BolaoFC').(isset($title) ? ' — '.$title : ''),
        'csrf' => true,
        'includeSweetalert' => true,
        'includeMarked' => true,
        'includeLivewireStyles' => true,
    ])
</head>
<body class="antialiased"
        x-data="{ userMenu: false, compMenu: false, rightPanel: false, managementMobileMenu: false }"
      @rp-opened.window="rightPanel = true"
            @rp-closed.window="rightPanel = false"
            @toggle-management-mobile-menu.window="managementMobileMenu = !managementMobileMenu"
            @open-management-mobile-menu.window="managementMobileMenu = true">
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

    /* Header center: selected pool title for current competition */
    $headerPoolName = null;
    $routePool = request()->route('pool');

    // 1) Prefer pool from current route (always reflects the screen currently open)
    if ($routePool instanceof \App\Models\Pool) {
        $headerPoolName = $routePool->name;
    } elseif (is_string($routePool) && $routePool !== '') {
        $poolFromSlug = \App\Models\Pool::query()
            ->where('slug', $routePool)
            ->whereHas('competition', fn ($q) => $q->where('code', $currentCompetitionCode))
            ->first(['id', 'name']);
        $headerPoolName = $poolFromSlug?->name;
    }

    // 2) Fallback to selected pool in session for that competition
    $selectedPoolSessionKey = 'home_pool_'.$currentCompetitionCode;
    $selectedPoolId = session($selectedPoolSessionKey);
    $activeMembershipsForCompetition = $authUser->poolMemberships()
        ->where('status', 'active')
        ->whereHas('pool.competition', fn ($q) => $q->where('code', $currentCompetitionCode))
        ->with('pool:id,name')
        ->get();

    if (! $headerPoolName && is_numeric($selectedPoolId)) {
        $selectedMembership = $activeMembershipsForCompetition->first(fn ($m) => (int) $m->pool_id === (int) $selectedPoolId);
        $headerPoolName = $selectedMembership?->pool?->name;
    }

    if (! $headerPoolName && $activeMembershipsForCompetition->count() === 1) {
        $headerPoolName = $activeMembershipsForCompetition->first()?->pool?->name;
    }

    /* Management & pending */
    $managedPoolIds = $authUser->poolMemberships()
        ->whereIn('role', ['owner', 'manager'])
        ->where('status', 'active')
        ->pluck('pool_id');
    $isManagedPool  = $managedPoolIds->isNotEmpty();
    $pendingTotal   = $isManagedPool
        ? \App\Models\PoolMember::where('status', 'pending')->whereIn('pool_id', $managedPoolIds)->count()
        : 0;
    $managedPools = $isManagedPool
        ? \App\Models\Pool::query()
            ->whereIn('id', $managedPoolIds)
            ->with(['competition:id,code,name', 'season:id,year'])
            ->withCount(['members as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->orderBy('competition_id')
            ->orderBy('name')
            ->get(['id', 'competition_id', 'competition_season_id', 'name', 'slug'])
        : collect();
    $managedByCompetition = $managedPools->groupBy(function ($pool) {
        $code = strtoupper((string) ($pool->competition?->code ?? 'SEM'));
        $name = (string) ($pool->competition?->name ?? 'Sem competição');
        $season = (string) ($pool->season?->year ?? '—');
        return "{$code}|{$name}|{$season}";
    })->map(function ($pools, $groupKey) {
        [$code, $name, $season] = array_pad(explode('|', (string) $groupKey), 3, '—');
        return [
            'code' => $code,
            'name' => $name,
            'season' => $season,
            'pending_total' => (int) $pools->sum('pending_count'),
            'pools' => $pools,
        ];
    })->values();

    /* Helpers for active nav styling */
    $sbActive   = 'flex items-center h-11 pl-5 gap-[14px] text-bolao-accent border-l-[3px] border-bolao-accent bg-bolao-accent/[0.08] font-medium transition-all';
    $sbInactive = 'flex items-center h-11 pl-[19px] gap-[14px] text-bolao-muted border-l-[3px] border-transparent hover:bg-bolao-bg3 hover:text-slate-200 font-medium transition-all';
    $sbAdmin    = fn($isActive) => $isActive
        ? 'flex items-center h-11 pl-5 gap-[14px] text-amber-400 border-l-[3px] border-amber-400 bg-amber-400/[0.08] font-medium transition-all'
        : 'flex items-center h-11 pl-[19px] gap-[14px] text-bolao-muted border-l-[3px] border-transparent hover:bg-bolao-bg3 hover:text-slate-200 font-medium transition-all';
@endphp

{{-- ═══════════════════════════════════════════════════
    ROOT x-data — competition/user menus + right panel
    ════════════════════════════════════════════════ --}}
<div class="h-screen flex flex-col overflow-hidden md:flex-row">

    {{-- ╔══════════════════════════════════════════╗
            ║  SIDEBAR  (desktop/tablet only)          ║
         ║  Icon-only 64px tablet, 220px desktop   ║
         ╚══════════════════════════════════════════╝ --}}
    <aside class="bolao-sidebar hidden md:flex flex-col bg-bolao-bg2 border-r border-white/[0.07]">

        {{-- Logo / Competition --}}
        <div class="flex h-14 shrink-0 items-center gap-2.5 px-[18px] border-b border-white/[0.07]">
            <x-application-logo class="h-8 w-8 shrink-0" />
            <div class="sb-label min-w-0">
                <div class="font-bc font-extrabold text-[19px] leading-none text-white">
                    Bolão<span class="text-bolao-accent">FC</span>
                </div>
                @if($canSwitchCompetition)
                <div class="relative mt-1 w-[170px]">
                    <select
                        aria-label="Selecionar competição"
                        onchange="if(this.value) window.location.href = this.value"
                        class="w-full appearance-none rounded-md border border-bolao-accent/30 bg-bolao-accent/[0.08] px-2 py-1 pr-6 text-[10px] font-semibold leading-tight text-bolao-accent transition-colors hover:bg-bolao-accent/[0.14] focus:outline-none focus:ring-1 focus:ring-bolao-accent/50"
                    >
                        @foreach($allowedCompetitions as $code => $competition)
                        <option
                            value="{{ route('dashboard', ['competition' => $code]) }}"
                            @selected($currentCompetitionCode === $code)
                            class="bg-bolao-bg3 text-slate-100"
                        >
                            {{ $competition['name'] }} {{ $competition['season'] }} ({{ $code }}){{ !($competition['enabled'] ?? false) ? ' [OFF]' : '' }}
                        </option>
                        @endforeach
                    </select>
                    <i class="ti ti-chevron-down pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2 text-[10px] text-bolao-accent/80"></i>
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

            <a href="{{ route('dashboard', ['competition' => $currentCompetitionCode]) }}"
               class="{{ request()->routeIs('dashboard') ? $sbActive : $sbInactive }}">
                <i class="ti ti-home text-xl shrink-0"></i>
                <span class="sb-label text-sm">Início</span>
            </a>

            <a href="{{ route('pools.index', ['competition' => $currentCompetitionCode]) }}"
               class="{{ request()->routeIs('pools.*') ? $sbActive : $sbInactive }}">
                <i class="ti ti-trophy text-xl shrink-0"></i>
                <span class="sb-label text-sm">Meus Bolões</span>
            </a>

            @if($isManagedPool)
            <div x-data="{ open: {{ request()->routeIs('management.*') ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open"
                        class="{{ request()->routeIs('management.*') ? $sbActive : $sbInactive }} w-full flex items-center gap-2">
                    <i class="ti ti-layout-dashboard text-xl shrink-0"></i>
                    <span class="sb-label text-sm">Gestão</span>
                    @if($pendingTotal > 0)
                    <span class="ml-auto sb-label flex h-5 min-w-5 items-center justify-center rounded-full bg-bolao-accent text-[10px] font-bold text-black px-1">
                        {{ $pendingTotal }}
                    </span>
                    @endif
                    <i class="ti ti-chevron-down sb-label ml-2 mr-4 text-xs transition-transform" :class="open ? 'rotate-180' : ''"></i>
                </button>

                <div x-show="open" x-collapse class="mt-1 space-y-1 border-l border-bolao-muted2/30 ml-2.5 mr-3 pb-1">
                    @foreach($managedByCompetition as $group)
                    <div class="ml-4 border-l border-bolao-muted2/20 pl-2"
                         x-data="{ compOpen: {{ request()->routeIs('management.*') ? 'true' : 'false' }} }">
                        <button type="button" @click="compOpen = !compOpen"
                                class="w-full flex items-center gap-2 px-2 py-1.5 text-left rounded-md text-xs text-slate-300 hover:text-slate-200 hover:bg-bolao-bg4/25 transition-colors">
                            <i class="ti ti-folder text-sm text-bolao-accent/70 shrink-0"></i>
                            <span class="font-semibold text-bolao-accent truncate">{{ $group['code'] }} {{ $group['season'] }}</span>
                            @if($group['pending_total'] > 0)
                            <span class="ml-auto flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-400 text-[10px] font-bold text-black px-1">
                                {{ $group['pending_total'] }}
                            </span>
                            @endif
                            <i class="ti ti-chevron-down text-[10px] text-slate-500 transition-transform" :class="compOpen ? 'rotate-180' : ''"></i>
                        </button>

                        <div x-show="compOpen" x-collapse class="ml-3 mt-0.5 space-y-0.5 border-l border-bolao-muted2/10 pl-2">
                            @foreach($group['pools'] as $managedPool)
                            <a href="{{ route('management.pools', ['competition' => $group['code'], 'pool' => $managedPool->id]) }}"
                               class="inline-flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs transition-colors {{ request()->integer('pool') === (int) $managedPool->id ? 'bg-bolao-accent/12 text-bolao-accent shadow-[inset_0_0_0_1px_rgba(245,166,35,0.22)]' : 'text-slate-400 hover:bg-bolao-bg4/35 hover:text-slate-200' }}">
                                <i class="ti ti-trophy text-sm shrink-0"></i>
                                <span class="truncate">{{ $managedPool->name }}</span>
                                @if((int) $managedPool->pending_count > 0)
                                <span class="ml-auto flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-400 text-[10px] font-bold text-black px-1">
                                    {{ (int) $managedPool->pending_count }}
                                </span>
                                @endif
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
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
            <a href="{{ route('admin.teams.canonical') }}"
               class="{{ $sbAdmin(request()->routeIs('admin.teams.*')) }}">
                <i class="ti ti-shield text-xl shrink-0"></i>
                <span class="sb-label text-sm">Times</span>
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

        {{-- Mobile Header --}}
        <header class="flex md:hidden h-14 shrink-0 items-center gap-3 px-4 bg-bolao-bg border-b border-white/[0.07] z-30">
            {{-- Left: competition name — dropdown when multiple, plain text when single --}}
            @if($canSwitchCompetition)
            <div class="relative flex-1 min-w-0" x-data="{ open: false }" @click.outside="open = false">
                <button @click="open = !open" class="flex items-center gap-1 min-w-0 text-left w-full">
                    <div class="min-w-0">
                        <p class="font-bc font-bold text-[13px] leading-tight text-slate-300 truncate">
                            {{ $currentCompetition['name'] }} {{ $currentCompetition['season'] }}
                        </p>
                        @if($headerPoolName)
                        <p class="text-[11px] font-semibold leading-tight text-bolao-accent truncate mt-0.5">
                            {{ $headerPoolName }}
                        </p>
                        @endif
                    </div>
                    <i class="ti ti-chevron-down text-[11px] text-bolao-muted shrink-0 transition-transform duration-150"
                       :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     class="absolute left-0 top-full mt-2 z-50 min-w-[220px] rounded-xl border border-white/[0.1] bg-bolao-bg2 shadow-xl py-1">
                    @foreach($allowedCompetitions as $code => $competition)
                    <a href="{{ route('dashboard', ['competition' => $code]) }}"
                       class="flex items-center justify-between px-4 py-2.5 text-sm transition-colors
                              {{ $currentCompetitionCode === $code
                                  ? 'text-bolao-accent font-semibold bg-bolao-accent/[0.08]'
                                  : 'text-slate-300 hover:text-white hover:bg-bolao-bg3' }}">
                        <span>{{ $competition['name'] }} {{ $competition['season'] }}</span>
                        @if($currentCompetitionCode === $code)
                        <i class="ti ti-check text-xs text-bolao-accent"></i>
                        @endif
                    </a>
                    @endforeach
                </div>
            </div>
            @else
            <div class="flex-1 min-w-0">
                <p class="font-bc font-bold text-[13px] leading-tight text-slate-300 truncate">
                    {{ $currentCompetition['name'] }} {{ $currentCompetition['season'] }}
                </p>
                @if($headerPoolName)
                <p class="text-[11px] font-semibold leading-tight text-bolao-accent truncate mt-0.5">
                    {{ $headerPoolName }}
                </p>
                @endif
            </div>
            @endif
            {{-- Right: app name + right panel toggle --}}
            <div class="flex items-center gap-2 shrink-0">
                <x-application-logo class="h-8 w-8 shrink-0" />
                <button onclick="window.dispatchEvent(new CustomEvent('rp-open'))"
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-bolao-muted hover:text-slate-200 hover:bg-bolao-bg3 transition-colors">
                    <i class="ti ti-layout-sidebar-right text-xl"></i>
                </button>
            </div>
        </header>

        {{-- Desktop Header (tablet+) --}}
        <header class="bolao-desktop-header bg-bolao-bg border-b border-white/[0.07]">
            {{-- Left: competition name --}}
            <div class="flex items-center min-w-0">
                <div class="min-w-0">
                    <p class="font-bc font-bold text-base leading-none text-slate-200 truncate">
                        {{ $currentCompetition['name'] }} {{ $currentCompetition['season'] }}
                    </p>
                </div>
            </div>
            {{-- Center: active pool name --}}
            <div class="flex flex-col items-center justify-center min-w-0 px-6">
                @if($headerPoolName)
                <span class="font-bc font-bold text-base leading-none text-bolao-accent truncate max-w-[320px]">
                    {{ $headerPoolName }}
                </span>
                @endif
                @stack('header-center')
            </div>
            {{-- Right: user avatar + page actions --}}
            <div class="flex items-center gap-2 justify-end">
                @stack('header-actions')
                <a href="{{ route('profile.edit') }}"
                   class="bolao-avatar w-8 h-8 shrink-0 text-xs hover:ring-2 hover:ring-bolao-accent/50 transition-all">
                    {{ $initials }}
                </a>
            </div>
        </header>

        {{-- Main slot --}}
        <main class="flex-1 overflow-y-auto bolao-page-enter bolao-main-content">
            {{ $slot }}

            {{-- Site footer --}}
            <footer class="mt-8 border-t border-white/[0.07] px-6 py-5">
                <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:justify-between sm:text-left">
                    <p class="text-[11px] text-bolao-muted2 leading-snug">
                        &copy; {{ date('Y') }} <span class="text-bolao-muted font-medium">VixForge Sistemas</span>
                        &middot; v{{ config('app.version') }}
                        &middot; Apenas diversão, sem apostas financeiras
                    </p>
                    <nav class="flex items-center gap-4 flex-wrap justify-center sm:justify-end">
                        <a href="{{ route('legal.terms') }}"
                           class="text-[11px] text-bolao-muted hover:text-slate-300 transition-colors">
                            Termos de Uso
                        </a>
                        <a href="{{ route('legal.privacy-policy') }}"
                           class="text-[11px] text-bolao-muted hover:text-slate-300 transition-colors">
                            Privacidade
                        </a>
                        <a href="{{ route('about') }}"
                           class="text-[11px] text-bolao-muted hover:text-slate-300 transition-colors">
                            Sobre
                        </a>
                    </nav>
                </div>
            </footer>
        </main>

        {{-- Mobile Tab Bar --}}
        <nav class="fixed inset-x-0 bottom-0 z-40 flex md:hidden items-start pt-2 bg-bolao-bg2 border-t border-bolao-accent bolao-tabbar" style="height:68px">
            <a href="{{ route('dashboard', ['competition' => $currentCompetitionCode]) }}"
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
            <button type="button"
                    @click="managementMobileMenu = !managementMobileMenu"
                    class="flex flex-1 flex-col items-center gap-1 px-1 py-1 cursor-pointer transition-colors relative {{ request()->routeIs('management.*') ? 'text-bolao-accent' : 'text-bolao-muted2 hover:text-bolao-muted' }}">
                <span class="relative">
                    <i class="ti ti-layout-dashboard text-[22px]"></i>
                    @if($pendingTotal > 0)
                    <span class="absolute -top-1 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-bolao-accent text-[9px] font-bold text-black px-0.5">{{ $pendingTotal }}</span>
                    @endif
                </span>
                <span class="text-[10px] font-bold uppercase tracking-wide leading-none">Gestão</span>
            </button>
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

            <form method="POST"
                  action="{{ route('logout') }}"
                  class="flex flex-1 js-logout-confirm">
                @csrf
                <button type="submit"
                        class="flex w-full flex-col items-center gap-1 px-1 py-1 cursor-pointer transition-colors text-bolao-muted2 hover:text-red-400">
                    <i class="ti ti-logout text-[22px]"></i>
                    <span class="text-[10px] font-bold uppercase tracking-wide leading-none">Sair</span>
                </button>
            </form>
        </nav>

        @if($isManagedPool)
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
                        <p class="text-sm font-semibold text-slate-200">Selecionar gestão</p>
                        <p class="text-xs text-bolao-muted">Acesse rapidamente seus bolões e áreas administrativas</p>
                    </div>
                    <button type="button" @click="managementMobileMenu = false"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-bolao-muted hover:bg-bolao-bg3 hover:text-slate-200">
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-3 space-y-4">
                    <section class="space-y-2">
                        <p class="px-1 text-[10px] font-bold uppercase tracking-widest text-bolao-muted2">Gestão Bolão</p>
                        @forelse($managedByCompetition as $group)
                        <div class="rounded-xl border border-white/[0.08] bg-bolao-bg" x-data="{ open: {{ request()->routeIs('management.*') ? 'true' : 'false' }} }">
                            <button type="button"
                                    @click="open = !open"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-bolao-accent truncate">{{ $group['code'] }} {{ $group['season'] }}</p>
                                    <p class="text-[11px] text-bolao-muted truncate">{{ $group['name'] }}</p>
                                </div>
                                @if($group['pending_total'] > 0)
                                <span class="ml-auto inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-400 px-1 text-[10px] font-bold text-black">
                                    {{ (int) $group['pending_total'] }}
                                </span>
                                @endif
                                <i class="ti ti-chevron-down text-[11px] text-slate-500 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                            </button>

                            <div x-show="open" x-collapse class="space-y-1 px-2 pb-2">
                                @foreach($group['pools'] as $managedPool)
                                <a href="{{ route('management.pools', ['competition' => $group['code'], 'pool' => $managedPool->id]) }}"
                                   @click="managementMobileMenu = false"
                                   class="flex items-center gap-2 rounded-lg border px-3 py-2.5 transition-colors {{ request()->integer('pool') === (int) $managedPool->id ? 'border-amber-400/80 bg-amber-500/10' : 'border-transparent bg-bolao-bg3/40 text-slate-300' }}">
                                    <i class="ti ti-trophy text-sm {{ request()->integer('pool') === (int) $managedPool->id ? 'text-amber-300' : 'text-slate-500' }}"></i>
                                    <p class="truncate text-sm {{ request()->integer('pool') === (int) $managedPool->id ? 'font-semibold text-amber-300' : 'text-slate-200' }}">{{ $managedPool->name }}</p>
                                    @if((int) $managedPool->pending_count > 0)
                                    <span class="ml-auto inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-400 px-1 text-[10px] font-bold text-black">
                                        {{ (int) $managedPool->pending_count }}
                                    </span>
                                    @endif
                                </a>
                                @endforeach
                            </div>
                        </div>
                        @empty
                        <div class="rounded-xl border border-white/[0.08] bg-bolao-bg px-4 py-6 text-center">
                            <p class="text-sm text-slate-400">Nenhum bolão ativo para gerenciar.</p>
                        </div>
                        @endforelse
                    </section>

                    @if($authUser->is_admin)
                    <section class="space-y-2">
                        <p class="px-1 text-[10px] font-bold uppercase tracking-widest text-bolao-muted2">Gestão Aplicativo</p>

                        <a href="{{ route('admin.users.approval') }}"
                           @click="managementMobileMenu = false"
                           class="flex items-center gap-2 rounded-xl border border-amber-400/20 bg-amber-500/10 px-3 py-2.5 text-sm font-medium text-amber-300 transition-colors hover:bg-amber-500/15">
                            <i class="ti ti-users text-base"></i>
                            <span>Usuários</span>
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
                            <span>Correção Manual</span>
                        </a>

                        <a href="{{ route('admin.legal.index') }}"
                           @click="managementMobileMenu = false"
                           class="flex items-center gap-2 rounded-xl border border-white/[0.08] bg-bolao-bg px-3 py-2.5 text-sm text-slate-300 transition-colors hover:bg-bolao-bg3/60 hover:text-slate-100">
                            <i class="ti ti-file-text text-base"></i>
                            <span>Jurídico</span>
                        </a>
                    </section>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ╔══════════════════════════════════════════╗
         ║  RIGHT PANEL (1100 px+)                 ║
         ╚══════════════════════════════════════════╝ --}}
    <aside class="bolao-right-panel overflow-y-auto border-l border-white/[0.07] bg-bolao-bg p-4">
        <div id="rp-desktop-content"></div>
    </aside>

</div>{{-- end root flex --}}

{{-- ╔══════════════════════════════════════════════╗
     ║  MOBILE RIGHT PANEL                         ║
     ╚══════════════════════════════════════════════╝ --}}

{{-- Backdrop — Alpine controla opacidade/visibilidade --}}
<div x-show="rightPanel" x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="md:hidden fixed inset-0 z-50 bg-black/70"
     onclick="window.dispatchEvent(new CustomEvent('rp-close'))" aria-hidden="true"></div>

{{-- Drawer — sempre no DOM, posição controlada por JS (segue o dedo) --}}
<aside id="rp-drawer"
       class="md:hidden fixed inset-y-0 right-0 z-[60] w-72 flex flex-col bg-bolao-bg2 border-l border-bolao-accent/60 shadow-2xl"
       style="transform: translateX(100%); will-change: transform;">

    <div class="flex h-14 shrink-0 items-center justify-between px-4 border-b border-white/[0.07]">
        <button onclick="window.dispatchEvent(new CustomEvent('rp-close'))"
                class="flex h-9 w-9 items-center justify-center rounded-lg text-bolao-muted hover:text-slate-200 hover:bg-bolao-bg3 transition-colors">
            <i class="ti ti-x text-xl"></i>
        </button>
        <p class="font-bc font-bold text-base text-slate-200">Painel</p>
        <div class="w-9"></div>
    </div>

    <div id="rp-content" class="flex-1 overflow-y-auto p-4 space-y-4 pb-24"></div>
</aside>

<script>
(function () {
    var W = 288; // w-72
    var EDGE = 44;
    var isOpen = false, dragging = false, fromEdge = false;
    var tsx = 0, tsy = 0, lastX = 0, lastT = 0, vx = 0;
    var panel = null;
    var rankPositions = new Map();

    document.addEventListener('DOMContentLoaded', function () {
        panel = document.getElementById('rp-drawer');
        var live = document.getElementById('rp-live-data');
        if (live) {
            syncAll(); // popula os dois painéis no carregamento
            new MutationObserver(syncAll)
                .observe(live, { childList: true, subtree: true, characterData: true });
        }
    });

    function syncAll() {
        var live = document.getElementById('rp-live-data');
        if (!live) return;
        var html = live.innerHTML;
        var desktop = document.getElementById('rp-desktop-content');
        if (desktop) {
            desktop.innerHTML = html;
            animateRankingRows(desktop);
        }
        if (isOpen) {
            var mobile = document.getElementById('rp-content');
            if (mobile) {
                mobile.innerHTML = html;
                animateRankingRows(mobile);
            }
        }
    }

    function syncContent() {
        var live = document.getElementById('rp-live-data');
        var dest = document.getElementById('rp-content');
        if (live && dest) {
            dest.innerHTML = live.innerHTML;
            animateRankingRows(dest);
        }
    }

    function animateRankingRows(root) {
        if (!root) return;
        var rows = root.querySelectorAll('.rp-rank-row[data-rank-key][data-rank-pos]');
        rows.forEach(function (row) {
            var key = row.getAttribute('data-rank-key');
            var pos = parseInt(row.getAttribute('data-rank-pos') || '0', 10);
            if (!key || !Number.isFinite(pos) || pos <= 0) return;

            var prevPos = rankPositions.get(key);
            rankPositions.set(key, pos);
            if (!Number.isFinite(prevPos) || prevPos === pos) return;

            var rowHeight = row.offsetHeight || 34;
            var deltaY = (prevPos - pos) * rowHeight;
            if (deltaY !== 0) {
                row.style.transition = 'none';
                row.style.transform = 'translateY(' + deltaY + 'px)';
                void row.offsetHeight;
                row.style.transition = 'transform 260ms cubic-bezier(0.22,1,0.36,1), background-color 220ms ease';
                row.style.transform = 'translateY(0px)';
            }

            row.classList.remove('rp-rank-up', 'rp-rank-down');
            void row.offsetWidth;
            row.classList.add(pos < prevPos ? 'rp-rank-up' : 'rp-rank-down');
            window.setTimeout(function () {
                row.classList.remove('rp-rank-up', 'rp-rank-down');
                row.style.transition = '';
                row.style.transform = '';
            }, 450);
        });
    }

    function setT(x, anim) {
        if (!panel) return;
        panel.style.transition = anim ? 'transform 0.28s cubic-bezier(0.32,0.72,0,1)' : 'none';
        panel.style.transform = 'translateX(' + Math.max(0, x) + 'px)';
    }

    function open(anim) {
        syncContent();
        setT(0, anim !== false);
        if (!isOpen) { isOpen = true; window.dispatchEvent(new CustomEvent('rp-opened')); }
    }

    function close(anim) {
        setT(W, anim !== false);
        if (isOpen) { isOpen = false; window.dispatchEvent(new CustomEvent('rp-closed')); }
    }

    window.addEventListener('rp-open',  function () { open(); });
    window.addEventListener('rp-close', function () { close(); });

    document.addEventListener('touchstart', function (e) {
        if (window.innerWidth >= 768) return;
        tsx = e.touches[0].clientX;
        tsy = e.touches[0].clientY;
        lastX = tsx; lastT = Date.now(); vx = 0;
        fromEdge = tsx > window.innerWidth - EDGE;
        dragging = fromEdge || isOpen;
    }, { passive: true });

    document.addEventListener('touchmove', function (e) {
        if (!dragging || window.innerWidth >= 768) return;
        var x = e.touches[0].clientX;
        var dx = x - tsx;
        var dy = Math.abs(e.touches[0].clientY - tsy);
        if (dy > 40 && dy > Math.abs(dx)) { dragging = false; return; }
        var now = Date.now();
        if (now > lastT) vx = (x - lastX) / (now - lastT);
        lastX = x; lastT = now;
        setT(isOpen ? Math.max(0, dx) : Math.max(0, Math.min(W, W + dx)), false);
    }, { passive: true });

    document.addEventListener('touchend', function (e) {
        if (!dragging || window.innerWidth >= 768) return;
        dragging = false;
        var dx = e.changedTouches[0].clientX - tsx;
        if (!isOpen) {
            (dx < -(W * 0.35) || (vx < -0.4 && dx < -20)) ? open() : setT(W, true);
        } else {
            (dx > W * 0.35 || (vx > 0.4 && dx > 20)) ? close() : open();
        }
    }, { passive: true });
})();

document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.classList || !form.classList.contains('js-logout-confirm')) return;
    e.preventDefault();
    Swal.fire({
        title: 'Sair da conta?',
        text: 'Você precisará entrar novamente para continuar.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, sair',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f5a623',
        cancelButtonColor: '#252b38',
        reverseButtons: true,
        background: '#13161b',
        color: '#e2e8f0',
        customClass: {
            popup: 'border border-white/10 rounded-xl',
            confirmButton: 'font-semibold',
            cancelButton: 'font-semibold'
        }
    }).then(function (result) {
        if (result.isConfirmed) form.submit();
    });
});
</script>

@livewireScripts
@include('layouts.partials.cookie-consent')
@stack('scripts')
</body>
</html>
