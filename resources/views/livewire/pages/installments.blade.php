<?php
use function Livewire\Volt\{state, computed, layout, uses};
use App\Livewire\Concerns\HasMonthNavigation;
use App\Livewire\Concerns\HasScopeToggle;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionMirrorService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

layout('components.layouts.app');
uses([HasMonthNavigation::class, HasScopeToggle::class]);

state([
    'view'         => session('view_mode', 'personal'),
    'currentMonth' => session('current_month', now()->startOfMonth()->format('Y-m-d')),
]);

state([
    'showEditModal'    => false,
    'editGroupId'      => null,
    'editDescription'  => '',
    'editAmount'       => '',
    'editCategory'     => '',
    'editInstallments' => 1,
    'editDueDay'       => 1,
    'showDeleteModal'  => false,
    'deleteGroupId'    => null,
]);

/** Parcelas de um parcelamento; 'solo_<id>' é a parcela sem série. */
$groupItems = function (?string $groupId) {
    if (! $groupId) {
        return collect();
    }

    $query = Transaction::forView(auth()->user(), $this->view)
        ->where('is_installment', true)
        ->where('is_recurring', false);

    if (str_starts_with($groupId, 'solo_')) {
        $query->whereKey((int) substr($groupId, 5));
    } else {
        $query->where('recurring_group_id', $groupId);
    }

    return $query->orderBy('date')->orderBy('id')->get();
};

// Mantém o dia dentro do mês (dia 31 em fevereiro vira o último dia)
$dueDate = fn (Carbon $month, int $day): string =>
    $month->copy()->day(min($day, $month->daysInMonth))->toDateString();

/** installment_count/current precisam refletir as parcelas que de fato existem. */
$renumber = function (string $groupId, array $familyUserIds): void {
    $items = Transaction::whereIn('user_id', $familyUserIds)
        ->where('recurring_group_id', $groupId)
        ->orderBy('date')->orderBy('id')->get();

    $number = 1;
    foreach ($items as $tx) {
        $tx->update([
            'installment_current' => $number++,
            'installment_count'   => $items->count(),
        ]);
    }
};

$categoriesList = computed(fn () => Category::forView(auth()->user(), $this->view)
    ->orderBy('name')->pluck('name'));

$editItems = computed(fn () => $this->groupItems($this->editGroupId));

// Quantas parcelas serão criadas (positivo) ou removidas (negativo)
$editDelta = computed(fn (): int => (int) $this->editInstallments - $this->editItems->count());

$openEdit = function (string $groupId) {
    $items = $this->groupItems($groupId);
    $first = $items->first();

    if (! $first || ! $first->manageableBy(auth()->user())) {
        $this->dispatch('notify', 'Parcelamento não encontrado.');
        return;
    }

    $this->resetErrorBag();
    $this->editGroupId      = $groupId;
    $this->editDescription  = preg_replace('/\s*\(\d+\/\d+\)$/', '', $first->description);
    $this->editAmount       = number_format((float) $first->amount, 2, ',', '.');
    $this->editCategory     = $first->category ?? '';
    $this->editInstallments = $items->count();
    $this->editDueDay       = $first->date->day;
    $this->showEditModal    = true;
};

$closeEdit = function () {
    $this->reset(['showEditModal', 'editGroupId', 'editDescription', 'editAmount',
                  'editCategory', 'editInstallments', 'editDueDay']);
    $this->resetErrorBag();
};

