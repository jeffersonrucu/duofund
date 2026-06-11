<?php
use function Livewire\Volt\{state, computed, usesPagination, layout};
use App\Models\Transaction;
use Carbon\Carbon;

layout('components.layouts.app');
usesPagination();

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => session('current_month', now()->startOfMonth()->format('Y-m-d')),
    'confirmDeleteId' => null,
    'deleteMode' => 'single',
    'isRecurringDelete' => false,
    'showDeleteModal' => false
])->url();

$totals = computed(function() {
    $user = auth()->user();
    $familyIds = $user->getFamilyUserIds();
    $targetDate = Carbon::parse($this->currentMonth);

    $query = Transaction::query();

    if ($this->view === 'personal') {
        $query->where('user_id', $user->id)->where('scope', 'personal');
    } else {
        $query->whereIn('user_id', $familyIds)->where('scope', 'shared');
    }

    $query->whereYear('date', $targetDate->year)
          ->whereMonth('date', $targetDate->month);

    $income = (clone $query)->where('type', 'income')->sum('amount');
    $expense = (clone $query)->where('type', 'expense')->sum('amount');

    return [
        'income' => $income,
        'expense' => $expense,
        'balance' => $income - $expense
    ];
});

$baseQuery = function () {
    $user = auth()->user();
    $familyIds = $user->getFamilyUserIds();
    $targetDate = Carbon::parse($this->currentMonth);

    $query = Transaction::query();

    if ($this->view === 'personal') {
        $query->where('user_id', $user->id)->where('scope', 'personal');
    } else {
        $query->whereIn('user_id', $familyIds)->where('scope', 'shared');
    }

    return $query->whereYear('date', $targetDate->year)
                 ->whereMonth('date', $targetDate->month);
};

$lastUpdated = computed(function () {
    return $this->baseQuery()
        ->max('updated_at');
});

$incomes = computed(function () {
    return $this->baseQuery()
        ->where('type', 'income')
        ->orderBy('date', 'desc')
        ->orderBy('created_at', 'desc')
        ->get();
});

$outflows = computed(function () {
    return $this->baseQuery()
        ->whereIn('type', ['expense', 'savings'])
        ->orderBy('date', 'desc')
        ->orderBy('created_at', 'desc')
        ->paginate(20);
});

$setView = function ($mode) {
    $this->view = $mode;
    session(['view_mode' => $mode]);
    $this->dispatch('scope-changed', $mode);
    $this->resetPage();
};

$confirmDelete = function ($id) {
    $tx = Transaction::find($id);
    if (!$tx) return;

    if ($tx->recurring_group_id) {
        $this->confirmDeleteId = $id;
        $this->isRecurringDelete = true;
        $this->deleteMode = 'single';
        $this->showDeleteModal = true;
    } else {
        $this->confirmDeleteId = $id;
        $this->isRecurringDelete = false;
        $this->deleteTransaction();
    }
};

$deleteTransaction = function () {
    $tx = Transaction::find($this->confirmDeleteId);

    if ($tx && ($tx->user_id === auth()->id() || in_array($tx->user_id, auth()->user()->getFamilyUserIds()))) {
        if ($this->isRecurringDelete && $this->deleteMode === 'all') {
            Transaction::where('recurring_group_id', $tx->recurring_group_id)->delete();
            $msg = 'Série recorrente removida com sucesso.';
        } else {
            $tx->delete();
            $msg = 'Transação removida.';
        }
        $this->dispatch('notify', $msg);
    }
    $this->reset(['showDeleteModal', 'confirmDeleteId', 'isRecurringDelete', 'deleteMode']);
};

$deleteGroup = function() {
    $this->deleteMode = 'all';
    $this->deleteTransaction();
};

$prevMonth = function() { 
    $this->currentMonth = Carbon::parse($this->currentMonth)->subMonth()->format('Y-m-d'); 
    session(['current_month' => $this->currentMonth]);
    $this->resetPage(); 
};
$nextMonth = function() { 
    $this->currentMonth = Carbon::parse($this->currentMonth)->addMonth()->format('Y-m-d'); 
    session(['current_month' => $this->currentMonth]);
    $this->resetPage(); 
};
$today = function() { 
    $this->currentMonth = now()->startOfMonth()->format('Y-m-d'); 
    session(['current_month' => $this->currentMonth]);
    $this->resetPage(); 
};
?>

