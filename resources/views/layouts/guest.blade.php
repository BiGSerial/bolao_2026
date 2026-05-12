<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.base-head', [
        'title' => config('app.name', 'Bolão Copa'),
        'csrf' => true,
        'includeSweetalert' => true,
        'includeMarked' => true,
        'includeLivewireStyles' => true,
    ])

    @php
        try {
            $guestEula = \App\Models\LegalDocument::active()
                ->ofType(\App\Enums\LegalDocumentType::Eula)
                ->first();
            $guestPrivacy = \App\Models\LegalDocument::active()
                ->ofType(\App\Enums\LegalDocumentType::PrivacyPolicy)
                ->first();
        } catch (\Throwable) {
            $guestEula = null;
            $guestPrivacy = null;
        }
    @endphp

    <script>
        const LEGAL_DOCS = {
            eula: {
                title: @json($guestEula?->title ?? 'Termos de Uso'),
                content: @json($guestEula?->content ?? ''),
                version: @json($guestEula?->version ?? ''),
            },
            privacy: {
                title: @json($guestPrivacy?->title ?? 'Política de Privacidade'),
                content: @json($guestPrivacy?->content ?? ''),
                version: @json($guestPrivacy?->version ?? ''),
            }
        };
    </script>
</head>
<body class="font-sans antialiased min-h-screen flex flex-col items-center justify-center bg-bolao-bg p-4"
      x-data="createLegalModalData({
          docs: LEGAL_DOCS,
          modalBodyId: 'legal-modal-body',
          emptyHtml: '<p style=\'color:#64748b;text-align:center;padding:2rem 0;font-style:italic\'>Documento não disponível no momento.</p>'
      })">

    {{-- Background decoration --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-48 -right-48 w-[500px] h-[500px] rounded-full opacity-[0.06] blur-3xl"
             style="background:radial-gradient(circle,#f5a623,transparent)"></div>
        <div class="absolute -bottom-48 -left-48 w-[500px] h-[500px] rounded-full opacity-[0.04] blur-3xl"
             style="background:radial-gradient(circle,#e8390d,transparent)"></div>
    </div>

    {{-- Page content --}}
    <div class="relative w-full max-w-md">
        {{-- Logo --}}
        <div class="mb-8 flex flex-col items-center gap-3">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl text-black font-bc font-extrabold text-3xl shadow-lg"
                 style="background:linear-gradient(135deg,#f5a623,#e8390d);box-shadow:0 8px 32px rgba(245,166,35,0.3)">
                B
            </div>
            <div class="text-center">
                <h1 class="font-bc font-extrabold text-[28px] leading-none text-white">
                    Bolão<span class="text-bolao-accent">FC</span>
                </h1>
                <p class="text-sm text-bolao-muted mt-1">Copa do Mundo 2026 · USA · Canada · México</p>
            </div>
        </div>

        {{-- Content card --}}
        <div class="card p-8 shadow-2xl shadow-black/40">
            {{ $slot }}
        </div>

        <div class="mt-4 text-center text-xs text-bolao-muted2 space-y-2">
            <p>Plataforma recreativa de organização de palpites esportivos entre usuários.</p>
            <div class="flex flex-wrap items-center justify-center gap-3">
                <button type="button" @click="openLegal('eula')"
                        class="text-bolao-accent hover:text-bolao-accent2 transition-colors cursor-pointer">
                    Termos de Uso
                </button>
                <button type="button" @click="openLegal('privacy')"
                        class="text-bolao-accent hover:text-bolao-accent2 transition-colors cursor-pointer">
                    Privacidade
                </button>
                <a href="{{ route('about') }}" class="text-bolao-accent hover:text-bolao-accent2 transition-colors">Sobre</a>
            </div>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')

    {{-- Legal document modal — last in DOM to guarantee stacking above everything --}}
    <div x-show="legalModal"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-6"
         style="background:rgba(0,0,0,0.82)"
         @click.self="closeLegal()"
         @keydown.escape.window="closeLegal()">

        <div x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative flex flex-col w-full max-w-xl rounded-2xl overflow-hidden"
             style="height:min(86vh,680px);background:#0f172a;border:1px solid rgba(100,116,139,0.3);box-shadow:0 25px 60px rgba(0,0,0,0.8)">

            {{-- Header --}}
            <div class="flex items-center justify-between shrink-0 px-5 py-3.5 border-b border-slate-700/40">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-sm"
                         style="background:linear-gradient(to bottom right,#10b981,#059669);box-shadow:0 1px 4px rgba(5,150,105,0.5)">
                        ⚽
                    </div>
                    <h2 class="text-sm font-semibold text-white truncate" x-text="legalModalTitle"></h2>
                </div>
                <button @click="closeLegal()"
                        class="ml-3 shrink-0 flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors border-0 bg-transparent cursor-pointer">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Scrollable body --}}
            <div id="legal-modal-body"
                 class="flex-1 overflow-y-auto overscroll-contain legal-doc-body"
                 style="background:#ffffff;padding:1.5rem 1.75rem;color:#374151">
                <div x-html="renderMd(legalModalContent)"></div>
            </div>

            {{-- Footer --}}
            <div class="shrink-0 flex items-center justify-between gap-3 px-5 py-3.5 border-t border-slate-700/40">
                <p class="text-xs text-slate-600">Bolão Copa 2026</p>
                <button @click="closeLegal()"
                        class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white border-0 cursor-pointer transition-colors"
                        style="background:#059669;box-shadow:0 1px 3px rgba(5,150,105,0.4)"
                        onmouseover="this.style.background='#10b981'"
                        onmouseout="this.style.background='#059669'">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    Entendido
                </button>
            </div>
        </div>
    </div>
</body>
</html>
