<?php
use function Livewire\Volt\{state, computed, layout, uses};
use App\Livewire\Concerns\HasScopeToggle;
use App\Models\WishlistItem;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Str;

layout('components.layouts.app');
uses([HasScopeToggle::class]);

state([
    'view'            => session('view_mode', 'personal'),
    // form
    'showModal'       => false,
    'editId'          => null,
    'name'            => '',
    'url'             => '',
    'price'           => '',
    'priority'        => 'medium',
    'notes'           => '',
    'scope'           => 'personal',
    // simulation
    'simItemId'       => null,
    'simPayment'      => 'avista',
    'simInstallments' => 12,
    'simDate'         => '',
    'simIncome'       => 0,
    'simExpenses'     => 0,
    // delete confirm
    'confirmDeleteId' => null,
])->url();

$items = computed(function () {
    return WishlistItem::forView(auth()->user(), $this->view)
        ->with('user')
        ->orderByRaw("FIELD(status, 'pending', 'purchased')")
        ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
        ->orderBy('created_at', 'desc')
        ->get();
});

$simItem = computed(function () {
    return $this->simItemId ? WishlistItem::find($this->simItemId) : null;
});

$openModal = function ($id = null) {
    $this->reset(['name', 'url', 'price', 'notes', 'editId']);
    $this->priority = 'medium';
    $this->scope    = $this->view;

    if ($id) {
        $item = WishlistItem::find($id);
        if ($item && $item->manageableBy(auth()->user())) {
            $this->editId   = $item->id;
            $this->name     = $item->name;
            $this->url      = $item->url ?? '';
            $this->price    = number_format($item->price, 2, ',', '.');
            $this->priority = $item->priority;
            $this->notes    = $item->notes ?? '';
            $this->scope    = $item->scope;
        }
    }

    $this->showModal = true;
};

$save = function () {
    $this->price = \App\Support\Money::toDecimal($this->price) ?? '';

    $this->validate([
        'name'     => 'required|string|max:255',
        'price'    => 'required|numeric|min:0.01',
        'url'      => 'nullable|url',
        'priority' => 'required|in:high,medium,low',
        'scope'    => 'required|in:personal,shared',
        'notes'    => 'nullable|string|max:500',
    ], [
        'url.url'    => 'O link deve ser uma URL válida (ex: https://...).',
        'price.min'  => 'O preço deve ser maior que zero.',
        'name.required' => 'O nome é obrigatório.',
    ]);

    $data = [
        'user_id'  => auth()->id(),
        'name'     => $this->name,
        'price'    => $this->price,
        'url'      => $this->url ?: null,
        'priority' => $this->priority,
        'scope'    => $this->scope,
        'notes'    => $this->notes ?: null,
    ];

    if ($this->editId) {
        $item = WishlistItem::find($this->editId);
        if ($item && $item->user_id === auth()->id()) {
            $item->update($data);
            $msg = 'Desejo atualizado!';
        }
    } else {
        WishlistItem::create($data);
        $msg = 'Adicionado à lista!';
    }

    $this->showModal = false;
    $this->dispatch('notify', $msg ?? 'Salvo!');
};

$openSim = function ($id) {
    $now    = Carbon::now();
    $totals = app(\App\Services\MonthlySummaryService::class)
        ->for(auth()->user(), $this->view, $now);

    $this->simIncome    = $totals['income'];
    $this->simExpenses  = $totals['expense'];
    $this->simItemId    = $id;
    $this->simPayment   = 'avista';
    $this->simInstallments = 12;
    $this->simDate      = $now->format('Y-m-d');
};

$buyItem = function () {
    $item = WishlistItem::find($this->simItemId);
    if (!$item) return;

    $user    = auth()->user();
    $groupId = (string) Str::uuid();
    $base    = Carbon::parse($this->simDate);

    $data = [
        'user_id'  => $user->id,
        'type'     => 'expense',
        'scope'    => $item->scope,
        'category' => 'Lista de Desejos',
    ];

    if ($this->simPayment === 'avista') {
        Transaction::create(array_merge($data, [
            'description' => $item->name,
            'amount'      => $item->price,
            'date'        => $this->simDate,
        ]));
    } else {
        $count     = max(2, (int) $this->simInstallments);
        $installAmt = round($item->price / $count, 2);

        for ($i = 0; $i < $count; $i++) {
            Transaction::create(array_merge($data, [
                'description'        => $item->name . " (" . ($i + 1) . "/{$count})",
                'amount'             => $installAmt,
                'date'               => $base->copy()->addMonths($i)->format('Y-m-d'),
                'is_installment'     => true,
                'installment_current'=> $i + 1,
                'installment_count'  => $count,
                'recurring_group_id' => $groupId,
            ]));
        }
    }

    $item->update(['status' => 'purchased']);
    $this->simItemId = null;
    $this->dispatch('notify', 'Compra lançada no orçamento!');
    $this->redirect(request()->header('Referer') ?: route('dashboard'), navigate: true);
};

