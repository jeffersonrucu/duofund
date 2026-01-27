<?php
use function Livewire\Volt\{state, computed, layout};
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;

layout('components.layouts.app');

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => session('current_month', now()->startOfMonth()->format('Y-m-d'))
])->url();

$data = computed(function() {
    $user = auth()->user();
    $familyIds = $user->getFamilyUserIds();
    $date = Carbon::parse($this->currentMonth);

    $queryCat = Category::query();
    $queryTx = Transaction::query();

    if ($this->view === 'personal') {
        $queryCat->where('user_id', $user->id)->where('scope', 'personal');
        $queryTx->where('user_id', $user->id)->where('scope', 'personal');
    } else {
        $queryCat->whereIn('user_id', $familyIds)->where('scope', 'shared');
        $queryTx->whereIn('user_id', $familyIds)->where('scope', 'shared');
    }

    $categories = $queryCat->orderBy('name')->get();

    $usage = $queryTx->where('type', 'expense')
        ->whereYear('date', $date->year)
        ->whereMonth('date', $date->month)
        ->selectRaw('category, sum(amount) as total')
        ->groupBy('category')
        ->pluck('total', 'category');

    $totalBudget = $categories->sum('limit');

    return compact('categories', 'usage', 'totalBudget');
});

$setView = function ($mode) {
    $this->view = $mode;
    session(['view_mode' => $mode]);
    $this->dispatch('scope-changed', $mode);
};

$deleteCat = function($id) {
    $cat = Category::find($id);
    if($cat && ($cat->user_id === auth()->id() || in_array($cat->user_id, auth()->user()->getFamilyUserIds()))) {
        $cat->delete();
        $this->dispatch('notify', 'Categoria removida.');
    }
};

$prevMonth = function() { 
    $this->currentMonth = Carbon::parse($this->currentMonth)->subMonth()->format('Y-m-d'); 
    session(['current_month' => $this->currentMonth]);
};
$nextMonth = function() { 
    $this->currentMonth = Carbon::parse($this->currentMonth)->addMonth()->format('Y-m-d'); 
    session(['current_month' => $this->currentMonth]);
};
$today = function() { 
    $this->currentMonth = now()->startOfMonth()->format('Y-m-d'); 
    session(['current_month' => $this->currentMonth]);
};
?>

