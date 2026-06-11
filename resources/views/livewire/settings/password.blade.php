<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
        $this->dispatch('notify', 'Senha atualizada.');
    }
}; ?>

<div>
    <x-settings.layout :heading="__('Senha')" :subheading="__('Use uma senha longa e única para manter a conta segura.')">
        <form wire:submit="updatePassword" class="space-y-5">
            @php
                $field = 'w-full rounded-xl border bg-gray-50/60 px-4 py-3 text-sm text-gray-900 transition focus:border-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10';
            @endphp

            <div>
                <label for="current_password" class="mb-1.5 block text-sm font-semibold text-gray-700">Senha atual</label>
                <input id="current_password" type="password" wire:model="current_password" required autocomplete="current-password"
                       class="{{ $field }} @error('current_password') border-red-300 @else border-gray-200 @enderror">
                @error('current_password') <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-semibold text-gray-700">Nova senha</label>
                <input id="password" type="password" wire:model="password" required autocomplete="new-password"
                       class="{{ $field }} @error('password') border-red-300 @else border-gray-200 @enderror">
                @error('password') <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-gray-700">Confirmar nova senha</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" required autocomplete="new-password"
                       class="{{ $field }} border-gray-200">
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary/25 transition hover:bg-secondary active:scale-[.99]">
                    Salvar
                </button>
                <span x-data="{ shown: false }" x-cloak x-show="shown"
                      x-on:password-updated.window="shown = true; setTimeout(() => shown = false, 2500)"
                      x-transition class="text-sm font-medium text-green-600">Salvo!</span>
            </div>
        </form>
    </x-settings.layout>
</div>
