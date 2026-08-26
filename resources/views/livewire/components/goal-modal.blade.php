<?php

use function Livewire\Volt\{state, on, computed};
use App\Models\Goal;
use Carbon\Carbon;

state([
    'goal_id' => null,
    'name' => '',
    'target' => '',
    'current' => 0,
    'is_private' => true,
    'plan_mode' => 'monthly',          // monthly | date
    'monthly_target' => '',
    'target_date' => ''
]);

$planPreview = computed(function () {
    $remaining = max(0, (float) $this->target - (float) $this->current);
    if ($remaining <= 0) {
        return ((float) $this->target > 0) ? 'Meta já atingida.' : null;
    }

    if ($this->plan_mode === 'monthly') {
        $monthly = (float) $this->monthly_target;
        if ($monthly <= 0) {
            return null;
        }
        $months = (int) ceil($remaining / $monthly);
        $date = Carbon::now()->startOfMonth()->addMonths($months);
        $label = ucfirst($date->locale('pt_BR')->isoFormat('MMM/YY'));
        return "Conclui em {$label} (" . $months . ' ' . ($months == 1 ? 'mês' : 'meses') . ')';
    }

    if (! $this->target_date) {
        return null;
    }
    $raw = strlen($this->target_date) === 7 ? $this->target_date . '-01' : $this->target_date;
    $now = Carbon::now()->startOfMonth();
    $tgt = Carbon::parse($raw)->startOfMonth();
    $months = max(1, $now->diffInMonths($tgt));
    $needed = $remaining / $months;
    return 'Guarde R$ ' . number_format($needed, 2, ',', '.') . '/mês para concluir no prazo';
});

on(['reset-modal' => function ($scope = null) {
    $this->reset();
    $this->plan_mode = 'monthly';
    if ($scope) {
        $this->is_private = ($scope === 'personal');
    }
}]);

on(['edit-goal' => function ($id) {
    $goal = Goal::find($id);

    if ($goal) {
        $this->goal_id = $goal->id;
        $this->name = $goal->name;
        $this->target = $goal->target;
        $this->current = $goal->current;
        $this->is_private = (bool) $goal->is_private;
        $this->plan_mode = $goal->plan_mode ?: 'monthly';
        $this->monthly_target = $goal->monthly_target ?: '';
        $this->target_date = $goal->target_date ? $goal->target_date->format('Y-m') : '';
    }
}]);

$save = function() {
    $this->target = \App\Support\Money::toDecimal($this->target) ?? '';
    $this->current = \App\Support\Money::toDecimal($this->current) ?? 0;
    $this->monthly_target = \App\Support\Money::toDecimal($this->monthly_target) ?? '';

    $this->validate([
        'name' => 'required|string|max:255',
        'target' => 'required|numeric|min:0.01',
        'current' => 'required|numeric|min:0|lte:target',
        'is_private' => 'boolean',
        'plan_mode' => 'required|in:monthly,date',
        'monthly_target' => 'nullable|numeric|min:0.01',
        'target_date' => 'nullable|date_format:Y-m',
    ], [
        'current.lte' => 'O valor guardado não pode ser maior que o alvo.',
        'target.min' => 'O alvo deve ser maior que zero.',
        'target_date.date_format' => 'Selecione um mês válido.',
    ]);

    $scope = $this->is_private ? 'personal' : 'shared';

    if ($this->plan_mode === 'monthly' && (float) $this->monthly_target > 0) {
        $planMode = 'monthly';
        $monthly = $this->monthly_target;
        $date = null;
    } elseif ($this->plan_mode === 'date' && $this->target_date) {
        $planMode = 'date';
        $monthly = null;
        $date = strlen($this->target_date) === 7 ? $this->target_date . '-01' : $this->target_date;
    } else {
        $planMode = null;
        $monthly = null;
        $date = null;
    }

    $data = [
        'name' => $this->name,
        'target' => $this->target,
        'current' => $this->current,
        'is_private' => $this->is_private,
        'scope' => $scope,
        'plan_mode' => $planMode,
        'monthly_target' => $monthly,
        'target_date' => $date,
    ];

    if ($this->goal_id) {
        $goal = Goal::find($this->goal_id);

        if ($goal && $goal->manageableBy(auth()->user())) {
            $goal->update($data);
            $this->dispatch('notify', 'Meta atualizada com sucesso!');
        }
    } else {
        Goal::create(array_merge($data, ['user_id' => auth()->id()]));
        $this->dispatch('notify', 'Meta criada com sucesso!');
    }

    $this->reset();
    $this->dispatch('close-modal-goal');
    $this->redirect(request()->header('Referer') ?: route('dashboard'), navigate: true);
};

?>