<div x-data="{ categoryModalOpen: false }" @close-modal-category.window="categoryModalOpen = false">

    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 sm:mb-6 gap-3">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <div class="bg-white p-0.5 rounded-lg shadow-sm border border-gray-200 inline-flex">
                <button wire:click="setView('personal')"
                    class="px-3 py-1.5 rounded-md text-[11px] sm:text-sm font-medium transition flex items-center {{ $view === 'personal' ? 'bg-primary text-white shadow' : 'text-gray-500 hover:bg-gray-50' }}">
                    <i data-lucide="user" class="w-3 h-3 sm:w-4 sm:h-4 mr-1"></i> Meu Orçamento
                </button>
                <button wire:click="setView('shared')"
                    class="px-3 py-1.5 rounded-md text-[11px] sm:text-sm font-medium transition flex items-center {{ $view === 'shared' ? 'bg-purple-600 text-white shadow' : 'text-gray-500 hover:bg-gray-50' }}">
                    <i data-lucide="users" class="w-3 h-3 sm:w-4 sm:h-4 mr-1"></i> Nosso Orçamento
                </button>
            </div>
            <p class="text-[10px] text-gray-400 mt-1">
                @if($view === 'personal')
                    Vendo apenas suas categorias pessoais
                @else
                    Vendo categorias compartilhadas do casal
                @endif
            </p>
        </div>

        <div class="flex items-center bg-white rounded-lg shadow-sm border border-gray-200 p-0.5 justify-self-center">
            <button wire:click="prevMonth" class="p-1.5 hover:bg-gray-100 rounded-md transition text-gray-500">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>

            <div class="px-3 text-center min-w-[120px]">
                <h2 class="text-xs sm:text-sm font-bold text-gray-800 capitalize">
                    {{ \Carbon\Carbon::parse($currentMonth)->locale('pt_BR')->translatedFormat('F Y') }}
                </h2>
                @if(\Carbon\Carbon::parse($currentMonth)->isCurrentMonth())
                    <span class="text-[9px] text-green-600 font-medium bg-green-50 px-1.5 py-0.5 rounded-full">Mês Atual</span>
                @else
                    <p class="text-[9px] text-gray-400 cursor-pointer hover:text-primary transition underline decoration-dotted" wire:click="today">
                        Voltar para hoje
                    </p>
                @endif
            </div>

            <button wire:click="nextMonth" class="p-1.5 hover:bg-gray-100 rounded-md transition text-gray-500">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="hidden md:flex justify-self-end">
            <button @click="categoryModalOpen = true; Livewire.dispatch('open-new-category', { scope: '{{ $view }}' })"
                class="bg-gray-900 hover:bg-gray-800 text-white font-medium py-2 px-3 rounded-lg shadow items-center transition text-sm flex">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i> Nova Categoria
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
                        <i data-lucide="calculator" class="w-5 h-5"></i>
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
                        <i data-lucide="receipt" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] text-gray-500 font-medium">Total Gasto em {{ \Carbon\Carbon::parse($currentMonth)->locale('pt_BR')->translatedFormat('F') }}</p>
                            <span class="text-[10px] font-bold {{ $pctUsed > 100 ? 'text-red-600' : ($pctUsed > 80 ? 'text-yellow-600' : 'text-gray-500') }}">{{ $pctUsed }}%</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900">R$ {{ number_format($totalSpent, 2, ',', '.') }}</p>
                        <div class="w-full bg-red-100 rounded-full h-1.5 mt-1">
                            <div class="{{ $pctUsed > 100 ? 'bg-red-500' : ($pctUsed > 80 ? 'bg-yellow-500' : 'bg-primary') }} h-1.5 rounded-full transition-all duration-500" style="width: {{ min(100, $pctUsed) }}%"></div>
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
                
                // Se não tem limite, considera o valor gasto como 100%
                if ($hasLimit) {
                    $pct = round(($used / $cat->limit) * 100);
                } else {
                    $pct = $used > 0 ? 100 : 0;
                }

                if (!$hasLimit) {
                    // Sem limite definido - mostrar em cinza/neutro
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

            <div class="{{ $bgColor }} p-3 sm:p-4 rounded-xl border shadow-sm hover:shadow-md transition relative group flex flex-col h-full">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-white border border-gray-100 flex items-center justify-center shadow-sm">
                            <span class="font-bold text-sm {{ $iconColor }}">{{ substr($cat->name, 0, 1) }}</span>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 text-sm leading-tight">{{ $cat->name }}</h3>
                            @if($view === 'shared' && $cat->user_id !== auth()->id())
                                <span class="text-[9px] text-gray-400 flex items-center">
                                    <i data-lucide="user" class="w-2.5 h-2.5 mr-0.5"></i> {{ substr($cat->user->name ?? '?', 0, 10) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex space-x-0.5 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                        <button @click="categoryModalOpen = true; Livewire.dispatch('edit-category', { id: {{ $cat->id }}, name: '{{ $cat->name }}', limit: {{ $cat->limit }}, scope: '{{ $cat->scope }}' })"
                            class="p-1 hover:bg-gray-100 rounded-md text-gray-400 hover:text-blue-500 transition">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        </button>
                        <button wire:click="deleteCat({{ $cat->id }})" wire:confirm="Excluir a categoria {{ $cat->name }}?"
                            class="p-1 hover:bg-gray-100 rounded-md text-gray-400 hover:text-red-500 transition">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
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

                    <div class="w-full bg-gray-200/50 rounded-full h-2 overflow-hidden">
                        <div class="{{ $barColor }} h-2 rounded-full transition-all duration-1000 ease-out" style="width: {{ min(100, $pct) }}%"></div>
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
                    <i data-lucide="layers" class="w-6 h-6 opacity-50"></i>
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
                    <i data-lucide="plus" class="w-5 h-5"></i>
                </div>
                <span class="font-medium text-xs">Nova Categoria</span>
            </button>
        @endif
    </div>

    <livewire:components.category-modal />
</div>
