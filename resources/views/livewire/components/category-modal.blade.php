<?php
use function Livewire\Volt\{state, on};
use App\Models\Category;
use App\Models\Transaction; // [Adicionado] Necessário para atualizar as transações

state([
    'id' => null,
    'name' => '',
    'limit' => '',
    'scope' => 'personal',
    'isEditing' => false
]);

// Abre modal para criação, recebendo o escopo atual
on(['open-new-category' => function($scope = 'personal') {
    $this->reset();
    $this->isEditing = false;
    $this->scope = $scope; // Define o escopo corretamente
}]);

// Abre modal para edição
on(['edit-category' => function($id, $name, $limit, $scope) {
    $this->id = $id;
    $this->name = $name;
    $this->limit = $limit;
    $this->scope = $scope;
    $this->isEditing = true;
}]);

$save = function() {
    $this->validate([
        'name' => 'required',
        'limit' => 'required',
        'scope' => 'required'
    ]);

    if ($this->isEditing && $this->id) {
        // Atualizar
        $cat = Category::find($this->id);
        if ($cat && ($cat->user_id === auth()->id() || in_array($cat->user_id, auth()->user()->getFamilyUserIds()))) {
            $oldName = $cat->name; // Guarda o nome antigo

            $cat->update([
                'name' => $this->name,
                'limit' => $this->limit,
                'scope' => $this->scope
            ]);

            // Se o nome mudou, atualiza todas as transações vinculadas a este nome
            if ($oldName !== $this->name) {
                // Define quais usuários devem ser afetados (se for compartilhado, afeta a família)
                $targetUserIds = ($this->scope === 'shared')
                    ? auth()->user()->getFamilyUserIds()
                    : [auth()->id()];

                Transaction::whereIn('user_id', $targetUserIds)
                    ->where('scope', $this->scope)
                    ->where('category', $oldName)
                    ->update(['category' => $this->name]);
            }

            $msg = 'Categoria atualizada!';
        }
    } else {
        // Criar
        Category::create([
            'user_id' => auth()->id(),
            'name' => $this->name,
            'limit' => $this->limit,
            'scope' => $this->scope
        ]);
        $msg = 'Categoria criada!';
    }

    $this->dispatch('close-modal-category');
    $this->dispatch('notify', $msg ?? 'Sucesso');
    // Recarrega a página para atualizar os dados visuais
    $this->redirect(request()->header('Referer'), navigate: true);
};
?>

<div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm"
     :class="{ 'active': categoryModalOpen }" @click="categoryModalOpen = false">
    <div class="modal-content bg-white w-full max-w-sm p-6 rounded-xl shadow-2xl mx-4" @click.stop>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">{{ $isEditing ? 'Editar' : 'Nova' }} Categoria</h3>
            <button class="text-gray-400 hover:text-gray-600" @click="categoryModalOpen = false"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <form wire:submit="save" class="space-y-4">

            <div class="flex p-1 bg-gray-100 rounded-lg">
                <button type="button" wire:click="$set('scope', 'personal')"
                    class="flex-1 py-1.5 text-sm font-medium rounded-md transition {{ $scope === 'personal' ? 'bg-white shadow text-gray-800' : 'text-gray-500' }}">
                    Pessoal
                </button>
                <button type="button" wire:click="$set('scope', 'shared')"
                    class="flex-1 py-1.5 text-sm font-medium rounded-md transition {{ $scope === 'shared' ? 'bg-white shadow text-purple-600' : 'text-gray-500' }}">
                    Compartilhado
                </button>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Nome</label>
                <input type="text" wire:model="name" placeholder="Ex: Casa, Lazer" class="mt-1 block w-full rounded-lg border border-gray-300 p-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Limite Mensal (R$)</label>
                <input type="number" step="0.01" wire:model="limit" placeholder="Ex: 1000" class="mt-1 block w-full rounded-lg border border-gray-300 p-2 focus:ring-primary focus:border-primary">
            </div>

            <div class="flex justify-end pt-4">
                <button type="button" @click="categoryModalOpen = false" class="mr-3 text-gray-500 hover:text-gray-700 font-medium">Cancelar</button>
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg font-medium shadow-md hover:bg-secondary transition">Salvar</button>
            </div>
        </form>
    </div>
</div>
