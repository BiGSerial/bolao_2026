<section>
    <div class="card overflow-hidden">

        {{-- Section header --}}
        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-800">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-900/40 border border-emerald-700/30">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-white">Alterar Senha</h2>
                <p class="text-xs text-slate-500">Use uma senha longa e segura para proteger sua conta</p>
            </div>
        </div>

        {{-- Form body --}}
        <form method="post" action="{{ route('password.update') }}" class="p-5 space-y-4">
            @csrf
            @method('put')

            <div>
                <label for="update_password_current_password" class="label">Senha atual</label>
                <input id="update_password_current_password"
                       name="current_password"
                       type="password"
                       autocomplete="current-password"
                       class="input-field">
                @if($errors->updatePassword->has('current_password'))
                    <p class="mt-1.5 text-xs text-red-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $errors->updatePassword->first('current_password') }}
                    </p>
                @endif
            </div>

            <div>
                <label for="update_password_password" class="label">Nova senha</label>
                <input id="update_password_password"
                       name="password"
                       type="password"
                       autocomplete="new-password"
                       class="input-field">
                @if($errors->updatePassword->has('password'))
                    <p class="mt-1.5 text-xs text-red-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $errors->updatePassword->first('password') }}
                    </p>
                @endif
            </div>

            <div>
                <label for="update_password_password_confirmation" class="label">Confirmar nova senha</label>
                <input id="update_password_password_confirmation"
                       name="password_confirmation"
                       type="password"
                       autocomplete="new-password"
                       class="input-field">
                @if($errors->updatePassword->has('password_confirmation'))
                    <p class="mt-1.5 text-xs text-red-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $errors->updatePassword->first('password_confirmation') }}
                    </p>
                @endif
            </div>

            <div class="pt-1">
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Atualizar senha
                </button>
            </div>
        </form>
    </div>

    @if(session('status') === 'password-updated' || $errors->updatePassword->any())
    @push('scripts')
    <script>
        (function () {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                background: '#1e293b',
                color: '#e2e8f0',
                didOpen: (t) => { t.onmouseenter = Swal.stopTimer; t.onmouseleave = Swal.resumeTimer; },
            });

            @if(session('status') === 'password-updated')
            Toast.fire({ icon: 'success', title: 'Senha alterada com sucesso!', iconColor: '#10b981' });
            @elseif($errors->updatePassword->any())
            Toast.fire({
                icon: 'error',
                title: 'Não foi possível alterar a senha',
                text: @json($errors->updatePassword->first()),
                iconColor: '#f87171',
                timer: 5000,
            });
            @endif
        })();
    </script>
    @endpush
    @endif
</section>
