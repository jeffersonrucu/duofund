<?php
use function Livewire\Volt\{state, computed, usesPagination, layout, uses};
use App\Livewire\Concerns\HasMonthNavigation;
use App\Livewire\Concerns\HasScopeToggle;
use App\Models\Transaction;
use Carbon\Carbon;

layout('components.layouts.app');
uses([HasMonthNavigation::class, HasScopeToggle::class]);
usesPagination();

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => session('current_month', now()->startOfMonth()->format('Y-m-d')),
    'confirmDeleteId' => null,
    'deleteMode' => 'single',
    'isRecurringDelete' => false,
    'showDeleteModal' => false
])->url();

// Série recorrente "irmã" de um lançamento avulso (mesma descrição/valor, meses futuros)
state(['relatedGroupId' => null]);

$totals = computed(function() {
    return app(\App\Services\MonthlySummaryService::class)
        ->for(auth()->user(), $this->view, Carbon::parse($this->currentMonth));
});

$baseQuery = function () {
    return Transaction::forView(auth()->user(), $this->view)
        ->inMonth(Carbon::parse($this->currentMonth));
};

$lastUpdated = computed(function () {
    return $this->baseQuery()
        ->max('updated_at');
});

$incomes = computed(function () {
    return $this->baseQuery()
        ->where('type', 'income')
        ->with('user')
        ->orderBy('date', 'desc')
        ->orderBy('created_at', 'desc')
        ->get();
});

$outflows = computed(function () {
    return $this->baseQuery()
        ->whereIn('type', ['expense', 'savings'])
        ->with(['card', 'user'])
        ->orderBy('date', 'desc')
        ->orderBy('created_at', 'desc')
        ->paginate(20);
});

$confirmDelete = function ($id) {
    $tx = Transaction::find($id);
    if (!$tx) return;

    // Lançamento avulso pode ter uma série igual nos próximos meses (ex.: assinatura
    // relançada à mão) — oferece excluir as próximas ocorrências junto.
    $this->relatedGroupId = $tx->recurring_group_id ? null : Transaction::forView(auth()->user(), $this->view)
        ->whereNotNull('recurring_group_id')
        ->where('description', $tx->description)
        ->where('amount', $tx->amount)
        ->where('type', $tx->type)
        ->where('date', '>', $tx->date)
        ->value('recurring_group_id');

    $this->confirmDeleteId = $id;

    if ($tx->recurring_group_id || $this->relatedGroupId) {
        $this->isRecurringDelete = true;
        $this->deleteMode = 'single';
        $this->showDeleteModal = true;
        return;
    }

    $this->isRecurringDelete = false;
    $this->deleteTransaction();
};

$deleteTransaction = function () {
    $tx = Transaction::find($this->confirmDeleteId);
    $user = auth()->user();

    if ($tx && $tx->manageableBy($user)) {
        $groupId = $tx->recurring_group_id ?: $this->relatedGroupId;

        if ($this->isRecurringDelete && $groupId && in_array($this->deleteMode, ['all', 'forward'], true)) {
            // Remove a série (ou deste mês em diante) e os espelhos
            // "Transferido para conta conjunta"
            $fromDate = $this->deleteMode === 'forward' ? $tx->date->toDateString() : null;
            app(\App\Services\TransactionMirrorService::class)
                ->deleteSeries($groupId, $user->getFamilyUserIds(), $fromDate);

            // O avulso não pertence à série, então sai à parte
            if (! $tx->recurring_group_id) {
                $tx->delete();
            }

            $msg = $this->deleteMode === 'forward'
                ? 'Lançamentos removidos deste mês em diante.'
                : 'Série recorrente removida com sucesso.';
        } else {
            // FK mirror_transaction_id (cascade) remove o espelho junto
            $tx->delete();
            $msg = 'Transação removida.';
        }
        $this->dispatch('notify', $msg);
    }
    $this->reset(['showDeleteModal', 'confirmDeleteId', 'isRecurringDelete', 'deleteMode', 'relatedGroupId']);
};

$deleteGroup = function() {
    $this->deleteMode = 'all';
    $this->deleteTransaction();
};

?>

