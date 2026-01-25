<?php

use function Livewire\Volt\{state, computed, layout};
use App\Models\Transaction;
use App\Models\Category;
use Carbon\Carbon;

layout('components.layouts.app');

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => now()->startOfMonth()->format('Y-m-d')
])->url();

$setView = function ($mode) {
    $this->view = $mode;
    session(['view_mode' => $mode]);
};

// Navegação de Datas
$prevMonth = function() { $this->currentMonth = Carbon::parse($this->currentMonth)->subMonth()->format('Y-m-d'); };
$nextMonth = function() { $this->currentMonth = Carbon::parse($this->currentMonth)->addMonth()->format('Y-m-d'); };
$today = function() { $this->currentMonth = now()->startOfMonth()->format('Y-m-d'); };

$summary = computed(function() {
    $user = auth()->user();
    $targetDate = Carbon::parse($this->currentMonth);
    $familyIds = $user->getFamilyUserIds();

    $queryTx = Transaction::query();
    $queryCat = Category::query();

    // Filtro de Escopo
    if ($this->view === 'personal') {
        $queryTx->where('user_id', $user->id)->where('scope', 'personal');
        $queryCat->where('user_id', $user->id)->where('scope', 'personal');
    } else {
        $queryTx->whereIn('user_id', $familyIds)->where('scope', 'shared');
        $queryCat->whereIn('user_id', $familyIds)->where('scope', 'shared');
    }

    // Filtro de Data Base
    $queryTx->whereYear('date', $targetDate->year)
            ->whereMonth('date', $targetDate->month);

    // Cálculos
    $income = (clone $queryTx)->where('type', 'income')->sum('amount');
    $expense = (clone $queryTx)->where('type', 'expense')->sum('amount');

    // Categorias e Orçamento
    $cats = $queryCat->orderBy('name')->get();
    $budgetTotal = $cats->sum('limit');

    $catUsage = (clone $queryTx)->where('type', 'expense')
        ->selectRaw('category, sum(amount) as total')
        ->groupBy('category')->pluck('total', 'category');

    // Recentes (Apenas deste mês)
    $recent = (clone $queryTx)
        ->orderBy('date', 'desc')
        ->orderBy('created_at', 'desc')
        ->take(6)
        ->get();

    // Porcentagem Geral
    if ($budgetTotal > 0) {
        $pctUsed = min(100, round(($expense / $budgetTotal) * 100));
    } else {
        $pctUsed = $expense > 0 ? 100 : 0;
    }

    return [
        'income' => $income,
        'expense' => $expense,
        'result' => $income - $expense, // Sobra do mês
        'budgetTotal' => $budgetTotal,
        'pctUsed' => $pctUsed,
        'categories' => $cats,
        'catUsage' => $catUsage,
        'recent' => $recent
    ];
});
?>

