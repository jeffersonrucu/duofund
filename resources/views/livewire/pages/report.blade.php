<?php

use function Livewire\Volt\{state, computed, layout};
use App\Models\Transaction;
use App\Models\Category;
use Carbon\Carbon;

layout('components.layouts.app');

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

$report = computed(function () {
    $user = auth()->user();
    $familyIds = $user->getFamilyUserIds();
    $date = Carbon::parse($this->currentMonth)->startOfMonth();

    $query = Transaction::query();
    if ($this->view === 'personal') {
        $query->where('user_id', $user->id)->where('scope', 'personal');
    } else {
        $query->whereIn('user_id', $familyIds)->where('scope', 'shared');
    }
    $query->whereYear('date', $date->year)->whereMonth('date', $date->month);

    $income  = (clone $query)->where('type', 'income')->sum('amount');
    $expense = (clone $query)->where('type', 'expense')->sum('amount');
    $savings = (clone $query)->where('type', 'savings')->sum('amount');

    $catUsage = (clone $query)
        ->where('type', 'expense')
        ->selectRaw('category, sum(amount) as total, count(*) as qty')
        ->groupBy('category')
        ->orderByDesc('total')
        ->get();

    $transactions = (clone $query)
        ->orderByRaw("CASE type WHEN 'income' THEN 0 WHEN 'savings' THEN 1 ELSE 2 END")
        ->orderBy('date', 'asc')
        ->orderBy('created_at', 'asc')
        ->get();

    $incomes = $transactions->where('type', 'income')->values();
    $outflows = $transactions->whereIn('type', ['expense', 'savings'])->values();

    return [
        'income'       => $income,
        'expense'      => $expense,
        'savings'      => $savings,
        'balance'      => $income - $expense,
        'catUsage'     => $catUsage,
        'incomes'      => $incomes,
        'outflows'     => $outflows,
        'txQty'        => $transactions->count(),
    ];
});
?>

