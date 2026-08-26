{{-- Toggle de visão personal/shared usado no header das páginas (chama setView). --}}
@props([
    'view',                          // visão atual da página
    'personalLabel' => 'Meu Dinheiro',
    'sharedLabel' => 'Nosso Dinheiro',
])

<div {{ $attributes->merge(['class' => 'bg-white p-0.5 rounded-lg shadow-sm border border-gray-200 inline-flex']) }} role="group" aria-label="Visão">
    <button wire:click="setView('personal')" aria-pressed="{{ $view === 'personal' ? 'true' : 'false' }}"
        class="px-3 py-1.5 rounded-md text-[11px] sm:text-sm font-medium transition flex items-center {{ $view === 'personal' ? 'bg-primary text-white shadow' : 'text-gray-500 hover:bg-gray-50' }}">
        <x-lucide-user class="w-3 h-3 sm:w-4 sm:h-4 mr-1" aria-hidden="true" /> {{ $personalLabel }}
    </button>
    <button wire:click="setView('shared')" aria-pressed="{{ $view === 'shared' ? 'true' : 'false' }}"
        class="px-3 py-1.5 rounded-md text-[11px] sm:text-sm font-medium transition flex items-center {{ $view === 'shared' ? 'bg-purple-600 text-white shadow' : 'text-gray-500 hover:bg-gray-50' }}">
        <x-lucide-users class="w-3 h-3 sm:w-4 sm:h-4 mr-1" aria-hidden="true" /> {{ $sharedLabel }}
    </button>
</div>
