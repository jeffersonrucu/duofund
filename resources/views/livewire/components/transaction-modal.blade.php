<?php

use function Livewire\Volt\{state, mount, on};
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Card;
use App\Services\TransactionMirrorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

state([
    'transactionId' => null,
    'isEditing' => false,
    'description' => '', 'amount' => '', 'type' => 'expense', 'category' => '',
    'date' => date('Y-m-d'), 'repetition' => 'single', 'installments' => '', 'installments_paid' => 0,
    'scope' => 'personal', 'categories_list' => [],
    'payment_method' => '', 'card_id' => '', 'cards_list' => [],
    'hasGroupId' => false,
    'editMode' => 'single',
    'showEditConfirmation' => false,
    'pendingEditData' => null,
    'affectedCount' => 0
]);

$loadCats = function() {
    $user = auth()->user();
    $this->categories_list = Category::forView($user, $this->scope)->pluck('name');
    $this->cards_list = Card::forView($user, $this->scope)
        ->orderBy('label')->orderBy('last4')->get()
        ->map(fn($c) => ['id' => $c->id, 'name' => $c->display_name])->values();

    // Trocar de escopo troca a lista; o cartão selecionado pode não estar mais nela.
    $ids = collect($this->cards_list)->pluck('id')->all();
    if ($this->card_id && ! in_array((int) $this->card_id, $ids, true)) {
        $this->card_id = '';
    }
};

mount($loadCats);
on(['category-added' => $loadCats]);

on(['open-new-transaction' => function ($type = 'expense', $scope = 'personal', $date = null, $repetition = 'single') {
    $this->reset(['description', 'amount', 'category', 'installments', 'installments_paid', 'transactionId', 'isEditing', 'hasGroupId', 'editMode', 'showEditConfirmation', 'pendingEditData', 'affectedCount', 'payment_method', 'card_id']);
    $this->type = $type;
    $this->date = $date ? Carbon::parse($date)->format('Y-m-d') : date('Y-m-d');
    $this->repetition = $repetition;
    $this->scope = in_array($scope, ['personal', 'shared'], true) ? $scope : 'personal';
    $this->loadCats();
}]);

on(['edit-transaction' => function($id) {
    $tx = Transaction::find($id);
    if ($tx && $tx->manageableBy(auth()->user())) {
        $this->transactionId = $tx->id;
        $this->isEditing = true;
        $this->description = $tx->description;
        $this->amount = number_format($tx->amount, 2, ',', '.');
        $this->type = $tx->type;
        $this->scope = $tx->scope;
        $this->date = $tx->date->format('Y-m-d');
        $this->hasGroupId = !empty($tx->recurring_group_id);
        $this->editMode = 'single';
        $this->showEditConfirmation = false;
        $this->loadCats();
        $this->category = $tx->category;
        $this->payment_method = $tx->payment_method ?? '';
        $this->card_id = $tx->card_id ?? '';
        $this->repetition = 'single';
    }
}]);

