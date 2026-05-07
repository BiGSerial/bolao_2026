<section x-data="{ open: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">

    {{-- Danger zone card --}}
    <div class="rounded-xl border border-red-900/40 bg-pitch-900 overflow-hidden">

        {{-- Section header --}}
        <div class="flex items-center gap-3 px-5 py-4 border-b border-red-900/30 bg-red-950/20">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-900/50 border border-red-700/40">
                <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-red-400">Zona de Perigo</h2>
                <p class="text-xs text-slate-500">Ações irreversíveis para sua conta</p>
            </div>
        </div>

        {{-- Content --}}
        <div class="px-5 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-slate-200">Excluir minha conta</p>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed max-w-md">
                    Todos os seus dados, palpites e participações em bolões serão excluídos permanentemente.
                    Esta ação não pode ser desfeita.
                </p>
            </div>
            <button @click="open = true"
                    class="btn-danger shrink-0 self-start sm:self-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Excluir conta
            </button>
        </div>
    </div>

    {{-- Confirmation modal --}}
    <div x-show="open"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="open = false">

        {{-- Backdrop --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/70 backdrop-blur-sm"
             @click="open = false">
        </div>

        {{-- Modal panel --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-2"
             class="relative w-full max-w-md rounded-2xl border border-red-900/50 bg-pitch-900 shadow-2xl shadow-black/60 overflow-hidden">

            {{-- Modal header --}}
            <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-800/80">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-900/40 border border-red-700/40">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Confirmar exclusão</h3>
                    <p class="text-xs text-slate-500">Esta ação é permanente e não pode ser desfeita</p>
                </div>
                <button @click="open = false"
                        class="ml-auto flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Modal body --}}
            <form method="post" action="{{ route('profile.destroy') }}" class="p-5 space-y-4">
                @csrf
                @method('delete')

                <p class="text-sm text-slate-400 leading-relaxed">
                    Ao confirmar, todos os seus palpites, participações em bolões e dados pessoais serão
                    <strong class="text-red-400 font-semibold">excluídos permanentemente</strong>.
                    Digite sua senha para continuar.
                </p>

                <div>
                    <label for="delete_password" class="label">Sua senha atual</label>
                    <input id="delete_password"
                           name="password"
                           type="password"
                           placeholder="••••••••"
                           class="input-field"
                           x-ref="deletePassword">
                    @if($errors->userDeletion->has('password'))
                        <p class="mt-1.5 text-xs text-red-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            {{ $errors->userDeletion->first('password') }}
                        </p>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3 pt-1">
                    <button type="button" @click="open = false" class="btn-secondary">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Sim, excluir minha conta
                    </button>
                </div>
            </form>
        </div>
    </div>


</section>
