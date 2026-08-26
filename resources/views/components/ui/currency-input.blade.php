{{-- Input monetário com máscara pt-BR (Alpine.data('currencyInput') no layout).
     No servidor, normalize com App\Support\Money::toDecimal() antes de validar. --}}
@props([
    'model',                 // propriedade Livewire (string em formato pt-BR)
    'label' => null,
    'placeholder' => '0,00',
    'required' => false,
])

<div>
    @if($label)
        <label for="currency-{{ $model }}" class="block text-[11px] font-medium text-gray-500 mb-1">{{ $label }}</label>
    @endif
    <div class="relative" x-data="currencyInput('{{ $model }}')">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true">R$</span>
        <input type="text" inputmode="numeric" x-ref="amount" id="currency-{{ $model }}"
            placeholder="{{ $placeholder }}" @if($required) required @endif
            x-on:input="onInput($event)"
            {{ $attributes->merge(['class' => 'w-full pl-9 pr-3 py-2 text-sm font-semibold rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary']) }}>
    </div>
    @error($model) <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
</div>
