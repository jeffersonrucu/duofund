<?php
use function Livewire\Volt\{state, computed, layout, uses};
use App\Livewire\Concerns\HasMonthNavigation;
use App\Livewire\Concerns\HasScopeToggle;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;

layout('components.layouts.app');
uses([HasMonthNavigation::class, HasScopeToggle::class]);

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => session('current_month', now()->startOfMonth()->format('Y-m-d')),
    'confirmDeleteId' => null,
    'confirmDeleteName' => ''
])->url();

state([
    'detailCatId' => null,
    'detailCatName' => null,
]);

$data = computed(function() {
    $user = auth()->user();
    $date = Carbon::parse($this->currentMonth);

    $categories = Category::forView($user, $this->view)->with('user')->orderBy('name')->get();

    $usage = Transaction::forView($user, $this->view)
        ->inMonth($date)
        ->where('type', 'expense')
        ->selectRaw('category, sum(amount) as total')
        ->groupBy('category')
        ->pluck('total', 'category');

    $totalBudget = $categories->sum('limit');

    return compact('categories', 'usage', 'totalBudget');
});

$detailTransactions = computed(function() {
    if (!$this->detailCatName) return collect();

    return Transaction::forView(auth()->user(), $this->view)
        ->inMonth(Carbon::parse($this->currentMonth))
        ->where('type', 'expense')
        ->where('category', $this->detailCatName)
        ->orderBy('date', 'desc')
        ->orderBy('created_at', 'desc')
        ->get();
});

$openCategoryDetail = function($id) {
    $cat = Category::find($id);
    if (!$cat) return;
    $this->detailCatId = $id;
    $this->detailCatName = $cat->name;
};

$closeCategoryDetail = function() {
    $this->detailCatId = null;
    $this->detailCatName = null;
};

$setConfirmDelete = function($id) {
    $cat = Category::find($id);
    $this->confirmDeleteId = $id;
    $this->confirmDeleteName = $cat?->name ?? '';
};

$deleteCat = function($id) {
    $cat = Category::find($id);
    if($cat && $cat->manageableBy(auth()->user())) {
        $cat->delete();
        $this->dispatch('notify', 'Categoria removida.');
    }
    $this->reset(['confirmDeleteId', 'confirmDeleteName']);
};

?>

