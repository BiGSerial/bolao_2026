<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.base-head', [
        'title' => config('app.name', 'Bolão Copa').' · Aceite obrigatório',
        'includeMarked' => true,
        'includeLivewireStyles' => true,
    ])

    <script>
        const ACCEPTANCE_CONFIG = {
            acceptEula: {{ old('accept_eula') ? 'true' : 'false' }},
            acceptPrivacyPolicy: {{ old('accept_privacy_policy') ? 'true' : 'false' }},
            eulaContent: @json($eula?->content ?? ''),
            privacyContent: @json($privacyPolicy?->content ?? ''),
        };
    </script>
</head>
<body class="min-h-screen bg-pitch-950 font-sans antialiased flex items-center justify-center p-3 sm:p-6"
      x-data="createAcceptanceData({
          ...ACCEPTANCE_CONFIG,
          emptyHtml: '<p style=\'color:#64748b;font-style:italic;text-align:center;padding:1.5rem 0\'>Conteúdo não disponível.</p>'
      })">

    {{-- Background --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-emerald-900/15 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 rounded-full bg-blue-900/15 blur-3xl"></div>
    </div>

    {{-- Dialog --}}
    <div class="relative w-full max-w-xl flex flex-col rounded-2xl border border-slate-700/60 bg-pitch-900 shadow-2xl overflow-hidden"
         style="max-height: min(90vh, 700px)">

        {{-- Dialog header --}}
        <div class="shrink-0 px-5 py-4 border-b border-slate-700/50 bg-pitch-900/95">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-700 text-base shadow shadow-emerald-900/50">
                    ⚽
                </div>
                <div>
                    <h1 class="text-sm font-bold text-white leading-tight">Aceite de Termos Obrigatório</h1>
                    <p class="text-xs text-slate-500 leading-tight">Leia e aceite para continuar no Bolão Copa 2026</p>
                </div>
                <span class="ml-auto shrink-0 text-xs font-medium text-emerald-400 bg-emerald-900/30 border border-emerald-700/40 px-2 py-0.5 rounded-full">
                    Obrigatório
                </span>
            </div>
        </div>

        {{-- Errors --}}
        @if ($errors->any())
        <div class="shrink-0 mx-4 mt-3 flex items-start gap-2.5 rounded-lg border border-red-700/50 bg-red-900/20 px-3 py-2.5 text-xs text-red-300">
            <svg class="mt-0.5 w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <ul>@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
        </div>
        @endif

        {{-- Tabs --}}
        <div class="shrink-0 flex border-b border-slate-700/50 bg-pitch-900/80">
            <button type="button"
                    @click="activeTab = 'eula'"
                    :class="activeTab === 'eula'
                        ? 'border-b-2 border-emerald-500 text-emerald-400 bg-emerald-900/10'
                        : 'text-slate-500 hover:text-slate-300'"
                    class="flex-1 px-4 py-2.5 text-xs font-medium transition-colors flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Termos de Uso
                <span x-show="acceptEula" class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
            </button>
            <button type="button"
                    @click="activeTab = 'privacy'"
                    :class="activeTab === 'privacy'
                        ? 'border-b-2 border-emerald-500 text-emerald-400 bg-emerald-900/10'
                        : 'text-slate-500 hover:text-slate-300'"
                    class="flex-1 px-4 py-2.5 text-xs font-medium transition-colors flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Política de Privacidade
                <span x-show="acceptPrivacyPolicy" class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
            </button>
        </div>

        {{-- Scrollable content area --}}
        <div class="flex-1 overflow-y-auto min-h-0">

            {{-- EULA tab --}}
            <div x-show="activeTab === 'eula'">
                @if($eula)
                <div class="flex items-center gap-2 px-4 py-1.5 border-b border-slate-800/80 bg-slate-900/40">
                    <span class="text-xs text-slate-500">{{ $eula->title }}</span>
                    <span class="text-slate-700 text-xs">·</span>
                    <span class="text-xs text-slate-600">v{{ $eula->version }}</span>
                    @if($eula->published_at)
                    <span class="text-slate-700 text-xs">·</span>
                    <span class="text-xs text-slate-600">{{ $eula->published_at->format('d/m/Y') }}</span>
                    @endif
                </div>
                <div class="legal-doc-body px-5 py-4" x-html="renderMd(eulaContent)"></div>
                @else
                <div class="flex flex-col items-center justify-center py-12 text-slate-500 gap-2">
                    <svg class="w-8 h-8 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">Termos de Uso ainda não publicados.</p>
                </div>
                @endif
            </div>

            {{-- Privacy tab --}}
            <div x-show="activeTab === 'privacy'">
                @if($privacyPolicy)
                <div class="flex items-center gap-2 px-4 py-1.5 border-b border-slate-800/80 bg-slate-900/40">
                    <span class="text-xs text-slate-500">{{ $privacyPolicy->title }}</span>
                    <span class="text-slate-700 text-xs">·</span>
                    <span class="text-xs text-slate-600">v{{ $privacyPolicy->version }}</span>
                    @if($privacyPolicy->published_at)
                    <span class="text-slate-700 text-xs">·</span>
                    <span class="text-xs text-slate-600">{{ $privacyPolicy->published_at->format('d/m/Y') }}</span>
                    @endif
                </div>
                <div class="legal-doc-body px-5 py-4" x-html="renderMd(privacyContent)"></div>
                @else
                <div class="flex flex-col items-center justify-center py-12 text-slate-500 gap-2">
                    <svg class="w-8 h-8 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <p class="text-sm">Política de Privacidade ainda não publicada.</p>
                </div>
                @endif
            </div>

        </div>

        {{-- Dialog footer (fixed) --}}
        <form method="POST" action="{{ route('legal.acceptance.store') }}"
              class="shrink-0 border-t border-slate-700/50 bg-pitch-900/95 px-5 py-4 space-y-3">
            @csrf

            {{-- Checkboxes --}}
            <div class="space-y-2">
                <label class="flex items-center gap-2.5 cursor-pointer group">
                    <input type="checkbox" name="accept_eula" value="1"
                           x-model="acceptEula"
                           @checked(old('accept_eula'))
                           class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-emerald-500 focus:ring-emerald-500/40 focus:ring-offset-0 cursor-pointer shrink-0">
                    <span class="text-xs text-slate-400 group-hover:text-slate-200 transition-colors select-none">
                        Li e aceito os <button type="button" @click="activeTab = 'eula'" class="text-emerald-400 hover:underline">Termos de Uso</button>
                    </span>
                </label>
                <label class="flex items-center gap-2.5 cursor-pointer group">
                    <input type="checkbox" name="accept_privacy_policy" value="1"
                           x-model="acceptPrivacyPolicy"
                           @checked(old('accept_privacy_policy'))
                           class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-emerald-500 focus:ring-emerald-500/40 focus:ring-offset-0 cursor-pointer shrink-0">
                    <span class="text-xs text-slate-400 group-hover:text-slate-200 transition-colors select-none">
                        Li e aceito a <button type="button" @click="activeTab = 'privacy'" class="text-emerald-400 hover:underline">Política de Privacidade</button>
                    </span>
                </label>
            </div>

            {{-- Buttons --}}
            <div class="flex items-center justify-between gap-3 pt-1">
                <button type="button"
                        onclick="document.getElementById('decline-form').submit()"
                        class="text-sm text-slate-500 hover:text-red-400 transition-colors">
                    Recusar e sair
                </button>

                <button type="submit"
                        :disabled="!(acceptEula && acceptPrivacyPolicy)"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 disabled:bg-slate-700 disabled:text-slate-500 disabled:cursor-not-allowed px-5 py-2 text-sm font-semibold text-white transition-colors disabled:pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Aceitar e continuar
                </button>
            </div>
        </form>
    </div>

    {{-- Hidden logout form for "Recusar" --}}
    <form id="decline-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>
    @livewireScripts
</body>
</html>