$togglePurchased = function ($id) {
    $item = WishlistItem::find($id);
    if ($item && $item->manageableBy(auth()->user())) {
        $newStatus = $item->status === 'purchased' ? 'pending' : 'purchased';
        $item->update(['status' => $newStatus]);
        $this->dispatch('notify', $newStatus === 'purchased' ? 'Marcado como comprado!' : 'Devolvido à lista de desejos.');
    }
};

$setConfirmDelete = function ($id) {
    $this->confirmDeleteId = $id;
};

$deleteItem = function ($id) {
    $item = WishlistItem::find($id);
    if ($item && $item->manageableBy(auth()->user())) {
        $item->delete();
        $this->dispatch('notify', 'Item removido da lista.');
    }
    $this->confirmDeleteId = null;
};
?>

<div>
    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 sm:mb-6 gap-3">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <x-ui.view-toggle :view="$view" personal-label="Minha Lista" shared-label="Nossa Lista" />
            <p class="text-[10px] text-gray-400 mt-1">
                {{ $this->items->count() }} {{ $this->items->count() === 1 ? 'item' : 'itens' }}
                @php $total = $this->items->where('status','pending')->sum('price'); @endphp
                · Total: R$ {{ number_format($total, 2, ',', '.') }}
            </p>
        </div>

        <div class="hidden md:flex justify-self-end md:col-start-3">
            <button wire:click="openModal()"
                class="bg-primary hover:bg-secondary text-white font-medium py-2 px-4 rounded-lg shadow-md shadow-primary/25 items-center transition text-sm flex">
                <x-lucide-plus class="w-4 h-4 mr-1.5" /> Novo Desejo
            </button>
        </div>
    </div>

    {{-- MOBILE ADD BUTTON --}}
    <div class="md:hidden mb-4">
        <button wire:click="openModal()"
            class="w-full bg-primary hover:bg-secondary text-white font-medium py-2.5 px-4 rounded-xl shadow-md shadow-primary/25 items-center transition text-sm flex justify-center">
            <x-lucide-plus class="w-4 h-4 mr-1.5" /> Novo Desejo
        </button>
    </div>

    {{-- GRID DE ITENS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
        @forelse($this->items as $item)
            @php
                $isPurchased = $item->status === 'purchased';
                $priorityConfig = match($item->priority) {
                    'high'   => ['label' => 'Alta',  'class' => 'bg-red-100 text-red-700'],
                    'low'    => ['label' => 'Baixa', 'class' => 'bg-gray-100 text-gray-500'],
                    default  => ['label' => 'Média', 'class' => 'bg-amber-100 text-amber-700'],
                };
            @endphp

            <div class="bg-white border {{ $isPurchased ? 'border-gray-100 opacity-60' : 'border-gray-200' }} rounded-xl p-4 hover:shadow-md transition group relative flex flex-col">
                {{-- Top bar --}}
                <div class="flex justify-between items-start mb-3">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $priorityConfig['class'] }}">
                            {{ $priorityConfig['label'] }}
                        </span>
                        @if($isPurchased)
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Comprado</span>
                        @endif
                        @if($view === 'shared' && $item->user_id !== auth()->id())
                            <span class="text-[9px] bg-purple-100 text-purple-600 px-1.5 py-0.5 rounded-full">
                                {{ substr($item->user->name ?? '?', 0, 8) }}
                            </span>
                        @endif
                    </div>
                    <div class="flex gap-0.5 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                        @if($item->user_id === auth()->id())
                        <button wire:click="openModal({{ $item->id }})"
                            class="p-1 hover:bg-gray-100 rounded-md text-gray-400 hover:text-blue-500 transition">
                            <x-lucide-pencil class="w-3.5 h-3.5" />
                        </button>
                        <button wire:click="setConfirmDelete({{ $item->id }})"
                            class="p-1 hover:bg-gray-100 rounded-md text-gray-400 hover:text-red-500 transition">
                            <x-lucide-trash-2 class="w-3.5 h-3.5" />
                        </button>
                        @endif
                    </div>
                </div>

                {{-- Nome e preço --}}
                <div class="mb-3 flex-1">
                    <h3 class="font-bold text-gray-900 text-sm leading-tight mb-1.5 {{ $isPurchased ? 'line-through text-gray-400' : '' }}">
                        {{ $item->name }}
                    </h3>
                    <div class="text-2xl font-black text-gray-900 tracking-tight">
                        R$ {{ number_format(floor($item->price), 0, ',', '.') }}<span class="text-sm text-gray-400 font-medium">,{{ substr(number_format($item->price, 2), -2) }}</span>
                    </div>
                    @if($item->notes)
                    <p class="text-[11px] text-gray-400 mt-1.5 line-clamp-2">{{ $item->notes }}</p>
                    @endif
                </div>

                {{-- Ações --}}
                <div class="space-y-1.5 pt-3 border-t border-gray-100">
                    @if($item->url)
                    <a href="{{ $item->url }}" target="_blank" rel="noopener"
                        class="w-full py-1.5 flex items-center justify-center gap-1.5 text-[11px] font-medium text-gray-600 hover:text-primary bg-gray-50 hover:bg-blue-50 rounded-lg transition">
                        <x-lucide-external-link class="w-3.5 h-3.5" /> Ver produto
                    </a>
                    @endif

                    @if(!$isPurchased)
                    <button wire:click="openSim({{ $item->id }})"
                        class="w-full py-2 flex items-center justify-center gap-1.5 text-[11px] font-semibold text-white bg-primary hover:bg-secondary rounded-lg transition">
                        <x-lucide-bar-chart-2 class="w-3.5 h-3.5" /> Simular compra
                    </button>
                    @endif

                    @if($item->user_id === auth()->id())
                    <button wire:click="togglePurchased({{ $item->id }})"
                        class="w-full py-1.5 flex items-center justify-center gap-1.5 text-[11px] font-medium rounded-lg transition
                            {{ $isPurchased ? 'text-gray-500 hover:text-gray-700 bg-gray-50 hover:bg-gray-100' : 'text-green-700 hover:text-green-800 bg-green-50 hover:bg-green-100' }}">
                        <x-dynamic-component :component="'lucide-'.($isPurchased ? 'rotate-ccw' : 'check-circle')" class="w-3.5 h-3.5" />
                        {{ $isPurchased ? 'Desfazer' : 'Marcar como comprado' }}
                    </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center py-12 text-gray-400 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center mb-3 shadow-sm">
                    <x-lucide-gift class="w-7 h-7 opacity-40" />
                </div>
                <h3 class="font-semibold text-gray-600 text-sm">Lista vazia</h3>
                <p class="text-xs mt-1 mb-4 text-gray-400">Adicione produtos que você quer comprar.</p>
                <button wire:click="openModal()"
                    class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-medium hover:bg-secondary transition shadow">
                    Adicionar primeiro desejo
                </button>
            </div>
        @endforelse
    </div>

    {{-- ==================== MODAL ADICIONAR/EDITAR ==================== --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex sm:items-center sm:justify-center bg-gray-900/50 backdrop-blur-sm sm:p-4"
         wire:click="$set('showModal', false)">
        <div class="bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-2xl sm:max-h-[90vh] overflow-y-auto flex flex-col"
             x-data="sheet(() => $wire.set('showModal', false))" :style="sheetStyle" @click.stop>
            <div class="sticky top-0 bg-white z-10"
                 x-on:touchstart.passive="start($event)" x-on:touchmove="move($event)" x-on:touchend="end()">
                <div class="sm:hidden flex justify-center pt-2"><span class="w-10 h-1 bg-gray-300 rounded-full"></span></div>
                <div class="border-b border-gray-100 px-4 py-3 flex justify-between items-center">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ $editId ? 'Editar desejo' : 'Novo desejo' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="p-2.5 sm:p-1.5 -mr-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" aria-label="Fechar">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <form wire:submit="save" class="p-4 space-y-3 flex-1">
                {{-- Nome --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Nome do produto</label>
                    <input type="text" wire:model="name" placeholder="Ex: iPhone 16 Pro, PS5..." required autofocus
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                    @error('name') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                {{-- Preço --}}
                <x-ui.currency-input model="price" label="Preço" required class="!text-lg !font-bold" />

                {{-- Link --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Link do produto <span class="font-normal text-gray-400">— opcional</span></label>
                    <input type="url" wire:model="url" placeholder="https://..."
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                    @error('url') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                {{-- Prioridade + Visibilidade --}}
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Prioridade</label>
                        <div class="flex bg-gray-100 p-0.5 rounded-lg">
                            <button type="button" wire:click="$set('priority', 'high')"
                                class="flex-1 py-1.5 text-[10px] font-semibold rounded transition {{ $priority === 'high' ? 'bg-red-500 text-white shadow' : 'text-gray-500' }}">Alta</button>
                            <button type="button" wire:click="$set('priority', 'medium')"
                                class="flex-1 py-1.5 text-[10px] font-semibold rounded transition {{ $priority === 'medium' ? 'bg-white shadow text-amber-600' : 'text-gray-500' }}">Média</button>
                            <button type="button" wire:click="$set('priority', 'low')"
                                class="flex-1 py-1.5 text-[10px] font-semibold rounded transition {{ $priority === 'low' ? 'bg-white shadow text-gray-700' : 'text-gray-500' }}">Baixa</button>
                        </div>
                    </div>
                    <div class="flex-1">
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Visibilidade</label>
                        <div class="flex bg-gray-100 p-0.5 rounded-lg">
                            <button type="button" wire:click="$set('scope', 'personal')"
                                class="flex-1 py-1.5 text-[10px] font-medium rounded transition {{ $scope === 'personal' ? 'bg-white shadow text-gray-800' : 'text-gray-500' }}">Só eu</button>
                            <button type="button" wire:click="$set('scope', 'shared')"
                                class="flex-1 py-1.5 text-[10px] font-medium rounded transition {{ $scope === 'shared' ? 'bg-white shadow text-purple-600' : 'text-gray-500' }}">Casal</button>
                        </div>
                    </div>
                </div>

                {{-- Notas --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Notas <span class="font-normal text-gray-400">— opcional</span></label>
                    <textarea wire:model="notes" placeholder="Cor, modelo, onde encontrou..." rows="2"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary resize-none"></textarea>
                </div>

                <button type="submit"
                    class="w-full py-2.5 rounded-lg font-semibold text-sm text-white bg-primary hover:bg-secondary transition">
                    {{ $editId ? 'Salvar alterações' : 'Adicionar à lista' }}
                </button>
            </form>
        </div>
    </div>
    @endif

    {{-- ==================== MODAL SIMULADOR ==================== --}}
    @if($simItemId && $this->simItem)
    @php
        $si       = $this->simItem;
        $siPrice  = (float) $si->price;
        $siBal    = (float) $simIncome - (float) $simExpenses;
        $siInst   = max(2, (int) $simInstallments);
        $siInstAmt = round($siPrice / $siInst, 2);
        $siAfterAv = $siBal - $siPrice;
        $siAfterPa = $siBal - $siInstAmt;
        $siPctAv   = $simIncome > 0 ? round(($siPrice / $simIncome) * 100, 1) : 0;
        $siPctPa   = $simIncome > 0 ? round(($siInstAmt / $simIncome) * 100, 1) : 0;
        $siEndDate = Carbon::parse($simDate)->addMonths($siInst - 1);
        $siCanAv   = $siAfterAv >= 0;
        $siCanPa   = $siAfterPa >= 0;
    @endphp

    <div class="fixed inset-0 z-50 flex sm:items-end sm:justify-center bg-gray-900/60 backdrop-blur-sm sm:p-4"
         wire:click="$set('simItemId', null)">
        <div class="bg-white w-full sm:max-w-md sm:rounded-2xl shadow-2xl max-h-[92vh] overflow-y-auto flex flex-col"
             x-data="sheet(() => $wire.set('simItemId', null))" :style="sheetStyle" @click.stop>

            {{-- Header --}}
            <div class="sticky top-0 bg-white z-10"
                 x-on:touchstart.passive="start($event)" x-on:touchmove="move($event)" x-on:touchend="end()">
                <div class="sm:hidden flex justify-center pt-2"><span class="w-10 h-1 bg-gray-300 rounded-full"></span></div>
                <div class="border-b border-gray-100 px-4 py-3 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 flex items-center gap-1.5">
                            <x-lucide-bar-chart-2 class="w-4 h-4 text-primary" /> Simular compra
                        </h3>
                        <p class="text-[11px] text-gray-500 mt-0.5 truncate max-w-[220px]">{{ $si->name }}</p>
                    </div>
                    <button wire:click="$set('simItemId', null)" class="p-2.5 sm:p-1.5 -mr-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" aria-label="Fechar">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <div class="p-4 space-y-4">
                {{-- Preço do produto --}}
                <div class="text-center py-2">
                    <p class="text-[11px] text-gray-400 mb-0.5">Valor do produto</p>
                    <div class="text-3xl font-black text-gray-900">
                        R$ {{ number_format($siPrice, 2, ',', '.') }}
                    </div>
                </div>

                {{-- Resumo financeiro atual --}}
                <div class="bg-gray-50 rounded-xl p-3 space-y-1.5">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Seu mês atual</p>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">Entradas</span>
                        <span class="font-semibold text-green-600">R$ {{ number_format($simIncome, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">Saídas</span>
                        <span class="font-semibold text-red-500">R$ {{ number_format($simExpenses, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-xs pt-1.5 border-t border-gray-200">
                        <span class="text-gray-700 font-medium">Saldo livre</span>
                        <span class="font-bold {{ $siBal >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                            R$ {{ number_format($siBal, 2, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Toggle tipo de pagamento --}}
                <div class="flex bg-gray-100 p-0.5 rounded-lg">
                    <button type="button" wire:click="$set('simPayment', 'avista')"
                        class="flex-1 py-2 text-xs font-semibold rounded-md transition {{ $simPayment === 'avista' ? 'bg-white shadow text-gray-900' : 'text-gray-500' }}">
                        À Vista
                    </button>
                    <button type="button" wire:click="$set('simPayment', 'parcelado')"
                        class="flex-1 py-2 text-xs font-semibold rounded-md transition {{ $simPayment === 'parcelado' ? 'bg-white shadow text-gray-900' : 'text-gray-500' }}">
                        Parcelado
                    </button>
                </div>

                {{-- === À VISTA === --}}
                @if($simPayment === 'avista')
                <div class="space-y-3">
                    <div class="{{ $siCanAv ? 'bg-blue-50 border-blue-100' : 'bg-red-50 border-red-100' }} border rounded-xl p-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Saldo livre</span>
                            <span class="font-semibold">R$ {{ number_format($siBal, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Valor da compra</span>
                            <span class="font-semibold text-red-600">− R$ {{ number_format($siPrice, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold pt-2 border-t {{ $siCanAv ? 'border-blue-200' : 'border-red-200' }}">
                            <span>Saldo após compra</span>
                            <span class="{{ $siCanAv ? 'text-blue-700' : 'text-red-600' }}">
                                R$ {{ number_format($siAfterAv, 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    @if($simIncome > 0)
                    <div class="text-xs text-gray-500 text-center">
                        Representa <strong>{{ $siPctAv }}%</strong> da sua renda deste mês
                    </div>
                    @endif

                    @if($siCanAv)
                    <div class="flex items-center gap-2 p-2.5 bg-green-50 rounded-lg border border-green-100">
                        <x-lucide-check-circle-2 class="w-4 h-4 text-green-600 flex-shrink-0" />
                        <span class="text-[11px] text-green-700 font-medium">Você consegue comprar à vista este mês!</span>
                    </div>
                    @else
                    <div class="flex items-start gap-2 p-2.5 bg-amber-50 rounded-lg border border-amber-100">
                        <x-lucide-alert-triangle class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                        <div class="text-[11px] text-amber-800">
                            <strong>Saldo insuficiente.</strong>
                            Faltam R$ {{ number_format(abs($siAfterAv), 2, ',', '.') }} para comprar à vista.
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- === PARCELADO === --}}
                @if($simPayment === 'parcelado')
                <div class="space-y-3">
                    {{-- Seletor de parcelas --}}
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-2">Número de parcelas</label>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            @foreach([2, 3, 6, 10, 12, 18, 24] as $n)
                            <button type="button" wire:click="$set('simInstallments', {{ $n }})"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition
                                    {{ $simInstallments == $n ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:border-primary hover:text-primary' }}">
                                {{ $n }}x
                            </button>
                            @endforeach
                        </div>
                        <input type="number" wire:model.live="simInstallments" min="2" max="60"
                            class="w-full px-3 py-1.5 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary text-center"
                            placeholder="Outro...">
                    </div>

                    {{-- Resultado --}}
                    <div class="{{ $siCanPa ? 'bg-blue-50 border-blue-100' : 'bg-amber-50 border-amber-100' }} border rounded-xl p-4 space-y-2">
                        <div class="text-center mb-2">
                            <div class="text-2xl font-black {{ $siCanPa ? 'text-primary' : 'text-amber-700' }}">
                                R$ {{ number_format($siInstAmt, 2, ',', '.') }}
                            </div>
                            <p class="text-[11px] text-gray-500">por mês · {{ $siInst }}x</p>
                        </div>

                        @if($simIncome > 0)
                        <div>
                            <div class="flex justify-between text-[11px] mb-1">
                                <span class="text-gray-500">Impacto na renda</span>
                                <span class="font-bold {{ $siPctPa > 30 ? 'text-red-600' : ($siPctPa > 15 ? 'text-amber-600' : 'text-green-600') }}">
                                    {{ $siPctPa }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all {{ $siPctPa > 30 ? 'bg-red-500' : ($siPctPa > 15 ? 'bg-amber-500' : 'bg-green-500') }}"
                                    style="width: {{ min(100, $siPctPa) }}%"></div>
                            </div>
                        </div>
                        @endif

                        <div class="flex justify-between text-xs pt-2 border-t {{ $siCanPa ? 'border-blue-200' : 'border-amber-200' }}">
                            <span class="text-gray-600">Saldo após parcela</span>
                            <span class="font-bold {{ $siCanPa ? 'text-gray-900' : 'text-red-600' }}">
                                R$ {{ number_format($siAfterPa, 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs text-center">
                        <div class="bg-gray-50 rounded-lg p-2.5">
                            <p class="text-gray-400 mb-0.5">Total pago</p>
                            <p class="font-bold text-gray-800">R$ {{ number_format($siInstAmt * $siInst, 2, ',', '.') }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2.5">
                            <p class="text-gray-400 mb-0.5">Última parcela</p>
                            <p class="font-bold text-gray-800 capitalize">
                                {{ $siEndDate->locale('pt_BR')->isoFormat('MMM/YYYY') }}
                            </p>
                        </div>
                    </div>

                    @if($siCanPa)
                    <div class="flex items-center gap-2 p-2.5 bg-green-50 rounded-lg border border-green-100">
                        <x-lucide-check-circle-2 class="w-4 h-4 text-green-600 flex-shrink-0" />
                        <span class="text-[11px] text-green-700 font-medium">Parcela cabe no seu orçamento atual!</span>
                    </div>
                    @else
                    <div class="flex items-start gap-2 p-2.5 bg-amber-50 rounded-lg border border-amber-100">
                        <x-lucide-alert-triangle class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                        <span class="text-[11px] text-amber-800">Parcela ultrapassa o saldo livre atual. Considere mais parcelas.</span>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Data da compra --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Data da compra</label>
                    <input type="date" wire:model="simDate"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                </div>

                {{-- Aviso --}}
                <div class="flex items-start gap-2 p-2.5 bg-blue-50 rounded-lg border border-blue-100 text-[10px] text-blue-700">
                    <x-lucide-info class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                    <span>Ao confirmar, as parcelas serão lançadas em <strong>Transações</strong> e o item marcado como comprado.</span>
                </div>
            </div>

            {{-- Footer --}}
            <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3 flex gap-2">
                <button wire:click="$set('simItemId', null)"
                    class="flex-1 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                    Fechar
                </button>
                <button wire:click="buyItem"
                    class="flex-1 py-2.5 text-sm font-semibold text-white bg-primary hover:bg-secondary rounded-lg transition flex items-center justify-center gap-1.5">
                    <x-lucide-shopping-cart class="w-4 h-4" />
                    {{ $simPayment === 'avista' ? 'Lançar à vista' : "Lançar {$siInst}x" }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ==================== MODAL DELETE CONFIRM ==================== --}}
    @if($confirmDeleteId)
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4"
         wire:click="$set('confirmDeleteId', null)">
        <div class="bg-white rounded-xl shadow-xl p-5 w-full max-w-xs" @click.stop>
            <div class="text-center mb-4">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-3">
                    <x-lucide-trash-2 class="w-6 h-6" />
                </div>
                <h3 class="text-sm font-bold text-gray-900">Remover da lista?</h3>
                <p class="text-xs text-gray-500 mt-1">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="$set('confirmDeleteId', null)"
                    class="flex-1 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-200 transition">
                    Cancelar
                </button>
                <button wire:click="deleteItem({{ $confirmDeleteId }})"
                    class="flex-1 py-2.5 bg-red-600 text-white rounded-lg font-semibold text-sm hover:bg-red-700 transition">
                    Remover
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