<div class="modal-overlay fixed inset-0 z-50 flex sm:items-center sm:justify-center bg-gray-900/50 backdrop-blur-sm" role="dialog" aria-modal="true"
     :class="{ 'active': goalModalOpen }"
     @close-modal-goal.window="goalModalOpen = false"
     @click="goalModalOpen = false"
     x-cloak
     x-show="goalModalOpen"
     x-transition.opacity>

    <div class="bg-white w-full h-full sm:h-auto sm:max-w-sm sm:rounded-xl shadow-2xl sm:mx-4 flex flex-col"
         x-data="sheet(() => goalModalOpen = false)" :style="sheetStyle" @click.stop>
        <!-- Header (alça de arrasto + título) -->
        <div x-on:touchstart.passive="start($event)" x-on:touchmove="move($event)" x-on:touchend="end()">
            <div class="sm:hidden flex justify-center pt-2"><span class="w-10 h-1 bg-gray-300 rounded-full"></span></div>
            <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">{{ $goal_id ? 'Editar Meta' : 'Nova Meta' }}</h3>
                <button class="text-gray-400 hover:text-gray-600 p-2.5 sm:p-1 -mr-1" @click="goalModalOpen = false" aria-label="Fechar">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>
        </div>

        <!-- Form -->
        <form wire:submit="save" class="flex-1 overflow-y-auto p-4 space-y-3">
            <!-- Toggle Pessoal/Compartilhado -->
            <div>
                <div class="flex p-0.5 bg-gray-100 rounded-lg">
                    <button type="button" wire:click="$set('is_private', true)"
                        class="flex-1 py-1.5 text-[11px] font-medium rounded-md transition {{ $is_private ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700' }}">
                        <x-lucide-user class="w-3 h-3 inline mr-0.5" /> Minha Meta
                    </button>
                    <button type="button" wire:click="$set('is_private', false)"
                        class="flex-1 py-1.5 text-[11px] font-medium rounded-md transition {{ !$is_private ? 'bg-white shadow text-purple-600' : 'text-gray-500 hover:text-gray-700' }}">
                        <x-lucide-users class="w-3 h-3 inline mr-0.5" /> Nossa Meta
                    </button>
                </div>
                <p class="text-[10px] text-gray-400 mt-1 text-center">
                    @if($is_private)
                        Apenas você verá esta meta
                    @else
                        Você e seu parceiro(a) verão esta meta
                    @endif
                </p>
            </div>

            <!-- Nome -->
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Nome da Meta</label>
                <input type="text" wire:model="name" required placeholder="Ex: Viagem Europa"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-accent focus:border-accent">
                @error('name') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
            </div>

            <!-- Valor Alvo -->
            <x-ui.currency-input model="target" label="Quanto quer juntar? (R$)" required
                class="focus:ring-accent focus:border-accent" placeholder="10.000,00" />

            <!-- Valor Atual -->
            <div>
                <x-ui.currency-input model="current" label="Já tem guardado? (R$)" required
                    class="focus:ring-accent focus:border-accent" />
                <p class="text-[10px] text-gray-400 mt-1">Valor inicial que você já poupou</p>
            </div>

            <!-- Planejamento -->
            <div class="pt-1 border-t border-gray-100">
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Planejamento</label>
                <div class="flex p-0.5 bg-gray-100 rounded-lg mb-2">
                    <button type="button" wire:click="$set('plan_mode', 'monthly')"
                        class="flex-1 py-1.5 text-[11px] font-medium rounded-md transition {{ $plan_mode === 'monthly' ? 'bg-white shadow text-gray-800' : 'text-gray-500' }}">
                        Por mês
                    </button>
                    <button type="button" wire:click="$set('plan_mode', 'date')"
                        class="flex-1 py-1.5 text-[11px] font-medium rounded-md transition {{ $plan_mode === 'date' ? 'bg-white shadow text-gray-800' : 'text-gray-500' }}">
                        Por data
                    </button>
                </div>

                @if($plan_mode === 'monthly')
                <x-ui.currency-input model="monthly_target" placeholder="Quanto guardar por mês?"
                    class="focus:ring-accent focus:border-accent" />
                @else
                <input type="month" wire:model.blur="target_date"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:ring-1 focus:ring-accent focus:border-accent">
                @error('target_date') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                @endif

                @if($this->planPreview)
                <p class="text-[11px] text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-2.5 py-2 mt-2 flex items-start gap-1.5">
                    <x-lucide-calendar-clock class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                    <span>{{ $this->planPreview }}</span>
                </p>
                @endif
                <p class="text-[10px] text-gray-400 mt-1.5">Opcional — ajuda a prever quando você conclui.</p>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-gray-100 flex gap-2">
            <button type="button" @click="goalModalOpen = false"
                class="flex-1 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Cancelar
            </button>
            <button type="submit" form="goal-form" wire:click="save"
                class="flex-1 py-2.5 text-sm font-semibold text-white bg-accent rounded-lg hover:bg-yellow-600 transition">
                {{ $goal_id ? 'Salvar' : 'Criar Meta' }}
            </button>
        </div>
    </div>
</div>
