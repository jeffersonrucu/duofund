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
        'name' => 'required|string|max:100',
        'limit' => 'nullable|numeric|min:0',
        'scope' => 'required|in:personal,shared'
    ]);

    if ($this->isEditing && $this->id) {
        // Atualizar
        $cat = Category::find($this->id);
        if ($cat && ($cat->user_id === auth()->id() || in_array($cat->user_id, auth()->user()->getFamilyUserIds()))) {
            $oldName = $cat->name; // Guarda o nome antigo

            $cat->update([
                'name' => $this->name,
                'limit' => $this->limit ?: 0,
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
            'limit' => $this->limit ?: 0,
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

<div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 sm:p-4"
     :class="{ 'active': categoryModalOpen }" @click="categoryModalOpen = false">
    <div class="modal-content bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-2xl sm:max-h-[85vh] overflow-y-auto" @click.stop>
        
        <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex justify-between items-center z-10">
            <h3 class="text-base font-semibold text-gray-900">{{ $isEditing ? 'Editar' : 'Nova' }} Categoria</h3>
            <button class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" @click="categoryModalOpen = false">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form wire:submit="save" class="p-4 space-y-3">

            {{-- Toggle de escopo --}}
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Visibilidade</label>
                <div class="flex bg-gray-100 p-0.5 rounded-lg">
                    <button type="button" wire:click="$set('scope', 'personal')"
                        class="flex-1 py-1.5 text-[11px] font-medium rounded transition {{ $scope === 'personal' ? 'bg-white shadow text-gray-800' : 'text-gray-500' }}">
                        Só eu
                    </button>
                    <button type="button" wire:click="$set('scope', 'shared')"
                        class="flex-1 py-1.5 text-[11px] font-medium rounded transition {{ $scope === 'shared' ? 'bg-white shadow text-purple-600' : 'text-gray-500' }}">
                        Casal
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Nome</label>
                <input type="text" wire:model="name" placeholder="Ex: Moradia, Lazer..." 
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                @error('name') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Limite mensal (R$) <span class="font-normal text-gray-400">— opcional</span></label>
                <input type="number" step="0.01" min="0" wire:model="limit" placeholder="0 para sem limite"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                @error('limit') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="w-full py-2.5 rounded-lg font-semibold text-sm text-white bg-primary active:bg-blue-700 transition">
                {{ $isEditing ? 'Salvar' : 'Criar Categoria' }}
            </button>
        </form>
    </div>
</div>
