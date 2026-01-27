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
    'modalOpen' => false
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
        $this->modalOpen = true;
    }
}]);

$save = function() {
    $this->validate([
        'amount' => 'required|numeric|min:0.01',
        'date' => 'required|date',
    ]);

    $goal = Goal::find($this->goal_id);
    if (!$goal) {
        $this->dispatch('notify', 'Meta não encontrada.');
        return;
    }

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
    $this->redirect(request()->header('Referer'), navigate: true);
};

?>

<div class="modal-overlay fixed inset-0 z-50 flex sm:items-center sm:justify-center bg-gray-900/50 backdrop-blur-sm"
     :class="{ 'active': $wire.modalOpen }"
     x-show="$wire.modalOpen"
     x-cloak
     x-transition.opacity
     @click="$wire.set('modalOpen', false)">

    <div class="bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-2xl sm:mx-4 flex flex-col" @click.stop>
        <!-- Header -->
        <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100">
            <div>
                <h3 class="text-base font-semibold text-gray-800">Reservar para Meta</h3>
                <p class="text-[11px] text-gray-500">{{ $goal_name }}</p>
            </div>
            <button class="text-gray-400 hover:text-gray-600 p-1" wire:click="$set('modalOpen', false)">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
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
                @php
                    $percent = $goal_target > 0 ? min(100, ($goal_current / $goal_target) * 100) : 0;
                @endphp
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-green-500 h-1.5 rounded-full transition-all" style="width: {{ $percent }}%"></div>
                </div>
                <p class="text-[10px] text-gray-400 mt-1 text-right">{{ number_format($percent, 0) }}% concluído</p>
            </div>

            <!-- Valor -->
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Quanto deseja reservar? (R$)</label>
                <input type="number" step="0.01" min="0.01" wire:model="amount" required
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-green-500 focus:border-green-500 text-center font-semibold text-green-600"
                    placeholder="0,00">
                @error('amount') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
            </div>

            <!-- Data -->
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Data</label>
                <input type="date" wire:model="date" required
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-green-500 focus:border-green-500">
            </div>

            <!-- Info box -->
            <div class="bg-green-50 p-2.5 rounded-lg text-[10px] text-green-700 border border-green-100">
                <i data-lucide="piggy-bank" class="w-3 h-3 inline mr-0.5"></i>
                <strong>Isso é uma reserva, não uma despesa.</strong>
                <span class="text-green-600 block mt-0.5">O valor será registrado como poupança para esta meta.</span>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-gray-100 flex gap-2">
            <button type="button" wire:click="$set('modalOpen', false)"
                class="flex-1 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Cancelar
            </button>
            <button type="submit" wire:click="save"
                class="flex-1 py-2.5 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition flex items-center justify-center">
                <i data-lucide="piggy-bank" class="w-4 h-4 mr-1.5"></i> Reservar
            </button>
        </div>
    </div>
</div>
