<?php

use function Livewire\Volt\{state, computed, layout, uses};
use App\Livewire\Concerns\HasMonthNavigation;
use App\Livewire\Concerns\HasScopeToggle;
use App\Models\Transaction;
use App\Models\Category;
use Carbon\Carbon;

layout('components.layouts.app');
uses([HasMonthNavigation::class, HasScopeToggle::class]);

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => session('current_month', now()->startOfMonth()->format('Y-m-d'))
])->url();



$exportCsv = function () {
    $r = $this->report;
    $mes = Carbon::parse($this->currentMonth);

    $arquivo = sprintf(
        'duofund-extrato-%s-%s.csv',
        $this->view === 'personal' ? 'pessoal' : 'casal',
        $mes->format('Y-m'),
    );

    // Excel em pt-BR lê `;` como separador e precisa do BOM para UTF-8.
    // Valores começando com = + - @ viram fórmula: prefixa com apóstrofo.
    $seguro = fn (?string $v) => $v !== null && preg_match('/^[=+\-@\t\r]/', $v)
        ? "'" . $v
        : (string) $v;

    return response()->streamDownload(function () use ($r, $seguro) {
        $saida = fopen('php://output', 'w');
        fwrite($saida, "\xEF\xBB\xBF");

        fputcsv($saida, ['Data', 'Descrição', 'Categoria', 'Tipo', 'Quem', 'Valor'], ';');

        $rotulo = ['income' => 'Receita', 'expense' => 'Despesa', 'savings' => 'Reserva'];

        foreach ($r['incomes']->concat($r['outflows'])->sortBy('date') as $t) {
            fputcsv($saida, [
                $t->date->format('d/m/Y'),
                $seguro($t->description),
                $seguro($t->category ?: 'Sem categoria'),
                $rotulo[$t->type] ?? $t->type,
                $seguro($t->user?->name),
                number_format((float) $t->amount, 2, ',', ''),
            ], ';');
        }

        fclose($saida);
    }, $arquivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
};

$report = computed(function () {
    $user = auth()->user();
    $date = Carbon::parse($this->currentMonth)->startOfMonth();

    $totals = app(\App\Services\MonthlySummaryService::class)->for($user, $this->view, $date);
    $income  = $totals['income'];
    $expense = $totals['expense'];
    $savings = $totals['savings'];

    $query = Transaction::forView($user, $this->view)->inMonth($date);

    $catUsage = (clone $query)
        ->where('type', 'expense')
        ->selectRaw('category, sum(amount) as total, count(*) as qty')
        ->groupBy('category')
        ->orderByDesc('total')
        ->get();

    $transactions = (clone $query)
        ->with('user')
        ->orderByRaw("CASE type WHEN 'income' THEN 0 WHEN 'savings' THEN 1 ELSE 2 END")
        ->orderBy('date', 'asc')
        ->orderBy('created_at', 'asc')
        ->get();

    $incomes = $transactions->where('type', 'income')->values();
    $outflows = $transactions->whereIn('type', ['expense', 'savings'])->values();

    // Mês anterior, para o comparativo. Uma query agregada, sem carregar linhas.
    $prevDate = $date->copy()->subMonth();
    $prevTotals = app(\App\Services\MonthlySummaryService::class)->for($user, $this->view, $prevDate);

    $prevCatUsage = Transaction::forView($user, $this->view)
        ->inMonth($prevDate)
        ->where('type', 'expense')
        ->selectRaw('category, sum(amount) as total')
        ->groupBy('category')
        ->pluck('total', 'category');

    // Variação percentual. Sem base anterior não existe "x% a mais": null.
    $delta = function (float $atual, float $anterior): ?float {
        if ($anterior <= 0.0) {
            return null;
        }

        return round(($atual - $anterior) / $anterior * 100, 1);
    };

    return [
        'income'       => $income,
        'expense'      => $expense,
        'savings'      => $savings,
        'balance'      => $income - $expense,
        'catUsage'     => $catUsage,
        'incomes'      => $incomes,
        'outflows'     => $outflows,
        'txQty'        => $transactions->count(),
        'prevMonth'    => $prevDate,
        'prev'         => [
            'income'  => $prevTotals['income'],
            'expense' => $prevTotals['expense'],
            'balance' => $prevTotals['balance'],
        ],
        'delta'        => [
            'income'  => $delta($income, $prevTotals['income']),
            'expense' => $delta($expense, $prevTotals['expense']),
        ],
        'catDelta'     => $catUsage->mapWithKeys(fn ($linha) => [
            (string) $linha->category => $delta(
                (float) $linha->total,
                (float) ($prevCatUsage[$linha->category] ?? 0),
            ),
        ]),
        'prevCatUsage' => $prevCatUsage,
    ];
});
?>

<div>
    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 gap-3 print:hidden">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <x-ui.view-toggle :view="$view" personal-label="Meu Extrato" shared-label="Nosso Extrato" />
        </div>

        <div class="flex items-center bg-white rounded-lg shadow-sm border border-gray-200 p-0.5 justify-self-center">
            <button wire:click="prevMonth" class="p-1.5 hover:bg-gray-100 rounded-md transition text-gray-500">
                <x-lucide-chevron-left class="w-4 h-4" />
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
                <x-lucide-chevron-right class="w-4 h-4" />
            </button>
        </div>

        <div class="flex items-center gap-2 justify-self-center md:justify-self-end" x-data>
            <button wire:click="exportCsv" wire:loading.attr="disabled"
                class="bg-white hover:bg-gray-50 text-gray-700 font-medium py-2 px-3 rounded-lg shadow-sm border border-gray-200 items-center transition text-sm flex disabled:opacity-60">
                <x-lucide-file-down class="w-4 h-4 mr-1.5" />
                CSV
            </button>

            <button onclick="exportReportPdf(this)" x-ref="pdfBtn"
                class="bg-primary hover:bg-secondary text-white font-medium py-2 px-4 rounded-lg shadow-md shadow-primary/25 items-center transition text-sm flex disabled:opacity-60 disabled:cursor-wait">
                <x-lucide-file-down class="w-4 h-4 mr-1.5" />
                <span data-pdf-label>Exportar PDF</span>
            </button>
        </div>
    </div>

    {{-- RESUMO COMPACTO --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 mb-4 grid grid-cols-3 sm:grid-cols-4 gap-3 divide-x divide-gray-100">
        <div class="px-2">
            <p class="text-[9px] text-gray-400 font-medium uppercase tracking-wide">Receitas</p>
            <p class="text-[11px] sm:text-xs font-bold text-green-600">R$ {{ number_format($this->report['income'], 2, ',', '.') }}</p>
            <x-ui.delta-badge :delta="$this->report['delta']['income']" class="mt-0.5 inline-block" />
        </div>
        <div class="px-2">
            <p class="text-[9px] text-gray-400 font-medium uppercase tracking-wide">Despesas</p>
            <p class="text-[11px] sm:text-xs font-bold text-red-600">R$ {{ number_format($this->report['expense'], 2, ',', '.') }}</p>
            <x-ui.delta-badge :delta="$this->report['delta']['expense']" invert class="mt-0.5 inline-block" />
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

    {{-- POR CATEGORIA · vs MÊS ANTERIOR --}}
    @php
        $catUsage = $this->report['catUsage'];
        $prevCat  = $this->report['prevCatUsage'];
        $mesAnterior = $this->report['prevMonth']->locale('pt_BR')->isoFormat('MMM/YY');
    @endphp
    @if($catUsage->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-lucide-bar-chart-3 class="w-3.5 h-3.5 text-gray-500" />
                <h3 class="text-[11px] font-bold text-gray-800">Por categoria</h3>
            </div>
            <span class="text-[9px] text-gray-400">comparado a {{ $mesAnterior }}</span>
        </div>

        <div class="divide-y divide-gray-50">
            @foreach($catUsage as $linha)
                @php
                    $nome = $linha->category ?: 'Sem categoria';
                    $anterior = (float) ($prevCat[$linha->category] ?? 0);
                @endphp
                <div class="px-3 py-2 flex items-center justify-between gap-3">
                    <span class="text-[11px] text-gray-700 truncate min-w-0">{{ $nome }}</span>

                    <div class="flex items-center gap-2.5 flex-shrink-0">
                        <span class="text-[9px] text-gray-400 tabular-nums hidden sm:inline">
                            {{ $anterior > 0 ? 'R$ ' . number_format($anterior, 2, ',', '.') : 'estreia' }}
                        </span>
                        <span class="text-[11px] font-bold text-gray-900 tabular-nums">
                            R$ {{ number_format($linha->total, 2, ',', '.') }}
                        </span>
                        <x-ui.delta-badge :delta="$this->report['catDelta'][$linha->category] ?? null" invert />
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

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
                <x-lucide-arrow-up-circle class="w-3.5 h-3.5 text-green-600" />
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
                        <x-dynamic-component :component="'lucide-'.($r['icon'])" class="w-3 h-3" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-medium text-gray-900 truncate">{{ preg_replace('/\s*\(\d+\/\d+\)$/', '', $t->description) }}</span>
                            @if($t->is_recurring)
                                <x-lucide-repeat class="w-2.5 h-2.5 text-blue-400" />
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
                <x-lucide-arrow-down-circle class="w-3.5 h-3.5 text-red-600" />
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
                        <x-dynamic-component :component="'lucide-'.($r['icon'])" class="w-3 h-3" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-medium text-gray-900 truncate">{{ preg_replace('/\s*\(\d+\/\d+\)$/', '', $t->description) }}</span>
                            @if($t->is_installment)
                                <span class="px-1 py-0 bg-yellow-100 text-yellow-800 rounded text-[9px] font-bold">{{ $t->installment_current }}/{{ $t->installment_count }}</span>
                            @elseif($t->is_recurring)
                                <x-lucide-repeat class="w-2.5 h-2.5 text-blue-400" />
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

    {{-- ============================================================= --}}
    {{-- DOCUMENTO PDF (renderizado fora da tela, capturado por html2pdf) --}}
    {{-- ============================================================= --}}
    @php
        $r          = $this->report;
        $monthLabel = \Illuminate\Support\Str::ucfirst(\Carbon\Carbon::parse($currentMonth)->locale('pt_BR')->isoFormat('MMMM [de] YYYY'));
        $scopeLabel = $view === 'personal' ? 'Conta Pessoal' : 'Conta Compartilhada';
        $outTotal   = $r['expense'] + $r['savings'];
        $brl        = fn ($v) => 'R$ ' . number_format($v, 2, ',', '.');
        $pdfFile    = \Illuminate\Support\Str::slug('DuoFund Extrato ' . ($view === 'personal' ? 'Pessoal' : 'Compartilhado') . ' '
                        . \Carbon\Carbon::parse($currentMonth)->locale('pt_BR')->translatedFormat('F Y')) . '.pdf';
        $cleanDesc  = fn ($d) => preg_replace('/\s*\(\d+\/\d+\)$/', '', $d);
        $badge      = function ($t) {
            return match ($t) {
                'income'  => ['bg' => '#dcfce7', 'fg' => '#16a34a', 'glyph' => '↑'],
                'savings' => ['bg' => '#ede9fe', 'fg' => '#7c3aed', 'glyph' => '◆'],
                default   => ['bg' => '#fee2e2', 'fg' => '#dc2626', 'glyph' => '↓'],
            };
        };
        $valColor   = fn ($t) => $t === 'income' ? '#16a34a' : ($t === 'savings' ? '#7c3aed' : '#1f2937');
        $sign       = fn ($t) => $t === 'income' ? '+ ' : ($t === 'savings' ? '' : '− ');
    @endphp

    <div id="report-pdf-wrap" aria-hidden="true"
         style="position:fixed; left:-10000px; top:0; width:794px; background:#ffffff; z-index:-1;">
        <div id="report-pdf" data-filename="{{ $pdfFile }}" class="pdfdoc">

            {{-- HEADER DE MARCA --}}
            <div class="pdf-hero pdf-avoid">
                <div class="pdf-hero-left">
                    <div class="pdf-logo">DF</div>
                    <div>
                        <div class="pdf-brand">DuoFund</div>
                        <div class="pdf-tag">Finanças a Dois</div>
                    </div>
                </div>
                <div class="pdf-hero-right">
                    <div class="pdf-doctitle">Extrato Mensal</div>
                    <div class="pdf-docsub">{{ $scopeLabel }} · {{ $monthLabel }}</div>
                    <div class="pdf-docdate">Gerado em {{ now()->locale('pt_BR')->isoFormat('DD/MM/YYYY [às] HH:mm') }}</div>
                </div>
            </div>

            {{-- CARTÕES DE RESUMO --}}
            <div class="pdf-cards pdf-avoid">
                <div class="pdf-card" style="border-top-color:#16a34a">
                    <span class="pdf-card-label">Receitas</span>
                    <span class="pdf-card-value" style="color:#16a34a">{{ $brl($r['income']) }}</span>
                </div>
                <div class="pdf-card" style="border-top-color:#dc2626">
                    <span class="pdf-card-label">Despesas</span>
                    <span class="pdf-card-value" style="color:#dc2626">{{ $brl($outTotal) }}</span>
                </div>
                <div class="pdf-card" style="border-top-color:{{ $r['balance'] >= 0 ? '#2674D9' : '#ea580c' }}">
                    <span class="pdf-card-label">Saldo</span>
                    <span class="pdf-card-value" style="color:{{ $r['balance'] >= 0 ? '#2674D9' : '#ea580c' }}">{{ $brl($r['balance']) }}</span>
                </div>
                <div class="pdf-card" style="border-top-color:#64748b">
                    <span class="pdf-card-label">Lançamentos</span>
                    <span class="pdf-card-value" style="color:#1f2937">{{ $r['txQty'] }}</span>
                </div>
            </div>

            {{-- GASTOS POR CATEGORIA --}}
            @if($r['catUsage']->count() > 0)
            <div class="pdf-section pdf-avoid">
                <div class="pdf-sec-head" style="background:#eef4fd">
                    <span class="pdf-sec-title">Gastos por categoria</span>
                    <span class="pdf-sec-total" style="color:#2674D9">{{ $brl($outTotal) }}</span>
                </div>
                <div class="pdf-cats">
                    @foreach($r['catUsage'] as $cat)
                        @php $pct = $outTotal > 0 ? round($cat->total / $outTotal * 100) : 0; @endphp
                        <div class="pdf-cat pdf-avoid">
                            <div class="pdf-cat-info">
                                <span class="pdf-cat-name">{{ $cat->category ?: 'Sem categoria' }}</span>
                                <span class="pdf-cat-qty">{{ $cat->qty }} {{ $cat->qty == 1 ? 'lançamento' : 'lançamentos' }}</span>
                            </div>
                            <div class="pdf-cat-bar"><div class="pdf-cat-fill" style="width:{{ $pct }}%"></div></div>
                            <div class="pdf-cat-vals">
                                <span class="pdf-cat-amt">{{ $brl($cat->total) }}</span>
                                <span class="pdf-cat-pct">{{ $pct }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ENTRADAS --}}
            <div class="pdf-section">
                <div class="pdf-sec-head pdf-avoid" style="background:#ecfdf3">
                    <span class="pdf-sec-title">Entradas</span>
                    <span class="pdf-sec-meta">{{ $r['incomes']->count() }} {{ $r['incomes']->count() == 1 ? 'lançamento' : 'lançamentos' }}</span>
                    <span class="pdf-sec-total" style="color:#16a34a">{{ $brl($r['income']) }}</span>
                </div>
                @php $prev = null; @endphp
                @forelse($r['incomes'] as $t)
                    @php $d = $t->date->format('Y-m-d'); $b = $badge($t->type); @endphp
                    @if($prev !== $d)
                        <div class="pdf-day pdf-avoid">{{ $t->date->locale('pt_BR')->isoFormat('D [de] MMMM') }}</div>
                        @php $prev = $d; @endphp
                    @endif
                    <div class="pdf-row pdf-avoid">
                        <span class="pdf-ico" style="background:{{ $b['bg'] }};color:{{ $b['fg'] }}">{{ $b['glyph'] }}</span>
                        <div class="pdf-row-main">
                            <span class="pdf-row-desc">{{ $cleanDesc($t->description) }}@if($view === 'shared' && $t->user_id !== auth()->id())<span class="pdf-who">{{ $t->user->name }}</span>@endif</span>
                            <span class="pdf-row-cat">{{ $t->category }}</span>
                        </div>
                        <span class="pdf-row-val" style="color:{{ $valColor($t->type) }}">{{ $sign($t->type) }}{{ $brl($t->amount) }}</span>
                    </div>
                @empty
                    <div class="pdf-empty">Sem entradas neste mês.</div>
                @endforelse
            </div>

            {{-- SAÍDAS --}}
            <div class="pdf-section">
                <div class="pdf-sec-head pdf-avoid" style="background:#fef2f2">
                    <span class="pdf-sec-title">Saídas</span>
                    <span class="pdf-sec-meta">{{ $r['outflows']->count() }} {{ $r['outflows']->count() == 1 ? 'lançamento' : 'lançamentos' }}</span>
                    <span class="pdf-sec-total" style="color:#dc2626">{{ $brl($outTotal) }}</span>
                </div>
                @php $prev = null; @endphp
                @forelse($r['outflows'] as $t)
                    @php $d = $t->date->format('Y-m-d'); $b = $badge($t->type); @endphp
                    @if($prev !== $d)
                        <div class="pdf-day pdf-avoid">{{ $t->date->locale('pt_BR')->isoFormat('D [de] MMMM') }}</div>
                        @php $prev = $d; @endphp
                    @endif
                    <div class="pdf-row pdf-avoid">
                        <span class="pdf-ico" style="background:{{ $b['bg'] }};color:{{ $b['fg'] }}">{{ $b['glyph'] }}</span>
                        <div class="pdf-row-main">
                            <span class="pdf-row-desc">{{ $cleanDesc($t->description) }}@if($t->is_installment)<span class="pdf-inst">{{ $t->installment_current }}/{{ $t->installment_count }}</span>@endif@if($view === 'shared' && $t->user_id !== auth()->id())<span class="pdf-who">{{ $t->user->name }}</span>@endif</span>
                            <span class="pdf-row-cat">{{ $t->category }}</span>
                        </div>
                        <span class="pdf-row-val" style="color:{{ $valColor($t->type) }}">{{ $sign($t->type) }}{{ $brl($t->amount) }}</span>
                    </div>
                @empty
                    <div class="pdf-empty">Sem saídas neste mês.</div>
                @endforelse

                @if($r['txQty'] > 0)
                <div class="pdf-balance pdf-avoid">
                    <span>Saldo do período</span>
                    <span style="color:{{ $r['balance'] >= 0 ? '#2674D9' : '#ea580c' }}">{{ $brl($r['balance']) }}</span>
                </div>
                @endif
            </div>

            <div class="pdf-foot">DuoFund · Finanças a Dois · duofund.studiostg.com.br</div>
        </div>
    </div>

    {{-- ESTILOS DO DOCUMENTO PDF --}}
    <style>
        .pdfdoc { width:794px; background:#fff; padding:14px 16px;
            font-family:'DM Sans',-apple-system,Segoe UI,sans-serif; color:#1f2937; box-sizing:border-box; }
        .pdfdoc * { box-sizing:border-box; }

        /* HERO */
        .pdf-hero { display:flex; justify-content:space-between; align-items:center;
            background:linear-gradient(135deg,#3b82e6 0%,#1e5fc0 100%); border-radius:12px;
            padding:14px 18px; color:#fff; margin-bottom:12px; }
        .pdf-hero-left { display:flex; align-items:center; gap:11px; }
        .pdf-logo { width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,.18);
            display:flex; align-items:center; justify-content:center; font-weight:700; font-size:17px; letter-spacing:.5px; }
        .pdf-brand { font-size:17px; font-weight:700; line-height:1.1; }
        .pdf-tag { font-size:10px; opacity:.85; font-weight:500; }
        .pdf-hero-right { text-align:right; }
        .pdf-doctitle { font-size:15px; font-weight:700; line-height:1.2; }
        .pdf-docsub { font-size:10px; opacity:.9; margin-top:2px; }
        .pdf-docdate { font-size:8.5px; opacity:.75; margin-top:1px; }

        /* CARTÕES */
        .pdf-cards { display:flex; gap:9px; margin-bottom:12px; }
        .pdf-card { flex:1; background:#f8fafc; border:1px solid #eef2f7; border-top:3px solid #ccc;
            border-radius:9px; padding:8px 11px; display:flex; flex-direction:column; gap:2px; }
        .pdf-card-label { font-size:8.5px; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; font-weight:600; }
        .pdf-card-value { font-size:14px; font-weight:700; }

        /* SEÇÕES */
        .pdf-section { border:1px solid #eef2f7; border-radius:9px; overflow:hidden; margin-bottom:11px; }
        .pdf-sec-head { display:flex; align-items:center; gap:8px; padding:7px 12px; border-bottom:1px solid #eef2f7; }
        .pdf-sec-title { font-size:11px; font-weight:700; color:#374151; }
        .pdf-sec-meta { font-size:9px; color:#94a3b8; margin-left:auto; }
        .pdf-sec-total { font-size:11px; font-weight:700; }
        .pdf-sec-head .pdf-sec-meta + .pdf-sec-total { margin-left:0; }

        /* CATEGORIAS */
        .pdf-cats { padding:3px 12px 6px; }
        .pdf-cat { display:flex; align-items:center; gap:10px; padding:5px 0; border-bottom:1px solid #f3f4f6; }
        .pdf-cat:last-child { border-bottom:none; }
        .pdf-cat-info { width:140px; flex-shrink:0; display:flex; flex-direction:column; }
        .pdf-cat-name { font-size:10.5px; font-weight:600; color:#374151; }
        .pdf-cat-qty { font-size:8px; color:#9ca3af; }
        .pdf-cat-bar { flex:1; height:6px; background:#eef2f7; border-radius:99px; overflow:hidden; }
        .pdf-cat-fill { height:100%; background:linear-gradient(90deg,#3b82e6,#1e5fc0); border-radius:99px; }
        .pdf-cat-vals { width:100px; flex-shrink:0; text-align:right; }
        .pdf-cat-amt { font-size:10.5px; font-weight:700; color:#1f2937; }
        .pdf-cat-pct { font-size:8px; color:#9ca3af; margin-left:5px; }

        /* DIAS + LINHAS */
        .pdf-day { padding:4px 12px; background:#f8fafc; font-size:8.5px; font-weight:700;
            text-transform:uppercase; letter-spacing:.6px; color:#9ca3af; border-bottom:1px solid #f3f4f6; }
        .pdf-row { display:flex; align-items:center; gap:9px; padding:5px 12px; border-bottom:1px solid #f6f7f9; }
        .pdf-ico { width:19px; height:19px; flex-shrink:0; border-radius:99px; display:flex;
            align-items:center; justify-content:center; font-size:11px; font-weight:700; line-height:1; }
        .pdf-row-main { flex:1; min-width:0; display:flex; flex-direction:column; gap:0; }
        .pdf-row-desc { font-size:10.5px; font-weight:600; color:#1f2937; }
        .pdf-row-cat { font-size:8.5px; color:#9ca3af; }
        .pdf-row-val { font-size:10.5px; font-weight:700; white-space:nowrap; }
        .pdf-inst { display:inline-block; margin-left:5px; padding:0 5px; border-radius:5px;
            background:#fef9c3; color:#854d0e; font-size:8px; font-weight:700; vertical-align:middle; }
        .pdf-who { display:inline-block; margin-left:5px; padding:0 6px; border-radius:99px;
            background:#ede9fe; color:#6d28d9; font-size:8px; font-weight:700; vertical-align:middle; }
        .pdf-empty { padding:13px; text-align:center; font-size:10px; color:#9ca3af; }
        .pdf-balance { display:flex; justify-content:space-between; align-items:center; padding:8px 12px;
            background:#f8fafc; border-top:1px solid #eef2f7; font-size:11px; font-weight:700; color:#374151; }
        .pdf-foot { text-align:center; font-size:8px; color:#cbd5e1; margin-top:4px; letter-spacing:.3px; }
    </style>

    @assets
    @vite('resources/js/report-pdf.js')
    @endassets

    <script>
        async function exportReportPdf(btn) {
            var el = document.getElementById('report-pdf');
            var wrap = document.getElementById('report-pdf-wrap');
            if (!el || !wrap || !window.loadHtml2Pdf) { window.print(); return; }
            var label = btn.querySelector('[data-pdf-label]');
            var original = label ? label.textContent : '';
            if (label) label.textContent = 'Gerando…';
            btn.disabled = true;

            // Chunk sob demanda: só baixa no primeiro clique.
            var html2pdf;
            try {
                html2pdf = await window.loadHtml2Pdf();
            } catch (e) {
                if (label) label.textContent = original;
                btn.disabled = false;
                window.print();
                return;
            }

            // Captura fiel: algum ancestral do layout tem `transform`, o que faz o
            // `position:fixed` herdar o deslocamento da sidebar e o html2canvas cortar
            // a borda. Reparentamos o wrap direto no <body> (sem ancestral transformado)
            // e cobrimos a tela enquanto gera.
            var parentBefore = wrap.parentNode;
            document.body.appendChild(wrap);
            var cover = document.createElement('div');
            cover.style.cssText = 'position:fixed;inset:0;background:#ffffff;z-index:2147483646;';
            document.body.appendChild(cover);
            wrap.style.left = '0';
            wrap.style.top = '0';
            wrap.style.zIndex = '2147483647';

            function restore() {
                wrap.style.left = '-10000px';
                wrap.style.zIndex = '-1';
                if (parentBefore) parentBefore.appendChild(wrap);
                if (cover.parentNode) cover.parentNode.removeChild(cover);
                if (label) label.textContent = original;
                btn.disabled = false;
            }

            // NÃO passar width/windowWidth: o html2pdf centraliza o container no
            // viewport real e o html2canvas precisa do mesmo viewport, senão desalinha.
            html2pdf().set({
                margin: 0,
                filename: el.getAttribute('data-filename') || 'DuoFund-Extrato.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollX: 0, scrollY: 0 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'], avoid: ['.pdf-avoid'] }
            }).from(el).save().then(restore).catch(restore);
        }
    </script>
</div>
