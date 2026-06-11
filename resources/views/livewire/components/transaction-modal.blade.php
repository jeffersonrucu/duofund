<?php

use function Livewire\Volt\{state, mount, on};
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Str;

state([
    'transactionId' => null,
    'isEditing' => false,
    'description' => '', 'amount' => '', 'type' => 'expense', 'category' => '',
    'date' => date('Y-m-d'), 'repetition' => 'single', 'installments' => '', 'installments_paid' => 0,
    'scope' => 'personal', 'categories_list' => [],
    'hasGroupId' => false,
    'editAll' => false,
    'showEditConfirmation' => false,
    'pendingEditData' => null,
    'affectedCount' => 0
]);

$loadCats = function() {
    $query = Category::query();
    if ($this->scope === 'personal') {
        $query->where('user_id', auth()->id())->where('scope', 'personal');
    } else {
        $query->whereIn('user_id', auth()->user()->getFamilyUserIds())->where('scope', 'shared');
    }
    $this->categories_list = $query->pluck('name');
};

mount($loadCats);
on(['category-added' => $loadCats]);

on(['open-new-transaction' => function ($type = 'expense', $scope = 'personal', $date = null, $repetition = 'single') {
    $this->reset(['description', 'amount', 'category', 'installments', 'installments_paid', 'transactionId', 'isEditing', 'hasGroupId', 'editAll', 'showEditConfirmation', 'pendingEditData', 'affectedCount']);
    $this->type = $type;
    $this->date = $date ? Carbon::parse($date)->format('Y-m-d') : date('Y-m-d');
    $this->repetition = $repetition;
    $this->scope = $scope;
    $this->loadCats();
}]);

on(['edit-transaction' => function($id) {
    $tx = Transaction::find($id);
    if ($tx && ($tx->user_id === auth()->id() || in_array($tx->user_id, auth()->user()->getFamilyUserIds()))) {
        $this->transactionId = $tx->id;
        $this->isEditing = true;
        $this->description = $tx->description;
        $this->amount = number_format($tx->amount, 2, ',', '.');
        $this->type = $tx->type;
        $this->scope = $tx->scope;
        $this->date = $tx->date->format('Y-m-d');
        $this->hasGroupId = !empty($tx->recurring_group_id);
        $this->editAll = false;
        $this->showEditConfirmation = false;
        $this->loadCats();
        $this->category = $tx->category;
        $this->repetition = 'single';
    }
}]);

