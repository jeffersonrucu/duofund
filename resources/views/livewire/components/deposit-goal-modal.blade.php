<?php

use function Livewire\Volt\{state, on};
use App\Models\Goal;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

state([
    'goal_id' => null,
    'goal_name' => '',
    'goal_target' => 0,
    'goal_current' => 0,
    'amount' => '',
    'date' => date('Y-m-d'),
    'modalOpen' => false,
    'mode' => 'deposit'
]);

on(['open-deposit-modal' => function($id, $name) {
    $goal = Goal::find($id);
    if ($goal) {
        $this->goal_id = $goal->id;
        $this->goal_name = $goal->name;
        $this->goal_target = $goal->target;
        $this->goal_current = $goal->current;
        $this->amount = '';
        $this->date = date('Y-m-d');
        $this->mode = 'deposit';
        $this->modalOpen = true;
    }
}]);

on(['open-withdraw-modal' => function($id, $name) {
    $goal = Goal::find($id);
    if ($goal) {
        $this->goal_id = $goal->id;
        $this->goal_name = $goal->name;
        $this->goal_target = $goal->target;
        $this->goal_current = $goal->current;
        $this->amount = '';
        $this->date = date('Y-m-d');
        $this->mode = 'withdraw';
        $this->modalOpen = true;
    }
}]);

$save = function() {
    $this->amount = \App\Support\Money::toDecimal($this->amount) ?? '';

    $this->validate([
        'amount' => 'required|numeric|min:0.01',
        'date' => 'required|date',
    ]);

    $goal = Goal::find($this->goal_id);
    if (!$goal || !$goal->manageableBy(auth()->user())) {
        $this->dispatch('notify', 'Meta não encontrada.');
        return;
    }

    if ($this->mode === 'withdraw') {
        $amount = min((float) $this->amount, $goal->current);
        $goal->decrement('current', $amount);

        $this->modalOpen = false;
        $this->dispatch('goal-updated');
        $this->dispatch('notify', 'Valor retirado da meta.');
    } else {
        DB::transaction(function() use ($goal) {
            $goal->increment('current', $this->amount);

            $scope = $goal->is_private ? 'personal' : 'shared';

            Transaction::create([
                'user_id' => auth()->id(),
                'description' => "Reserva para meta: {$this->goal_name}",
                'amount' => $this->amount,
                'type' => 'savings',
                'category' => 'Reserva para Metas',
                'date' => $this->date,
                'scope' => $scope,
                'is_recurring' => false,
                'is_installment' => false
            ]);
        });

        $this->modalOpen = false;
        $this->dispatch('goal-updated');
        $this->dispatch('notify', 'Valor reservado para a meta!');
    }

    $this->redirect(request()->header('Referer') ?: route('dashboard'), navigate: true);
};

?>

<div class="modal-overlay fixed inset-0 z-50 flex sm:items-center sm:justify-center bg-gray-900/50 backdrop-blur-sm" role="dialog" aria-modal="true"
     :class="{ 'active': $wire.modalOpen }"
     x-show="$wire.modalOpen"
     x-cloak
     x-transition.opacity
     @click="$wire.set('modalOpen', false)">

    <div class="bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-2xl sm:mx-4 flex flex-col"
         x-data="sheet(() => $wire.set('modalOpen', false))" :style="sheetStyle" @click.stop>
        <!-- Header (alça de arrasto + título) -->
        <div x-on:touchstart.passive="start($event)" x-on:touchmove="move($event)" x-on:touchend="end()">
            <div class="sm:hidden flex justify-center pt-2"><span class="w-10 h-1 bg-gray-300 rounded-full"></span></div>
            <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">
                        {{ $mode === 'deposit' ? 'Reservar para Meta' : 'Retirar da Meta' }}
                    </h3>
                    <p class="text-[11px] text-gray-500">{{ $goal_name }}</p>
                </div>
                <button class="text-gray-400 hover:text-gray-600 p-2.5 sm:p-1 -mr-1" wire:click="$set('modalOpen', false)" aria-label="Fechar">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>
        </div>

        <!-- Content -->
        <form wire:submit="save" class="flex-1 overflow-y-auto p-4 space-y-3">
            <!-- Progresso atual -->
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="flex justify-between text-[11px] mb-1.5">
                    <span class="text-gray-500">Progresso atual</span>
                    <span class="font-medium text-gray-700">
                        R$ {{ number_format($goal_current, 2, ',', '.') }} / R$ {{ number_format($goal_target, 2, ',', '.') }}
                    </span>
                </div>
                @php $percent = $goal_target > 0 ? min(100, ($goal_current / $goal_target) * 100) : 0; @endphp
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full transition-all" style="width: {{ $percent }}%"></div>
                </div>
                <p class="text-[10px] text-gray-400 mt-1 text-right">{{ number_format($percent, 0) }}% concluído</p>
            </div>

            <!-- Valor -->
            <div>
                <x-ui.currency-input model="amount" required
                    :label="$mode === 'deposit' ? 'Quanto deseja reservar? (R$)' : 'Quanto deseja retirar? (R$)'"
                    class="{{ $mode === 'deposit'
                        ? 'focus:ring-green-500 focus:border-green-500 text-green-600'
                        : 'focus:ring-red-500 focus:border-red-500 text-red-600' }}" />
                @if($mode === 'withdraw')
                <p class="text-[10px] text-gray-400 mt-1">Máximo disponível: R$ {{ number_format($goal_current, 2, ',', '.') }}</p>
                @endif
            </div>

            @if($mode === 'deposit')
            <!-- Data -->
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Data</label>
                <input type="date" wire:model="date" required
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-green-500 focus:border-green-500">
            </div>

            <div class="bg-green-50 p-2.5 rounded-lg text-[10px] text-green-700 border border-green-100">
                <x-lucide-piggy-bank class="w-3 h-3 inline mr-0.5" />
                <strong>Isso é uma reserva, não uma despesa.</strong>
                <span class="text-green-600 block mt-0.5">O valor será registrado como poupança para esta meta.</span>
            </div>
            @else
            <div class="bg-red-50 p-2.5 rounded-lg text-[10px] text-red-700 border border-red-100">
                <x-lucide-info class="w-3 h-3 inline mr-0.5" />
                <strong>Retirada de meta.</strong>
                <span class="text-red-600 block mt-0.5">O valor será subtraído do progresso. Limite: valor já acumulado.</span>
            </div>
            @endif
        </form>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-gray-100 flex gap-2">
            <button type="button" wire:click="$set('modalOpen', false)"
                class="flex-1 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Cancelar
            </button>
            <button type="submit" wire:click="save"
                class="flex-1 py-2.5 text-sm font-semibold text-white rounded-lg transition flex items-center justify-center
                    {{ $mode === 'deposit' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }}">
                @if($mode === 'deposit')
                    <x-lucide-piggy-bank class="w-4 h-4 mr-1.5" /> Reservar
                @else
                    <x-lucide-minus-circle class="w-4 h-4 mr-1.5" /> Retirar
                @endif
            </button>
        </div>
    </div>
</div>
