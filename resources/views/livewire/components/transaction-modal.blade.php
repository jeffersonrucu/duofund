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
    'date' => date('Y-m-d'), 'repetition' => 'single', 'installments' => '',
    'scope' => 'personal', 'categories_list' => [],
    'hasGroupId' => false, // Indica se a transação faz parte de um grupo
    'editAll' => false     // Checkbox do usuário
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

on(['open-new-transaction' => function ($type = 'expense', $scope = 'personal') {
    $this->reset(['description', 'amount', 'category', 'installments', 'transactionId', 'isEditing', 'hasGroupId', 'editAll']);
    $this->type = $type;
    $this->date = date('Y-m-d');
    $this->repetition = 'single';
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

        // Verifica se tem grupo para mostrar a opção de "Editar Todas"
        $this->hasGroupId = !empty($tx->recurring_group_id);
        $this->editAll = false;

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
        'description' => 'required',
        'amount' => 'required|numeric',
        'type' => 'required',
        'date' => 'required',
        'scope' => 'required'
    ];

    if ($this->type === 'expense') {
        $rules['category'] = 'required';
    }

    $this->validate($rules);

    if ($this->type === 'income') {
        $this->category = 'Receita';
    }

    $user = auth()->user();

    if ($this->isEditing && $this->transactionId) {
        // --- ATUALIZAÇÃO ---
        $tx = Transaction::find($this->transactionId);

        $updateData = [
            'description' => $this->description,
            'amount' => $this->amount,
            'type' => $this->type,
            'category' => $this->category,
            'scope' => $this->scope
        ];

        if ($this->hasGroupId && $this->editAll) {
            // Atualiza TODAS do grupo (mantendo as datas originais de cada uma)
            Transaction::where('recurring_group_id', $tx->recurring_group_id)
                ->update($updateData);

            // A data, alteramos apenas da atual para não bagunçar o calendário das outras
            $tx->update(['date' => $this->date]);

            $msg = 'Série atualizada com sucesso!';
        } else {
            // Atualiza só esta
            $tx->update(array_merge($updateData, ['date' => $this->date]));
            $msg = 'Transação atualizada!';
        }

    } else {
        // --- CRIAÇÃO ---
        $baseDate = Carbon::parse($this->date);
        $groupId = (string) Str::uuid(); // Gera ID do grupo

        if ($this->type === 'income' && $this->scope === 'shared') {
            Transaction::create([
                'user_id' => $user->id,
                'description' => "Transferência para Conjunto ({$this->category})",
                'amount' => $this->amount,
                'type' => 'expense',
                'category' => 'Transferências',
                'date' => $this->date,
                'scope' => 'personal'
            ]);
            $this->description = "Aporte de " . $user->name . " - " . $this->description;
        }

        $data = [
            'user_id' => $user->id, 'description' => $this->description, 'amount' => $this->amount,
            'type' => $this->type, 'category' => $this->category, 'scope' => $this->scope
        ];

        if ($this->repetition === 'single') {
            Transaction::create(array_merge($data, ['date' => $this->date]));
        }
        elseif ($this->repetition === 'installment') {
            $count = (int) $this->installments;
            for ($i = 0; $i < $count; $i++) {
                Transaction::create(array_merge($data, [
                    'date' => $baseDate->copy()->addMonths($i),
                    'description' => $this->description . " (" . ($i + 1) . "/$count)",
                    'is_installment' => true,
                    'installment_current' => $i + 1,
                    'installment_count' => $count,
                    'recurring_group_id' => $groupId
                ]));
            }
        }
        elseif ($this->repetition === 'recurring') {
            // Gera 5 anos de recorrência
            $years = 5;
            $count = $years * 12;
            $curr = $baseDate->copy();

            for ($i = 0; $i < $count; $i++) {
                Transaction::create(array_merge($data, [
                    'date' => $curr->format('Y-m-d'),
                    'is_recurring' => true,
                    'recurring_group_id' => $groupId
                ]));
                $curr->addMonth();
            }
        }
        $msg = 'Transação salva!';
    }

    $this->reset(['description', 'amount', 'repetition', 'installments', 'transactionId', 'isEditing', 'hasGroupId', 'editAll']);
    $this->dispatch('close-modal-transaction');
    $this->dispatch('notify', $msg ?? 'Sucesso');
    $this->redirect(request()->header('Referer'), navigate: true);
};
?>

<div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm"
     :class="{ 'active': transactionModalOpen }" @click="transactionModalOpen = false">
    <div class="modal-content bg-white w-full max-w-lg p-6 rounded-xl shadow-2xl mx-4 border-t-4 border-primary" @click.stop>

        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">
                {{ $isEditing ? 'Editar' : 'Nova' }} {{ $type == 'income' ? 'Receita' : 'Despesa' }}
            </h3>
            <button class="text-gray-400 hover:text-gray-600" @click="transactionModalOpen = false"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>

        <form wire:submit="save" class="space-y-4">

            {{-- Escopo --}}
            <div class="flex p-1 bg-gray-100 rounded-lg">
                <button type="button" wire:click="$set('scope', 'personal'); $call('loadCats')"
                    class="flex-1 py-1.5 text-sm font-medium rounded-md transition {{ $scope === 'personal' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700' }}">
                    👤 Pessoal
                </button>
                <button type="button" wire:click="$set('scope', 'shared'); $call('loadCats')"
                    class="flex-1 py-1.5 text-sm font-medium rounded-md transition {{ $scope === 'shared' ? 'bg-white shadow text-primary' : 'text-gray-500 hover:text-gray-700' }}">
                    🏠 Compartilhado
                </button>
            </div>

            @if($type === 'income' && $scope === 'shared')
                <div class="text-xs bg-blue-50 text-blue-700 p-2 rounded border border-blue-100 flex items-start">
                    <i data-lucide="info" class="w-4 h-4 mr-2 flex-shrink-0"></i>
                    Isso será registrado como uma Despesa no seu painel Pessoal e uma Receita no Compartilhado.
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div class="{{ $type === 'income' ? 'col-span-2' : '' }}">
                    <label class="block text-sm font-medium text-gray-700">Valor (R$)</label>
                    <input type="text" inputmode="decimal" wire:model="amount" placeholder="0,00" required class="mt-1 block w-full rounded-lg border border-gray-300 p-2 focus:ring-primary focus:border-primary">
                </div>

                @if($type === 'expense')
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Categoria</label>
                        <select wire:model="category" required class="mt-1 block w-full rounded-lg border border-gray-300 p-2 focus:ring-primary focus:border-primary">
                            <option value="">Selecione</option>
                            @foreach($categories_list as $cat) <option value="{{ $cat }}">{{ $cat }}</option> @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Descrição</label>
                <input type="text" wire:model="description" placeholder="Ex: Salário, Venda..." class="mt-1 block w-full rounded-lg border border-gray-300 p-2 focus:ring-primary focus:border-primary">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select wire:model.live="type" class="mt-1 block w-full rounded-lg border border-gray-300 p-2 focus:ring-primary focus:border-primary">
                        <option value="expense">Despesa</option>
                        <option value="income">Receita</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Data {{ $isEditing ? '' : 'Inicial' }}</label>
                    <input type="date" wire:model="date" required class="mt-1 block w-full rounded-lg border border-gray-300 p-2 focus:ring-primary focus:border-primary">
                </div>
            </div>

            {{-- OPÇÃO DE EDITAR EM LOTE --}}
            @if($isEditing && $hasGroupId)
                <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" wire:model="editAll" class="rounded text-primary focus:ring-primary border-gray-300 h-4 w-4">
                        <span class="text-sm text-yellow-800 font-medium">Aplicar alterações para todas as recorrências futuras e passadas?</span>
                    </label>
                    <p class="text-xs text-yellow-600 mt-1 ml-6">Isso atualizará valor, descrição e categoria de toda a série.</p>
                </div>
            @endif

            @if(!$isEditing)
                <div class="border-t pt-4 mt-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Frequência</label>
                    <div class="flex space-x-4 mb-3">
                        <label class="flex items-center"><input type="radio" wire:model.live="repetition" value="single" class="text-primary h-4 w-4 border-gray-300"><span class="ml-2 text-sm">Única</span></label>
                        <label class="flex items-center"><input type="radio" wire:model.live="repetition" value="recurring" class="text-primary h-4 w-4 border-gray-300"><span class="ml-2 text-sm">Todo mês</span></label>
                        <label class="flex items-center"><input type="radio" wire:model.live="repetition" value="installment" class="text-primary h-4 w-4 border-gray-300"><span class="ml-2 text-sm">Parcelada</span></label>
                    </div>

                    @if($repetition === 'installment')
                        <label class="block text-sm font-medium text-gray-700 mb-1">Número de Parcelas</label>
                        <input type="number" wire:model="installments" placeholder="Ex: 12" class="block w-full rounded-lg border border-gray-300 p-2 focus:ring-primary focus:border-primary">
                    @endif
                </div>
            @endif

            <div class="flex justify-end pt-4">
                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary font-medium transition shadow-md">
                    {{ $isEditing ? 'Atualizar' : 'Salvar' }}
                </button>
            </div>
        </form>
    </div>
</div>
