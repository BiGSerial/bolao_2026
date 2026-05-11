<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Bolão Copa') }} · {{ $documentType->label() }}</title>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
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

    {{-- Background --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-emerald-900/10 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 rounded-full bg-blue-900/10 blur-3xl"></div>
    </div>

    <main class="fixed inset-0 z-[2147483647] isolate flex items-center justify-center px-3 py-4 sm:px-6 sm:py-8 lg:px-8 lg:py-10">
        <div class="absolute inset-0 bg-pitch-950/92 backdrop-blur-[2px]" aria-hidden="true"></div>
        <div class="relative mx-auto flex w-full items-center justify-center">

            @if($document)
            <section class="relative z-10 w-full sm:w-[92vw] md:w-[86vw] lg:w-[78vw] xl:w-[68vw] 2xl:w-[60vw] max-w-[980px] rounded-2xl border border-slate-700/70 bg-pitch-900/95 shadow-2xl backdrop-blur-md">
                <header class="border-b border-slate-700/60 px-4 py-3 sm:px-6 sm:py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.16em] text-emerald-400 font-medium mb-1">
                                {{ $documentType->label() }}
                            </p>
                            <h1 class="text-base font-semibold leading-tight text-white sm:text-lg">{{ $document->title }}</h1>
                            <div class="mt-1 flex items-center gap-2 text-[11px] text-slate-500">
                                <span class="inline-flex items-center rounded-full bg-emerald-900/40 px-2 py-0.5 text-emerald-400 ring-1 ring-emerald-500/30">
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

                <div id="doc-content"
                     class="legal-doc-body overflow-y-auto px-4 py-5 sm:px-6 sm:py-6
                            h-[62vh] sm:h-[64vh] lg:h-[66vh] 2xl:h-[60vh]">
                </div>

                <script>
                    (function () {
                        var el = document.getElementById('doc-content');
                        if (!el) return;
                        if (typeof marked !== 'undefined') {
                            marked.setOptions({ breaks: true, gfm: true });
                            el.innerHTML = marked.parse(DOC_CONTENT || '');
                        } else {
                            el.innerHTML = (DOC_CONTENT || '').replace(/\n/g, '<br>');
                        }
                    })();
                </script>
            </section>
            @else
            <section class="w-full max-w-xl rounded-2xl border border-slate-700/70 bg-pitch-900/90 px-6 py-12 text-center shadow-2xl backdrop-blur-md">
                <svg class="mx-auto w-10 h-10 text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-slate-400 text-sm font-medium">Documento ainda não publicado.</p>
                <p class="text-slate-600 text-xs mt-1">Entre em contato com o administrador.</p>
                <a href="javascript:history.back()"
                   class="mt-5 inline-flex items-center justify-center rounded-md border border-slate-700 px-3 py-2 text-xs text-slate-300 hover:bg-slate-800/60 transition-colors">
                    Voltar
                </a>
            </section>
            @endif

        </div>
    </main>
    @livewireScripts
</body>
</html>