$save = function () {
    if ($this->amount) {
        $amountClean = str_replace('.', '', $this->amount);
        $amountClean = str_replace(',', '.', $amountClean);
        $this->amount = $amountClean;
    }

    $rules = [
        'description' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0.01',
        'type' => 'required|in:income,expense',
        'date' => 'required|date',
        'scope' => 'required|in:personal,shared'
    ];

    if ($this->repetition === 'installment') {
        $rules['installments'] = 'required|integer|min:2|max:120';
        $rules['installments_paid'] = 'required|integer|min:0';
    }

    $this->validate($rules);

    if ($this->type === 'income') {
        $this->category = 'Receita';
    } elseif (empty($this->category)) {
        $this->category = 'Sem categoria';
    } else {
        // Criar categoria automaticamente se não existir
        $exists = Category::where('name', $this->category)
            ->where('scope', $this->scope)
            ->whereIn('user_id', auth()->user()->getFamilyUserIds())
            ->exists();
        
        if (!$exists) {
            Category::create([
                'user_id' => auth()->id(),
                'name' => $this->category,
                'limit' => 0,
                'scope' => $this->scope
            ]);
        }
    }

    $user = auth()->user();

    if ($this->isEditing && $this->transactionId) {
        $tx = Transaction::find($this->transactionId);

        $updateData = [
            'description' => $this->description,
            'amount' => $this->amount,
            'type' => $this->type,
            'category' => $this->category,
            'scope' => $this->scope
        ];

        if ($this->hasGroupId && $this->editAll && !$this->showEditConfirmation) {
            $this->affectedCount = Transaction::where('recurring_group_id', $tx->recurring_group_id)->count();
            $this->pendingEditData = $updateData;
            $this->showEditConfirmation = true;
            return;
        }

        if ($this->hasGroupId && $this->editAll) {
            Transaction::where('recurring_group_id', $tx->recurring_group_id)->update($updateData);
            $tx->update(['date' => $this->date]);
            $msg = "Série atualizada!";
        } else {
            $tx->update(array_merge($updateData, ['date' => $this->date]));
            $msg = 'Atualizado!';
        }

    } else {
        $baseDate = Carbon::parse($this->date);
        $groupId = (string) Str::uuid();

        $data = [
            'user_id' => $user->id, 
            'description' => $this->description, 
            'amount' => $this->amount,
            'type' => $this->type, 
            'category' => $this->category, 
            'scope' => $this->scope
        ];

        if ($this->repetition === 'single') {
            $newTx = Transaction::create(array_merge($data, ['date' => $this->date]));

            // Se for uma receita compartilhada, debita da conta pessoal
            if ($newTx->scope == 'shared' && $newTx->type == 'income') {
                Transaction::create([
                    'user_id' => $user->id,
                    'description' => 'Transferido para conta conjunta',
                    'amount' => $newTx->amount,
                    'type' => 'expense',
                    'category' => 'Transferência',
                    'scope' => 'personal',
                    'date' => $newTx->date
                ]);
            }
        }
        elseif ($this->repetition === 'installment') {
            $count = (int) $this->installments;
            $paid  = max(0, min((int) $this->installments_paid, $count - 1));
            $baseDate = $baseDate->subMonths($paid);
            for ($i = 0; $i < $count; $i++) {
                $newTx = Transaction::create(array_merge($data, [
                    'date' => $baseDate->copy()->addMonths($i),
                    'description' => $this->description,
                    'is_installment' => true,
                    'installment_current' => $i + 1,
                    'installment_count' => $count,
                    'recurring_group_id' => $groupId
                ]));

                if ($newTx->scope == 'shared' && $newTx->type == 'income') {
                    Transaction::create([
                        'user_id' => $user->id,
                        'description' => 'Transferido para conta conjunta ' . " (" . ($i + 1) . "/$count)",
                        'amount' => $newTx->amount,
                        'type' => 'expense',
                        'category' => 'Transferência',
                        'scope' => 'personal',
                        'date' => $newTx->date,
                        'is_installment' => true,
                        'installment_current' => $i + 1,
                        'installment_count' => $count,
                        'recurring_group_id' => $groupId . '_personal'
                    ]);
                }
            }
        }
        elseif ($this->repetition === 'recurring') {
            $years = 5;
            $count = $years * 12;
            $curr = $baseDate->copy();

            for ($i = 0; $i < $count; $i++) {
                $newTx = Transaction::create(array_merge($data, [
                    'date' => $curr->format('Y-m-d'),
                    'is_recurring' => true,
                    'recurring_group_id' => $groupId
                ]));

                if ($newTx->scope == 'shared' && $newTx->type == 'income') {
                    Transaction::create([
                        'user_id' => $user->id,
                        'description' => 'Transferido para conta conjunta',
                        'amount' => $newTx->amount,
                        'type' => 'expense',
                        'category' => 'Transferência',
                        'scope' => 'personal',
                        'date' => $newTx->date,
                        'is_recurring' => true,
                        'recurring_group_id' => $groupId . '_personal'
                    ]);
                }
                $curr->addMonth();
            }
        }
        $msg = 'Salvo!';
    }

    $this->reset(['description', 'amount', 'repetition', 'installments', 'installments_paid', 'transactionId', 'isEditing', 'hasGroupId', 'editAll', 'showEditConfirmation', 'pendingEditData', 'affectedCount']);
    $this->dispatch('close-modal-transaction');
    $this->dispatch('notify', $msg);
    $this->redirect(request()->header('Referer'), navigate: true);
};

$confirmBatchEdit = function() {
    $pendingData = $this->pendingEditData;
    $txId = $this->transactionId;
    $dateValue = $this->date;
    
    if (!$pendingData || !$txId) {
        $this->showEditConfirmation = false;
        return;
    }
    
    $tx = Transaction::find($txId);
    if (!$tx || !$tx->recurring_group_id) {
        $this->showEditConfirmation = false;
        return;
    }
    
    // Aplica a atualização em toda a série
    Transaction::where('recurring_group_id', $tx->recurring_group_id)->update($pendingData);
    
    // Atualiza a data do item atual
    $tx->update(['date' => $dateValue]);
    
    // Limpa os estados
    $this->showEditConfirmation = false;
    $this->pendingEditData = null;
    $this->affectedCount = 0;
    $this->transactionId = null;
    $this->isEditing = false;
    $this->hasGroupId = false;
    $this->editAll = false;
    $this->description = '';
    $this->amount = '';
    $this->repetition = 'single';
    $this->installments = '';
    $this->installments_paid = 0;
    
    $this->dispatch('close-modal-transaction');
    $this->dispatch('notify', 'Série atualizada!');
    
    return $this->redirect(request()->header('Referer'), navigate: true);
};

