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
            <input id="password" type="password" name="password"
                   required autocomplete="current-password"
                   class="input-field"
                   placeholder="••••••••">
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
    </form>

    @if (session('register_success') || session('status'))
        @push('scripts')
            <script>
                @if (session('register_success'))
                    Swal.fire({
                        icon: 'success',
                        title: 'Cadastro realizado com sucesso!',
                        text: 'Sua conta foi criada e está aguardando aprovação do administrador.',
                        confirmButtonText: 'Entendi'
                    });
                @elseif (session('status'))
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso',
                        text: @json(session('status')),
                        confirmButtonText: 'Entendi'
                    });
                @endif
            </script>
        @endpush
    @endif
</x-guest-layout>
