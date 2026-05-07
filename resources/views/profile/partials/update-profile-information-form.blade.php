<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<section>
    <div class="card overflow-hidden">

        {{-- Section header --}}
        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-800">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-900/40 border border-blue-700/30">
                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-white">Informações do Perfil</h2>
                <p class="text-xs text-slate-500">Atualize seu nome e endereço de e-mail</p>
            </div>
        </div>

        {{-- Form body --}}
        <form method="post" action="{{ route('profile.update') }}" class="p-5 space-y-4">
            @csrf
            @method('patch')

            <div>
                <label for="name" class="label">Nome completo</label>
                <input id="name" name="name" type="text"
                       value="{{ old('name', $user->name) }}"
                       required autofocus autocomplete="name"
                       class="input-field">
                @error('name')
                    <p class="mt-1.5 text-xs text-red-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label for="email" class="label">E-mail</label>
                <input id="email" name="email" type="email"
                       value="{{ old('email', $user->email) }}"
                       required autocomplete="username"
                       class="input-field">
                @error('email')
                    <p class="mt-1.5 text-xs text-red-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $message }}
                    </p>
                @enderror

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-2 flex flex-wrap items-center gap-2 rounded-lg border border-amber-700/30 bg-amber-900/20 px-3 py-2.5">
                        <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="text-xs text-amber-300">E-mail não verificado.</p>
                        <button form="send-verification"
                                class="text-xs font-semibold text-amber-400 underline hover:text-amber-300 transition-colors">
                            Reenviar verificação
                        </button>
                    </div>
                @endif
            </div>

            <div class="pt-1 flex items-center gap-3">
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Salvar alterações
                </button>
            </div>
        </form>
    </div>

    @if(session('status') === 'profile-updated' || session('status') === 'verification-link-sent')
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

            @if(session('status') === 'profile-updated')
            Toast.fire({ icon: 'success', title: 'Perfil atualizado!', iconColor: '#10b981' });
            @elseif(session('status') === 'verification-link-sent')
            Toast.fire({ icon: 'info', title: 'E-mail de verificação reenviado.', iconColor: '#60a5fa' });
            @endif
        })();
    </script>
    @endpush
    @endif
</section>