$cancelBatchEdit = function() {
    $this->showEditConfirmation = false;
    $this->pendingEditData = null;
    $this->affectedCount = 0;
};
?>

<div>
    {{-- Modal de Confirmação de Edição em Lote --}}
    @if($showEditConfirmation)
    <div class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-900/60 p-4">
        <div class="bg-white rounded-xl shadow-xl p-4 w-full max-w-xs">
            <div class="text-center mb-3">
                <div class="bg-amber-100 text-amber-600 w-10 h-10 rounded-full flex items-center justify-center mx-auto mb-2">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <h3 class="text-sm font-bold text-gray-900">Alterar {{ $affectedCount }} itens?</h3>
                <p class="text-xs text-gray-500 mt-1">Toda a série será atualizada.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="cancelBatchEdit" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium text-xs">
                    Cancelar
                </button>
                <button type="button" wire:click="confirmBatchEdit" wire:loading.attr="disabled" class="flex-1 py-2 bg-amber-500 text-white rounded-lg font-medium text-xs disabled:opacity-50">
                    <span wire:loading.remove wire:target="confirmBatchEdit">Confirmar</span>
                    <span wire:loading wire:target="confirmBatchEdit">Aguarde...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    <div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 sm:p-4"
         :class="{ 'active': transactionModalOpen }" @click="transactionModalOpen = false">
        <div class="modal-content bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-2xl sm:max-h-[85vh] overflow-y-auto" @click.stop>
            
            {{-- Header --}}
            <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex justify-between items-center z-10">
                <h3 class="text-base font-semibold text-gray-900">
                    {{ $isEditing ? 'Editar' : ($type == 'income' ? 'Nova Receita' : 'Nova Despesa') }}
                </h3>
                <button class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" @click="transactionModalOpen = false">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form wire:submit="save" class="p-4 space-y-3">
                
                {{-- Toggle Tipo --}}
                @if(!$isEditing)
                <div class="flex bg-gray-100 p-0.5 rounded-lg">
                    <button type="button" wire:click="$set('type', 'expense')"
                        class="flex-1 py-2 text-xs font-medium rounded-md transition {{ $type === 'expense' ? 'bg-white shadow text-red-600' : 'text-gray-500' }}">
                        <i data-lucide="minus-circle" class="w-3.5 h-3.5 inline mr-1"></i> Despesa
                    </button>
                    <button type="button" wire:click="$set('type', 'income')"
                        class="flex-1 py-2 text-xs font-medium rounded-md transition {{ $type === 'income' ? 'bg-white shadow text-green-600' : 'text-gray-500' }}">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5 inline mr-1"></i> Receita
                    </button>
                </div>
                @endif

                {{-- Valor --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Valor</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                        <input type="text" inputmode="decimal" wire:model="amount" placeholder="0,00" required 
                            class="w-full pl-9 pr-3 py-2 text-lg font-bold rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                    </div>
                    @error('amount') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                {{-- Descrição --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Descrição</label>
                    <input type="text" wire:model="description" placeholder="Ex: Mercado, Salário..." required
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                    @error('description') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                {{-- Categoria --}}
                @if($type === 'expense')
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Categoria</label>
                    <input type="text" wire:model="category" list="categories-list" placeholder="Digite ou selecione..."
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                    <datalist id="categories-list">
                        @foreach($categories_list as $cat) 
                            <option value="{{ $cat }}">
                        @endforeach
                    </datalist>
                </div>
                @endif

                {{-- Data --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Data</label>
                    <input type="date" wire:model="date" required
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                </div>

                {{-- Visibilidade e Repetição --}}
                @if(!$isEditing)
                <div class="flex gap-3">
                    {{-- Escopo --}}
                    <div class="flex-1">
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Visibilidade</label>
                        <div class="flex bg-gray-100 p-0.5 rounded-lg">
                            <button type="button" wire:click="$set('scope', 'personal'); $call('loadCats')"
                                class="flex-1 py-1.5 text-[11px] font-medium rounded transition {{ $scope === 'personal' ? 'bg-white shadow text-gray-800' : 'text-gray-500' }}">
                                Só eu
                            </button>
                            <button type="button" wire:click="$set('scope', 'shared'); $call('loadCats')"
                                class="flex-1 py-1.5 text-[11px] font-medium rounded transition {{ $scope === 'shared' ? 'bg-white shadow text-purple-600' : 'text-gray-500' }}">
                                Casal
                            </button>
                        </div>
                    </div>

                    {{-- Frequência --}}
                    <div class="flex-1">
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Repetição</label>
                        <div class="flex bg-gray-100 p-0.5 rounded-lg">
                            <button type="button" wire:click="$set('repetition', 'single')"
                                class="flex-1 py-1.5 text-[11px] font-medium rounded transition {{ $repetition === 'single' ? 'bg-white shadow' : 'text-gray-500' }}">
                                Única
                            </button>
                            <button type="button" wire:click="$set('repetition', 'recurring')"
                                class="flex-1 py-1.5 text-[11px] font-medium rounded transition {{ $repetition === 'recurring' ? 'bg-white shadow' : 'text-gray-500' }}">
                                Mensal
                            </button>
                            <button type="button" wire:click="$set('repetition', 'installment')"
                                class="flex-1 py-1.5 text-[11px] font-medium rounded transition {{ $repetition === 'installment' ? 'bg-white shadow' : 'text-gray-500' }}">
                                Parcelas
                            </button>
                        </div>
                    </div>
                </div>

                @if($repetition === 'recurring')
                <div class="flex items-start gap-2 p-2.5 bg-blue-50 rounded-lg text-[11px] text-blue-700 border border-blue-100">
                    <i data-lucide="info" class="w-3.5 h-3.5 flex-shrink-0 mt-0.5"></i>
                    <span>Serão criados lançamentos mensais para os <strong>próximos 5 anos</strong> (60 meses). Exclua individualmente quando necessário.</span>
                </div>
                @endif

                @if($repetition === 'installment')
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Total de parcelas</label>
                        <input type="number" wire:model="installments" placeholder="Ex: 12" min="2" max="120"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        @error('installments') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex-1">
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Já pagas</label>
                        <input type="number" wire:model="installments_paid" placeholder="0" min="0"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        @error('installments_paid') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                </div>
                @if($installments_paid > 0)
                <div class="flex items-start gap-2 p-2.5 bg-amber-50 rounded-lg text-[11px] text-amber-800 border border-amber-100">
                    <i data-lucide="info" class="w-3.5 h-3.5 flex-shrink-0 mt-0.5"></i>
                    <span>A data da 1ª parcela será ajustada para <strong>{{ $installments_paid }} {{ $installments_paid == 1 ? 'mês' : 'meses' }} atrás</strong>.</span>
                </div>
                @endif
                @endif

                @if($type === 'income' && $scope === 'shared')
                <div class="flex items-start gap-2 p-2.5 bg-amber-50 rounded-lg text-[11px] text-amber-800 border border-amber-200">
                    <i data-lucide="info" class="w-3.5 h-3.5 flex-shrink-0 mt-0.5"></i>
                    <span>Uma despesa equivalente será registrada em <strong>Meu Dinheiro</strong> como "Transferência para conta conjunta".</span>
                </div>
                @endif
                @endif

                {{-- Editar série --}}
                @if($isEditing && $hasGroupId)
                <label class="flex items-center gap-2 p-2.5 bg-amber-50 rounded-lg border border-amber-100 cursor-pointer">
                    <input type="checkbox" wire:model="editAll" class="rounded text-amber-600 focus:ring-amber-500 border-gray-300 w-3.5 h-3.5">
                    <span class="text-xs text-amber-800">Aplicar para toda a série</span>
                </label>
                @endif

                {{-- Botão --}}
                <button type="submit" 
                    class="w-full py-2.5 rounded-lg font-semibold text-sm text-white transition
                        {{ $type === 'income' ? 'bg-green-600 active:bg-green-700' : 'bg-primary active:bg-blue-700' }}">
                    {{ $isEditing ? 'Salvar' : 'Adicionar' }}
                </button>
            </form>
        </div>
    </div>
</div>
