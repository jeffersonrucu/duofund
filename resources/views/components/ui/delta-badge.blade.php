{{-- Variação percentual contra o mês anterior.
     `delta` null = não havia base de comparação (mês anterior zerado).
     `invert` para despesas, onde subir é ruim. --}}
@props([
    'delta',
    'invert' => false,
])

@if($delta === null)
    <span {{ $attributes->merge(['class' => 'text-[9px] text-gray-300']) }}>—</span>
@else
    @php
        $subiu = $delta > 0;
        $neutro = abs($delta) < 0.05;
        $ruim = $invert ? $subiu : ! $subiu;

        $cor = $neutro
            ? 'text-gray-400 bg-gray-50'
            : ($ruim ? 'text-red-600 bg-red-50' : 'text-green-600 bg-green-50');
    @endphp

    <span {{ $attributes->merge(['class' => "text-[9px] font-semibold tabular-nums rounded px-1 py-0.5 {$cor}"]) }}
          title="Contra o mês anterior">
        @if($neutro)
            =
        @else
            {{ $subiu ? '▲' : '▼' }} {{ number_format(abs($delta), 1, ',', '.') }}%
        @endif
    </span>
@endif
