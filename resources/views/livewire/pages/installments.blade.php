<?php
use function Livewire\Volt\{state, computed, layout, uses};
use App\Livewire\Concerns\HasMonthNavigation;
use App\Livewire\Concerns\HasScopeToggle;
use App\Models\Transaction;
use Carbon\Carbon;

layout('components.layouts.app');
uses([HasMonthNavigation::class, HasScopeToggle::class]);

state([
    'view'         => session('view_mode', 'personal'),
    'currentMonth' => session('current_month', now()->startOfMonth()->format('Y-m-d')),
]);


$monthGroups = computed(function () {
    $user         = auth()->user();
    $monthStart   = Carbon::parse($this->currentMonth)->startOfMonth();
    $monthEnd     = Carbon::parse($this->currentMonth)->endOfMonth();

    $transactions = Transaction::forView($user, $this->view)
        ->where('is_installment', true)
        ->where('is_recurring', false)
        ->orderBy('date')
        ->get();
    $groups       = $transactions->groupBy(fn ($t) => $t->recurring_group_id ?? 'solo_' . $t->id);

    $result = [];
    foreach ($groups as $groupId => $items) {
        $dueItems = $items->filter(fn ($t) => $t->date->between($monthStart, $monthEnd));
        if ($dueItems->isEmpty()) continue;

        $first             = $items->first();
        $totalInstallments = $first->installment_count ?: $items->count();
        $paidBefore        = $items->filter(fn ($t) => $t->date->lt($monthStart))->count();
        $dueTx             = $dueItems->first();
        $currentNumber     = $dueTx->installment_current ?? ($paidBefore + 1);
        $remainingAfter    = max(0, $totalInstallments - $paidBefore - $dueItems->count());
        $progressPct       = $totalInstallments > 0 ? round(($paidBefore / $totalInstallments) * 100) : 0;
        $isLast            = $remainingAfter === 0;

        $result[] = [
            'group_id'          => $groupId,
            'description'       => $first->description,
            'category'          => $first->category ?? 'Geral',
            'amount_per'        => $first->amount,
            'total_installments'=> $totalInstallments,
            'paid_before'       => $paidBefore,
            'current_number'    => $currentNumber,
            'remaining_after'   => $remainingAfter,
            'total_amount'      => $first->amount * $totalInstallments,
            'remaining_amount'  => $first->amount * $remainingAfter,
            'due_date'          => $dueTx->date,
            'progress_pct'      => $progressPct,
            'is_last'           => $isLast,
        ];
    }

    return collect($result)->sortBy('description')->values();
});

$summary = computed(function () {
    $groups     = $this->monthGroups;
    $nextStart  = Carbon::parse($this->currentMonth)->addMonth()->startOfMonth();
    $nextEnd    = $nextStart->copy()->endOfMonth();
    $nextMonthTotal = Transaction::forView(auth()->user(), $this->view)
        ->where('is_installment', true)
        ->whereBetween('date', [$nextStart, $nextEnd])
        ->sum('amount');

    return [
        'count'           => $groups->count(),
        'total_due'       => $groups->sum('amount_per'),
        'next_month_total'=> $nextMonthTotal,
    ];
});
?>

