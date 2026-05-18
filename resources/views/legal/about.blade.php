<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.base-head', [
        'title' => config('app.name', 'Bolão Copa').' · Sobre',
        'includeMarked' => true,
        'includeLivewireStyles' => true,
    ])

    @php
        $privacyContactEmail = (string) config('app.privacy_contact_email', 'privacidade@vixforge.com.br');
        try {
            $aboutEula = \App\Models\LegalDocument::active()
                ->ofType(\App\Enums\LegalDocumentType::Eula)
                ->first();
            $aboutPrivacy = \App\Models\LegalDocument::active()
                ->ofType(\App\Enums\LegalDocumentType::PrivacyPolicy)
                ->first();
        } catch (\Throwable) {
            $aboutEula = null;
            $aboutPrivacy = null;
        }
    @endphp

    <script>
        const LEGAL_DOCS = {
            eula:    { title: @json($aboutEula?->title ?? 'Termos de Uso'), content: @json($aboutEula?->content ?? '') },
            privacy: { title: @json($aboutPrivacy?->title ?? 'Política de Privacidade'), content: @json($aboutPrivacy?->content ?? '') },
        };
    </script>
</head>
<body class="min-h-screen bg-pitch-950 font-sans antialiased flex items-center justify-center p-3 sm:p-6"
      x-data="createLegalModalData({
          docs: LEGAL_DOCS,
          modalBodyId: 'about-modal-body',
          emptyHtml: '<p style=\'color:#64748b;text-align:center;padding:2rem 0;font-style:italic\'>Documento não disponível.</p>'
      })">

    {{-- Background --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-emerald-900/15 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 rounded-full bg-blue-900/15 blur-3xl"></div>
    </div>

    {{-- Card --}}
    <div class="relative flex flex-col w-full max-w-xl rounded-2xl border border-slate-700/60 bg-pitch-900 shadow-2xl overflow-hidden"
         style="height:min(86vh,680px)">

        {{-- Header --}}
        <div class="flex items-center justify-between shrink-0 px-5 py-3.5 border-b border-slate-700/40 bg-pitch-900">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-sm"
                     style="background:linear-gradient(to bottom right,#10b981,#059669);box-shadow:0 1px 4px rgba(5,150,105,0.5)">
                    ⚽
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-white truncate">Sobre a Plataforma</p>
                    <p class="text-xs text-slate-500 leading-tight">Bolão Copa 2026</p>
                </div>
            </div>
            <a href="javascript:history.back()"
               class="ml-3 shrink-0 flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </a>
        </div>

        {{-- Scrollable body --}}
        <div class="flex-1 overflow-y-auto overscroll-contain px-5 py-5 space-y-4">

            <div>
                <p class="text-xs uppercase tracking-widest text-emerald-400 font-medium mb-1">Institucional</p>
                <h2 class="text-sm font-semibold text-white mb-2">O que é o Bolão Copa 2026</h2>
                <div class="space-y-1.5 text-xs text-slate-400 leading-relaxed">
                    <p>O Bolão Copa 2026 é uma plataforma recreativa e social destinada à organização de palpites esportivos entre usuários, amigos, familiares e grupos privados.</p>
                    <p>A plataforma <strong class="text-slate-200 font-medium">não realiza apostas</strong>, não intermedeia pagamentos, não arrecada valores, não administra prêmios e não garante repasses financeiros entre participantes.</p>
                    <p>Qualquer acordo, contribuição ou premiação combinada entre participantes ocorre fora da plataforma e sob responsabilidade exclusiva dos próprios usuários.</p>
                </div>
            </div>

            <div class="border-t border-slate-800"></div>

            <div>
                <p class="text-xs uppercase tracking-widest text-emerald-400 font-medium mb-2">Privacidade & LGPD</p>
                <div class="space-y-2">
                    @foreach([
                        ['Dados coletados:', 'nome completo, e-mail e nome de exibição (apelido).'],
                        ['Apelido:', 'pode aparecer em grupos, rankings e placares visíveis a outros participantes.'],
                        ['Nome e e-mail:', 'são privados, usados apenas para autenticação e operação da conta.'],
                        ['Compartilhamento:', 'a plataforma não vende, aluga ou comercializa dados pessoais.'],
                    ] as [$label, $text])
                    <div class="flex items-start gap-2">
                        <svg class="shrink-0 mt-0.5 w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            <strong class="text-slate-200 font-medium">{{ $label }}</strong> {{ $text }}
                        </p>
                    </div>
                    @endforeach
                    <div class="flex items-start gap-2">
                        <svg class="shrink-0 mt-0.5 w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Exclusão de conta e dados: <a href="mailto:{{ $privacyContactEmail }}" class="text-emerald-400 hover:underline">{{ $privacyContactEmail }}</a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-800"></div>

            <div>
                <p class="text-xs uppercase tracking-widest text-emerald-400 font-medium mb-2">Documentos legais</p>
                <div class="flex flex-wrap gap-3">
                    <button type="button" @click="openLegal('eula')"
                            class="inline-flex items-center gap-1.5 text-xs text-emerald-400 hover:text-emerald-300 transition-colors cursor-pointer bg-transparent border-0 p-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Termos de Uso
                    </button>
                    <button type="button" @click="openLegal('privacy')"
                            class="inline-flex items-center gap-1.5 text-xs text-emerald-400 hover:text-emerald-300 transition-colors cursor-pointer bg-transparent border-0 p-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Política de Privacidade
                    </button>
                </div>
            </div>

        </div>

        {{-- Footer --}}
        <div class="shrink-0 flex items-center justify-between gap-3 px-5 py-3.5 border-t border-slate-700/40 bg-pitch-900">
            <p class="text-xs text-slate-600">Plataforma recreativa · Sem fins lucrativos</p>
            <a href="javascript:history.back()"
               class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white transition-colors"
               style="background:#059669" onmouseover="this.style.background='#10b981'" onmouseout="this.style.background='#059669'">
                Voltar
            </a>
        </div>
    </div>

    {{-- Legal modal — last in DOM --}}
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

            <div class="flex items-center justify-between shrink-0 px-5 py-3.5 border-b border-slate-700/40">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-sm"
                         style="background:linear-gradient(to bottom right,#10b981,#059669)">⚽</div>
                    <h2 class="text-sm font-semibold text-white truncate" x-text="legalModalTitle"></h2>
                </div>
                <button @click="closeLegal()"
                        class="ml-3 shrink-0 flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors border-0 bg-transparent cursor-pointer">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div id="about-modal-body"
                 class="flex-1 overflow-y-auto overscroll-contain legal-doc-body"
                 style="background:#ffffff;padding:1.5rem 1.75rem;color:#374151"
                 x-html="renderMd(legalModalContent)">
            </div>

            <div class="shrink-0 flex items-center justify-between gap-3 px-5 py-3.5 border-t border-slate-700/40">
                <p class="text-xs text-slate-600">Bolão Copa 2026</p>
                <button @click="closeLegal()"
                        class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white border-0 cursor-pointer transition-colors"
                        style="background:#059669" onmouseover="this.style.background='#10b981'" onmouseout="this.style.background='#059669'">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    Entendido
                </button>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
