<?php
use function Livewire\Volt\{state, computed, usesPagination, layout};
use App\Models\Transaction;
use Carbon\Carbon;

layout('components.layouts.app');
usesPagination();

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => now()->startOfMonth()->format('Y-m-d'),

    // Estados do Modal de Exclusão
    'confirmDeleteId' => null,
    'deleteMode' => 'single',
    'isRecurringDelete' => false,
    'showDeleteModal' => false
])->url();

// Cálculo dos Totais para os Cards do Topo (Apenas do mês atual)
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

    // Clona a query para não afetar os outros cálculos
    $income = (clone $query)->where('type', 'income')->sum('amount');
    $expense = (clone $query)->where('type', 'expense')->sum('amount');

    return [
        'income' => $income,
        'expense' => $expense,
        'balance' => $income - $expense
    ];
});

// Listagem Paginada
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
    $this->resetPage();
};

// --- LÓGICA DE EXCLUSÃO (Mantida igual) ---
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

// Navegação
$prevMonth = function() { $this->currentMonth = Carbon::parse($this->currentMonth)->subMonth()->format('Y-m-d'); $this->resetPage(); };
$nextMonth = function() { $this->currentMonth = Carbon::parse($this->currentMonth)->addMonth()->format('Y-m-d'); $this->resetPage(); };
$today = function() { $this->currentMonth = now()->startOfMonth()->format('Y-m-d'); $this->resetPage(); };
?>