<div>
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
                    Vendo apenas suas transações pessoais
                @else
                    Vendo transações compartilhadas do casal
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
                class="bg-primary hover:bg-secondary text-white font-medium py-2 px-4 rounded-lg shadow-md shadow-primary/25 items-center transition text-sm flex">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i> Adicionar
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
            <i data-lucide="history" class="w-3 h-3"></i>
            <span>Atualizado {{ $updated->locale('pt_BR')->diffForHumans() }}</span>
        </div>
    @endif

    {{-- ENTRADAS --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between bg-green-50/40">
            <div class="flex items-center gap-2">
                <i data-lucide="arrow-up-circle" class="w-3.5 h-3.5 text-green-600"></i>
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
                        <i data-lucide="arrow-up" class="w-3 h-3"></i>
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
                    <span class="text-[11px] font-bold whitespace-nowrap text-green-600">
                        + R$ {{ number_format($t->amount, 2, ',', '.') }}
                    </span>
                    <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                        <button @click="transactionModalOpen = true; Livewire.dispatch('edit-transaction', { id: {{ $t->id }} })"
                            class="p-1 text-blue-500 hover:bg-blue-50 rounded-md transition">
                            <i data-lucide="pencil" class="w-3 h-3"></i>
                        </button>
                        <button wire:click="confirmDelete({{ $t->id }})"
                            class="p-1 text-red-500 hover:bg-red-50 rounded-md transition">
                            <i data-lucide="trash-2" class="w-3 h-3"></i>
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
                <i data-lucide="arrow-down-circle" class="w-3.5 h-3.5 text-red-600"></i>
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
                        <i data-lucide="{{ $t->type == 'savings' ? 'piggy-bank' : 'arrow-down' }}" class="w-3 h-3"></i>
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
                    <span class="text-[11px] font-bold whitespace-nowrap {{ $t->type == 'savings' ? 'text-violet-600' : 'text-gray-900' }}">
                        {{ $t->type == 'savings' ? '' : '-' }} R$ {{ number_format($t->amount, 2, ',', '.') }}
                    </span>
                    <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                        <button @click="transactionModalOpen = true; Livewire.dispatch('edit-transaction', { id: {{ $t->id }} })"
                            class="p-1 text-blue-500 hover:bg-blue-50 rounded-md transition">
                            <i data-lucide="pencil" class="w-3 h-3"></i>
                        </button>
                        <button wire:click="confirmDelete({{ $t->id }})"
                            class="p-1 text-red-500 hover:bg-red-50 rounded-md transition">
                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                    <div class="w-10 h-10 bg-gray-50 rounded-full flex items-center justify-center mb-2">
                        <i data-lucide="calendar-x" class="w-5 h-5 opacity-50"></i>
                    </div>
                    <p class="font-medium text-[11px]">Nenhuma saída neste mês.</p>
                    <button wire:click="prevMonth" class="text-[10px] text-primary hover:underline mt-1">Ver mês anterior</button>
                </div>
            @endforelse
        </div>

        @if($this->outflows->hasPages())
            <div class="p-3 border-t border-gray-100 bg-gray-50">
                {{ $this->outflows->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL DE EXCLUSÃO --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 z-[60] flex sm:items-center sm:justify-center bg-gray-900/50 backdrop-blur-sm" wire:click="$set('showDeleteModal', false)">
        <div class="bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-xl flex flex-col" @click.stop>
            <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                <div class="bg-red-100 text-red-600 w-12 h-12 rounded-full flex items-center justify-center mb-3">
                    <i data-lucide="repeat" class="w-6 h-6"></i>
                </div>
                <h3 class="text-base font-bold text-gray-900">Excluir Recorrência</h3>
                <p class="text-sm text-gray-500 mt-2">
                    Este item faz parte de uma série.<br>Como deseja proceder?
                </p>
            </div>

            <div class="p-4 border-t border-gray-100 space-y-2">
                <button wire:click="$set('deleteMode', 'single'); $call('deleteTransaction')"
                    class="w-full py-2.5 text-sm bg-white border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition flex items-center justify-center">
                    <i data-lucide="check" class="w-4 h-4 mr-2 text-gray-400"></i> Apenas este
                </button>

                <button wire:click="deleteGroup"
                    class="w-full py-2.5 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold transition flex items-center justify-center">
                    <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i> Todos (Série Completa)
                </button>

                <button wire:click="$set('showDeleteModal', false)" class="w-full py-2 text-xs text-gray-400 hover:text-gray-600">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
