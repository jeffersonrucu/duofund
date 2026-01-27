<?php

use function Livewire\Volt\{state, computed, layout, on};
use App\Models\Transaction;
use App\Models\Category;
use Carbon\Carbon;

layout('components.layouts.app');

on(['family-updated' => function () {
    $this->summary;
}]);

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => session('current_month', now()->startOfMonth()->format('Y-m-d'))
])->url();

$setView = function ($mode) {
    $this->view = $mode;
    session(['view_mode' => $mode]);
    $this->dispatch('scope-changed', $mode);
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

$summary = computed(function() {
    $user = auth()->user();
    $targetDate = Carbon::parse($this->currentMonth);
    $familyIds = $user->getFamilyUserIds();

    $queryTx = Transaction::query();
    $queryCat = Category::query();

    if ($this->view === 'personal') {
        $queryTx->where('user_id', $user->id)->where('scope', 'personal');
        $queryCat->where('user_id', $user->id)->where('scope', 'personal');
    } else {
        $queryTx->whereIn('user_id', $familyIds)->where('scope', 'shared');
        $queryCat->whereIn('user_id', $familyIds)->where('scope', 'shared');
    }

    $queryTx->whereYear('date', $targetDate->year)
            ->whereMonth('date', $targetDate->month);

    $income = (clone $queryTx)->where('type', 'income')->sum('amount');
    $expense = (clone $queryTx)->where('type', 'expense')->sum('amount');

    $cats = $queryCat->orderBy('name')->get();
    $budgetTotal = $cats->sum('limit');

    $catUsage = (clone $queryTx)->where('type', 'expense')
        ->selectRaw('category, sum(amount) as total')
        ->groupBy('category')->pluck('total', 'category');

    $recent = (clone $queryTx)
        ->orderBy('date', 'desc')
        ->orderBy('created_at', 'desc')
        ->take(6)
        ->get();

    if ($budgetTotal > 0) {
        $pctUsed = min(100, round(($expense / $budgetTotal) * 100));
    } else {
        $pctUsed = $expense > 0 ? 100 : 0;
    }

    return [
        'income' => $income,
        'expense' => $expense,
        'result' => $income - $expense,
        'budgetTotal' => $budgetTotal,
        'pctUsed' => $pctUsed,
        'categories' => $cats,
        'catUsage' => $catUsage,
        'recent' => $recent
    ];
});
?>

<div wire:poll.10s>
    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 sm:mb-6 gap-3">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <div class="bg-white p-0.5 rounded-lg shadow-sm border border-gray-200 inline-flex">
                <button wire:click="setView('personal')"
                    class="px-3 py-1.5 rounded-md text-[11px] sm:text-sm font-medium transition flex items-center {{ $view === 'personal' ? 'bg-primary text-white shadow' : 'text-gray-500 hover:bg-gray-50' }}">
                    <i data-lucide="user" class="w-3 h-3 sm:w-4 sm:h-4 mr-1"></i> Meu Dinheiro
                </button>
                <button wire:click="setView('shared')"
                    class="px-3 py-1.5 rounded-md text-[11px] sm:text-sm font-medium transition flex items-center {{ $view === 'shared' ? 'bg-purple-600 text-white shadow' : 'text-gray-500 hover:bg-gray-50' }}">
                    <i data-lucide="users" class="w-3 h-3 sm:w-4 sm:h-4 mr-1"></i> Nosso Dinheiro
                </button>
            </div>
            <p class="text-[10px] text-gray-400 mt-1">
                @if($view === 'personal')
                    Vendo apenas suas finanças pessoais
                @else
                    Vendo finanças compartilhadas do casal
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
            <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}', date: '{{ $currentMonth }}' })"
                class="bg-gray-900 hover:bg-gray-800 text-white font-medium py-2 px-3 rounded-lg shadow items-center transition text-sm flex">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i> Adicionar
            </button>
        </div>
    </div>

    @if ($view === 'shared')
        @if(auth()->user()->family?->users()->count() < 2)
            <livewire:components.invite-partner />
        @else
            <div class="bg-gradient-to-r from-green-600 to-teal-600 rounded-xl p-4 text-white shadow-lg mb-4 sm:mb-6 flex justify-between items-center">
                <div class="flex items-center">
                    <i data-lucide="check-circle-2" class="w-6 h-6 mr-2"></i>
                    <div>
                        <h2 class="text-sm sm:text-base font-bold">Família Sincronizada</h2>
                        <p class="text-green-100 text-[11px] mt-0.5">
                            Você e sua parceira(o) estão prontos para gerenciar as finanças.
                        </p>
                    </div>
                </div>
                <div class="flex -space-x-2">
                    @foreach(auth()->user()->family->users as $member)
                        <div class="w-8 h-8 rounded-full flex items-center justify-center bg-white/20 text-white font-bold text-xs ring-2 ring-white" title="{{ $member->name }}">
                            {{ substr($member->name, 0, 1) }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- GRID DE CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
        {{-- CARD 1: RECEITAS --}}
        <div class="bg-gradient-to-br from-green-50 to-white p-4 rounded-xl shadow-sm border border-green-100 relative overflow-hidden group">
            <div class="absolute right-0 top-0 p-2 opacity-10 group-hover:opacity-20 transition">
                <i data-lucide="trending-up" class="w-12 h-12 text-green-600"></i>
            </div>
            <div class="relative z-10">
                <p class="text-green-800 text-[11px] font-medium mb-0.5 flex items-center">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span> Entradas
                </p>
                <h3 class="text-xl sm:text-2xl font-extrabold text-gray-900 tracking-tight">
                    R$ {{ number_format($this->summary['income'], 2, ',', '.') }}
                </h3>
            </div>
        </div>

        {{-- CARD 2: SAÍDAS --}}
        <div class="bg-gradient-to-br from-red-50 to-white p-4 rounded-xl shadow-sm border border-red-100 relative overflow-hidden group">
            <div class="absolute right-0 top-0 p-2 opacity-10 group-hover:opacity-20 transition">
                <i data-lucide="trending-down" class="w-12 h-12 text-red-600"></i>
            </div>
            <div class="relative z-10">
                <div class="flex justify-between items-start mb-0.5">
                    <p class="text-red-800 text-[11px] font-medium flex items-center">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span> Saídas
                    </p>
                    <span class="text-[9px] font-bold {{ $this->summary['pctUsed'] > 100 ? 'bg-red-200 text-red-800' : 'bg-gray-100 text-gray-600' }} px-1.5 py-0.5 rounded">
                        {{ $this->summary['pctUsed'] }}%
                    </span>
                </div>
                <h3 class="text-xl sm:text-2xl font-extrabold text-gray-900 tracking-tight">
                    R$ {{ number_format($this->summary['expense'], 2, ',', '.') }}
                </h3>
                <p class="text-[10px] text-gray-500 mt-1">
                    Meta: R$ {{ number_format($this->summary['budgetTotal'], 2, ',', '.') }}
                </p>
                <div class="w-full bg-red-100 rounded-full h-1 mt-1">
                    <div class="bg-red-500 h-1 rounded-full transition-all duration-500" style="width: {{ $this->summary['pctUsed'] }}%"></div>
                </div>
            </div>
        </div>

        {{-- CARD 3: BALANÇO --}}
        @php
            $result = $this->summary['result'];
            $isPositive = $result >= 0;
            $bg = $isPositive ? 'from-blue-50 to-white border-blue-100' : 'from-orange-50 to-white border-orange-100';
            $text = $isPositive ? 'text-blue-900' : 'text-orange-900';
            $subtext = $isPositive ? 'text-blue-600' : 'text-orange-600';
            $icon = $isPositive ? 'wallet' : 'alert-circle';
        @endphp
        <div class="bg-gradient-to-br {{ $bg }} p-4 rounded-xl shadow-sm border relative overflow-hidden group">
            <div class="absolute right-0 top-0 p-2 opacity-10 group-hover:opacity-20 transition">
                <i data-lucide="{{ $icon }}" class="w-12 h-12 {{ $isPositive ? 'text-blue-600' : 'text-orange-600' }}"></i>
            </div>
            <div class="relative z-10">
                <p class="{{ $text }} text-[11px] font-medium mb-0.5 flex items-center">
                    <i data-lucide="{{ $icon }}" class="w-3 h-3 mr-1"></i> Resultado
                </p>
                <h3 class="text-xl sm:text-2xl font-extrabold text-gray-900 tracking-tight">
                    R$ {{ number_format($result, 2, ',', '.') }}
                </h3>
                <p class="text-[10px] {{ $subtext }} mt-1 font-medium">
                    {{ $isPositive ? 'Saldo positivo neste mês' : 'Gastos superaram ganhos' }}
                </p>
            </div>
        </div>
    </div>

    {{-- SEÇÃO INFERIOR --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-4 sm:mb-6">
        {{-- COLUNA 1 e 2: Categorias --}}
        <div class="lg:col-span-2 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-sm font-bold text-gray-900">Orçamento por Categoria</h2>
                <button wire:click="$dispatch('open-new-category', { scope: '{{ $view }}' })" @click="categoryModalOpen = true" class="text-xs text-primary hover:underline font-medium">Gerenciar</button>
            </div>

            <div class="space-y-3">
            @forelse($this->summary['categories'] as $cat)
                @php
                    $used = $this->summary['catUsage'][$cat->name] ?? 0;
                    $hasLimit = $cat->limit > 0;
                    
                    // Se não tem limite, considera 100% (gasto total) e usa o valor gasto como referência
                    if ($hasLimit) {
                        $catPct = min(100, round(($used / $cat->limit) * 100));
                    } else {
                        $catPct = $used > 0 ? 100 : 0;
                    }

                    if (!$hasLimit) {
                        // Sem limite definido - mostrar em cinza/neutro
                        $barColor = 'bg-gray-400';
                        $textColor = 'text-gray-600';
                        $bgColor = 'bg-gray-50';
                    } elseif ($catPct >= 100) {
                        $barColor = 'bg-red-500';
                        $textColor = 'text-red-600';
                        $bgColor = 'bg-red-50';
                    } elseif ($catPct >= 75) {
                        $barColor = 'bg-yellow-500';
                        $textColor = 'text-yellow-700';
                        $bgColor = 'bg-yellow-50';
                    } else {
                        $barColor = 'bg-primary';
                        $textColor = 'text-gray-900';
                        $bgColor = 'bg-gray-50';
                    }
                @endphp
                <div class="group">
                    <div class="flex justify-between text-xs mb-1.5 items-end">
                        <div class="flex items-center">
                            <div class="w-6 h-6 {{ $bgColor }} rounded-md flex items-center justify-center mr-2 text-gray-600">
                                <span class="text-[10px] font-bold">{{ substr($cat->name, 0, 1) }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-900 block text-sm">{{ $cat->name }}</span>
                                @if($hasLimit)
                                    <span class="text-[10px] text-gray-400">R$ {{ number_format($cat->limit, 0, ',', '.') }}</span>
                                @else
                                    <span class="text-[10px] text-gray-400 italic">Sem limite</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="font-bold {{ $textColor }} block text-sm">R$ {{ number_format($used, 2, ',', '.') }}</span>
                            @if($hasLimit)
                                <span class="text-[9px] font-bold {{ $textColor }}">{{ $catPct }}%</span>
                            @endif
                        </div>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="{{ $barColor }} h-1.5 rounded-full transition-all duration-700 ease-out" style="width: {{ $catPct }}%"></div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-6 text-gray-400 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                    <i data-lucide="layers" class="w-8 h-8 mb-2 opacity-50"></i>
                    <p class="text-xs">Você ainda não criou categorias.</p>
                    <button @click="categoryModalOpen = true; Livewire.dispatch('open-new-category', { scope: '{{ $view }}' })" class="mt-2 text-primary text-xs font-medium hover:underline">Criar agora</button>
                </div>
            @endforelse
            </div>
        </div>

        {{-- COLUNA 3: Recentes --}}
        <div class="lg:col-span-1 bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-sm font-bold text-gray-900">Atividade Recente</h2>
                <a href="{{ route('expenses') }}" class="text-[10px] text-gray-500 hover:text-primary transition">Ver tudo</a>
            </div>

            <div class="flex-1 overflow-y-auto pr-1 space-y-2">
                @forelse($this->summary['recent'] as $t)
                    <div class="flex items-center justify-between group p-2 hover:bg-gray-50 rounded-lg transition border border-transparent hover:border-gray-100">
                        <div class="flex items-center overflow-hidden">
                            <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center mr-2 {{ $t->type == 'income' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                                <i data-lucide="{{ $t->type == 'income' ? 'arrow-up' : 'arrow-down' }}" class="w-3.5 h-3.5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-gray-900 truncate">{{ $t->description }}</p>
                                <p class="text-[10px] text-gray-500 flex items-center">
                                    {{ $t->date->format('d/m') }}
                                    <span class="mx-1">•</span>
                                    {{ $t->category }}
                                    @if($t->is_recurring)
                                        <i data-lucide="repeat" class="w-2.5 h-2.5 ml-1 text-blue-400"></i>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <span class="text-xs font-bold whitespace-nowrap {{ $t->type == 'income' ? 'text-green-600' : 'text-gray-900' }}">
                            {{ $t->type == 'income' ? '+' : '-' }}{{ number_format($t->amount, 0, ',', '.') }}
                        </span>
                    </div>
                @empty
                    <div class="h-full flex flex-col items-center justify-center text-gray-400 opacity-70 py-6">
                        <i data-lucide="calendar-x" class="w-8 h-8 mb-2"></i>
                        <p class="text-xs">Sem movimentações este mês.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-2">
                 <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}', date: '{{ $currentMonth }}' })"
                    class="py-2 px-2 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-[11px] font-bold transition flex justify-center items-center">
                    <i data-lucide="minus" class="w-3 h-3 mr-1"></i> Despesa
                 </button>
                 <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'income', scope: '{{ $view }}', date: '{{ $currentMonth }}' })"
                    class="py-2 px-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg text-[11px] font-bold transition flex justify-center items-center">
                    <i data-lucide="plus" class="w-3 h-3 mr-1"></i> Receita
                 </button>
            </div>
        </div>
    </div>

    {{-- MODAIS --}}
    <livewire:components.transaction-modal />
    <livewire:components.category-modal />
    <livewire:components.goal-modal />
</div>
