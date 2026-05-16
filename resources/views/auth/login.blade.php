<x-guest-layout>
    <h2 class="text-xl font-bold text-white mb-6">Entrar na sua conta</h2>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="label">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   class="input-field"
                   placeholder="seu@email.com">
            @error('email') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Senha</label>
            <div class="relative">
                <input id="password" type="password" name="password"
                       required autocomplete="current-password"
                       class="input-field pr-20"
                       placeholder="••••••••">
                <button type="button"
                        id="toggle-password-visibility"
                        class="absolute inset-y-0 right-0 px-3 text-sm text-slate-400 hover:text-slate-200 transition-colors"
                        aria-controls="password"
                        aria-label="Mostrar senha"
                        aria-pressed="false">
                    Mostrar
                </button>
            </div>
            @error('password') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember"
                       class="rounded border-slate-600 bg-pitch-800 text-emerald-600 focus:ring-emerald-500">
                <span class="text-sm text-slate-400">Lembrar de mim</span>
            </label>
            @if(Route::has('password.request'))
            <a href="{{ route('password.request') }}"
               class="text-sm text-emerald-400 hover:text-emerald-300 transition-colors">
                Esqueceu a senha?
            </a>
            @endif
        </div>

        <button type="submit" class="btn-primary w-full justify-center mt-2">
            Entrar
        </button>

        @if(Route::has('register'))
        <p class="text-center text-sm text-slate-500">
            Não tem conta?
            <a href="{{ route('register') }}" class="text-emerald-400 hover:text-emerald-300 transition-colors">
                Criar conta
            </a>
        </p>
        @endif

        <p class="text-center text-xs text-slate-500 pt-2">
            Ao entrar, você concorda com os
            <button type="button" @click="openLegal('eula')" class="text-emerald-400 hover:text-emerald-300 transition-colors cursor-pointer">Termos de Uso</button>
            e a
            <button type="button" @click="openLegal('privacy')" class="text-emerald-400 hover:text-emerald-300 transition-colors cursor-pointer">Política de Privacidade</button>.
        </p>
    </form>

    @if (session('status') || session('auth_error'))
        @push('scripts')
            <script>
                @if (session('status'))
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso',
                        text: @json(session('status')),
                        confirmButtonText: 'Entendi'
                    });
                @elseif (session('auth_error'))
                    Swal.fire({
                        icon: 'error',
                        title: 'Atenção',
                        text: @json(session('auth_error')),
                        confirmButtonText: 'Entendi'
                    });
                @endif
            </script>
        @endpush
    @endif

    @push('scripts')
        <script>
            (() => {
                const passwordInput = document.getElementById('password');
                const toggleButton = document.getElementById('toggle-password-visibility');

                if (!passwordInput || !toggleButton) {
                    return;
                }

                toggleButton.addEventListener('click', () => {
                    const isHidden = passwordInput.type === 'password';
                    passwordInput.type = isHidden ? 'text' : 'password';
                    toggleButton.textContent = isHidden ? 'Ocultar' : 'Mostrar';
                    toggleButton.setAttribute('aria-label', isHidden ? 'Ocultar senha' : 'Mostrar senha');
                    toggleButton.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                });
            })();
        </script>
    @endpush
</x-guest-layout>