<div>
    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 gap-3 print:hidden">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <div class="bg-white p-0.5 rounded-lg shadow-sm border border-gray-200 inline-flex">
                <button wire:click="setView('personal')"
                    class="px-3 py-1.5 rounded-md text-[11px] sm:text-sm font-medium transition flex items-center {{ $view === 'personal' ? 'bg-primary text-white shadow' : 'text-gray-500 hover:bg-gray-50' }}">
                    <i data-lucide="user" class="w-3 h-3 sm:w-4 sm:h-4 mr-1"></i> Meu Extrato
                </button>
                <button wire:click="setView('shared')"
                    class="px-3 py-1.5 rounded-md text-[11px] sm:text-sm font-medium transition flex items-center {{ $view === 'shared' ? 'bg-purple-600 text-white shadow' : 'text-gray-500 hover:bg-gray-50' }}">
                    <i data-lucide="users" class="w-3 h-3 sm:w-4 sm:h-4 mr-1"></i> Nosso Extrato
                </button>
            </div>
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
            <button onclick="window.print()"
                class="bg-primary hover:bg-secondary text-white font-medium py-2 px-4 rounded-lg shadow-md shadow-primary/25 items-center transition text-sm flex">
                <i data-lucide="printer" class="w-4 h-4 mr-1.5"></i> Imprimir
            </button>
        </div>
    </div>

    {{-- CABEÇALHO DE IMPRESSÃO --}}
    <div class="hidden print:block mb-4">
        <h1 class="text-lg font-bold text-gray-900">DuoFund — Extrato Mensal</h1>
        <p class="text-xs text-gray-500 capitalize">
            {{ $view === 'personal' ? 'Conta Pessoal' : 'Conta Compartilhada' }}
            — {{ \Carbon\Carbon::parse($currentMonth)->locale('pt_BR')->translatedFormat('F Y') }}
            — gerado em {{ now()->locale('pt_BR')->translatedFormat('d/m/Y') }}
        </p>
        <hr class="my-2 border-gray-300">
    </div>

    {{-- RESUMO COMPACTO --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 mb-4 grid grid-cols-3 sm:grid-cols-4 gap-3 divide-x divide-gray-100">
        <div class="px-2">
            <p class="text-[9px] text-gray-400 font-medium uppercase tracking-wide">Receitas</p>
            <p class="text-[11px] sm:text-xs font-bold text-green-600">R$ {{ number_format($this->report['income'], 2, ',', '.') }}</p>
        </div>
        <div class="px-2">
            <p class="text-[9px] text-gray-400 font-medium uppercase tracking-wide">Despesas</p>
            <p class="text-[11px] sm:text-xs font-bold text-red-600">R$ {{ number_format($this->report['expense'], 2, ',', '.') }}</p>
        </div>
        <div class="px-2">
            <p class="text-[9px] text-gray-400 font-medium uppercase tracking-wide">Saldo</p>
            @php $bal = $this->report['balance']; @endphp
            <p class="text-[11px] sm:text-xs font-bold {{ $bal >= 0 ? 'text-blue-600' : 'text-orange-600' }}">R$ {{ number_format($bal, 2, ',', '.') }}</p>
        </div>
        <div class="px-2 hidden sm:block">
            <p class="text-[9px] text-gray-400 font-medium uppercase tracking-wide">Lançamentos</p>
            <p class="text-[11px] sm:text-xs font-bold text-gray-900">{{ $this->report['txQty'] }}</p>
        </div>
    </div>

    {{-- EXTRATO --}}
    @php
        $renderTx = function ($t, $view) {
            $iconBg = $t->type == 'income' ? 'bg-green-50 text-green-600' : ($t->type == 'savings' ? 'bg-violet-50 text-violet-600' : 'bg-red-50 text-red-600');
            $icon = $t->type == 'income' ? 'arrow-up' : ($t->type == 'savings' ? 'piggy-bank' : 'arrow-down');
            $valueColor = $t->type == 'income' ? 'text-green-600' : ($t->type == 'savings' ? 'text-violet-600' : 'text-gray-900');
            $sign = $t->type == 'income' ? '+' : ($t->type == 'savings' ? '' : '-');
            return compact('iconBg', 'icon', 'valueColor', 'sign');
        };
    @endphp

    {{-- ENTRADAS --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between bg-green-50/40">
            <div class="flex items-center gap-2">
                <i data-lucide="arrow-up-circle" class="w-3.5 h-3.5 text-green-600"></i>
                <h3 class="text-[11px] font-bold text-gray-800">Entradas</h3>
            </div>
            <div class="flex items-center gap-3 text-[9px]">
                <span class="text-gray-400">{{ $this->report['incomes']->count() }} {{ $this->report['incomes']->count() == 1 ? 'lançamento' : 'lançamentos' }}</span>
                <span class="font-bold text-green-600">R$ {{ number_format($this->report['income'], 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="divide-y divide-gray-50">
            @php $prevDate = null; @endphp
            @forelse($this->report['incomes'] as $t)
                @php
                    $txDate = $t->date->format('Y-m-d');
                    $showDateHeader = $prevDate !== $txDate;
                    $prevDate = $txDate;
                    $r = $renderTx($t, $view);
                @endphp
                @if($showDateHeader)
                <div class="px-3 py-1 bg-gray-50/80 border-b border-gray-100">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                        {{ $t->date->locale('pt_BR')->isoFormat('D [de] MMMM') }}
                    </span>
                </div>
                @endif
                <div class="px-3 py-2 hover:bg-gray-50/50 transition flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center {{ $r['iconBg'] }}">
                        <i data-lucide="{{ $r['icon'] }}" class="w-3 h-3"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-medium text-gray-900 truncate">{{ preg_replace('/\s*\(\d+\/\d+\)$/', '', $t->description) }}</span>
                            @if($t->is_recurring)
                                <i data-lucide="repeat" class="w-2.5 h-2.5 text-blue-400"></i>
                            @endif
                            @if($view === 'shared' && $t->user_id !== auth()->id())
                                <span class="w-3.5 h-3.5 bg-purple-100 text-purple-700 rounded-full flex items-center justify-center text-[8px] font-bold flex-shrink-0">
                                    {{ substr($t->user->name, 0, 1) }}
                                </span>
                            @endif
                        </div>
                        <p class="text-[9px] text-gray-400">
                            <span class="bg-gray-100 px-1 py-0.5 rounded text-gray-500">{{ $t->category }}</span>
                        </p>
                    </div>
                    <span class="text-[11px] font-bold whitespace-nowrap {{ $r['valueColor'] }}">
                        {{ $r['sign'] }} R$ {{ number_format($t->amount, 2, ',', '.') }}
                    </span>
                </div>
            @empty
                <div class="px-3 py-4 text-center text-[11px] text-gray-400">Sem entradas neste mês.</div>
            @endforelse
        </div>
    </div>

    {{-- SAÍDAS --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between bg-red-50/40">
            <div class="flex items-center gap-2">
                <i data-lucide="arrow-down-circle" class="w-3.5 h-3.5 text-red-600"></i>
                <h3 class="text-[11px] font-bold text-gray-800">Saídas</h3>
            </div>
            <div class="flex items-center gap-3 text-[9px]">
                <span class="text-gray-400">{{ $this->report['outflows']->count() }} {{ $this->report['outflows']->count() == 1 ? 'lançamento' : 'lançamentos' }}</span>
                <span class="font-bold text-red-600">R$ {{ number_format($this->report['expense'] + $this->report['savings'], 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="divide-y divide-gray-50">
            @php $prevDate = null; @endphp
            @forelse($this->report['outflows'] as $t)
                @php
                    $txDate = $t->date->format('Y-m-d');
                    $showDateHeader = $prevDate !== $txDate;
                    $prevDate = $txDate;
                    $r = $renderTx($t, $view);
                @endphp
                @if($showDateHeader)
                <div class="px-3 py-1 bg-gray-50/80 border-b border-gray-100">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                        {{ $t->date->locale('pt_BR')->isoFormat('D [de] MMMM') }}
                    </span>
                </div>
                @endif
                <div class="px-3 py-2 hover:bg-gray-50/50 transition flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center {{ $r['iconBg'] }}">
                        <i data-lucide="{{ $r['icon'] }}" class="w-3 h-3"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-medium text-gray-900 truncate">{{ preg_replace('/\s*\(\d+\/\d+\)$/', '', $t->description) }}</span>
                            @if($t->is_installment)
                                <span class="px-1 py-0 bg-yellow-100 text-yellow-800 rounded text-[9px] font-bold">{{ $t->installment_current }}/{{ $t->installment_count }}</span>
                            @elseif($t->is_recurring)
                                <i data-lucide="repeat" class="w-2.5 h-2.5 text-blue-400"></i>
                            @endif
                            @if($view === 'shared' && $t->user_id !== auth()->id())
                                <span class="w-3.5 h-3.5 bg-purple-100 text-purple-700 rounded-full flex items-center justify-center text-[8px] font-bold flex-shrink-0">
                                    {{ substr($t->user->name, 0, 1) }}
                                </span>
                            @endif
                        </div>
                        <p class="text-[9px] text-gray-400">
                            <span class="bg-gray-100 px-1 py-0.5 rounded text-gray-500">{{ $t->category }}</span>
                        </p>
                    </div>
                    <span class="text-[11px] font-bold whitespace-nowrap {{ $r['valueColor'] }}">
                        {{ $r['sign'] }} R$ {{ number_format($t->amount, 2, ',', '.') }}
                    </span>
                </div>
            @empty
                <div class="px-3 py-4 text-center text-[11px] text-gray-400">Sem saídas neste mês.</div>
            @endforelse
        </div>

        @if($this->report['txQty'] > 0)
        <div class="px-3 py-2 bg-gray-50/80 border-t border-gray-100 flex items-center justify-between text-[11px]">
            <span class="font-bold text-gray-700">Saldo do Período</span>
            <span class="font-bold {{ $this->report['balance'] >= 0 ? 'text-blue-600' : 'text-orange-600' }}">
                R$ {{ number_format($this->report['balance'], 2, ',', '.') }}
            </span>
        </div>
        @endif
    </div>

    {{-- CSS DE IMPRESSÃO --}}
    <style>
        @media print {
            body { background: white !important; font-size: 11px; }
            header, footer, nav, .print\:hidden { display: none !important; }
            main { padding: 0 !important; }
            .shadow-sm, .shadow-md, .shadow-lg { box-shadow: none !important; }
            .rounded-xl { border-radius: 4px !important; }
            .hover\:bg-gray-50\/50:hover { background: transparent !important; }
        }
    </style>
</div>
