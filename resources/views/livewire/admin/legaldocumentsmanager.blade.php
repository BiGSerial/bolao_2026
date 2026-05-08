<div class="p-4 sm:p-6 lg:p-8 animate-fade-in">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Jurídico</h1>
        <p class="text-sm text-slate-400 mt-1">Versionamento de Termos de Uso e Política de Privacidade.</p>
    </div>

    {{-- 2-column grid: form (7) + histories (5) --}}
    <div class="flex flex-col gap-6 lg:grid lg:grid-cols-12 lg:items-start">

        {{-- ── FORM: Nova versão (7 cols) ── --}}
        <div class="lg:col-span-7">
        <div class="card p-5 space-y-4">
            <h2 class="text-base font-semibold text-slate-200 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nova versão
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="label">Tipo</label>
                    <select wire:model.live="type" class="input-field mt-1">
                        <option value="EULA">Termos de Uso (EULA)</option>
                        <option value="PRIVACY_POLICY">Política de Privacidade</option>
                    </select>
                    @error('type') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Título</label>
                    <input type="text" wire:model="title" class="input-field mt-1" placeholder="Ex: Termos de Uso Bolão 2026">
                    @error('title') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Próxima versão</label>
                    <div class="input-field mt-1 flex items-center gap-2 text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        <span class="font-semibold text-emerald-400">{{ $nextVersion }}</span>
                        <span class="text-xs text-slate-600">(automática)</span>
                    </div>
                </div>
            </div>

            {{-- Content textarea + variable chips --}}
            <div x-data="{
                insertVar(text) {
                    const ta = this.$refs.contentTA;
                    const start = ta.selectionStart;
                    const end = ta.selectionEnd;
                    ta.setRangeText(text, start, end, 'end');
                    ta.focus();
                    $wire.set('content', ta.value);
                }
            }">
                <label class="label">Conteúdo
                    <span class="text-slate-600 font-normal ml-1">(suporta Markdown)</span>
                </label>

                <div class="mt-1.5 mb-2 flex flex-wrap gap-1.5">
                    <span class="text-xs text-slate-500 self-center mr-1">Inserir variável:</span>
                    @php $chipClass = 'inline-flex items-center gap-1 rounded-md border border-slate-700 bg-slate-800 px-2 py-0.5 text-xs text-slate-300 hover:border-emerald-600/60 hover:text-emerald-300 hover:bg-slate-700 transition-colors font-mono'; @endphp
                    <button type="button" @click="insertVar('@{{versao}}')" class="{{ $chipClass }}">@{{versao}}</button>
                    <button type="button" @click="insertVar('@{{data}}')" class="{{ $chipClass }}">@{{data}}</button>
                    <button type="button" @click="insertVar('@{{hora}}')" class="{{ $chipClass }}">@{{hora}}</button>
                    <button type="button" @click="insertVar('@{{data_hora}}')" class="{{ $chipClass }}">@{{data_hora}}</button>
                    <button type="button" @click="insertVar('@{{ano}}')" class="{{ $chipClass }}">@{{ano}}</button>
                    <button type="button" @click="insertVar('@{{app}}')" class="{{ $chipClass }}">@{{app}}</button>
                </div>

                <textarea x-ref="contentTA"
                          wire:model="content"
                          rows="10"
                          class="input-field font-mono text-sm"
                          placeholder="Cole aqui o texto jurídico. Use @{{versao}}, @{{data}}, @{{app}} como variáveis — serão substituídas ao salvar."></textarea>
                @error('content') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                    <input type="checkbox" wire:model="publishNow"
                           class="rounded border-slate-600 bg-slate-800 text-emerald-500 focus:ring-emerald-500/40">
                    Publicar imediatamente após salvar
                </label>

                <button wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="btn-primary sm:ml-auto">
                    <svg wire:loading.remove wire:target="save" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    <svg wire:loading wire:target="save" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span wire:loading.remove wire:target="save">Salvar versão</span>
                    <span wire:loading wire:target="save">Salvando…</span>
                </button>
            </div>
        </div>
        </div>

        {{-- ── HISTORY cards (5 cols, sticky) ── --}}
        <div class="lg:col-span-5 lg:sticky lg:top-6 flex flex-col gap-4
                    lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto lg:pr-1
                    scrollbar-thin scrollbar-track-transparent scrollbar-thumb-slate-700">

            {{-- ── DOCUMENT HISTORY ── --}}
            <div class="card p-5 space-y-3">
                <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
                    <h2 class="text-base font-semibold text-slate-200 flex items-center gap-2 shrink-0">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Histórico de documentos
                    </h2>
                    <div class="flex gap-2">
                        <input type="text" wire:model.live.debounce.300ms="search"
                               class="input-field text-sm flex-1 min-w-0" placeholder="Buscar…">
                        <select wire:model.live="filterType" class="input-field text-sm w-auto shrink-0">
                            <option value="">Todos</option>
                            <option value="EULA">EULA</option>
                            <option value="PRIVACY_POLICY">Privacidade</option>
                        </select>
                    </div>
                </div>

                {{-- Mobile cards --}}
                <div class="sm:hidden max-h-56 overflow-y-auto space-y-3 pr-1">
                    @forelse($documents as $doc)
                    <div wire:key="doc-card-{{ $doc->id }}" class="rounded-xl border border-slate-700/60 bg-slate-800/30 p-3 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-200 truncate">{{ $doc->title }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $doc->type->value }} · v{{ $doc->version }}</p>
                            </div>
                            <div class="shrink-0">
                                @if($doc->is_active)
                                <span class="badge-green">Ativo</span>
                                @elseif($doc->published_at)
                                <span class="badge-slate">Publicado</span>
                                @else
                                <span class="badge-amber">Rascunho</span>
                                @endif
                            </div>
                        </div>
                        <p class="text-xs text-slate-600">
                            {{ $doc->published_at?->format('d/m/Y H:i') ?? '—' }}
                            @if($doc->creator) · {{ $doc->creator->name }} @endif
                        </p>
                        <div class="flex gap-2 pt-1">
                            <button wire:click="selectDocument({{ $doc->id }})"
                                    class="btn-ghost btn-sm border border-slate-700 flex-1 justify-center">
                                Ver
                            </button>
                            @if(! $doc->published_at)
                            <button wire:click="publish({{ $doc->id }})"
                                    wire:loading.attr="disabled" wire:target="publish({{ $doc->id }})"
                                    class="inline-flex items-center justify-center flex-1 rounded-lg bg-emerald-700/30 px-3 py-1.5 text-xs font-medium text-emerald-400 ring-1 ring-emerald-500/30 hover:bg-emerald-700/50 transition-colors disabled:opacity-60">
                                <span wire:loading.remove wire:target="publish({{ $doc->id }})">Publicar</span>
                                <span wire:loading wire:target="publish({{ $doc->id }})">Publicando…</span>
                            </button>
                            @endif
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-slate-500 text-center py-6">Nenhum documento jurídico cadastrado.</p>
                    @endforelse
                </div>

                {{-- Desktop table --}}
                <div class="hidden sm:block">
                    <div class="overflow-auto max-h-56">
                        <table class="min-w-full">
                            <thead class="sticky top-0 z-10 bg-pitch-900">
                                <tr class="border-b border-slate-800">
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Título</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">v.</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                @forelse($documents as $doc)
                                <tr wire:key="doc-row-{{ $doc->id }}" class="hover:bg-slate-800/20 transition-colors">
                                    <td class="px-3 py-2 text-xs text-slate-400 font-mono whitespace-nowrap">{{ $doc->type->value }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-200 max-w-[10rem] truncate">
                                        <span title="{{ $doc->title }}">{{ $doc->title }}</span>
                                        <p class="text-slate-600 text-[10px] mt-0.5">{{ $doc->published_at?->format('d/m/Y') ?? 'Rascunho' }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-400 whitespace-nowrap">{{ $doc->version }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        @if($doc->is_active)
                                        <span class="badge-green">Ativo</span>
                                        @elseif($doc->published_at)
                                        <span class="badge-slate">Publicado</span>
                                        @else
                                        <span class="badge-amber">Rascunho</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="flex flex-wrap gap-1.5">
                                            <button wire:click="selectDocument({{ $doc->id }})"
                                                    class="btn-ghost btn-sm border border-slate-700">
                                                Ver
                                            </button>
                                            @if(! $doc->published_at)
                                            <button wire:click="publish({{ $doc->id }})"
                                                    wire:loading.attr="disabled" wire:target="publish({{ $doc->id }})"
                                                    class="inline-flex items-center rounded-md bg-emerald-700/30 px-2 py-1 text-xs font-medium text-emerald-400 ring-1 ring-emerald-500/30 hover:bg-emerald-700/50 transition-colors disabled:opacity-60">
                                                <span wire:loading.remove wire:target="publish({{ $doc->id }})">Publicar</span>
                                                <span wire:loading wire:target="publish({{ $doc->id }})">…</span>
                                            </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">Nenhum documento cadastrado.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($documents->hasPages())
                <div class="pt-2 border-t border-slate-800">
                    {{ $documents->links() }}
                </div>
                @endif
            </div>

            {{-- ── ACCEPTANCE HISTORY ── --}}
            <div class="card p-5 space-y-3">
                <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
                    <h2 class="text-base font-semibold text-slate-200 flex items-center gap-2 shrink-0">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        Histórico de aceites
                    </h2>
                    <input type="text" wire:model.live.debounce.300ms="acceptanceSearch"
                           class="input-field text-sm" placeholder="Buscar usuário ou e-mail…">
                </div>

                {{-- Mobile cards --}}
                <div class="sm:hidden max-h-56 overflow-y-auto space-y-3 pr-1">
                    @forelse($acceptances as $acc)
                    <div wire:key="acc-card-{{ $acc->id }}" class="rounded-xl border border-slate-700/60 bg-slate-800/30 p-3 space-y-1.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-200 truncate">{{ $acc->user?->name ?? '—' }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $acc->user?->email ?? '—' }}</p>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded-md bg-emerald-900/30 px-2 py-0.5 text-xs font-medium text-emerald-400 ring-1 ring-emerald-500/30">
                                {{ $acc->legalDocument?->type->value ?? '—' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 truncate">
                            {{ $acc->legalDocument?->title ?? '—' }}
                            <span class="text-slate-600">v{{ $acc->legalDocument?->version ?? '—' }}</span>
                        </p>
                        <p class="text-xs text-slate-600">
                            {{ $acc->accepted_at?->format('d/m/Y H:i') ?? '—' }}
                            @if($acc->ip_address) · {{ $acc->ip_address }} @endif
                        </p>
                    </div>
                    @empty
                    <p class="text-sm text-slate-500 text-center py-6">Nenhum aceite registrado ainda.</p>
                    @endforelse
                </div>

                {{-- Desktop table --}}
                <div class="hidden sm:block">
                    <div class="overflow-auto max-h-56">
                        <table class="min-w-full">
                            <thead class="sticky top-0 z-10 bg-pitch-900">
                                <tr class="border-b border-slate-800">
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Usuário</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Documento</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap">Aceito em</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">IP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                @forelse($acceptances as $acc)
                                <tr wire:key="acc-row-{{ $acc->id }}" class="hover:bg-slate-800/20 transition-colors">
                                    <td class="px-3 py-2">
                                        <p class="text-xs text-slate-200 truncate max-w-[8rem]" title="{{ $acc->user?->name }}">{{ $acc->user?->name ?? '—' }}</p>
                                        <p class="text-[10px] text-slate-500 truncate max-w-[8rem]">{{ $acc->user?->email ?? '—' }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-300 max-w-[9rem] truncate">
                                        <span title="{{ $acc->legalDocument?->title }}">{{ $acc->legalDocument?->title ?? '—' }}</span>
                                        <p class="text-slate-600 text-[10px] mt-0.5">v{{ $acc->legalDocument?->version ?? '—' }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-500 whitespace-nowrap">{{ $acc->accepted_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-600 font-mono whitespace-nowrap">{{ $acc->ip_address ?? '—' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">Nenhum aceite registrado ainda.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($acceptances->hasPages())
                <div class="pt-2 border-t border-slate-800">
                    {{ $acceptances->links() }}
                </div>
                @endif
            </div>

        </div>{{-- /col-span-5 --}}

    </div>{{-- /grid --}}

    {{-- ── DOCUMENT PREVIEW MODAL — padrão legal ── --}}
    <div id="doc-preview-modal"
         style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:0.75rem"
         onclick="if(event.target===this)closeDocModal()">

        <div class="relative flex flex-col w-full max-w-xl rounded-2xl overflow-hidden"
             style="height:min(86vh,680px);background:#0f172a;border:1px solid rgba(100,116,139,0.3);box-shadow:0 25px 60px rgba(0,0,0,0.8)">

            {{-- Header --}}
            <div class="flex items-center justify-between shrink-0 px-5 py-3.5 border-b border-slate-700/40">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-sm"
                         style="background:linear-gradient(to bottom right,#10b981,#059669)">⚽</div>
                    <div class="min-w-0">
                        <p id="modal-doc-title" class="text-sm font-semibold text-white truncate"></p>
                        <p id="modal-doc-meta" class="text-xs text-slate-500 mt-0.5 truncate"></p>
                    </div>
                </div>
                <button onclick="closeDocModal()"
                        class="ml-3 shrink-0 flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors border-0 bg-transparent cursor-pointer">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Scrollable body --}}
            <div id="modal-doc-content"
                 class="flex-1 overflow-y-auto overscroll-contain legal-doc-body"
                 style="background:#ffffff;padding:1.5rem 1.75rem;color:#374151">
            </div>

            {{-- Footer --}}
            <div class="shrink-0 flex items-center justify-between gap-3 px-5 py-3.5 border-t border-slate-700/40">
                <p class="text-xs text-slate-600">Bolão Copa 2026</p>
                <button onclick="closeDocModal()"
                        class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white border-0 cursor-pointer transition-colors"
                        style="background:#059669" onmouseover="this.style.background='#10b981'" onmouseout="this.style.background='#059669'">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    Fechar
                </button>
            </div>
        </div>
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

    $wire.on('open-doc-preview', (payload) => {
        const d = Array.isArray(payload) ? payload[0] : payload;

        document.getElementById('modal-doc-title').textContent = d.title || '';
        document.getElementById('modal-doc-meta').textContent =
            [d.type + ' v' + d.version, d.publishedAt || 'Rascunho', d.creator]
                .filter(Boolean).join(' · ');

        const el = document.getElementById('modal-doc-content');
        const raw = d.content || '';
        if (typeof marked !== 'undefined') {
            marked.setOptions({ breaks: true, gfm: true });
            el.innerHTML = marked.parse(raw);
        } else {
            el.innerHTML = raw.replace(/\n/g, '<br>');
        }

        const modal = document.getElementById('doc-preview-modal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });

    window.closeDocModal = function () {
        document.getElementById('doc-preview-modal').style.display = 'none';
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDocModal();
    });
</script>
@endscript