<div>
    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 sm:mb-6 gap-3">

        {{-- Scope toggle --}}
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <x-ui.view-toggle :view="$view" personal-label="Meu Dinheiro" shared-label="Nosso Dinheiro" />
            <p class="text-[10px] text-gray-400 mt-1">
                @if($view === 'personal') Vendo seus parcelamentos pessoais
                @else Vendo parcelamentos do casal
                @endif
            </p>
        </div>

        {{-- Month navigator --}}
        <div class="flex items-center bg-white rounded-lg shadow-sm border border-gray-200 p-0.5 justify-self-center">
            <button wire:click="prevMonth" class="p-1.5 hover:bg-gray-100 rounded-md transition text-gray-500 min-h-[44px] min-w-[44px] sm:min-h-0 sm:min-w-0 flex items-center justify-center">
                <x-lucide-chevron-left class="w-4 h-4" />
            </button>
            <div class="px-3 text-center min-w-[130px]">
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

        {{-- New installment button --}}
        <div class="hidden md:flex justify-self-end">
            <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}', repetition: 'installment' })"
                class="bg-primary hover:bg-secondary text-white font-medium py-2 px-4 rounded-lg shadow-md shadow-primary/25 items-center transition text-sm flex">
                <x-lucide-plus class="w-4 h-4 mr-1.5" /> Nova Parcela
            </button>
        </div>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 sm:p-4">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-7 h-7 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <x-lucide-credit-card class="w-3.5 h-3.5 text-amber-600" />
                </div>
                <span class="text-[10px] text-gray-400 font-medium leading-tight">Parcelas</span>
            </div>
            <p class="text-2xl font-black text-gray-900 leading-none">{{ $this->summary['count'] }}</p>
            <p class="text-[10px] text-gray-400 mt-1">neste mês</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 sm:p-4">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-7 h-7 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <x-lucide-banknote class="w-3.5 h-3.5 text-red-500" />
                </div>
                <span class="text-[10px] text-gray-400 font-medium leading-tight">Total do Mês</span>
            </div>
            <p class="text-base font-black text-gray-900 leading-none">R$ {{ number_format($this->summary['total_due'], 0, ',', '.') }}</p>
            <p class="text-[10px] text-gray-400 mt-1">em parcelas</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 sm:p-4">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-7 h-7 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <x-lucide-trending-down class="w-3.5 h-3.5 text-orange-500" />
                </div>
                <span class="text-[10px] text-gray-400 font-medium leading-tight">Próximo Mês</span>
            </div>
            <p class="text-base font-black text-gray-900 leading-none">R$ {{ number_format($this->summary['next_month_total'], 0, ',', '.') }}</p>
            <p class="text-[10px] text-gray-400 mt-1">em parcelas</p>
        </div>
    </div>

    {{-- INSTALLMENT CARDS --}}
    <div class="space-y-3">
        @forelse($this->monthGroups as $group)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="p-4">
                {{-- Top row --}}
                <div class="flex items-start justify-between mb-3">
                    <div class="min-w-0 flex-1 mr-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-gray-900 text-sm truncate">
                                {{ preg_replace('/\s*\(\d+\/\d+\)$/', '', $group['description']) }}
                            </h3>
                            <span class="px-1.5 py-0.5 bg-yellow-100 text-yellow-800 rounded text-[9px] font-bold flex-shrink-0">
                                {{ $group['current_number'] }}/{{ $group['total_installments'] }}
                            </span>
                            @if($group['is_last'])
                                <span class="px-1.5 py-0.5 bg-green-100 text-green-700 rounded-full text-[9px] font-bold flex-shrink-0">ÚLTIMA</span>
                            @endif
                        </div>
                        <span class="text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded mt-1 inline-block">{{ $group['category'] }}</span>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-base font-black text-gray-900">R$ {{ number_format($group['amount_per'], 2, ',', '.') }}</p>
                        <p class="text-[10px] text-gray-400">esta parcela</p>
                    </div>
                </div>

                {{-- Progress bar --}}
                <div class="mb-3">
                    <div class="flex justify-between text-[10px] mb-1.5">
                        <span class="text-gray-400">{{ $group['paid_before'] }} de {{ $group['total_installments'] }} pagas</span>
                        <span class="font-bold {{ $group['is_last'] ? 'text-green-600' : 'text-amber-600' }}">{{ $group['progress_pct'] }}%</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 {{ $group['is_last'] ? 'bg-green-400' : 'bg-amber-400' }}"
                             style="width: {{ $group['progress_pct'] }}%"></div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between pt-2 border-t border-gray-50 text-[11px]">
                    <div class="flex items-center gap-3">
                        <div>
                            <span class="text-gray-400">Total:</span>
                            <span class="font-semibold text-gray-700 ml-1">R$ {{ number_format($group['total_amount'], 2, ',', '.') }}</span>
                        </div>
                        @if($group['remaining_after'] > 0)
                        <div>
                            <span class="text-gray-400">Faltam {{ $group['remaining_after'] }}x:</span>
                            <span class="font-semibold text-orange-600 ml-1">R$ {{ number_format($group['remaining_amount'], 2, ',', '.') }}</span>
                        </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-1 text-blue-600">
                        <x-lucide-calendar-clock class="w-3 h-3" />
                        <span>{{ $group['due_date']->format('d/m') }}</span>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm flex flex-col items-center justify-center py-14 text-gray-400">
            <div class="w-14 h-14 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                <x-lucide-credit-card class="w-7 h-7 opacity-30" />
            </div>
            <p class="font-semibold text-sm text-gray-600">Nenhuma parcela neste mês</p>
            <p class="text-xs text-gray-400 mt-1">
                @if(\Carbon\Carbon::parse($currentMonth)->isFuture())
                    Nenhum parcelamento vence neste período
                @else
                    Navegue pelos meses ou adicione um novo parcelamento
                @endif
            </p>
            <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}', repetition: 'installment' })"
                class="mt-4 text-xs text-primary hover:underline font-medium flex items-center gap-1">
                <x-lucide-plus class="w-3 h-3" /> Adicionar parcelamento
            </button>
        </div>
        @endforelse
    </div>
</div>
