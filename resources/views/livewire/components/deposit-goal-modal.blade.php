<?php

use function Livewire\Volt\{state, on};
use App\Models\Goal;
use App\Models\Transaction;

state([
    'goal_id' => null,
    'goal_name' => '',
    'amount' => '',
    'date' => date('Y-m-d'),
    'modalOpen' => false
]);

on(['open-deposit-modal' => function($id, $name) {
    $this->goal_id = $id;
    $this->goal_name = $name;
    $this->amount = '';
    $this->date = date('Y-m-d');
    $this->modalOpen = true;
}]);

$save = function() {
    $this->validate([
        'amount' => 'required|numeric|min:0.01',
        'date' => 'required|date',
    ]);

    $goal = Goal::find($this->goal_id);
    if ($goal) {
        // 1. Atualiza a Meta
        $goal->increment('current', $this->amount);

        // 2. Define o escopo da transação (Se a meta é privada -> Scope Pessoal)
        $scope = $goal->is_private ? 'personal' : 'shared';

        // 3. Cria a Despesa no escopo correspondente
        Transaction::create([
            'user_id' => auth()->id(),
            'description' => "Depósito na meta: {$this->goal_name}",
            'amount' => $this->amount,
            'type' => 'expense',
            'category' => 'Metas / Investimentos',
            'date' => $this->date,
            'scope' => $scope, // <--- Aqui está a lógica
            'is_recurring' => false,
            'is_installment' => false
        ]);
    }

    $this->modalOpen = false;
    $this->dispatch('goal-updated');
    $this->dispatch('notify', 'Valor adicionado à meta!');
    $this->redirect(request()->header('Referer'), navigate: true);
};

?>

<div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm"
     :class="{ 'active': $wire.modalOpen }"
     x-show="$wire.modalOpen"
     x-cloak
     x-transition.opacity>
    
    <div class="modal-content bg-white w-full max-w-sm p-6 rounded-xl shadow-2xl mx-4 border-t-4 border-green-500" @click.stop>
        
        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-xl font-bold text-gray-800">Adicionar Valor</h3>
                <p class="text-xs text-gray-500">Meta: <span class="font-bold">{{ $goal_name }}</span></p>
            </div>
            <button class="text-gray-400 hover:text-gray-600" wire:click="$set('modalOpen', false)">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Valor a depositar (R$)</label>
                <input type="number" step="0.01" wire:model="amount" required class="mt-1 block w-full rounded-lg border-gray-300 border p-2 focus:ring-green-500 focus:border-green-500 text-lg font-semibold text-green-600">
                @error('amount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Data do Depósito</label>
                <input type="date" wire:model="date" required class="mt-1 block w-full rounded-lg border-gray-300 border p-2 focus:ring-green-500 focus:border-green-500">
            </div>

            <div class="bg-blue-50 p-3 rounded-lg text-xs text-blue-700">
                <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                O valor sairá do seu saldo atual (conforme o tipo da meta).
            </div>

            <div class="flex justify-end pt-4">
                <button type="button" wire:click="$set('modalOpen', false)" class="mr-3 px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">Cancelar</button>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-medium transition shadow-md flex items-center">
                    <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i> Confirmar
                </button>
            </div>
        </form>
    </div>
</div>