<div x-data="{ categoryModalOpen: false }" @close-modal-category.window="categoryModalOpen = false">

    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 sm:mb-6 gap-3">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <x-ui.view-toggle :view="$view" personal-label="Minhas Categorias" shared-label="Nossas Categorias" />
            <p class="text-[10px] text-gray-400 mt-1">
                @if($view === 'personal')
                    Vendo apenas suas categorias pessoais
                @else
                    Vendo categorias compartilhadas do casal
                @endif
            </p>
        </div>

        <div class="flex items-center bg-white rounded-lg shadow-sm border border-gray-200 p-0.5 justify-self-center">
            <button wire:click="prevMonth" class="p-1.5 hover:bg-gray-100 rounded-md transition text-gray-500 min-h-[44px] min-w-[44px] sm:min-h-0 sm:min-w-0 flex items-center justify-center">
                <x-lucide-chevron-left class="w-4 h-4" />
            </button>

            <div class="px-3 text-center min-w-[120px]">
                <div class="relative inline-block">
                    <h2 class="text-xs sm:text-sm font-bold text-gray-800 capitalize">
                        {{ \Carbon\Carbon::parse($currentMonth)->locale('pt_BR')->translatedFormat('F Y') }}
                    </h2>
                    <input type="month" value="{{ \Carbon\Carbon::parse($currentMonth)->format('Y-m') }}"
                        x-on:change="$wire.selectMonth($event.target.value)"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" aria-label="Escolher mês">
                </div>
                @if(\Carbon\Carbon::parse($currentMonth)->isCurrentMonth())
                    <span class="text-[9px] text-green-600 font-medium bg-green-50 px-1.5 py-0.5 rounded-full">Mês Atual</span>
                @else
                    <p class="text-[9px] text-gray-400 cursor-pointer hover:text-primary transition underline decoration-dotted" wire:click="today">
                        Voltar para hoje
                    </p>
                @endif
            </div>

            <button wire:click="nextMonth" class="p-1.5 hover:bg-gray-100 rounded-md transition text-gray-500 min-h-[44px] min-w-[44px] sm:min-h-0 sm:min-w-0 flex items-center justify-center">
                <x-lucide-chevron-right class="w-4 h-4" />
            </button>
        </div>

        <div class="hidden md:flex justify-self-end">
            <button @click="categoryModalOpen = true; Livewire.dispatch('open-new-category', { scope: '{{ $view }}' })"
                class="bg-primary hover:bg-secondary text-white font-medium py-2 px-4 rounded-lg shadow-md shadow-primary/25 items-center transition text-sm flex">
                <x-lucide-plus class="w-4 h-4 mr-1.5" /> Nova Categoria
            </button>
        </div>
    </div>

    {{-- RESUMO --}}
    @php
        $totalBudget = $this->data['totalBudget'];
        $totalSpent = collect($this->data['usage'])->sum();
        $pctUsed = $totalBudget > 0 ? min(100, round(($totalSpent / $totalBudget) * 100)) : ($totalSpent > 0 ? 100 : 0);
    @endphp

    <div class="mb-4 sm:mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="bg-gradient-to-r from-blue-50 to-white p-4 rounded-xl border border-blue-100 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-100 text-blue-600 rounded-lg">
                        <x-lucide-calculator class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[11px] text-gray-500 font-medium">Total Planejado</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900">R$ {{ number_format($totalBudget, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-red-50 to-white p-4 rounded-xl border border-red-100 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-red-100 text-red-600 rounded-lg">
                        <x-lucide-receipt class="w-5 h-5" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] text-gray-500 font-medium">Total Gasto em {{ \Carbon\Carbon::parse($currentMonth)->locale('pt_BR')->translatedFormat('F') }}</p>
                            <span class="text-[10px] font-bold {{ $pctUsed > 100 ? 'text-red-600' : ($pctUsed > 80 ? 'text-yellow-600' : 'text-gray-500') }}">{{ $pctUsed }}%</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900">R$ {{ number_format($totalSpent, 2, ',', '.') }}</p>
                        <div class="w-full bg-red-100 rounded-full h-2 mt-1.5">
                            <div class="{{ $pctUsed > 100 ? 'bg-red-500' : ($pctUsed > 80 ? 'bg-yellow-500' : 'bg-primary') }} h-2 rounded-full transition-all duration-500" style="width: {{ min(100, $pctUsed) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- GRID DE CATEGORIAS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
        @forelse($this->data['categories'] as $cat)
            @php
                $used = $this->data['usage'][$cat->name] ?? 0;
                $hasLimit = $cat->limit > 0;

                if ($hasLimit) {
                    $pct = round(($used / $cat->limit) * 100);
                } else {
                    $pct = $used > 0 ? 100 : 0;
                }

                if (!$hasLimit) {
                    $barColor = 'bg-gray-400';
                    $textColor = 'text-gray-600';
                    $bgColor = 'bg-gray-50 border-gray-200';
                    $iconColor = 'text-gray-400';
                } elseif($pct > 100) {
                    $barColor = 'bg-red-500';
                    $textColor = 'text-red-600';
                    $bgColor = 'bg-red-50 border-red-100';
                    $iconColor = 'text-red-500';
                } elseif($pct > 80) {
                    $barColor = 'bg-yellow-500';
                    $textColor = 'text-yellow-700';
                    $bgColor = 'bg-yellow-50 border-yellow-100';
                    $iconColor = 'text-yellow-600';
                } else {
                    $barColor = 'bg-primary';
                    $textColor = 'text-primary';
                    $bgColor = 'bg-white border-gray-200';
                    $iconColor = 'text-gray-400';
                }
            @endphp

            <div wire:click="openCategoryDetail({{ $cat->id }})"
                class="{{ $bgColor }} p-3 sm:p-4 rounded-xl border shadow-sm hover:shadow-md transition relative group flex flex-col h-full cursor-pointer">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-white border border-gray-100 flex items-center justify-center shadow-sm">
                            <span class="font-bold text-sm {{ $iconColor }}">{{ substr($cat->name, 0, 1) }}</span>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 text-sm leading-tight">{{ $cat->name }}</h3>
                            @if($view === 'shared' && $cat->user_id !== auth()->id())
                                <span class="text-[9px] text-gray-400 flex items-center">
                                    <x-lucide-user class="w-2.5 h-2.5 mr-0.5" /> {{ substr($cat->user->name ?? '?', 0, 10) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex space-x-0.5 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                        <button @click.stop="categoryModalOpen = true; Livewire.dispatch('edit-category', { id: {{ $cat->id }}, name: @js($cat->name), limit: {{ $cat->limit }}, scope: '{{ $cat->scope }}' })"
                            class="p-1 hover:bg-gray-100 rounded-md text-gray-400 hover:text-blue-500 transition">
                            <x-lucide-pencil class="w-3.5 h-3.5" />
                        </button>
                        <button wire:click.stop="setConfirmDelete({{ $cat->id }})"
                            class="p-1 hover:bg-gray-100 rounded-md text-gray-400 hover:text-red-500 transition">
                            <x-lucide-trash-2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <div class="mt-auto">
                    <div class="flex justify-between items-end mb-1.5">
                        <div>
                            <span class="text-[10px] text-gray-500">Gasto</span>
                            <p class="font-bold text-gray-900 text-sm">R$ {{ number_format($used, 2, ',', '.') }}</p>
                        </div>
                        @if($hasLimit)
                            <span class="text-[11px] font-bold {{ $textColor }}">{{ $pct }}%</span>
                        @endif
                    </div>

                    <div class="w-full bg-gray-200/50 rounded-full h-2.5 overflow-hidden">
                        <div class="{{ $barColor }} h-2.5 rounded-full transition-all duration-1000 ease-out" style="width: {{ min(100, $pct) }}%"></div>
                    </div>

                    <div class="mt-1.5 text-[10px] text-gray-400 flex justify-between">
                        @if($hasLimit)
                            <span>Limite: <b>R$ {{ number_format($cat->limit, 2, ',', '.') }}</b></span>
                            <span class="{{ $cat->limit - $used < 0 ? 'text-red-500 font-bold' : '' }}">Restam: R$ {{ number_format($cat->limit - $used, 2, ',', '.') }}</span>
                        @else
                            <span class="italic">Sem limite definido</span>
                            <span>Total gasto</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center p-8 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 text-gray-400">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mb-3 shadow-sm">
                    <x-lucide-layers class="w-6 h-6 opacity-50" />
                </div>
                <span class="font-semibold text-gray-600 text-sm">Nenhuma categoria configurada</span>
                <p class="text-xs mt-1 text-gray-400">Categorias ajudam você a planejar seus limites.</p>
                <button @click="categoryModalOpen = true; Livewire.dispatch('open-new-category', { scope: '{{ $view }}' })"
                    class="mt-3 px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:border-primary hover:text-primary transition shadow-sm">
                    Criar Primeira Categoria
                </button>
            </div>
        @endforelse

        @if(count($this->data['categories']) > 0)
            <button @click="categoryModalOpen = true; Livewire.dispatch('open-new-category', { scope: '{{ $view }}' })"
                class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-dashed border-gray-300 text-gray-400 hover:border-primary hover:text-primary hover:bg-blue-50/50 transition h-full min-h-[120px] group">
                <div class="w-10 h-10 rounded-full bg-gray-100 group-hover:bg-white flex items-center justify-center mb-1.5 transition">
                    <x-lucide-plus class="w-5 h-5" />
                </div>
                <span class="font-medium text-xs">Nova Categoria</span>
            </button>
        @endif
    </div>

    {{-- Modal de Confirmação de Exclusão --}}
    @if($confirmDeleteId)
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4"
         wire:click="$set('confirmDeleteId', null)">
        <div class="bg-white rounded-xl shadow-xl p-5 w-full max-w-xs" @click.stop>
            <div class="text-center mb-4">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-3">
                    <x-lucide-trash-2 class="w-6 h-6" />
                </div>
                <h3 class="text-sm font-bold text-gray-900">Excluir categoria?</h3>
                <p class="text-xs text-gray-500 mt-1 font-medium">{{ $confirmDeleteName }}</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="$set('confirmDeleteId', null)"
                    class="flex-1 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-200 transition">
                    Cancelar
                </button>
                <button wire:click="deleteCat({{ $confirmDeleteId }})"
                    class="flex-1 py-2.5 bg-red-600 text-white rounded-lg font-semibold text-sm hover:bg-red-700 transition">
                    Excluir
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: detalhe / listagem de uma categoria --}}
    @if($detailCatId)
    @php
        $detailTxs = $this->detailTransactions;
        $detailTotal = $detailTxs->sum('amount');
    @endphp
    <div class="fixed inset-0 z-[45] flex sm:items-center sm:justify-center bg-gray-900/50 backdrop-blur-sm sm:p-4"
         wire:click="closeCategoryDetail">
        <div class="bg-white w-full h-full sm:h-auto sm:max-w-md sm:rounded-xl shadow-2xl sm:max-h-[85vh] overflow-y-auto flex flex-col"
             x-data="sheet(() => $wire.closeCategoryDetail())" :style="sheetStyle" @click.stop>

            {{-- Header --}}
            <div class="sticky top-0 bg-white z-10"
                 x-on:touchstart.passive="start($event)" x-on:touchmove="move($event)" x-on:touchend="end()">
                <div class="sm:hidden flex justify-center pt-2"><span class="w-10 h-1 bg-gray-300 rounded-full"></span></div>
                <div class="border-b border-gray-100 px-4 py-3 flex justify-between items-center">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-gray-900 truncate">{{ $detailCatName }}</h3>
                        <p class="text-[11px] text-gray-500">
                            {{ $detailTxs->count() }} {{ $detailTxs->count() == 1 ? 'lançamento' : 'lançamentos' }}
                            · {{ \Carbon\Carbon::parse($currentMonth)->locale('pt_BR')->translatedFormat('F') }}
                        </p>
                    </div>
                    <button wire:click="closeCategoryDetail" class="p-2.5 sm:p-1.5 -mr-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" aria-label="Fechar">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>
                <div class="px-4 py-2.5 bg-red-50/50 border-b border-gray-100 flex justify-between items-center">
                    <span class="text-[11px] font-medium text-gray-500">Total gasto</span>
                    <span class="text-sm font-bold text-red-600">R$ {{ number_format($detailTotal, 2, ',', '.') }}</span>
                </div>
            </div>

            {{-- Lista --}}
            <div class="divide-y divide-gray-50">
                @php $prevDate = null; @endphp
                @forelse($detailTxs as $t)
                    @php
                        $txDate = $t->date->format('Y-m-d');
                        $showDateHeader = $prevDate !== $txDate;
                        $prevDate = $txDate;
                    @endphp
                    @if($showDateHeader)
                    <div class="px-4 py-1 bg-gray-50/80 border-b border-gray-100">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                            @if($t->date->isToday()) Hoje
                            @elseif($t->date->isYesterday()) Ontem
                            @else {{ $t->date->locale('pt_BR')->isoFormat('D [de] MMMM') }}
                            @endif
                        </span>
                    </div>
                    @endif
                    <button type="button"
                        @click="transactionModalOpen = true; Livewire.dispatch('edit-transaction', { id: {{ $t->id }} }); $wire.closeCategoryDetail()"
                        class="w-full text-left px-4 py-2.5 hover:bg-gray-50 transition flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center {{ $t->type == 'savings' ? 'bg-violet-50 text-violet-600' : 'bg-red-50 text-red-600' }}">
                            <x-dynamic-component :component="'lucide-'.($t->type == 'savings' ? 'piggy-bank' : 'arrow-down')" class="w-3.5 h-3.5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-medium text-gray-900 truncate">{{ preg_replace('/\s*\(\d+\/\d+\)$/', '', $t->description) }}</span>
                                @if($t->is_installment)
                                    <span class="px-1 py-0 bg-yellow-100 text-yellow-800 rounded text-[9px] font-bold flex-shrink-0">{{ $t->installment_current }}/{{ $t->installment_count }}</span>
                                @elseif($t->is_recurring)
                                    <x-lucide-repeat class="w-2.5 h-2.5 text-blue-400 flex-shrink-0" />
                                @endif
                                @if($view === 'shared' && $t->user_id !== auth()->id())
                                    <span class="w-3.5 h-3.5 bg-purple-100 text-purple-700 rounded-full flex items-center justify-center text-[8px] font-bold flex-shrink-0">
                                        {{ substr($t->user->name ?? '?', 0, 1) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <span class="text-xs font-bold whitespace-nowrap text-gray-900">R$ {{ number_format($t->amount, 2, ',', '.') }}</span>
                        <x-lucide-chevron-right class="w-3.5 h-3.5 text-gray-300 flex-shrink-0" />
                    </button>
                @empty
                    <div class="flex flex-col items-center justify-center py-10 text-gray-400">
                        <div class="w-10 h-10 bg-gray-50 rounded-full flex items-center justify-center mb-2">
                            <x-lucide-receipt class="w-5 h-5 opacity-50" />
                        </div>
                        <p class="font-medium text-xs">Nenhum gasto nesta categoria neste mês.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    <livewire:components.category-modal />
</div>
