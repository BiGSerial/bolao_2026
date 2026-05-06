<x-guest-layout>
    <h2 class="text-xl font-bold text-white mb-6">Criar conta</h2>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="label">Nome completo</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}"
                   required autofocus autocomplete="name"
                   class="input-field" placeholder="Seu nome">
            @error('name') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="label">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   required autocomplete="username"
                   class="input-field" placeholder="seu@email.com">
            @error('email') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="area" class="label">Área / Setor <span class="text-slate-600">(opcional)</span></label>
            <input id="area" type="text" name="area" value="{{ old('area') }}"
                   class="input-field" placeholder="Ex: TI, Comercial, CIP...">
            @error('area') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="phone" class="label">Telefone <span class="text-slate-600">(opcional)</span></label>
            <input id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                   class="input-field" placeholder="+55 (11) 99999-9999">
            @error('phone') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Senha</label>
            <input id="password" type="password" name="password"
                   required autocomplete="new-password"
                   class="input-field" placeholder="Mínimo 8 caracteres">
            @error('password') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="label">Confirmar senha</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                   required autocomplete="new-password"
                   class="input-field" placeholder="Repita a senha">
            @error('password_confirmation') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-primary w-full justify-center mt-2">
            Criar conta
        </button>

        <p class="text-center text-sm text-slate-500">
            Já tem conta?
            <a href="{{ route('login') }}" class="text-emerald-400 hover:text-emerald-300 transition-colors">Entrar</a>
        </p>
    </form>

    @if ($errors->any())
        @push('scripts')
            <script>
                const registerErrors = @json($errors->all());
                Swal.fire({
                    icon: 'error',
                    title: 'Não foi possível concluir o cadastro',
                    html: registerErrors.map((message) => (
                        `<div>${Swal.escapeHtml(message)}</div>`
                    )).join(''),
                    confirmButtonText: 'Entendi'
                });
            </script>
        @endpush
    @endif
</x-guest-layout>
