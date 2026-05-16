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
            <label class="label">Apelido (nome de exibição) <span class="text-red-400">*</span></label>
            <input type="text" wire:model.blur="display_name"
                   maxlength="80" autocomplete="nickname"
                   placeholder="Como seu nome vai aparecer no bolão"
                   class="input-field">
            @error('display_name') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
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

        {{-- Aceite dos termos --}}
        <div class="pt-3 border-t border-slate-800 space-y-2">
            <label class="flex items-start gap-2.5 cursor-pointer group">
                <input type="checkbox" wire:model="acceptEula"
                       class="mt-0.5 w-4 h-4 rounded border-slate-600 bg-slate-800 text-emerald-500 focus:ring-emerald-500/40 focus:ring-offset-0 cursor-pointer shrink-0">
                <span class="text-xs text-slate-400 group-hover:text-slate-200 transition-colors select-none leading-relaxed">
                    Li e aceito os <button type="button" @click="openLegal('eula')" class="text-emerald-400 hover:underline">Termos de Uso</button>
                </span>
            </label>
            @error('acceptEula') <p class="ml-6 text-xs text-red-400">{{ $message }}</p> @enderror

            <label class="flex items-start gap-2.5 cursor-pointer group">
                <input type="checkbox" wire:model="acceptPrivacy"
                       class="mt-0.5 w-4 h-4 rounded border-slate-600 bg-slate-800 text-emerald-500 focus:ring-emerald-500/40 focus:ring-offset-0 cursor-pointer shrink-0">
                <span class="text-xs text-slate-400 group-hover:text-slate-200 transition-colors select-none leading-relaxed">
                    Li e aceito a <button type="button" @click="openLegal('privacy')" class="text-emerald-400 hover:underline">Política de Privacidade</button>
                </span>
            </label>
            @error('acceptPrivacy') <p class="ml-6 text-xs text-red-400">{{ $message }}</p> @enderror
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