$saveEdit = function () {
    $this->editAmount = Money::toDecimal($this->editAmount) ?? '';

    $this->validate([
        'editDescription'  => 'required|string|max:255',
        'editAmount'       => 'required|numeric|min:0.01',
        'editCategory'     => 'nullable|string|max:255',
        'editInstallments' => 'required|integer|min:1|max:120',
        'editDueDay'       => 'required|integer|min:1|max:31',
    ]);

    $user  = auth()->user();
    $items = $this->groupItems($this->editGroupId);
    $first = $items->first();

    if (! $first || ! $first->manageableBy($user)) {
        $this->dispatch('notify', 'Parcelamento não encontrado.');
        $this->closeEdit();
        return;
    }

    $count   = (int) $this->editInstallments;
    $dueDay  = (int) $this->editDueDay;
    $mirrors = app(TransactionMirrorService::class);

    DB::transaction(function () use ($items, $first, $count, $dueDay, $user, $mirrors) {
        // Encolher/crescer trabalha em série; a parcela avulsa ganha um grupo agora
        $groupId = $first->recurring_group_id ?: (string) Str::uuid();

        $kept    = $items->take($count);
        $removed = $items->slice($count);

        if ($removed->isNotEmpty()) {
            $mirrors->deleteSeries(
                $groupId,
                $user->getFamilyUserIds(),
                $removed->first()->date->toDateString()
            );
        }

        $fields = [
            'description' => $this->editDescription,
            'amount'      => $this->editAmount,
            'category'    => $this->editCategory ?: 'Sem categoria',
        ];

        foreach ($kept as $tx) {
            $tx->update($fields + [
                'date'               => $this->dueDate($tx->date, $dueDay),
                'is_installment'     => true,
                'recurring_group_id' => $groupId,
            ]);
            $mirrors->reconcile($tx);
        }

        // Cresceu: continua mês a mês depois da última parcela
        $cursor = $kept->last()->date->copy();
        for ($i = $kept->count(); $i < $count; $i++) {
            $cursor = $cursor->copy()->startOfMonth()->addMonth();

            $newTx = Transaction::create($fields + [
                'user_id'            => $first->user_id,
                'type'               => $first->type,
                'scope'              => $first->scope,
                'payment_method'     => $first->payment_method,
                'card_id'            => $first->card_id,
                'date'               => $this->dueDate($cursor, $dueDay),
                'is_installment'     => true,
                'recurring_group_id' => $groupId,
            ]);
            $mirrors->createFor($newTx);
        }

        $this->renumber($groupId, $user->getFamilyUserIds());
    });

    $this->closeEdit();
    $this->dispatch('notify', 'Parcelamento atualizado!');
};

$confirmDelete = function (string $groupId) {
    $first = $this->groupItems($groupId)->first();

    if (! $first || ! $first->manageableBy(auth()->user())) {
        $this->dispatch('notify', 'Parcelamento não encontrado.');
        return;
    }

    $this->deleteGroupId   = $groupId;
    $this->showDeleteModal = true;
};

