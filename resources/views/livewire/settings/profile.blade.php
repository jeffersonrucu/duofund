<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $name = '';
    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id)
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
        $this->dispatch('notify', 'Perfil atualizado.');
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));
            return;
        }

        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<div>
    <x-settings.layout :heading="__('Perfil')" :subheading="__('Atualize seu nome e e-mail.')">
        <form wire:submit="updateProfileInformation" class="space-y-5">
            {{-- Nome --}}
            <div>
                <label for="name" class="mb-1.5 block text-sm font-semibold text-gray-700">Nome</label>
                <input id="name" type="text" wire:model="name" required autofocus autocomplete="name"
                       class="w-full rounded-xl border bg-gray-50/60 px-4 py-3 text-sm text-gray-900 transition focus:border-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10 @error('name') border-red-300 @else border-gray-200 @enderror">
                @error('name') <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- E-mail --}}
            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold text-gray-700">E-mail</label>
                <input id="email" type="email" wire:model="email" required autocomplete="email"
                       class="w-full rounded-xl border bg-gray-50/60 px-4 py-3 text-sm text-gray-900 transition focus:border-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10 @error('email') border-red-300 @else border-gray-200 @enderror">
                @error('email') <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                    <p class="mt-2 text-xs text-gray-500">
                        Seu e-mail ainda não foi verificado.
                        <button type="button" wire:click.prevent="resendVerificationNotification" class="font-semibold text-primary hover:text-secondary">
                            Reenviar verificação
                        </button>
                    </p>
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-xs font-medium text-green-600">Um novo link de verificação foi enviado para seu e-mail.</p>
                    @endif
                @endif
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary/25 transition hover:bg-secondary active:scale-[.99]">
                    Salvar
                </button>
                <span x-data="{ shown: false }" x-cloak x-show="shown"
                      x-on:profile-updated.window="shown = true; setTimeout(() => shown = false, 2500)"
                      x-transition class="text-sm font-medium text-green-600">Salvo!</span>
            </div>
        </form>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</div>
