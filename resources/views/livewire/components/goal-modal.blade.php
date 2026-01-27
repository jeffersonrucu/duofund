<?php

use function Livewire\Volt\{state, on};
use App\Models\Goal;

state([
    'goal_id' => null,
    'name' => '',
    'target' => '',
    'current' => 0,
    'is_private' => true
]);

on(['reset-modal' => function ($scope = null) {
    $this->reset();
    if ($scope) {
        $this->is_private = ($scope === 'personal');
    }
}]);

on(['edit-goal' => function ($id) {
    $goal = Goal::find($id);

    if ($goal) {
        $this->goal_id = $goal->id;
        $this->name = $goal->name;
        $this->target = $goal->target;
        $this->current = $goal->current;
        $this->is_private = (bool) $goal->is_private;
    }
}]);

$save = function() {
    $this->validate([
        'name' => 'required|string|max:255',
        'target' => 'required|numeric|min:0.01',
        'current' => 'required|numeric|min:0|lte:target',
        'is_private' => 'boolean'
    ], [
        'current.lte' => 'O valor guardado não pode ser maior que o alvo.',
        'target.min' => 'O alvo deve ser maior que zero.',
    ]);

    $scope = $this->is_private ? 'personal' : 'shared';

    if ($this->goal_id) {
        $goal = Goal::find($this->goal_id);

        if ($goal && $goal->user_id === auth()->id()) {
            $goal->update([
                'name' => $this->name,
                'target' => $this->target,
                'current' => $this->current,
                'is_private' => $this->is_private,
                'scope' => $scope
            ]);
            $this->dispatch('notify', 'Meta atualizada com sucesso!');
        }
    } else {
        Goal::create([
            'user_id' => auth()->id(),
            'name' => $this->name,
            'target' => $this->target,
            'current' => $this->current,
            'is_private' => $this->is_private,
            'scope' => $scope
        ]);
        $this->dispatch('notify', 'Meta criada com sucesso!');
    }

    $this->reset();
    $this->dispatch('close-modal-goal');
    $this->redirect(request()->header('Referer'), navigate: true);
};

?>

<div class="modal-overlay fixed inset-0 z-50 flex sm:items-center sm:justify-center bg-gray-900/50 backdrop-blur-sm"
     :class="{ 'active': goalModalOpen }"
     @close-modal-goal.window="goalModalOpen = false"
     @click="goalModalOpen = false"
     x-cloak
     x-show="goalModalOpen"
     x-transition.opacity>

    <div class="bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-2xl sm:mx-4 flex flex-col" @click.stop>
        <!-- Header -->
        <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">{{ $goal_id ? 'Editar Meta' : 'Nova Meta' }}</h3>
            <button class="text-gray-400 hover:text-gray-600 p-1" @click="goalModalOpen = false">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Form -->
        <form wire:submit="save" class="flex-1 overflow-y-auto p-4 space-y-3">
            <!-- Toggle Pessoal/Compartilhado -->
            <div>
                <div class="flex p-0.5 bg-gray-100 rounded-lg">
                    <button type="button" wire:click="$set('is_private', true)"
                        class="flex-1 py-1.5 text-[11px] font-medium rounded-md transition {{ $is_private ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700' }}">
                        <i data-lucide="user" class="w-3 h-3 inline mr-0.5"></i> Minha Meta
                    </button>
                    <button type="button" wire:click="$set('is_private', false)"
                        class="flex-1 py-1.5 text-[11px] font-medium rounded-md transition {{ !$is_private ? 'bg-white shadow text-purple-600' : 'text-gray-500 hover:text-gray-700' }}">
                        <i data-lucide="users" class="w-3 h-3 inline mr-0.5"></i> Nossa Meta
                    </button>
                </div>
                <p class="text-[10px] text-gray-400 mt-1 text-center">
                    @if($is_private)
                        Apenas você verá esta meta
                    @else
                        Você e seu parceiro(a) verão esta meta
                    @endif
                </p>
            </div>

            <!-- Nome -->
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Nome da Meta</label>
                <input type="text" wire:model="name" required placeholder="Ex: Viagem Europa"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-accent focus:border-accent">
                @error('name') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
            </div>

            <!-- Valor Alvo -->
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Quanto quer juntar? (R$)</label>
                <input type="number" wire:model="target" required step="0.01" min="0.01" placeholder="10000.00"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-accent focus:border-accent">
                @error('target') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
            </div>

            <!-- Valor Atual -->
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Já tem guardado? (R$)</label>
                <input type="number" wire:model="current" required step="0.01" min="0" placeholder="0.00"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-accent focus:border-accent">
                <p class="text-[10px] text-gray-400 mt-1">Valor inicial que você já poupou</p>
                @error('current') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
            </div>
        </form>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-gray-100 flex gap-2">
            <button type="button" @click="goalModalOpen = false"
                class="flex-1 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Cancelar
            </button>
            <button type="submit" form="goal-form" wire:click="save"
                class="flex-1 py-2.5 text-sm font-semibold text-white bg-accent rounded-lg hover:bg-yellow-600 transition">
                {{ $goal_id ? 'Salvar' : 'Criar Meta' }}
            </button>
        </div>
    </div>
</div>