<div>
    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 sm:mb-6 gap-3">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <x-ui.view-toggle :view="$view" personal-label="Meu Dinheiro" shared-label="Nosso Dinheiro" />
            <p class="text-[10px] text-gray-400 mt-1">
                @if($view === 'personal')
                    Vendo apenas suas transações pessoais
                @else
                    Vendo transações compartilhadas do casal
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

    {{-- RESUMO COMPACTO --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 mb-4 grid grid-cols-3 gap-3 divide-x divide-gray-100">
        <div class="px-2">
            <p class="text-[9px] text-gray-400 font-medium uppercase tracking-wide">Entradas</p>
            <p class="text-[11px] sm:text-xs font-bold text-green-600">R$ {{ number_format($this->totals['income'], 2, ',', '.') }}</p>
        </div>
        <div class="px-2">
            <p class="text-[9px] text-gray-400 font-medium uppercase tracking-wide">Saídas</p>
            <p class="text-[11px] sm:text-xs font-bold text-red-600">R$ {{ number_format($this->totals['expense'], 2, ',', '.') }}</p>
        </div>
        <div class="px-2">
            <p class="text-[9px] text-gray-400 font-medium uppercase tracking-wide">Saldo</p>
            @php $bal = $this->totals['balance']; @endphp
            <p class="text-[11px] sm:text-xs font-bold {{ $bal >= 0 ? 'text-blue-600' : 'text-orange-600' }}">R$ {{ number_format($bal, 2, ',', '.') }}</p>
        </div>
    </div>

    {{-- ÚLTIMA ATUALIZAÇÃO --}}
    @if($this->lastUpdated)
        @php $updated = \Carbon\Carbon::parse($this->lastUpdated); @endphp
        <div class="flex items-center justify-center gap-1.5 mb-4 -mt-1 text-[10px] text-gray-400"
             title="Atualizado em {{ $updated->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY [às] HH:mm') }}">
            <x-lucide-history class="w-3 h-3" />
            <span>Atualizado {{ $updated->locale('pt_BR')->diffForHumans() }}</span>
        </div>
    @endif

    {{-- ENTRADAS --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between bg-green-50/40">
            <div class="flex items-center gap-2">
                <x-lucide-arrow-up-circle class="w-3.5 h-3.5 text-green-600" />
                <h3 class="text-[11px] font-bold text-gray-800">Entradas</h3>
            </div>
            <div class="flex items-center gap-3 text-[9px]">
                <span class="text-gray-400">{{ $this->incomes->count() }} {{ $this->incomes->count() == 1 ? 'lançamento' : 'lançamentos' }}</span>
                <span class="font-bold text-green-600">R$ {{ number_format($this->totals['income'], 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="divide-y divide-gray-50">
            @php $prevDate = null; @endphp
            @forelse($this->incomes as $t)
                @php
                    $txDate = $t->date->format('Y-m-d');
                    $showDateHeader = $prevDate !== $txDate;
                    $prevDate = $txDate;
                @endphp
                @if($showDateHeader)
                <div class="px-3 py-1 bg-gray-50/80 border-b border-gray-100">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                        @if($t->date->isToday()) Hoje
                        @elseif($t->date->isYesterday()) Ontem
                        @else {{ $t->date->locale('pt_BR')->isoFormat('D [de] MMMM') }}
                        @endif
                    </span>
                </div>
                @endif
                <div class="px-3 py-2 hover:bg-gray-50 transition group flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center bg-green-50 text-green-600">
                        <x-lucide-arrow-up class="w-3 h-3" />
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
                    <span class="text-[11px] font-bold whitespace-nowrap text-green-600">
                        + R$ {{ number_format($t->amount, 2, ',', '.') }}
                    </span>
                    <div class="flex items-center gap-2 sm:gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity flex-shrink-0">
                        <button @click="transactionModalOpen = true; Livewire.dispatch('edit-transaction', { id: {{ $t->id }} })"
                            class="w-11 h-11 sm:w-9 sm:h-9 flex items-center justify-center text-blue-500 hover:bg-blue-50 rounded-lg transition" aria-label="Editar" title="Editar">
                            <x-lucide-pencil class="w-4 h-4" />
                        </button>
                        <button wire:click="confirmDelete({{ $t->id }})"
                            class="w-11 h-11 sm:w-9 sm:h-9 flex items-center justify-center text-red-500 hover:bg-red-50 rounded-lg transition" aria-label="Excluir" title="Excluir">
                            <x-lucide-trash-2 class="w-4 h-4" />
                        </button>
                    </div>
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
                <span class="text-gray-400">{{ $this->outflows->total() }} {{ $this->outflows->total() == 1 ? 'lançamento' : 'lançamentos' }}</span>
                <span class="font-bold text-red-600">R$ {{ number_format($this->totals['expense'], 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="divide-y divide-gray-50">
            @php $prevDate = null; @endphp
            @forelse($this->outflows as $t)
                @php
                    $txDate = $t->date->format('Y-m-d');
                    $showDateHeader = $prevDate !== $txDate;
                    $prevDate = $txDate;
                @endphp
                @if($showDateHeader)
                <div class="px-3 py-1 bg-gray-50/80 border-b border-gray-100">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                        @if($t->date->isToday()) Hoje
                        @elseif($t->date->isYesterday()) Ontem
                        @else {{ $t->date->locale('pt_BR')->isoFormat('D [de] MMMM') }}
                        @endif
                    </span>
                </div>
                @endif
                <div class="px-3 py-2 hover:bg-gray-50 transition group flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center {{ $t->type == 'savings' ? 'bg-violet-50 text-violet-600' : 'bg-red-50 text-red-600' }}">
                        <x-dynamic-component :component="'lucide-'.($t->type == 'savings' ? 'piggy-bank' : 'arrow-down')" class="w-3 h-3" />
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
                        <p class="text-[9px] text-gray-400 flex items-center gap-1 flex-wrap">
                            <span class="bg-gray-100 px-1 py-0.5 rounded text-gray-500">{{ $t->category }}</span>
                            @if($t->payment_method)
                                @php
                                    // credit/debit foram normalizados para 'card' na migration 2026_06_30_020000;
                                    // cash/transfer podem existir em dados antigos
                                    $pmLabels = \App\Enums\PaymentMethod::labels() + ['cash'=>'Dinheiro','transfer'=>'Transferência'];
                                    $pmIcons = \App\Enums\PaymentMethod::icons() + ['cash'=>'banknote','transfer'=>'arrow-left-right'];
                                @endphp
                                <span class="inline-flex items-center gap-0.5 bg-blue-50 px-1 py-0.5 rounded text-blue-500">
                                    <x-dynamic-component :component="'lucide-'.($pmIcons[$t->payment_method] ?? 'wallet')" class="w-2.5 h-2.5" />
                                    {{ $pmLabels[$t->payment_method] ?? $t->payment_method }}@if($t->card) ····{{ $t->card->last4 }}@endif
                                </span>
                            @endif
                        </p>
                    </div>
                    <span class="text-[11px] font-bold whitespace-nowrap {{ $t->type == 'savings' ? 'text-violet-600' : 'text-gray-900' }}">
                        {{ $t->type == 'savings' ? '' : '-' }} R$ {{ number_format($t->amount, 2, ',', '.') }}
                    </span>
                    <div class="flex items-center gap-2 sm:gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity flex-shrink-0">
                        <button @click="transactionModalOpen = true; Livewire.dispatch('edit-transaction', { id: {{ $t->id }} })"
                            class="w-11 h-11 sm:w-9 sm:h-9 flex items-center justify-center text-blue-500 hover:bg-blue-50 rounded-lg transition" aria-label="Editar" title="Editar">
                            <x-lucide-pencil class="w-4 h-4" />
                        </button>
                        <button wire:click="confirmDelete({{ $t->id }})"
                            class="w-11 h-11 sm:w-9 sm:h-9 flex items-center justify-center text-red-500 hover:bg-red-50 rounded-lg transition" aria-label="Excluir" title="Excluir">
                            <x-lucide-trash-2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                    <div class="w-10 h-10 bg-gray-50 rounded-full flex items-center justify-center mb-2">
                        <x-lucide-calendar-x class="w-5 h-5 opacity-50" />
                    </div>
                    <p class="font-medium text-[11px]">Nenhuma saída neste mês.</p>
                    <button wire:click="prevMonth" class="text-[10px] text-primary hover:underline mt-1">Ver mês anterior</button>
                </div>
            @endforelse
        </div>

        @if($this->outflows->hasPages())
            <div class="p-3 border-t border-gray-100 bg-gray-50">
                {{ $this->outflows->links('pagination.duofund') }}
            </div>
        @endif
    </div>

    {{-- MODAL DE EXCLUSÃO --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 z-[60] flex sm:items-center sm:justify-center bg-gray-900/50 backdrop-blur-sm" wire:click="$set('showDeleteModal', false)">
        <div class="bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-xl flex flex-col" @click.stop>
            <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                <div class="bg-red-100 text-red-600 w-12 h-12 rounded-full flex items-center justify-center mb-3">
                    <x-lucide-repeat class="w-6 h-6" />
                </div>
                <h3 class="text-base font-bold text-gray-900">Excluir Recorrência</h3>
                <p class="text-sm text-gray-500 mt-2">
                    @if($relatedGroupId)
                        Este item é avulso, mas existe uma recorrência igual nos próximos meses.<br>Como deseja proceder?
                    @else
                        Este item faz parte de uma série.<br>Como deseja proceder?
                    @endif
                </p>
            </div>

            <div class="p-4 border-t border-gray-100 space-y-2">
                <button wire:click="$set('deleteMode', 'single'); $call('deleteTransaction')"
                    class="w-full py-2.5 text-sm bg-white border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition flex items-center justify-center">
                    <x-lucide-check class="w-4 h-4 mr-2 text-gray-400" /> Apenas este
                </button>

                <button wire:click="$set('deleteMode', 'forward'); $call('deleteTransaction')"
                    class="w-full py-2.5 text-sm bg-white border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition flex items-center justify-center">
                    <x-lucide-calendar-clock class="w-4 h-4 mr-2 text-gray-400" /> Deste mês em diante
                </button>

                <button wire:click="deleteGroup"
                    class="w-full py-2.5 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold transition flex items-center justify-center">
                    <x-lucide-trash-2 class="w-4 h-4 mr-2" /> Todos (Série Completa)
                </button>

                <button wire:click="$set('showDeleteModal', false)" class="w-full py-2 text-xs text-gray-400 hover:text-gray-600">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
