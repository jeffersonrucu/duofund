<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="mt-8 border-t border-gray-100 pt-6" x-data="{ open: false }">
    <h3 class="text-sm font-bold text-red-700">Excluir conta</h3>
    <p class="mt-1 text-sm text-gray-500">Apaga sua conta e todos os dados associados. Esta ação é permanente.</p>

    <button type="button" @click="open = true"
            class="mt-4 inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-100">
        <x-lucide-trash-2 class="h-4 w-4" /> Excluir conta
    </button>

    {{-- Modal de confirmação --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
         x-transition.opacity @click="open = false" @keydown.escape.window="open = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @click.stop>
            <div class="mb-4 flex items-start gap-3">
                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                    <x-lucide-alert-triangle class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Excluir sua conta?</h3>
                    <p class="mt-1 text-sm text-gray-500">Tudo será apagado de forma permanente. Digite sua senha para confirmar.</p>
                </div>
            </div>

            <form wire:submit="deleteUser" class="space-y-4">
                <div>
                    <input type="password" wire:model="password" placeholder="Sua senha" autocomplete="current-password"
                           class="w-full rounded-xl border bg-gray-50/60 px-4 py-3 text-sm text-gray-900 transition focus:border-red-400 focus:bg-white focus:outline-none focus:ring-4 focus:ring-red-100 @error('password') border-red-300 @else border-gray-200 @enderror">
                    @error('password') <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="open = false"
                            class="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                        Excluir conta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
