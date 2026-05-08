<x-guest-layout>
    <h2 class="text-xl font-bold text-white mb-2">Recuperar senha</h2>
    <div class="mb-6 text-sm text-slate-400">
        Informe seu e-mail e enviaremos um link para você redefinir sua senha.
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="'E-mail'" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus placeholder="seu@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Enviar link de recuperação
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
