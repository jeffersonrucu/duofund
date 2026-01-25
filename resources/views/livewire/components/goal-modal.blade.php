<?php

use function Livewire\Volt\{state, on};
use App\Models\Goal;

state([
    'goal_id' => null, // ID para controle de edição
    'name' => '',
    'target' => '',
    'current' => 0,
    'is_private' => true // Default Pessoal
]);

// Listener para resetar o modal ao clicar em "Nova Meta"
on(['reset-modal' => function () {
    $this->reset();
}]);

// Listener para carregar dados ao clicar em "Editar"
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
        'name' => 'required|string',
        'target' => 'required|numeric|min:0.01',
        'current' => 'required|numeric|min:0',
        'is_private' => 'boolean'
    ]);

    // Define o escopo com base na privacidade selecionada
    $scope = $this->is_private ? 'personal' : 'shared';

    if ($this->goal_id) {
        // Atualizar existente
        $goal = Goal::find($this->goal_id);

        // Verifica permissão (apenas dono edita)
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
        // Criar nova
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
    // Recarrega a página ou navega para atualizar a lista
    $this->redirect(request()->header('Referer'), navigate: true);
};

?>

<div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm"
     :class="{ 'active': goalModalOpen }"
     @close-modal-goal.window="goalModalOpen = false"
     @click="goalModalOpen = false"
     x-cloak
     x-show="goalModalOpen"
     x-transition.opacity>

    <div class="modal-content bg-white w-full max-w-sm p-6 rounded-xl shadow-2xl mx-4 border-t-4 border-accent" @click.stop>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">{{ $goal_id ? 'Editar Meta' : 'Nova Meta' }}</h3>
            <button class="text-gray-400 hover:text-gray-600" @click="goalModalOpen = false"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <form wire:submit="save" class="space-y-4">

            <div class="flex p-1 bg-gray-100 rounded-lg">
                <button type="button" wire:click="$set('is_private', true)"
                    class="flex-1 py-1.5 text-sm font-medium rounded-md transition {{ $is_private ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700' }}">
                    👤 Pessoal
                </button>
                <button type="button" wire:click="$set('is_private', false)"
                    class="flex-1 py-1.5 text-sm font-medium rounded-md transition {{ !$is_private ? 'bg-white shadow text-purple-600' : 'text-gray-500 hover:text-gray-700' }}">
                    🏠 Compartilhado
                </button>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Nome da Meta</label>
                <input type="text" wire:model="name" required placeholder="Ex: Viagem Europa" class="mt-1 block w-full rounded-lg border-gray-300 border p-2 focus:ring-accent focus:border-accent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Valor Alvo (R$)</label>
                <input type="number" wire:model="target" required step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 border p-2 focus:ring-accent focus:border-accent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Já Guardado (R$)</label>
                <input type="number" wire:model="current" required step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 border p-2 focus:ring-accent focus:border-accent">
            </div>

            <div class="flex justify-end pt-4">
                <button type="button" @click="goalModalOpen = false" class="mr-3 px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">Cancelar</button>
                <button type="submit" class="bg-accent text-white px-4 py-2 rounded-lg hover:bg-yellow-600 font-medium transition shadow-md">
                    {{ $goal_id ? 'Salvar Alterações' : 'Criar Meta' }}
                </button>
            </div>
        </form>
    </div>
</div>