{{-- x-data com controle de TODOS os modais --}}
<div x-data="{
    transactionModalOpen: false,
    categoryModalOpen: false,
    goalModalOpen: false
}"
@close-modal-transaction.window="transactionModalOpen = false"
@close-modal-category.window="categoryModalOpen = false"
@close-modal-goal.window="goalModalOpen = false">

    {{-- HEADER DE ESCOPO E NAVEGAÇÃO --}}
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">

        {{-- Seletor Pessoal/Compartilhado --}}
        <div class="bg-white p-1 rounded-xl shadow-sm border border-gray-200 inline-flex">
            <button wire:click="setView('personal')"
                class="px-5 py-2 rounded-lg text-sm font-medium transition flex items-center {{ $view === 'personal' ? 'bg-primary text-white shadow-md' : 'text-gray-500 hover:bg-gray-50' }}">
                <i data-lucide="user" class="w-4 h-4 mr-2"></i> Pessoal
            </button>
            <button wire:click="setView('shared')"
                class="px-5 py-2 rounded-lg text-sm font-medium transition flex items-center {{ $view === 'shared' ? 'bg-purple-600 text-white shadow-md' : 'text-gray-500 hover:bg-gray-50' }}">
                <i data-lucide="users" class="w-4 h-4 mr-2"></i> Compartilhado
            </button>
        </div>

        {{-- Navegação de Mês --}}
        <div class="flex items-center bg-white rounded-xl shadow-sm border border-gray-200 p-1">
            <button wire:click="prevMonth" class="p-2 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </button>

            <div class="px-4 text-center min-w-[140px]">
                <h2 class="text-sm font-bold text-gray-800 capitalize">
                    {{ \Carbon\Carbon::parse($currentMonth)->translatedFormat('F Y') }}
                </h2>
                @if(\Carbon\Carbon::parse($currentMonth)->isCurrentMonth())
                    <span class="text-[10px] text-green-600 font-medium bg-green-50 px-2 py-0.5 rounded-full">Mês Atual</span>
                @else
                    <p class="text-[10px] text-gray-400 cursor-pointer hover:text-primary transition underline decoration-dotted" wire:click="today">
                        Voltar para hoje
                    </p>
                @endif
            </div>

            <button wire:click="nextMonth" class="p-2 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <i data-lucide="chevron-right" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Botão Principal --}}
        <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}' })"
            class="hidden md:flex bg-gray-900 hover:bg-gray-800 text-white font-medium py-2 px-4 rounded-xl shadow-lg shadow-gray-200 items-center transition transform hover:scale-105">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Adicionar
        </button>
    </div>

    @if($view === 'shared' && auth()->user()->family && auth()->user()->family->users()->count() < 2)
        <livewire:components.invite-partner />
    @endif

    {{-- GRID DE CARDS PRINCIPAIS --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

        {{-- CARD 1: RECEITAS --}}
        <div class="bg-gradient-to-br from-green-50 to-white p-6 rounded-2xl shadow-sm border border-green-100 relative overflow-hidden group">
            <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition">
                <i data-lucide="trending-up" class="w-16 h-16 text-green-600"></i>
            </div>
            <div class="relative z-10">
                <p class="text-green-800 text-sm font-medium mb-1 flex items-center">
                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span> Entradas
                </p>
                <h3 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                    R$ {{ number_format($this->summary['income'], 2, ',', '.') }}
                </h3>
            </div>
        </div>

        {{-- CARD 2: SAÍDAS --}}
        <div class="bg-gradient-to-br from-red-50 to-white p-6 rounded-2xl shadow-sm border border-red-100 relative overflow-hidden group">
            <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition">
                <i data-lucide="trending-down" class="w-16 h-16 text-red-600"></i>
            </div>
            <div class="relative z-10">
                <div class="flex justify-between items-start mb-1">
                    <p class="text-red-800 text-sm font-medium flex items-center">
                        <span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span> Saídas
                    </p>
                    <span class="text-xs font-bold {{ $this->summary['pctUsed'] > 100 ? 'bg-red-200 text-red-800' : 'bg-gray-100 text-gray-600' }} px-2 py-1 rounded-md">
                        {{ $this->summary['pctUsed'] }}% do Orçamento
                    </span>
                </div>
                <h3 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                    R$ {{ number_format($this->summary['expense'], 2, ',', '.') }}
                </h3>
                <p class="text-xs text-gray-500 mt-2">
                    Meta: R$ {{ number_format($this->summary['budgetTotal'], 2, ',', '.') }}
                </p>
                {{-- Barra de Progresso Mini --}}
                <div class="w-full bg-red-100 rounded-full h-1 mt-2">
                    <div class="bg-red-500 h-1 rounded-full transition-all duration-500" style="width: {{ $this->summary['pctUsed'] }}%"></div>
                </div>
            </div>
        </div>

        {{-- CARD 3: BALANÇO DO MÊS --}}
        @php
            $result = $this->summary['result'];
            $isPositive = $result >= 0;
            $bg = $isPositive ? 'from-blue-50 to-white border-blue-100' : 'from-orange-50 to-white border-orange-100';
            $text = $isPositive ? 'text-blue-900' : 'text-orange-900';
            $subtext = $isPositive ? 'text-blue-600' : 'text-orange-600';
            $icon = $isPositive ? 'wallet' : 'alert-circle';
        @endphp
        <div class="bg-gradient-to-br {{ $bg }} p-6 rounded-2xl shadow-sm border relative overflow-hidden group">
            <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition">
                <i data-lucide="{{ $icon }}" class="w-16 h-16 {{ $isPositive ? 'text-blue-600' : 'text-orange-600' }}"></i>
            </div>
            <div class="relative z-10">
                <p class="{{ $text }} text-sm font-medium mb-1 flex items-center">
                    <i data-lucide="{{ $icon }}" class="w-4 h-4 mr-2"></i> Resultado do Mês
                </p>
                <h3 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                    R$ {{ number_format($result, 2, ',', '.') }}
                </h3>
                <p class="text-xs {{ $subtext }} mt-2 font-medium">
                    {{ $isPositive ? 'Saldo positivo neste mês' : 'Atenção: Gastos superaram ganhos' }}
                </p>
            </div>
        </div>
    </div>

    {{-- SEÇÃO INFERIOR --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">

        {{-- COLUNA 1 e 2: Categorias --}}
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-bold text-gray-900">Orçamento por Categoria</h2>
                <button wire:click="$dispatch('open-new-category', { scope: '{{ $view }}' })" @click="categoryModalOpen = true" class="text-sm text-primary hover:underline font-medium">Gerenciar</button>
            </div>

            <div class="space-y-5">
            @forelse($this->summary['categories'] as $cat)
                @php
                    $used = $this->summary['catUsage'][$cat->name] ?? 0;
                    $catPct = $cat->limit > 0 ? min(100, round(($used / $cat->limit) * 100)) : 0;

                    // Cores Dinâmicas
                    if ($catPct >= 100) {
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
                    <div class="flex justify-between text-sm mb-2 items-end">
                        <div class="flex items-center">
                            <div class="w-8 h-8 {{ $bgColor }} rounded-lg flex items-center justify-center mr-3 text-gray-600">
                                <span class="text-xs font-bold">{{ substr($cat->name, 0, 1) }}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-900 block">{{ $cat->name }}</span>
                                <span class="text-xs text-gray-400">Limite: R$ {{ number_format($cat->limit, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="font-bold {{ $textColor }} block">R$ {{ number_format($used, 2, ',', '.') }}</span>
                            <span class="text-[10px] font-bold {{ $textColor }}">{{ $catPct }}%</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                        <div class="{{ $barColor }} h-2.5 rounded-full transition-all duration-700 ease-out" style="width: {{ $catPct }}%"></div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-10 text-gray-400 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                    <i data-lucide="layers" class="w-10 h-10 mb-2 opacity-50"></i>
                    <p>Você ainda não criou categorias.</p>
                    <button @click="categoryModalOpen = true; Livewire.dispatch('open-new-category', { scope: '{{ $view }}' })" class="mt-2 text-primary text-sm font-medium hover:underline">Criar agora</button>
                </div>
            @endforelse
            </div>
        </div>

        {{-- COLUNA 3: Recentes --}}
        <div class="lg:col-span-1 bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-bold text-gray-900">Atividade Recente</h2>
                <a href="{{ route('expenses') }}" class="text-xs text-gray-500 hover:text-primary transition">Ver tudo</a>
            </div>

            <div class="flex-1 overflow-y-auto pr-1 space-y-4">
                @forelse($this->summary['recent'] as $t)
                    <div class="flex items-center justify-between group p-2 hover:bg-gray-50 rounded-lg transition border border-transparent hover:border-gray-100">
                        <div class="flex items-center overflow-hidden">
                            <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center mr-3 {{ $t->type == 'income' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                                <i data-lucide="{{ $t->type == 'income' ? 'arrow-up' : 'arrow-down' }}" class="w-4 h-4"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $t->description }}</p>
                                <p class="text-xs text-gray-500 flex items-center">
                                    {{ $t->date->format('d/m') }}
                                    <span class="mx-1">•</span>
                                    {{ $t->category }}
                                    @if($t->is_recurring)
                                        <i data-lucide="repeat" class="w-3 h-3 ml-1 text-blue-400"></i>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <span class="text-sm font-bold whitespace-nowrap {{ $t->type == 'income' ? 'text-green-600' : 'text-gray-900' }}">
                            {{ $t->type == 'income' ? '+' : '-' }}{{ number_format($t->amount, 0, ',', '.') }}
                        </span>
                    </div>
                @empty
                    <div class="h-full flex flex-col items-center justify-center text-gray-400 opacity-70">
                        <i data-lucide="calendar-x" class="w-10 h-10 mb-2"></i>
                        <p class="text-sm">Sem movimentações este mês.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-6 pt-6 border-t border-gray-100 grid grid-cols-2 gap-3">
                 <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}' })"
                    class="py-2 px-3 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-xs font-bold transition flex justify-center items-center">
                    <i data-lucide="minus" class="w-3 h-3 mr-1"></i> Despesa
                 </button>
                 <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'income', scope: '{{ $view }}' })"
                    class="py-2 px-3 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg text-xs font-bold transition flex justify-center items-center">
                    <i data-lucide="plus" class="w-3 h-3 mr-1"></i> Receita
                 </button>
            </div>
        </div>
    </div>

    {{-- ATALHOS FLUTUANTES MOBILE (Opcional, visível só em telas pequenas) --}}
    <div class="md:hidden fixed bottom-6 right-6 flex flex-col gap-3 z-40">
        <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}' })" class="w-12 h-12 bg-red-500 rounded-full text-white shadow-lg flex items-center justify-center">
            <i data-lucide="minus" class="w-6 h-6"></i>
        </button>
        <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'income', scope: '{{ $view }}' })" class="w-12 h-12 bg-green-500 rounded-full text-white shadow-lg flex items-center justify-center">
            <i data-lucide="plus" class="w-6 h-6"></i>
        </button>
    </div>

    {{-- MODAIS --}}
    <livewire:components.transaction-modal />
    <livewire:components.category-modal />
    <livewire:components.goal-modal />
</div>
