{{-- Toggle Pessoal/Casal de modais, ligado a uma propriedade Livewire. --}}
@props([
    'model' => 'scope',        // propriedade Livewire (recebe 'personal' | 'shared')
    'value',                   // valor atual ($scope do componente pai)
    'personalLabel' => 'Só eu',
    'sharedLabel' => 'Casal',
])

<div {{ $attributes->merge(['class' => 'flex bg-gray-100 p-0.5 rounded-lg']) }} role="group" aria-label="Visibilidade">
    <button type="button" wire:click="$set('{{ $model }}', 'personal')"
        aria-pressed="{{ $value === 'personal' ? 'true' : 'false' }}"
        class="flex-1 py-1.5 text-[11px] font-medium rounded transition {{ $value === 'personal' ? 'bg-white shadow text-gray-800' : 'text-gray-500' }}">
        {{ $personalLabel }}
    </button>
    <button type="button" wire:click="$set('{{ $model }}', 'shared')"
        aria-pressed="{{ $value === 'shared' ? 'true' : 'false' }}"
        class="flex-1 py-1.5 text-[11px] font-medium rounded transition {{ $value === 'shared' ? 'bg-white shadow text-purple-600' : 'text-gray-500' }}">
        {{ $sharedLabel }}
    </button>
</div>
