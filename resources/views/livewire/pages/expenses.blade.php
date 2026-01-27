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

$transactions = computed(function () {
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

    return $query->orderBy('date', 'desc')->paginate(20);
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
                class="bg-gray-900 hover:bg-gray-800 text-white font-medium py-2 px-3 rounded-lg shadow items-center transition text-sm flex">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i> Adicionar
            </button>
        </div>
    </div>

    {{-- LISTA DE TRANSAÇÕES --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {{-- Header com resumo --}}
        <div class="p-3 sm:p-4 border-b border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-4">
                <div>
                    <h3 class="font-semibold text-gray-800 text-sm sm:text-base">Histórico de Transações</h3>
                    <p class="text-[10px] text-gray-400">Todas as movimentações do mês</p>
                </div>

                {{-- Resumo compacto --}}
                <div class="flex items-center gap-3 text-[11px] sm:text-xs">
                    <div class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                        <span class="text-gray-500">Entradas:</span>
                        <span class="font-bold text-green-600">R$ {{ number_format($this->totals['income'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                        <span class="text-gray-500">Saídas:</span>
                        <span class="font-bold text-red-600">R$ {{ number_format($this->totals['expense'], 0, ',', '.') }}</span>
                    </div>
                    @php $bal = $this->totals['balance']; @endphp
                    <div class="hidden sm:flex items-center gap-1 pl-2 border-l border-gray-200">
                        <span class="text-gray-500">Saldo:</span>
                        <span class="font-bold {{ $bal >= 0 ? 'text-blue-600' : 'text-orange-600' }}">
                            R$ {{ number_format($bal, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="divide-y divide-gray-50">
            @forelse($this->transactions as $t)
            <div class="p-3 hover:bg-gray-50 transition group flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                {{-- Lado Esquerdo: Ícone + Descrição --}}
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center {{ $t->type == 'income' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                        <i data-lucide="{{ $t->type == 'income' ? 'arrow-up' : 'arrow-down' }}" class="w-4 h-4"></i>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="font-medium text-gray-900 text-sm truncate">{{ $t->description }}</span>

                            @if($t->is_installment)
                                <span class="px-1 py-0.5 bg-yellow-100 text-yellow-800 rounded text-[9px] font-bold">
                                    {{ $t->installment_current }}/{{ $t->installment_count }}
                                </span>
                            @elseif($t->is_recurring)
                                <span class="px-1 py-0.5 bg-blue-100 text-blue-800 rounded text-[9px]">
                                    <i data-lucide="repeat" class="w-2.5 h-2.5"></i>
                                </span>
                            @endif

                            @if($view === 'shared' && $t->user_id !== auth()->id())
                                <span class="w-4 h-4 bg-purple-100 text-purple-700 rounded-full flex items-center justify-center text-[9px] font-bold">
                                    {{ substr($t->user->name, 0, 1) }}
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center text-[10px] text-gray-400 mt-0.5 gap-1.5">
                            <span>{{ $t->date->format('d/m/Y') }}</span>
                            <span class="w-0.5 h-0.5 rounded-full bg-gray-300"></span>
                            <span class="bg-gray-100 px-1 py-0.5 rounded text-gray-500">{{ $t->category }}</span>
                        </div>
                    </div>
                </div>

                {{-- Lado Direito: Valor + Ações --}}
                <div class="flex items-center justify-between sm:justify-end gap-4 pl-11 sm:pl-0">
                    <span class="text-sm font-bold whitespace-nowrap {{ $t->type == 'income' ? 'text-green-600' : 'text-gray-900' }}">
                        {{ $t->type == 'income' ? '+' : '-' }} R$ {{ number_format($t->amount, 2, ',', '.') }}
                    </span>

                    <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                        <button @click="transactionModalOpen = true; Livewire.dispatch('edit-transaction', { id: {{ $t->id }} })"
                            class="p-1 text-blue-500 hover:bg-blue-50 rounded-md transition">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        </button>
                        <button wire:click="confirmDelete({{ $t->id }})"
                            class="p-1 text-red-500 hover:bg-red-50 rounded-md transition">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>
            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-2">
                    <i data-lucide="calendar-x" class="w-6 h-6 opacity-50"></i>
                </div>
                <p class="font-medium text-sm">Nenhum lançamento neste mês.</p>
                <button wire:click="prevMonth" class="text-xs text-primary hover:underline mt-1">Ver mês anterior</button>
            </div>
            @endforelse
        </div>

        @if($this->transactions->hasPages())
            <div class="p-3 border-t border-gray-100 bg-gray-50">
                {{ $this->transactions->links() }}
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
