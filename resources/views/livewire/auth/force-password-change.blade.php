<div class="max-w-xl mx-auto p-6"
     x-data="{ newPassword: '', confirmPassword: '' }">
    <h1 class="text-2xl font-semibold mb-2">Troca obrigatoria de senha</h1>
    <p class="text-sm text-gray-600 mb-6">No primeiro acesso, voce precisa definir uma nova senha.</p>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
            Verifique os campos e tente novamente.
        </div>
    @endif

    <div class="space-y-4">
        <div>
            <label class="block text-sm mb-1">Nova senha</label>
            <input
                type="password"
                x-model="newPassword"
                autocomplete="new-password"
                class="input-field"
                placeholder="Digite a nova senha"
            />
            @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Confirmar nova senha</label>
            <input
                type="password"
                x-model="confirmPassword"
                autocomplete="new-password"
                class="input-field"
                placeholder="Repita a nova senha"
            />
            @error('password_confirmation') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <button
            type="button"
            @click="$wire.save(newPassword, confirmPassword)"
            wire:loading.attr="disabled"
            wire:target="save"
            class="inline-flex items-center px-4 py-2 rounded bg-indigo-600 text-white font-medium hover:bg-indigo-500 disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="save">Confirmar nova senha</span>
            <span wire:loading wire:target="save">Salvando...</span>
        </button>
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

        $wire.on('swal:password-changed', (payload) => {
            const data = Array.isArray(payload) ? payload[0] : payload;
            Swal.fire({
                icon: 'success',
                title: data?.title ?? 'Senha alterada com sucesso',
                text: data?.text ?? '',
                confirmButtonText: 'Continuar'
            }).then(() => {
                if (data?.redirect) {
                    window.location.href = data.redirect;
                }
            });
        });
    </script>
    @endscript
</div>
