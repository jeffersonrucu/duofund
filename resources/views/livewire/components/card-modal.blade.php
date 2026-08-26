<?php
use function Livewire\Volt\{state, on};
use App\Models\Card;

state([
    'id' => null,
    'last4' => '',
    'label' => '',
    'scope' => 'personal',
    'isEditing' => false,
]);

on(['open-new-card' => function($scope = 'personal') {
    $this->reset();
    $this->isEditing = false;
    $this->scope = $scope;
}]);

on(['edit-card' => function($id, $last4, $label, $scope) {
    $this->id = $id;
    $this->last4 = $last4;
    $this->label = $label ?? '';
    $this->scope = $scope;
    $this->isEditing = true;
}]);

$save = function() {
    $this->validate([
        'last4' => 'required|digits:4',
        'label' => 'nullable|string|max:50',
        'scope' => 'required|in:personal,shared',
    ], [], [
        'last4' => '4 últimos dígitos',
    ]);

    if ($this->isEditing && $this->id) {
        $card = Card::find($this->id);
        if ($card && $card->manageableBy(auth()->user())) {
            $card->update([
                'last4' => $this->last4,
                'label' => $this->label ?: null,
                'scope' => $this->scope,
            ]);
            $msg = 'Cartão atualizado!';
        }
    } else {
        Card::create([
            'user_id' => auth()->id(),
            'last4' => $this->last4,
            'label' => $this->label ?: null,
            'scope' => $this->scope,
        ]);
        $msg = 'Cartão adicionado!';
    }

    $this->dispatch('close-modal-card');
    $this->dispatch('notify', $msg ?? 'Sucesso');
    $this->redirect(request()->header('Referer') ?: route('dashboard'), navigate: true);
};
?>

<div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 sm:p-4" role="dialog" aria-modal="true"
     :class="{ 'active': cardModalOpen }" @click="cardModalOpen = false">
    <div class="modal-content bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-2xl sm:max-h-[85vh] overflow-y-auto"
         x-data="sheet(() => cardModalOpen = false)" :style="sheetStyle" @click.stop>

        <div class="sticky top-0 bg-white z-10"
             x-on:touchstart.passive="start($event)" x-on:touchmove="move($event)" x-on:touchend="end()">
            <div class="sm:hidden flex justify-center pt-2"><span class="w-10 h-1 bg-gray-300 rounded-full"></span></div>
            <div class="border-b border-gray-100 px-4 py-3 flex justify-between items-center">
                <h3 class="text-base font-semibold text-gray-900">{{ $isEditing ? 'Editar' : 'Novo' }} Cartão</h3>
                <button class="p-2.5 sm:p-1.5 -mr-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" @click="cardModalOpen = false" aria-label="Fechar">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>
        </div>

        <form wire:submit="save" class="p-4 space-y-3">

            {{-- Visibilidade --}}
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Visibilidade</label>
                <x-ui.scope-toggle :value="$scope" />
            </div>

            {{-- 4 últimos dígitos --}}
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">4 últimos dígitos</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm tracking-widest select-none">•••• •••• ••••</span>
                    <input type="text" inputmode="numeric" maxlength="4" wire:model="last4" placeholder="1234"
                        x-on:input="$event.target.value = $event.target.value.replace(/\D/g,'').slice(0,4)"
                        class="w-full pl-[8.5rem] pr-3 py-2 text-sm font-bold tracking-widest rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                </div>
                <p class="text-[10px] text-gray-400 mt-1 flex items-start gap-1">
                    <x-lucide-shield-check class="w-3 h-3 flex-shrink-0 mt-px" />
                    <span>Guardamos só os 4 últimos. Nunca registre o número completo.</span>
                </p>
                @error('last4') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
            </div>

            {{-- Apelido --}}
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Apelido <span class="font-normal text-gray-400">— opcional</span></label>
                <input type="text" wire:model="label" placeholder="Ex: Nubank, Cartão da loja..."
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                @error('label') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
            </div>

            <x-ui.button type="submit">
                {{ $isEditing ? 'Salvar' : 'Adicionar Cartão' }}
            </x-ui.button>
        </form>
    </div>
</div>