$save = function () {
    $this->amount = \App\Support\Money::toDecimal($this->amount) ?? '';

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

    if ($this->type === 'expense') {
        $rules['payment_method'] = 'nullable|' . PaymentMethod::rule();
        $ownerIds = $this->scope === 'personal'
            ? [auth()->id()]
            : auth()->user()->getFamilyUserIds();

        $rules['card_id'] = ['nullable', 'integer', Rule::exists('cards', 'id')
            ->whereIn('user_id', $ownerIds)
            ->where('scope', $this->scope)];
    }

    $this->validate($rules);

    // Origem só vale para despesa; cartão só quando origem = cartão
    if ($this->type !== 'expense') {
        $this->payment_method = '';
        $this->card_id = '';
    }
    if ($this->payment_method !== 'card') {
        $this->card_id = '';
    }
    $paymentMethod = $this->payment_method ?: null;
    $cardId = $this->card_id ?: null;

    if ($this->type === 'income') {
        $this->category = 'Receita';
    } elseif (empty($this->category)) {
        $this->category = 'Sem categoria';
    } else {
        $this->category = trim($this->category);
        $existing = Category::forView(auth()->user(), $this->scope)->pluck('name');

        // Reaproveita a grafia já cadastrada ("restaurantes" -> "Restaurantes")
        $match = $existing->first(fn ($name) => mb_strtolower($name) === mb_strtolower($this->category));

        if ($match !== null) {
            $this->category = $match;
        } else {
            // Evita duplicatas por erro de digitação ("Restaurante" x "Restaurantes")
            $similar = $existing->first(fn ($name) => levenshtein(mb_strtolower($name), mb_strtolower($this->category)) <= 2);

            if ($similar !== null) {
                $this->addError('category', "Já existe a categoria \"{$similar}\". Selecione-a ou escolha outro nome.");
                return;
            }

            Category::create([
                'user_id' => auth()->id(),
                'name' => $this->category,
                'limit' => 0,
                'scope' => $this->scope
            ]);
        }
    }

    $user = auth()->user();
    $mirrors = app(TransactionMirrorService::class);

    if ($this->isEditing && $this->transactionId) {
        $tx = Transaction::find($this->transactionId);

        if (!$tx || !$tx->manageableBy($user)) {
            $this->dispatch('notify', 'Você não tem permissão para editar esta transação.');
            return;
        }

        $updateData = [
            'description' => $this->description,
            'amount' => $this->amount,
            'type' => $this->type,
            'category' => $this->category,
            'scope' => $this->scope,
            'payment_method' => $paymentMethod,
            'card_id' => $cardId
        ];

        if ($this->hasGroupId && $this->editMode !== 'single' && !$this->showEditConfirmation) {
            $countQuery = Transaction::where('recurring_group_id', $tx->recurring_group_id);
            if ($this->editMode === 'forward') {
                $countQuery->where('date', '>=', $tx->date);
            }
            $this->affectedCount = $countQuery->count();
            $this->pendingEditData = $updateData;
            $this->showEditConfirmation = true;
            return;
        }

        $msg = DB::transaction(function () use ($tx, $updateData, $mirrors) {
            if ($this->hasGroupId && $this->editMode !== 'single') {
                $idsQuery = Transaction::where('recurring_group_id', $tx->recurring_group_id);
                if ($this->editMode === 'forward') {
                    $idsQuery->where('date', '>=', $tx->date);
                }
                $ids = $idsQuery->pluck('id');

                Transaction::whereIn('id', $ids)->update($updateData);
                $tx->update(['date' => $this->date]);
                $mirrors->reconcileMany($ids);

                return $this->editMode === 'forward'
                    ? 'Atualizado deste mês em diante!'
                    : 'Série atualizada!';
            }

            $tx->update(array_merge($updateData, ['date' => $this->date]));
            $mirrors->reconcile($tx);

            return 'Atualizado!';
        });

    } else {
        $baseDate = Carbon::parse($this->date);
        $groupId = (string) Str::uuid();

        $data = [
            'user_id' => $user->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'type' => $this->type,
            'category' => $this->category,
            'scope' => $this->scope,
            'payment_method' => $paymentMethod,
            'card_id' => $cardId
        ];

        DB::transaction(function () use ($data, $baseDate, $groupId, $mirrors) {
            if ($this->repetition === 'single') {
                $newTx = Transaction::create(array_merge($data, ['date' => $this->date]));
                $mirrors->createFor($newTx);
            }
            elseif ($this->repetition === 'installment') {
                $count = (int) $this->installments;
                $paid  = max(0, min((int) $this->installments_paid, $count - 1));
                $baseDate = $baseDate->subMonths($paid);
                for ($i = 0; $i < $count; $i++) {
                    $newTx = Transaction::create(array_merge($data, [
                        'date' => $baseDate->copy()->addMonths($i),
                        'is_installment' => true,
                        'installment_current' => $i + 1,
                        'installment_count' => $count,
                        'recurring_group_id' => $groupId
                    ]));
                    $mirrors->createFor($newTx);
                }
            }
            elseif ($this->repetition === 'recurring') {
                // Horizonte curto; o comando duofund:extend-recurrences
                // estende as séries ativas mês a mês.
                $count = Transaction::RECURRENCE_HORIZON_MONTHS;
                $curr = $baseDate->copy();

                for ($i = 0; $i < $count; $i++) {
                    $newTx = Transaction::create(array_merge($data, [
                        'date' => $curr->format('Y-m-d'),
                        'is_recurring' => true,
                        'recurring_group_id' => $groupId
                    ]));
                    $mirrors->createFor($newTx);
                    $curr->addMonth();
                }
            }
        });
        $msg = 'Salvo!';
    }

    $this->reset(['description', 'amount', 'repetition', 'installments', 'installments_paid', 'transactionId', 'isEditing', 'hasGroupId', 'editMode', 'showEditConfirmation', 'pendingEditData', 'affectedCount', 'payment_method', 'card_id']);
    $this->dispatch('close-modal-transaction');
    $this->dispatch('notify', $msg);
    $this->redirect(request()->header('Referer') ?: route('dashboard'), navigate: true);
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
    if (!$tx || !$tx->recurring_group_id || !$tx->manageableBy(auth()->user())) {
        $this->showEditConfirmation = false;
        return;
    }

    $originalDate = $tx->date->copy();
    $mirrors = app(TransactionMirrorService::class);

    DB::transaction(function () use ($tx, $pendingData, $dateValue, $originalDate, $mirrors) {
        // Aplica a atualização (toda a série ou deste mês em diante)
        $idsQuery = Transaction::where('recurring_group_id', $tx->recurring_group_id);
        if ($this->editMode === 'forward') {
            $idsQuery->where('date', '>=', $originalDate);
        }
        $ids = $idsQuery->pluck('id');

        Transaction::whereIn('id', $ids)->update($pendingData);

        // Atualiza a data do item atual
        $tx->update(['date' => $dateValue]);

        $mirrors->reconcileMany($ids);
    });

    $msg = $this->editMode === 'forward' ? 'Atualizado deste mês em diante!' : 'Série atualizada!';

    // Limpa os estados
    $this->showEditConfirmation = false;
    $this->pendingEditData = null;
    $this->affectedCount = 0;
    $this->transactionId = null;
    $this->isEditing = false;
    $this->hasGroupId = false;
    $this->editMode = 'single';
    $this->description = '';
    $this->amount = '';
    $this->repetition = 'single';
    $this->installments = '';
    $this->installments_paid = 0;
    $this->payment_method = '';
    $this->card_id = '';

    $this->dispatch('close-modal-transaction');
    $this->dispatch('notify', $msg);

    return $this->redirect(request()->header('Referer') ?: route('dashboard'), navigate: true);
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
                    <x-lucide-alert-triangle class="w-5 h-5" />
                </div>
                <h3 class="text-sm font-bold text-gray-900">Alterar {{ $affectedCount }} {{ $affectedCount == 1 ? 'item' : 'itens' }}?</h3>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $editMode === 'forward' ? 'Este lançamento e os próximos serão atualizados.' : 'Toda a série será atualizada.' }}
                </p>
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

    <div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 sm:p-4" role="dialog" aria-modal="true"
         :class="{ 'active': transactionModalOpen }" @click="transactionModalOpen = false">
        <div class="modal-content bg-white w-full h-full sm:h-auto sm:max-w-xl sm:rounded-xl shadow-2xl sm:max-h-[90vh] overflow-y-auto"
             x-data="sheet(() => transactionModalOpen = false)" :style="sheetStyle" @click.stop>

            {{-- Header (alça de arrasto + título) --}}
            <div class="sticky top-0 bg-white z-10"
                 x-on:touchstart.passive="start($event)" x-on:touchmove="move($event)" x-on:touchend="end()">
                <div class="sm:hidden flex justify-center pt-2"><span class="w-10 h-1 bg-gray-300 rounded-full"></span></div>
                <div class="border-b border-gray-100 px-4 py-3 flex justify-between items-center">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ $isEditing ? 'Editar' : ($type == 'income' ? 'Nova Receita' : 'Nova Despesa') }}
                    </h3>
                    <button class="p-2.5 sm:p-1.5 -mr-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" @click="transactionModalOpen = false" aria-label="Fechar">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <form wire:submit="save" class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 items-start">

                {{-- Toggle Tipo --}}
                @if(!$isEditing)
                <div class="flex bg-gray-100 p-0.5 rounded-lg sm:col-span-2">
                    <button type="button" wire:click="$set('type', 'expense')"
                        class="flex-1 py-2 text-xs font-medium rounded-md transition {{ $type === 'expense' ? 'bg-white shadow text-red-600' : 'text-gray-500' }}">
                        <x-lucide-minus-circle class="w-3.5 h-3.5 inline mr-1" /> Despesa
                    </button>
                    <button type="button" wire:click="$set('type', 'income')"
                        class="flex-1 py-2 text-xs font-medium rounded-md transition {{ $type === 'income' ? 'bg-white shadow text-green-600' : 'text-gray-500' }}">
                        <x-lucide-plus-circle class="w-3.5 h-3.5 inline mr-1" /> Receita
                    </button>
                </div>
                @endif

                {{-- Valor --}}
                <x-ui.currency-input model="amount" label="Valor" required class="!text-lg !font-bold" />

                {{-- Descrição --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Descrição</label>
                    <input type="text" wire:model="description" placeholder="Ex: Mercado, Salário..." required
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                    @error('description') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                {{-- Categoria --}}
                @if($type === 'expense')
                @php $isCustomCat = $category !== '' && !$categories_list->contains($category); @endphp

                {{-- Mobile: select nativo (otimizado p/ iOS/Android) --}}
                <div class="sm:hidden" x-data="{ custom: @js($isCustomCat) }">
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Categoria</label>
                    <select x-show="!custom"
                        x-on:change="
                            const v = $event.target.value;
                            if (v === '__new__') { custom = true; $wire.set('category', ''); $nextTick(() => $refs.newcat.focus()); }
                            else { $wire.set('category', v); }
                        "
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-gray-200 bg-white focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        <option value="" @selected($category === '')>Sem categoria</option>
                        @foreach($categories_list as $c)
                            <option value="{{ $c }}" @selected($category === $c)>{{ $c }}</option>
                        @endforeach
                        <option value="__new__">+ Nova categoria…</option>
                    </select>
                    <div x-show="custom" x-cloak class="flex gap-2">
                        <input type="text" x-ref="newcat" wire:model="category" placeholder="Nome da nova categoria"
                            class="flex-1 min-w-0 px-3 py-2.5 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        <button type="button" x-on:click="custom = false; $wire.set('category', '')"
                            class="px-3 rounded-lg border border-gray-200 text-gray-500 flex items-center" aria-label="Escolher da lista">
                            <x-lucide-list class="w-4 h-4" />
                        </button>
                    </div>
                    @error('category') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                    <p class="text-[10px] text-gray-400 mt-1.5 flex items-start gap-1">
                        <x-lucide-info class="w-3 h-3 flex-shrink-0 mt-px" />
                        <span>Categoria nova é criada na hora. Defina limites em <strong>Categorias</strong>.</span>
                    </p>
                </div>

                {{-- Desktop: combobox com busca --}}
                <div class="relative hidden sm:block sm:col-span-2"
                     x-data="{
                        open: false,
                        query: '',
                        cats: @js($categories_list->values()),
                        get filtered() {
                            const t = this.query.toLowerCase().trim();
                            if (!t) return this.cats;
                            return this.cats.filter(c => c.toLowerCase().includes(t));
                        },
                        get canCreate() {
                            const t = this.query.trim();
                            return t.length > 0 && !this.cats.some(c => c.toLowerCase() === t.toLowerCase());
                        },
                        update() { $wire.set('category', this.query, false); },
                        pick(c) { this.query = c; this.update(); this.open = false; }
                     }"
                     x-init="query = $wire.category || ''; $watch('$wire.category', v => { if ((v || '') !== query) query = v || '' })"
                     x-on:click.away="open = false"
                     x-on:keydown.escape="open = false">
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Categoria</label>

                    <div class="relative">
                        <input type="text" x-model="query" autocomplete="off"
                            x-on:focus="open = true" x-on:input="open = true; update()"
                            placeholder="Toque ou digite a categoria..."
                            class="w-full px-3 py-2 pr-9 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        <button type="button" tabindex="-1" x-on:click="open = !open"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 p-1">
                            <x-lucide-chevron-down class="w-4 h-4 transition-transform" ::class="open && 'rotate-180'" />
                        </button>
                    </div>

                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                        class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-44 overflow-y-auto py-1">
                        <template x-for="c in filtered" :key="c">
                            <button type="button" x-on:click="pick(c)"
                                class="w-full text-left px-3 py-2.5 text-sm hover:bg-blue-50 transition flex items-center justify-between gap-2 min-w-0"
                                :class="(query.toLowerCase() === c.toLowerCase()) ? 'text-primary font-semibold bg-blue-50/50' : 'text-gray-700'">
                                <span x-text="c" class="truncate"></span>
                                <span x-show="query.toLowerCase() === c.toLowerCase()" class="text-primary text-xs flex-shrink-0">✓</span>
                            </button>
                        </template>

                        <div x-show="filtered.length === 0 && !canCreate" class="px-3 py-2 text-xs text-gray-400">
                            Nada encontrado.
                        </div>

                        <button type="button" x-show="canCreate" x-on:click="open = false"
                            class="w-full text-left px-3 py-2.5 text-sm text-primary hover:bg-blue-50 transition border-t border-gray-100 flex items-center gap-1.5 min-w-0">
                            <span class="text-base leading-none flex-shrink-0">+</span>
                            <span class="truncate">Criar "<span class="font-semibold" x-text="query"></span>"</span>
                        </button>
                    </div>

                    @error('category') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                    <p class="text-[10px] text-gray-400 mt-1.5 flex items-start gap-1">
                        <x-lucide-info class="w-3 h-3 flex-shrink-0 mt-px" />
                        <span>Categoria nova é criada na hora. Defina os limites na página <strong>Categorias</strong>.</span>
                    </p>
                </div>
                @endif

                {{-- Origem do gasto (só despesa) --}}
                @if($type === 'expense')
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Origem <span class="font-normal text-gray-400">— opcional</span></label>
                    <select wire:model.live="payment_method"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-gray-200 bg-white focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        <option value="">Não informado</option>
                        <option value="pix">PIX</option>
                        <option value="card">Cartão</option>
                        <option value="boleto">Boleto</option>
                    </select>
                </div>

                @if($payment_method === 'card')
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Cartão</label>
                    @if(count($cards_list) > 0)
                    <select wire:model="card_id"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-gray-200 bg-white focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        <option value="">Selecione o cartão</option>
                        @foreach($cards_list as $c)
                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                    @else
                    <a href="{{ route('cards') }}" wire:navigate
                        class="flex items-center gap-2 p-2.5 bg-blue-50 rounded-lg text-[11px] text-blue-700 border border-blue-100">
                        <x-lucide-plus-circle class="w-3.5 h-3.5 flex-shrink-0" />
                        <span>Nenhum cartão {{ $scope === 'shared' ? 'do casal' : 'pessoal' }} cadastrado. Toque para cadastrar.</span>
                    </a>
                    @endif
                </div>
                @endif
                @endif

                {{-- Data --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Data</label>
                    <input type="date" wire:model="date" required
                        class="block w-full min-w-0 max-w-full appearance-none px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                </div>

                {{-- Visibilidade e Repetição --}}
                @if(!$isEditing)
                <div class="flex gap-3 sm:col-span-2">
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
                <div class="flex items-start gap-2 p-2.5 bg-blue-50 rounded-lg text-[11px] text-blue-700 border border-blue-100 sm:col-span-2">
                    <x-lucide-info class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                    <span>Serão criados lançamentos mensais para os <strong>próximos 5 anos</strong> (60 meses). Exclua individualmente quando necessário.</span>
                </div>
                @endif

                @if($repetition === 'installment')
                <div class="flex gap-3 sm:col-span-2">
                    <div class="flex-1">
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Total de parcelas</label>
                        <input type="number" inputmode="numeric" wire:model="installments" placeholder="Ex: 12" min="2" max="120"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        @error('installments') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex-1">
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Já pagas</label>
                        <input type="number" inputmode="numeric" wire:model="installments_paid" placeholder="0" min="0"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        @error('installments_paid') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                </div>
                @if($installments_paid > 0)
                <div class="flex items-start gap-2 p-2.5 bg-amber-50 rounded-lg text-[11px] text-amber-800 border border-amber-100 sm:col-span-2">
                    <x-lucide-info class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                    <span>A data da 1ª parcela será ajustada para <strong>{{ $installments_paid }} {{ $installments_paid == 1 ? 'mês' : 'meses' }} atrás</strong>.</span>
                </div>
                @endif
                @endif

                @if($type === 'income' && $scope === 'shared')
                <div class="flex items-start gap-2 p-2.5 bg-amber-50 rounded-lg text-[11px] text-amber-800 border border-amber-200 sm:col-span-2">
                    <x-lucide-info class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                    <span>Uma despesa equivalente será registrada em <strong>Meu Dinheiro</strong> como "Transferência para conta conjunta".</span>
                </div>
                @endif
                @endif

                {{-- Editar série --}}
                @if($isEditing && $hasGroupId)
                <div class="sm:col-span-2">
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Aplicar alteração a</label>
                    <div class="space-y-1.5">
                        @php
                            $editOptions = [
                                ['v' => 'single',  'title' => 'Só este lançamento',  'desc' => 'Os outros meses ficam como estão.'],
                                ['v' => 'forward', 'title' => 'Deste mês em diante', 'desc' => 'Altera este e os futuros. Meses anteriores não mudam.'],
                                ['v' => 'all',     'title' => 'Toda a série',        'desc' => 'Altera todos os lançamentos do grupo.'],
                            ];
                        @endphp
                        @foreach($editOptions as $opt)
                        <button type="button" wire:click="$set('editMode', '{{ $opt['v'] }}')"
                            class="w-full flex items-start gap-2.5 text-left p-2.5 rounded-lg border transition
                                {{ $editMode === $opt['v'] ? 'border-primary bg-blue-50/60' : 'border-gray-200 bg-white' }}">
                            <span class="mt-0.5 flex-shrink-0 w-4 h-4 rounded-full border flex items-center justify-center
                                {{ $editMode === $opt['v'] ? 'border-primary' : 'border-gray-300' }}">
                                <span class="w-2 h-2 rounded-full {{ $editMode === $opt['v'] ? 'bg-primary' : 'bg-transparent' }}"></span>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-medium {{ $editMode === $opt['v'] ? 'text-primary' : 'text-gray-800' }}">{{ $opt['title'] }}</span>
                                <span class="block text-[10px] text-gray-400 mt-0.5">{{ $opt['desc'] }}</span>
                            </span>
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Botão --}}
                <button type="submit"
                    class="w-full py-2.5 rounded-lg font-semibold text-sm text-white transition sm:col-span-2 sm:mt-1
                        {{ $type === 'income' ? 'bg-green-600 active:bg-green-700' : 'bg-primary active:bg-blue-700' }}">
                    {{ $isEditing ? 'Salvar' : 'Adicionar' }}
                </button>
            </form>
        </div>
    </div>
</div>
