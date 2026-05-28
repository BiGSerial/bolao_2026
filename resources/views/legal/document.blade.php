<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.base-head', [
        'title' => config('app.name', 'BolãoVF').' · '.$documentType->label(),
        'includeMarked' => true,
        'includeLivewireStyles' => true,
    ])
    @if($document)
    <script>
        const DOC_CONTENT = @json($document->content);
    </script>
    @endif
    <style>
        .doc-scroll {
            scrollbar-width: thin;
            scrollbar-color: #475569 transparent;
        }
        .doc-scroll::-webkit-scrollbar { width: 6px; }
        .doc-scroll::-webkit-scrollbar-track { background: transparent; }
        .doc-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 999px; }
        .doc-scroll::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
<body class="h-screen overflow-hidden bg-pitch-950 text-slate-100 font-sans antialiased">
    @php
        $legalDocumentLinksConfig = [
            [
                'label' => 'Termos de Uso',
                'type' => \App\Enums\LegalDocumentType::Eula,
                'route' => 'legal.terms',
            ],
            [
                'label' => 'Política de Privacidade',
                'type' => \App\Enums\LegalDocumentType::PrivacyPolicy,
                'route' => 'legal.privacy-policy',
            ],
            [
                'label' => 'Termo de Responsabilidade',
                'type' => \App\Enums\LegalDocumentType::Disclaimer,
                'route' => 'legal.disclaimer',
            ],
            [
                'label' => 'Política de Confidencialidade',
                'type' => \App\Enums\LegalDocumentType::ConfidentialityPolicy,
                'route' => 'legal.confidentiality-policy',
            ],
        ];

        $officialLinks = [];
        foreach ($legalDocumentLinksConfig as $linkConfig) {
            $latestDocument = \App\Models\LegalDocument::query()
                ->active()
                ->ofType($linkConfig['type'])
                ->latest('published_at')
                ->first();

            if ($latestDocument) {
                $officialLinks[] = [
                    'label' => $linkConfig['label'],
                    'url' => route($linkConfig['route']),
                ];
            }
        }
    @endphp

    <main class="fixed inset-0 z-[2147483647] isolate flex items-center justify-center px-3 py-4 sm:px-6 sm:py-8 lg:px-8 lg:py-10">
        <div class="absolute inset-0 bg-pitch-950" aria-hidden="true"></div>
        <div class="relative mx-auto flex w-full items-center justify-center">

            @if($document)
            <section class="relative z-10 w-full sm:w-[92vw] md:w-[86vw] lg:w-[78vw] xl:w-[68vw] 2xl:w-[60vw] max-w-[980px] rounded-2xl border border-slate-700/70 bg-pitch-900/95 shadow-2xl backdrop-blur-md">
                <header class="border-b border-slate-700/60 px-4 py-3 sm:px-6 sm:py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ url('/') }}" class="mb-2 inline-flex items-center gap-2">
                                <x-application-logo class="h-7 w-7 shrink-0" />
                                <span class="text-sm font-extrabold tracking-tight text-white">Bolão<span class="text-amber-400">VF</span></span>
                            </a>
                            <p class="text-[11px] uppercase tracking-[0.16em] text-amber-400 font-medium mb-1">
                                {{ $documentType->label() }}
                            </p>
                            <h1 class="text-base font-semibold leading-tight text-white sm:text-lg">{{ $document->title }}</h1>
                            <div class="mt-1 flex items-center gap-2 text-[11px] text-slate-500">
                                <span class="inline-flex items-center rounded-full bg-amber-900/40 px-2 py-0.5 text-amber-400 ring-1 ring-amber-500/30">
                                    v{{ $document->version }}
                                </span>
                                @if($document->published_at)
                                <span>Publicado em {{ $document->published_at->format('d/m/Y \à\s H:i') }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="javascript:history.back()"
                           class="shrink-0 inline-flex items-center gap-1.5 rounded-md border border-slate-700 px-2.5 py-1.5 text-xs text-slate-300 hover:bg-slate-800/60 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Voltar
                        </a>
                    </div>
                </header>

                @if(!empty($officialLinks))
                <div class="px-4 py-3 sm:px-6 border-b border-slate-700/60 bg-slate-900/40">
                    <p class="text-xs font-semibold text-slate-200 mb-1">Links oficiais para verificação</p>
                    <div class="space-y-1 text-xs">
                        @foreach($officialLinks as $officialLink)
                            <p>
                                <span class="text-slate-400">{{ $officialLink['label'] }}:</span>
                                <a href="{{ $officialLink['url'] }}" class="text-amber-400 underline hover:text-amber-300 break-all">{{ $officialLink['url'] }}</a>
                            </p>
                        @endforeach
                    </div>
                </div>
                @endif

                <div id="doc-content"
                     class="legal-doc-body doc-scroll overflow-y-auto px-4 py-5 sm:px-6 sm:py-6
                            h-[62vh] sm:h-[64vh] lg:h-[66vh] 2xl:h-[60vh]">
                </div>
                <footer class="border-t border-slate-700/60 px-4 py-2.5 sm:px-6 text-[11px] text-slate-400 flex items-center justify-between gap-2">
                    <span>&copy; {{ date('Y') }} VixForge Sistemas. Todos os direitos reservados.</span>
                    <span class="text-slate-500">v{{ config('app.version') }}</span>
                </footer>

                <script>
                    (() => {
                        const content = (DOC_CONTENT || '').trim();
                        if (content === '') {
                            const target = document.getElementById('doc-content');
                            if (target) {
                                target.innerHTML = "<p style='color:#94a3b8;font-style:italic;text-align:center;padding:1.2rem 0'>Documento publicado sem conteúdo textual no momento.</p>";
                            }
                            return;
                        }

                        const run = () => {
                            if (typeof window.renderLegalDocumentContent === 'function') {
                                window.renderLegalDocumentContent('doc-content', content);
                            }
                        };

                        run();
                        window.addEventListener('DOMContentLoaded', run, { once: true });
                        window.addEventListener('load', run, { once: true });
                        if (document.readyState === 'complete' || document.readyState === 'interactive') {
                            window.renderLegalDocumentContent('doc-content', content);
                        }
                    })();
                </script>
            </section>
            @else
            <section class="w-full max-w-xl rounded-2xl border border-slate-700/70 bg-pitch-900/95 px-6 py-12 text-center shadow-2xl backdrop-blur-md">
                <svg class="mx-auto w-10 h-10 text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-slate-300 text-sm font-medium">Documento ainda não publicado.</p>
                <p class="text-slate-500 text-xs mt-1">Entre em contato com o administrador.</p>
                @if(!empty($officialLinks))
                <div class="mt-4 space-y-1 text-xs text-left rounded-lg border border-slate-700 bg-slate-900/50 p-3">
                    @foreach($officialLinks as $officialLink)
                        <p>
                            <span class="text-slate-400">{{ $officialLink['label'] }}:</span>
                            <a href="{{ $officialLink['url'] }}" class="text-amber-400 underline hover:text-amber-300 break-all">{{ $officialLink['url'] }}</a>
                        </p>
                    @endforeach
                </div>
                @endif
                <a href="javascript:history.back()"
                   class="mt-5 inline-flex items-center justify-center rounded-md border border-slate-700 px-3 py-2 text-xs text-slate-300 hover:bg-slate-800/60 transition-colors">
                    Voltar
                </a>
                <p class="mt-4 text-[11px] text-slate-500">&copy; {{ date('Y') }} VixForge Sistemas. Todos os direitos reservados.</p>
            </section>
            @endif

        </div>
    </main>
    @livewireScripts
</body>
</html>