<div>
    {{-- HEADER UNIFICADO (Igual ao Dashboard) --}}
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
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

        <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}' })"
            class="hidden md:flex bg-gray-900 hover:bg-gray-800 text-white font-medium py-2 px-4 rounded-xl shadow-lg shadow-gray-200 items-center transition transform hover:scale-105">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Adicionar
        </button>
    </div>

    {{-- CARDS DE RESUMO (Mini Dashboard para contexto) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-br from-green-50 to-white p-4 rounded-xl border border-green-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs text-green-700 font-medium uppercase tracking-wide">Receitas</p>
                <p class="text-xl font-bold text-gray-900">R$ {{ number_format($this->totals['income'], 2, ',', '.') }}</p>
            </div>
            <div class="bg-green-100 p-2 rounded-lg text-green-600">
                <i data-lucide="trending-up" class="w-5 h-5"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-red-50 to-white p-4 rounded-xl border border-red-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs text-red-700 font-medium uppercase tracking-wide">Despesas</p>
                <p class="text-xl font-bold text-gray-900">R$ {{ number_format($this->totals['expense'], 2, ',', '.') }}</p>
            </div>
            <div class="bg-red-100 p-2 rounded-lg text-red-600">
                <i data-lucide="trending-down" class="w-5 h-5"></i>
            </div>
        </div>

        @php
            $bal = $this->totals['balance'];
            $isPos = $bal >= 0;
        @endphp
        <div class="bg-gradient-to-br {{ $isPos ? 'from-blue-50 to-white border-blue-100' : 'from-orange-50 to-white border-orange-100' }} p-4 rounded-xl border shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs {{ $isPos ? 'text-blue-700' : 'text-orange-700' }} font-medium uppercase tracking-wide">Saldo do Mês</p>
                <p class="text-xl font-bold text-gray-900">R$ {{ number_format($bal, 2, ',', '.') }}</p>
            </div>
            <div class="{{ $isPos ? 'bg-blue-100 text-blue-600' : 'bg-orange-100 text-orange-600' }} p-2 rounded-lg">
                <i data-lucide="{{ $isPos ? 'wallet' : 'alert-circle' }}" class="w-5 h-5"></i>
            </div>
        </div>
    </div>

    {{-- LISTA DE TRANSAÇÕES (Estilo Moderno) --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-gray-800 text-lg">Histórico de Lançamentos</h3>
            <span class="text-xs text-gray-400">Ordenado por data</span>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse($this->transactions as $t)
            <div class="p-4 hover:bg-gray-50 transition group flex flex-col sm:flex-row sm:items-center justify-between gap-3">

                {{-- Lado Esquerdo: Ícone + Descrição --}}
                <div class="flex items-center gap-4 overflow-hidden">
                    {{-- Ícone Circular --}}
                    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center {{ $t->type == 'income' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                        <i data-lucide="{{ $t->type == 'income' ? 'arrow-up' : 'arrow-down' }}" class="w-5 h-5"></i>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900 truncate">{{ $t->description }}</span>

                            {{-- Badges (Recorrente/Parcelado) --}}
                            @if($t->is_installment)
                                <span class="px-1.5 py-0.5 bg-yellow-100 text-yellow-800 rounded text-[10px] font-bold" title="Parcela {{ $t->installment_current }} de {{ $t->installment_count }}">
                                    {{ $t->installment_current }}/{{ $t->installment_count }}
                                </span>
                            @elseif($t->is_recurring)
                                <span class="px-1.5 py-0.5 bg-blue-100 text-blue-800 rounded text-[10px] font-bold" title="Recorrente">
                                    <i data-lucide="repeat" class="w-3 h-3"></i>
                                </span>
                            @endif

                            {{-- Avatar se Compartilhado --}}
                            @if($view === 'shared' && $t->user_id !== auth()->id())
                                <span class="w-5 h-5 bg-purple-100 text-purple-700 rounded-full flex items-center justify-center text-[10px] font-bold border border-purple-200" title="{{ $t->user->name }}">
                                    {{ substr($t->user->name, 0, 1) }}
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center text-xs text-gray-500 mt-0.5 gap-2">
                            <span>{{ $t->date->format('d/m/Y') }}</span>
                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                            <span class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">{{ $t->category }}</span>
                        </div>
                    </div>
                </div>

                {{-- Lado Direito: Valor + Ações --}}
                <div class="flex items-center justify-between sm:justify-end gap-6 pl-14 sm:pl-0">
                    <span class="text-base font-bold whitespace-nowrap {{ $t->type == 'income' ? 'text-green-600' : 'text-gray-900' }}">
                        {{ $t->type == 'income' ? '+' : '-' }} R$ {{ number_format($t->amount, 2, ',', '.') }}
                    </span>

                    {{-- Botões de Ação (Aparecem no hover em Desktop, fixos em Mobile) --}}
                    <div class="flex items-center gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                        <button @click="transactionModalOpen = true; Livewire.dispatch('edit-transaction', { id: {{ $t->id }} })"
                            class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Editar">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                        <button wire:click="confirmDelete({{ $t->id }})"
                            class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition" title="Excluir">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                    <i data-lucide="calendar-x" class="w-8 h-8 opacity-50"></i>
                </div>
                <p class="font-medium">Nenhum lançamento neste mês.</p>
                <button wire:click="prevMonth" class="text-sm text-primary hover:underline mt-1">Ver mês anterior</button>
            </div>
            @endforelse
        </div>

        @if($this->transactions->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50">
                {{ $this->transactions->links() }}
            </div>
        @endif
    </div>

    {{-- ATALHOS FLUTUANTES MOBILE --}}
    <div class="md:hidden fixed bottom-6 right-6 flex flex-col gap-3 z-40">
        <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'expense', scope: '{{ $view }}' })" class="w-12 h-12 bg-red-500 rounded-full text-white shadow-lg flex items-center justify-center">
            <i data-lucide="minus" class="w-6 h-6"></i>
        </button>
        <button @click="transactionModalOpen = true; Livewire.dispatch('open-new-transaction', { type: 'income', scope: '{{ $view }}' })" class="w-12 h-12 bg-green-500 rounded-full text-white shadow-lg flex items-center justify-center">
            <i data-lucide="plus" class="w-6 h-6"></i>
        </button>
    </div>

    {{-- MODAL DE EXCLUSÃO --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm" wire:click="$set('showDeleteModal', false)">
        <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm border-t-4 border-red-500" @click.stop>
            <div class="text-center mb-5">
                <div class="bg-red-100 text-red-600 w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm">
                    <i data-lucide="repeat" class="w-7 h-7"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Excluir Recorrência</h3>
                <p class="text-sm text-gray-500 mt-2 leading-relaxed">
                    Este item faz parte de uma série. <br>Como deseja proceder?
                </p>
            </div>

            <div class="space-y-3">
                <button wire:click="$set('deleteMode', 'single'); $call('deleteTransaction')"
                    class="w-full py-3 px-4 bg-white border border-gray-200 shadow-sm rounded-xl text-gray-700 hover:bg-gray-50 hover:border-gray-300 font-semibold transition text-sm flex items-center justify-center">
                    <i data-lucide="check" class="w-4 h-4 mr-2 text-gray-400"></i> Apenas este
                </button>

                <button wire:click="deleteGroup"
                    class="w-full py-3 px-4 bg-red-600 shadow-md shadow-red-200 text-white rounded-xl hover:bg-red-700 font-semibold transition text-sm flex items-center justify-center">
                    <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i> Todos (Série Completa)
                </button>
            </div>

            <button wire:click="$set('showDeleteModal', false)" class="mt-5 text-xs text-gray-400 hover:text-gray-600 w-full text-center underline">Cancelar</button>
        </div>
    </div>
    @endif
</div>
