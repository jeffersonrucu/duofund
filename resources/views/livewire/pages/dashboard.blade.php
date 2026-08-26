<?php

use function Livewire\Volt\{state, computed, layout, on, uses};
use App\Livewire\Concerns\HasMonthNavigation;
use App\Livewire\Concerns\HasScopeToggle;
use App\Models\Transaction;
use App\Models\Category;
use Carbon\Carbon;

layout('components.layouts.app');
uses([HasMonthNavigation::class, HasScopeToggle::class]);

on(['family-updated' => function () {
    $this->summary;
}]);

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => session('current_month', now()->startOfMonth()->format('Y-m-d'))
])->url();

state([
    'detailCatId' => null,
    'detailCatName' => null,
]);

$summary = computed(function() {
    $user = auth()->user();
    $targetDate = Carbon::parse($this->currentMonth);

    $totals = app(\App\Services\MonthlySummaryService::class)->for($user, $this->view, $targetDate);
    $income = $totals['income'];
    $expense = $totals['expense'];

    $queryTx = Transaction::forView($user, $this->view)->inMonth($targetDate);

    $cats = Category::forView($user, $this->view)->orderBy('name')->get();
    $budgetTotal = $cats->sum('limit');

    $catUsage = (clone $queryTx)->where('type', 'expense')
        ->selectRaw('category, sum(amount) as total')
        ->groupBy('category')->pluck('total', 'category');

    $recent = (clone $queryTx)
        ->with('user')
        ->orderBy('date', 'desc')
        ->orderBy('created_at', 'desc')
        ->take(6)
        ->get();

    if ($budgetTotal > 0) {
        $pctUsed = min(100, round(($expense / $budgetTotal) * 100));
    } else {
        $pctUsed = $expense > 0 ? 100 : 0;
    }

    // Categorias em risco: o dado já existia, mas só aparecia em /orcamento
    $alerts = $cats
        ->filter(fn ($c) => (float) $c->limit > 0)
        ->map(function ($c) use ($catUsage) {
            $used = (float) ($catUsage[$c->name] ?? 0);
            $limit = (float) $c->limit;

            return [
                'name' => $c->name,
                'used' => $used,
                'limit' => $limit,
                'pct' => (int) round($used / $limit * 100),
            ];
        })
        ->filter(fn ($a) => $a['pct'] >= 80)
        ->sortByDesc('pct')
        ->values();

    return [
        'alerts' => $alerts,
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
?>

<div wire:poll.10s>
    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 sm:mb-6 gap-3">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <x-ui.view-toggle :view="$view" personal-label="Meu Dinheiro" shared-label="Nosso Dinheiro" />
            <p class="text-[10px] text-gray-400 mt-1">
                @if($view === 'personal')
                    Vendo apenas suas finanças pessoais
                @else
                    Vendo finanças compartilhadas do casal
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
            <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}', date: '{{ $currentMonth }}' })"
                class="bg-primary hover:bg-secondary text-white font-medium py-2 px-4 rounded-lg shadow-md shadow-primary/25 items-center transition text-sm flex">
                <x-lucide-plus class="w-4 h-4 mr-1.5" /> Adicionar
            </button>
        </div>
    </div>

    @if($view === 'personal' && (!auth()->user()->family || auth()->user()->family->users->count() < 2))
    <div class="bg-violet-50 border border-violet-100 rounded-xl p-3 mb-4 sm:mb-6 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <x-lucide-heart-handshake class="w-4 h-4 text-violet-600" />
            </div>
            <div>
                <p class="text-sm font-medium text-violet-900">Gerencie finanças com seu(sua) parceiro(a)</p>
                <p class="text-[11px] text-violet-500">Convide e registrem juntos</p>
            </div>
        </div>
        <button wire:click="setView('shared')" class="text-xs font-semibold text-violet-700 bg-white border border-violet-200 px-3 py-1.5 rounded-lg hover:bg-violet-50 transition flex-shrink-0">
            Convidar
        </button>
    </div>
    @endif

    @if ($view === 'shared')
        @if(auth()->user()->family?->users()->count() < 2)
            <livewire:components.invite-partner />
        @else
            <div class="bg-gradient-to-r from-green-600 to-teal-600 rounded-xl p-4 text-white shadow-lg mb-4 sm:mb-6 flex justify-between items-center">
                <div class="flex items-center">
                    <x-lucide-check-circle-2 class="w-6 h-6 mr-2" />
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
    {{-- ALERTAS DE ORÇAMENTO --}}
    @php $alerts = $this->summary['alerts']; @endphp
    @if($alerts->isNotEmpty())
        @php
            $estourou = $alerts->where('pct', '>', 100);
            $critico = $estourou->isNotEmpty();
        @endphp
        <div class="rounded-xl border p-3 mb-4 sm:mb-6 {{ $critico ? 'bg-red-50 border-red-100' : 'bg-amber-50 border-amber-100' }}">
            <div class="flex items-start gap-2.5">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 {{ $critico ? 'bg-red-100' : 'bg-amber-100' }}">
                    <x-dynamic-component :component="'lucide-'.($critico ? 'alert-circle' : 'alert-triangle')"
                        class="w-4 h-4 {{ $critico ? 'text-red-600' : 'text-amber-600' }}" />
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold {{ $critico ? 'text-red-900' : 'text-amber-900' }}">
                        @if($critico)
                            {{ $estourou->count() }} {{ $estourou->count() === 1 ? 'categoria estourou' : 'categorias estouraram' }} o limite
                        @else
                            {{ $alerts->count() }} {{ $alerts->count() === 1 ? 'categoria está perto' : 'categorias estão perto' }} do limite
                        @endif
                    </p>

                    <ul class="mt-1 space-y-0.5">
                        @foreach($alerts->take(3) as $alerta)
                            <li class="text-[11px] flex items-baseline justify-between gap-2 {{ $alerta['pct'] > 100 ? 'text-red-700' : 'text-amber-700' }}">
                                <span class="truncate">{{ $alerta['name'] }}</span>
                                <span class="flex-shrink-0 font-semibold tabular-nums">
                                    {{ $alerta['pct'] }}% · R$ {{ number_format($alerta['used'], 2, ',', '.') }} de {{ number_format($alerta['limit'], 2, ',', '.') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    @if($alerts->count() > 3)
                        <p class="text-[10px] mt-1 {{ $critico ? 'text-red-600' : 'text-amber-600' }}">
                            e mais {{ $alerts->count() - 3 }}
                        </p>
                    @endif
                </div>

                <a href="{{ route('budget') }}" wire:navigate
                   class="flex-shrink-0 text-[11px] font-semibold px-2.5 py-1.5 rounded-lg transition {{ $critico ? 'text-red-700 hover:bg-red-100' : 'text-amber-700 hover:bg-amber-100' }}">
                    Ver
                </a>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
        {{-- CARD 1: RECEITAS --}}
        <div class="bg-gradient-to-br from-green-50 to-white p-4 rounded-xl shadow-sm border border-green-100 relative overflow-hidden group">
            <div class="absolute right-0 top-0 p-2 opacity-10 group-hover:opacity-20 transition">
                <x-lucide-trending-up class="w-12 h-12 text-green-600" />
            </div>
            <div class="relative z-10">
                <p class="text-green-800 text-[11px] font-medium mb-0.5 flex items-center">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span> Entradas
                </p>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                    R$ {{ number_format($this->summary['income'], 2, ',', '.') }}
                </h3>
            </div>
        </div>

        {{-- CARD 2: SAÍDAS --}}
        <div class="bg-gradient-to-br from-red-50 to-white p-4 rounded-xl shadow-sm border border-red-100 relative overflow-hidden group">
            <div class="absolute right-0 top-0 p-2 opacity-10 group-hover:opacity-20 transition">
                <x-lucide-trending-down class="w-12 h-12 text-red-600" />
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
                <h3 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                    R$ {{ number_format($this->summary['expense'], 2, ',', '.') }}
                </h3>
                <p class="text-[10px] text-gray-500 mt-1">
                    Meta: R$ {{ number_format($this->summary['budgetTotal'], 2, ',', '.') }}
                </p>
                <div class="w-full bg-red-100 rounded-full h-2 mt-1.5">
                    <div class="bg-red-500 h-2 rounded-full transition-all duration-500" style="width: {{ $this->summary['pctUsed'] }}%"></div>
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
                <x-dynamic-component :component="'lucide-'.($icon)" class="w-12 h-12 {{ $isPositive ? 'text-blue-600' : 'text-orange-600' }}" />
            </div>
            <div class="relative z-10">
                <p class="{{ $text }} text-[11px] font-medium mb-0.5 flex items-center">
                    <x-dynamic-component :component="'lucide-'.($icon)" class="w-3 h-3 mr-1" /> Resultado
                </p>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
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
                <button type="button" wire:click="openCategoryDetail({{ $cat->id }})"
                    class="group w-full text-left cursor-pointer block hover:bg-gray-50 active:bg-gray-100 rounded-lg -mx-1.5 px-1.5 py-1 transition">
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
                    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div class="{{ $barColor }} h-2 rounded-full transition-all duration-700 ease-out" style="width: {{ $catPct }}%"></div>
                    </div>
                </button>
            @empty
                <div class="flex flex-col items-center justify-center py-6 text-gray-400 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                    <x-lucide-layers class="w-8 h-8 mb-2 opacity-50" />
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
                    <button type="button"
                        @click="transactionModalOpen = true; Livewire.dispatch('edit-transaction', { id: {{ $t->id }} })"
                        class="w-full text-left flex items-center justify-between group p-2 min-h-[48px] hover:bg-gray-50 active:bg-gray-100 rounded-lg transition border border-transparent hover:border-gray-100 cursor-pointer"
                        title="Tocar para editar">
                        <div class="flex items-center overflow-hidden">
                            <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center mr-2 {{ $t->type == 'income' ? 'bg-green-100 text-green-600' : ($t->type == 'savings' ? 'bg-violet-100 text-violet-600' : 'bg-red-100 text-red-600') }}">
                                <x-dynamic-component :component="'lucide-'.($t->type == 'income' ? 'arrow-up' : ($t->type == 'savings' ? 'piggy-bank' : 'arrow-down'))" class="w-3.5 h-3.5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-gray-900 truncate">{{ $t->description }}</p>
                                <p class="text-[10px] text-gray-500 flex items-center">
                                    {{ $t->date->format('d/m') }}
                                    <span class="mx-1">•</span>
                                    {{ $t->category }}
                                    @if($t->is_recurring)
                                        <x-lucide-repeat class="w-2.5 h-2.5 ml-1 text-blue-400" />
                                    @endif
                                </p>
                            </div>
                        </div>
                        <span class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-xs font-bold {{ $t->type == 'income' ? 'text-green-600' : ($t->type == 'savings' ? 'text-violet-600' : 'text-gray-900') }}">
                                {{ $t->type == 'income' ? '+' : ($t->type == 'savings' ? '' : '-') }}{{ number_format($t->amount, 0, ',', '.') }}
                            </span>
                            <span class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 group-hover:text-primary group-hover:bg-blue-50 transition">
                                <x-lucide-pencil class="w-3.5 h-3.5" />
                            </span>
                        </span>
                    </button>
                @empty
                    <div class="h-full flex flex-col items-center justify-center text-gray-400 opacity-70 py-6">
                        <x-lucide-calendar-x class="w-8 h-8 mb-2" />
                        <p class="text-xs">Sem movimentações este mês.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-2">
                 <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}', date: '{{ $currentMonth }}' })"
                    class="py-2 px-2 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-[11px] font-bold transition flex justify-center items-center">
                    <x-lucide-minus class="w-3 h-3 mr-1" /> Despesa
                 </button>
                 <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'income', scope: '{{ $view }}', date: '{{ $currentMonth }}' })"
                    class="py-2 px-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg text-[11px] font-bold transition flex justify-center items-center">
                    <x-lucide-plus class="w-3 h-3 mr-1" /> Receita
                 </button>
            </div>
        </div>
    </div>

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

    {{-- MODAIS --}}
    <livewire:components.transaction-modal />
    <livewire:components.category-modal />
    <livewire:components.goal-modal />
</div>
