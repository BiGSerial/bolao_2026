<div>
    <h2 class="text-xl font-bold text-white mb-1">Criar conta</h2>
    <p class="text-sm text-slate-400 mb-6">Preencha os dados abaixo para solicitar acesso.</p>

    <form wire:submit="save" class="space-y-4">

        <div>
            <label class="label">Nome completo <span class="text-red-400">*</span></label>
            <input type="text" wire:model.blur="name"
                   autocomplete="name" placeholder="Seu nome completo"
                   class="input-field">
            @error('name') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label">E-mail <span class="text-red-400">*</span></label>
            <input type="email" wire:model.blur="email"
                   autocomplete="email" placeholder="seu@email.com"
                   class="input-field">
            @error('email') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label">Telefone <span class="text-red-400">*</span></label>
            <input type="text" wire:model.live="phone"
                   maxlength="15" inputmode="numeric"
                   placeholder="(11) 99999-9999"
                   class="input-field">
            @error('phone') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label">Senha <span class="text-red-400">*</span></label>
            <input type="password" wire:model.blur="password"
                   autocomplete="new-password" placeholder="Mínimo 8 caracteres"
                   class="input-field">
            @error('password') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label">Confirmar senha <span class="text-red-400">*</span></label>
            <input type="password" wire:model.blur="password_confirmation"
                   autocomplete="new-password" placeholder="Repita a senha"
                   class="input-field">
            @error('password_confirmation') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                wire:loading.attr="disabled" wire:loading.class="opacity-75"
                class="btn-primary w-full mt-2">
            <svg wire:loading wire:target="save" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span wire:loading.remove wire:target="save">Criar conta</span>
            <span wire:loading wire:target="save">Criando conta…</span>
        </button>

        <p class="text-center text-sm text-slate-500">
            Já tem conta?
            <a href="{{ route('login') }}" class="text-emerald-400 hover:text-emerald-300 transition-colors">Entrar</a>
        </p>
    </form>

    @script
    <script>
        $wire.on('swal:alert', (payload) => {
            const data = Array.isArray(payload) ? payload[0] : payload;
            const messages = data?.messages ?? [];

            Swal.fire({
                icon: data?.icon ?? 'info',
                title: data?.title ?? 'Aviso',
                html: messages.map((message) => (
                    `<div>${Swal.escapeHtml(message)}</div>`
                )).join(''),
                confirmButtonText: 'Entendi'
            });
        });
    </script>
    @endscript
</div>
