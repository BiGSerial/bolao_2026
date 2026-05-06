<div class="max-w-xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-2">Troca obrigatoria de senha</h1>
    <p class="text-sm text-gray-600 mb-6">No primeiro acesso, voce precisa definir uma nova senha.</p>

    @if (session('status'))
        <div class="mb-4 rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
            Verifique os campos e tente novamente.
        </div>
    @endif

    <form wire:submit="save" class="space-y-4">
        <div>
            <label class="block text-sm mb-1">Senha atual</label>
            <input type="password" wire:model.blur="current_password" class="w-full rounded border-gray-300" />
            @error('current_password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Nova senha</label>
            <input type="password" wire:model.blur="password" class="w-full rounded border-gray-300" />
            @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Confirmar nova senha</label>
            <input type="password" wire:model.blur="password_confirmation" class="w-full rounded border-gray-300" />
        </div>

        <button
            type="submit"
            class="inline-flex items-center px-4 py-2 rounded bg-indigo-600 text-white font-medium hover:bg-indigo-500 disabled:opacity-50"
            wire:loading.attr="disabled"
            wire:target="save"
        >
            <span wire:loading.remove wire:target="save">Confirmar nova senha</span>
            <span wire:loading wire:target="save">Salvando...</span>
        </button>
    </form>
</div>
