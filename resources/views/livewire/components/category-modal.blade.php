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
    $this->limit = \App\Support\Money::toDecimal($this->limit) ?? '';

    $this->name = trim($this->name);

    $this->validate([
        'name' => 'required|string|max:100',
        'limit' => 'nullable|numeric|min:0',
        'scope' => 'required|in:personal,shared'
    ]);

    // Nome único por escopo, ignorando caixa — a família inteira enxerga o shared
    $duplicate = Category::forView(auth()->user(), $this->scope)
        ->whereRaw('LOWER(name) = ?', [mb_strtolower($this->name)])
        ->when($this->isEditing && $this->id, fn ($q) => $q->where('id', '!=', $this->id))
        ->exists();

    if ($duplicate) {
        $this->addError('name', 'Já existe uma categoria com esse nome.');
        return;
    }

    if ($this->isEditing && $this->id) {
        // Atualizar
        $cat = Category::find($this->id);
        if ($cat && $cat->manageableBy(auth()->user())) {
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
    $this->redirect(request()->header('Referer') ?: route('dashboard'), navigate: true);
};
?>

<div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 sm:p-4" role="dialog" aria-modal="true"
     :class="{ 'active': categoryModalOpen }" @click="categoryModalOpen = false">
    <div class="modal-content bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-2xl sm:max-h-[85vh] overflow-y-auto"
         x-data="sheet(() => categoryModalOpen = false)" :style="sheetStyle" @click.stop>

        <div class="sticky top-0 bg-white z-10"
             x-on:touchstart.passive="start($event)" x-on:touchmove="move($event)" x-on:touchend="end()">
            <div class="sm:hidden flex justify-center pt-2"><span class="w-10 h-1 bg-gray-300 rounded-full"></span></div>
            <div class="border-b border-gray-100 px-4 py-3 flex justify-between items-center">
                <h3 class="text-base font-semibold text-gray-900">{{ $isEditing ? 'Editar' : 'Nova' }} Categoria</h3>
                <button class="p-2.5 sm:p-1.5 -mr-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" @click="categoryModalOpen = false" aria-label="Fechar">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>
        </div>

        <form wire:submit="save" class="p-4 space-y-3">

            {{-- Toggle de escopo --}}
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Visibilidade</label>
                <x-ui.scope-toggle :value="$scope" />
            </div>

            <div>
                <label for="category-name" class="block text-[11px] font-medium text-gray-500 mb-1">Nome</label>
                <input id="category-name" type="text" wire:model="name" placeholder="Ex: Moradia, Lazer..."
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                @error('name') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
            </div>

            <x-ui.currency-input model="limit" placeholder="0 para sem limite">
                <x-slot:label>Limite mensal (R$) <span class="font-normal text-gray-400">— opcional</span></x-slot:label>
            </x-ui.currency-input>

            <x-ui.button type="submit">
                {{ $isEditing ? 'Salvar' : 'Criar Categoria' }}
            </x-ui.button>
        </form>
    </div>
</div>