$deletePlan = function (string $mode = 'all') {
    $user  = auth()->user();
    $first = $this->groupItems($this->deleteGroupId)->first();

    if (! $first || ! $first->manageableBy($user)) {
        $this->reset(['showDeleteModal', 'deleteGroupId']);
        return;
    }

    $groupId    = $first->recurring_group_id;
    $isForward  = $mode === 'forward';
    $monthStart = Carbon::parse($this->currentMonth)->startOfMonth()->toDateString();

    if (! $groupId) {
        $first->delete();
    } else {
        DB::transaction(function () use ($groupId, $isForward, $monthStart, $user) {
            app(TransactionMirrorService::class)
                ->deleteSeries($groupId, $user->getFamilyUserIds(), $isForward ? $monthStart : null);

            $this->renumber($groupId, $user->getFamilyUserIds());
        });
    }

    $this->reset(['showDeleteModal', 'deleteGroupId']);
    $this->dispatch('notify', $isForward
        ? 'Parcelas removidas deste mês em diante.'
        : 'Parcelamento removido.');
};

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
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden" wire:key="plan-{{ $group['group_id'] }}">
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
                        <div class="flex items-center justify-end gap-1 mt-1.5 -mr-1.5">
                            <button wire:click="openEdit('{{ $group['group_id'] }}')"
                                class="w-10 h-10 sm:w-9 sm:h-9 flex items-center justify-center text-blue-500 hover:bg-blue-50 rounded-lg transition"
                                aria-label="Editar parcelamento" title="Editar parcelamento">
                                <x-lucide-pencil class="w-4 h-4" />
                            </button>
                            <button wire:click="confirmDelete('{{ $group['group_id'] }}')"
                                class="w-10 h-10 sm:w-9 sm:h-9 flex items-center justify-center text-red-500 hover:bg-red-50 rounded-lg transition"
                                aria-label="Excluir parcelamento" title="Excluir parcelamento">
                                <x-lucide-trash-2 class="w-4 h-4" />
                            </button>
                        </div>
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

    {{-- MODAL DE EDIÇÃO DO PARCELAMENTO --}}
    @if($showEditModal)
    <div class="fixed inset-0 z-[60] flex sm:items-center sm:justify-center bg-gray-900/50 backdrop-blur-sm" wire:click="closeEdit">
        <div class="bg-white w-full h-full sm:h-auto sm:max-w-md sm:rounded-xl shadow-xl flex flex-col sm:max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="sticky top-0 bg-white z-10 border-b border-gray-100 px-4 py-3 flex justify-between items-center">
                <h3 class="text-base font-semibold text-gray-900">Editar parcelamento</h3>
                <button wire:click="closeEdit" class="p-2.5 sm:p-1.5 -mr-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" aria-label="Fechar">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <form wire:submit="saveEdit" class="p-4 space-y-3">
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Descrição</label>
                    <input type="text" wire:model="editDescription" required
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                    @error('editDescription') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-ui.currency-input model="editAmount" label="Valor da parcela" required class="!font-bold" />

                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Dia do vencimento</label>
                        <input type="number" min="1" max="31" wire:model="editDueDay" required
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        @error('editDueDay') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Categoria</label>
                    <select wire:model="editCategory"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-gray-200 bg-white focus:ring-1 focus:ring-primary/30 focus:border-primary">
                        <option value="">Sem categoria</option>
                        @foreach($this->categoriesList as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                        @if($editCategory && ! $this->categoriesList->contains($editCategory))
                            <option value="{{ $editCategory }}">{{ $editCategory }}</option>
                        @endif
                    </select>
                    @error('editCategory') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Quantidade de parcelas</label>
                    <input type="number" min="1" max="120" wire:model.live="editInstallments" required
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-primary/30 focus:border-primary">
                    @error('editInstallments') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror

                    @if($this->editDelta < 0)
                        <p class="text-[10px] text-red-600 mt-1.5 flex items-start gap-1">
                            <x-lucide-alert-triangle class="w-3 h-3 flex-shrink-0 mt-px" />
                            <span>As <strong>{{ abs($this->editDelta) }}</strong> últimas parcelas serão excluídas.</span>
                        </p>
                    @elseif($this->editDelta > 0)
                        <p class="text-[10px] text-amber-600 mt-1.5 flex items-start gap-1">
                            <x-lucide-plus class="w-3 h-3 flex-shrink-0 mt-px" />
                            <span><strong>{{ $this->editDelta }}</strong> {{ $this->editDelta === 1 ? 'nova parcela' : 'novas parcelas' }} nos meses seguintes.</span>
                        </p>
                    @endif

                    <p class="text-[10px] text-gray-400 mt-1.5 flex items-start gap-1">
                        <x-lucide-info class="w-3 h-3 flex-shrink-0 mt-px" />
                        <span>Descrição, valor, categoria e vencimento valem para todas as parcelas.</span>
                    </p>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="button" wire:click="closeEdit"
                        class="flex-1 py-2.5 text-sm bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition">
                        Cancelar
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                        class="flex-1 py-2.5 text-sm bg-primary text-white rounded-lg font-semibold hover:bg-secondary transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="saveEdit">Salvar</span>
                        <span wire:loading wire:target="saveEdit">Salvando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- MODAL DE EXCLUSÃO DO PARCELAMENTO --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 z-[60] flex sm:items-center sm:justify-center bg-gray-900/50 backdrop-blur-sm" wire:click="$set('showDeleteModal', false)">
        <div class="bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-xl flex flex-col" @click.stop>
            <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                <div class="bg-red-100 text-red-600 w-12 h-12 rounded-full flex items-center justify-center mb-3">
                    <x-lucide-credit-card class="w-6 h-6" />
                </div>
                <h3 class="text-base font-bold text-gray-900">Excluir parcelamento</h3>
                <p class="text-sm text-gray-500 mt-2">
                    Remover todas as parcelas ou só as deste mês em diante?
                </p>
            </div>

            <div class="p-4 border-t border-gray-100 space-y-2">
                <button wire:click="deletePlan('forward')"
                    class="w-full py-2.5 text-sm bg-white border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition flex items-center justify-center">
                    <x-lucide-calendar-clock class="w-4 h-4 mr-2 text-gray-400" /> Deste mês em diante
                </button>

                <button wire:click="deletePlan('all')"
                    class="w-full py-2.5 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold transition flex items-center justify-center">
                    <x-lucide-trash-2 class="w-4 h-4 mr-2" /> Todas as parcelas
                </button>

                <button wire:click="$set('showDeleteModal', false)" class="w-full py-2 text-xs text-gray-400 hover:text-gray-600">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
