<?php
use function Livewire\Volt\{state, computed, layout, uses};
use App\Livewire\Concerns\HasScopeToggle;
use App\Models\Goal;

layout('components.layouts.app');
uses([HasScopeToggle::class]);

state(['view' => session('view_mode', 'personal'), 'confirmDeleteId' => null])->url();

$goals = computed(function () {
    return Goal::forView(auth()->user(), $this->view)
        ->with('user')
        ->orderBy('created_at', 'desc')
        ->get();
});

$summary = computed(function () {
    $goals = $this->goals;
    $totalTarget = (float) $goals->sum('target');
    $totalCurrent = (float) $goals->sum(fn ($g) => min((float) $g->current, (float) $g->target));
    $pct = $totalTarget > 0 ? min(100, round($totalCurrent / $totalTarget * 100)) : 0;
    $completed = $goals->filter(fn ($g) => $g->remaining <= 0)->count();

    return [
        'totalTarget' => $totalTarget,
        'totalCurrent' => $totalCurrent,
        'pct' => $pct,
        'completed' => $completed,
        'count' => $goals->count(),
    ];
});

$setConfirmDelete = function ($id) {
    $this->confirmDeleteId = $id;
};

$deleteGoal = function ($id) {
    $goal = Goal::find($id);
    if ($goal && $goal->manageableBy(auth()->user())) {
        $goal->delete();
        $this->dispatch('notify', 'Meta excluída com sucesso.');
    }
    $this->confirmDeleteId = null;
};
?>

<div x-data="{ goalModalOpen: false, depositModalOpen: false }"
     @close-modal-goal.window="goalModalOpen = false"
     @open-deposit-modal.window="depositModalOpen = true"
     @goal-updated.window="depositModalOpen = false">

    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 sm:mb-6 gap-3">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <x-ui.view-toggle :view="$view" personal-label="Minhas Metas" shared-label="Nossas Metas" />
            <p class="text-[10px] text-gray-400 mt-1">
                @if($view === 'personal')
                    Vendo apenas suas metas pessoais
                @else
                    Vendo metas compartilhadas do casal
                @endif
            </p>
        </div>

        <div class="hidden md:flex justify-self-end">
            <button @click="Livewire.dispatch('reset-modal', { scope: '{{ $view }}' }); goalModalOpen = true;"
                class="bg-primary hover:bg-secondary text-white font-medium py-2 px-4 rounded-lg shadow-md shadow-primary/25 items-center transition text-sm flex">
                <x-lucide-plus class="w-4 h-4 mr-1.5" /> Nova Meta
            </button>
        </div>
    </div>

    {{-- RESUMO --}}
    @php $s = $this->summary; @endphp
    @if($s['count'] > 0)
    <div class="bg-white border border-gray-200 rounded-xl p-4 mb-4 sm:mb-6 shadow-sm">
        <div class="flex items-center justify-between gap-3 mb-2">
            <div class="min-w-0">
                <p class="text-[11px] text-gray-500 font-medium">Guardado no total</p>
                <p class="text-lg sm:text-xl font-bold text-gray-900">
                    R$ {{ number_format($s['totalCurrent'], 2, ',', '.') }}
                    <span class="text-xs font-medium text-gray-400">de R$ {{ number_format($s['totalTarget'], 2, ',', '.') }}</span>
                </p>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="text-xl sm:text-2xl font-bold {{ $s['pct'] >= 100 ? 'text-green-600' : 'text-primary' }}">{{ $s['pct'] }}%</p>
                <p class="text-[10px] text-gray-400">{{ $s['completed'] }} de {{ $s['count'] }} {{ $s['count'] == 1 ? 'meta concluída' : 'metas concluídas' }}</p>
            </div>
        </div>
        <div class="w-full bg-gray-200/70 rounded-full h-2.5 overflow-hidden">
            <div class="{{ $s['pct'] >= 100 ? 'bg-green-500' : 'bg-primary' }} h-2.5 rounded-full transition-all duration-1000 ease-out" style="width: {{ $s['pct'] }}%"></div>
        </div>
    </div>
    @endif

    {{-- GRID DE METAS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
        @forelse($this->goals as $goal)
            @php
                $target = $goal->target > 0 ? $goal->target : 1;
                $percent = min(100, ($goal->current / $target) * 100);
                $remaining = max(0, $goal->target - $goal->current);
                $f = $goal->forecast();

                if($percent >= 100) {
                    $barColor = 'bg-green-500';
                    $badgeColor = 'bg-green-100 text-green-700';
                    $icon = 'check-circle';
                    $iconColor = 'text-green-500';
                } else {
                    $barColor = 'bg-blue-600';
                    $badgeColor = 'bg-blue-50 text-blue-700';
                    $icon = 'target';
                    $iconColor = 'text-blue-500';
                }
            @endphp

            <div class="bg-white border border-gray-200 rounded-xl p-3 sm:p-4 hover:shadow-md transition group relative flex flex-col h-full">
                <div class="flex items-start gap-3 mb-3">
                    <div class="w-10 h-10 flex-shrink-0 {{ $percent >= 100 ? 'bg-green-50' : 'bg-blue-50' }} rounded-lg flex items-center justify-center">
                        <x-dynamic-component :component="'lucide-'.($icon)" class="w-5 h-5 {{ $iconColor }}" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-sm text-gray-900 leading-tight mb-1 truncate">{{ $goal->name }}</h3>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $badgeColor }}">
                                {{ number_format($percent, 0) }}%
                            </span>
                            @if($view === 'shared' && $goal->user_id !== auth()->id())
                                <span class="text-[9px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded flex items-center">
                                    <x-lucide-user class="w-2.5 h-2.5 mr-0.5" /> {{ $goal->user->name ?? 'User' }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($goal->user_id === auth()->id())
                        <div class="flex gap-0.5 flex-shrink-0 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                            <button @click="Livewire.dispatch('edit-goal', { id: {{ $goal->id }} }); goalModalOpen = true"
                                class="p-1.5 min-h-[44px] min-w-[44px] sm:min-h-0 sm:min-w-0 flex items-center justify-center hover:bg-gray-100 rounded-md text-gray-400 hover:text-blue-600 transition">
                                <x-lucide-pencil class="w-3.5 h-3.5" />
                            </button>
                            <button wire:click="setConfirmDelete({{ $goal->id }})"
                                class="p-1.5 min-h-[44px] min-w-[44px] sm:min-h-0 sm:min-w-0 flex items-center justify-center hover:bg-gray-100 rounded-md text-gray-400 hover:text-red-600 transition">
                                <x-lucide-trash-2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    @endif
                </div>

                <div class="mt-auto">
                    <div class="flex justify-between items-end text-[11px] mb-1.5">
                        <span class="text-gray-500">Atual</span>
                        <span class="font-bold text-gray-900 text-sm">R$ {{ number_format($goal->current, 2, ',', '.') }}</span>
                    </div>

                    <div class="w-full bg-gray-200/80 rounded-full h-2.5 overflow-hidden mb-2">
                        <div class="{{ $barColor }} h-2.5 rounded-full transition-all duration-1000 ease-out" style="width: {{ $percent }}%"></div>
                    </div>

                    <div class="flex justify-between items-center text-[10px] text-gray-500 border-t border-gray-100 pt-2 mb-2">
                        <span>Alvo: <b>R$ {{ number_format($goal->target, 2, ',', '.') }}</b></span>
                        @if($remaining > 0)
                            <span class="text-blue-600 font-medium">Falta: R$ {{ number_format($remaining, 2, ',', '.') }}</span>
                        @else
                            <span class="text-green-600 font-bold flex items-center"><x-lucide-party-popper class="w-2.5 h-2.5 mr-0.5" /> Atingida!</span>
                        @endif
                    </div>

                    {{-- Previsão + ritmo --}}
                    @if($remaining > 0)
                        @if($f['hasPlan'])
                        <div class="rounded-lg bg-gray-50 border border-gray-100 px-2.5 py-2 mb-2 space-y-1">
                            <div class="flex items-center gap-1.5 text-[11px] text-gray-700">
                                <x-lucide-calendar-clock class="w-3.5 h-3.5 text-primary flex-shrink-0" />
                                @if($f['overdue'])
                                    <span class="text-red-600 font-medium">Prazo vencido — faltam R$ {{ number_format($f['neededMonthly'], 2, ',', '.') }}</span>
                                @elseif($f['mode'] === 'monthly')
                                    <span>Conclui em <b class="capitalize">{{ $f['forecastDate']->locale('pt_BR')->isoFormat('MMM/YY') }}</b> · {{ $f['monthsLeft'] }} {{ $f['monthsLeft'] == 1 ? 'mês' : 'meses' }}</span>
                                @else
                                    <span>Até <b class="capitalize">{{ $f['forecastDate']->locale('pt_BR')->isoFormat('MMM/YY') }}</b> · guarde <b>R$ {{ number_format($f['neededMonthly'], 2, ',', '.') }}</b>/mês</span>
                                @endif
                            </div>
                            <div class="flex items-center justify-between text-[10px]">
                                <span class="text-gray-500">Ritmo médio: <b class="text-gray-700">R$ {{ number_format($f['pace'], 2, ',', '.') }}</b>/mês</span>
                                @if($f['onTrack'])
                                    <span class="inline-flex items-center gap-0.5 text-green-700 bg-green-100 px-1.5 py-0.5 rounded-full font-medium">
                                        <x-lucide-trending-up class="w-2.5 h-2.5" /> No ritmo
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-0.5 text-red-700 bg-red-100 px-1.5 py-0.5 rounded-full font-medium">
                                        <x-lucide-trending-down class="w-2.5 h-2.5" /> Atrasado
                                    </span>
                                @endif
                            </div>
                        </div>
                        @elseif($goal->user_id === auth()->id())
                        <button @click="Livewire.dispatch('edit-goal', { id: {{ $goal->id }} }); goalModalOpen = true"
                            class="w-full mb-2 px-2.5 py-1.5 rounded-lg border border-dashed border-gray-300 text-[11px] text-gray-500 hover:border-primary hover:text-primary transition flex items-center justify-center gap-1">
                            <x-lucide-calendar-plus class="w-3.5 h-3.5" /> Definir prazo / quanto por mês
                        </button>
                        @endif
                    @endif

                    <div class="space-y-1.5">
                        @if($remaining > 0)
                        <button wire:click="$dispatch('open-deposit-modal', { id: {{ $goal->id }}, name: @js($goal->name) })"
                            class="w-full py-2 bg-green-50 text-green-700 rounded-lg text-xs font-medium hover:bg-green-100 transition flex items-center justify-center">
                            <x-lucide-piggy-bank class="w-3.5 h-3.5 mr-1.5" /> Reservar Valor
                        </button>
                        @endif
                        @if($goal->current > 0)
                        <button wire:click="$dispatch('open-withdraw-modal', { id: {{ $goal->id }}, name: @js($goal->name) })"
                            class="w-full py-2 bg-red-50 text-red-700 rounded-lg text-xs font-medium hover:bg-red-100 transition flex items-center justify-center">
                            <x-lucide-minus-circle class="w-3.5 h-3.5 mr-1.5" /> Retirar Valor
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center py-10 text-gray-400 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mb-3 shadow-sm">
                    <x-lucide-flag class="w-6 h-6 opacity-40" />
                </div>
                <h3 class="font-semibold text-gray-600 text-sm">Nenhuma meta definida</h3>
                <p class="text-xs mt-1 mb-3 text-gray-400">Defina objetivos para poupar dinheiro.</p>
                <button @click="Livewire.dispatch('reset-modal', { scope: '{{ $view }}' }); goalModalOpen = true;"
                    class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-medium hover:bg-secondary transition shadow">
                    Criar Primeira Meta
                </button>
            </div>
        @endforelse
    </div>

    {{-- Modal de Confirmação de Exclusão --}}
    @if($confirmDeleteId)
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4"
         wire:click="$set('confirmDeleteId', null)">
        <div class="bg-white rounded-xl shadow-xl p-5 w-full max-w-xs" @click.stop>
            <div class="text-center mb-4">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-3">
                    <x-lucide-trash-2 class="w-6 h-6" />
                </div>
                <h3 class="text-sm font-bold text-gray-900">Excluir esta meta?</h3>
                <p class="text-xs text-gray-500 mt-1">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="$set('confirmDeleteId', null)"
                    class="flex-1 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-200 transition">
                    Cancelar
                </button>
                <button wire:click="deleteGoal({{ $confirmDeleteId }})"
                    class="flex-1 py-2.5 bg-red-600 text-white rounded-lg font-semibold text-sm hover:bg-red-700 transition">
                    Excluir
                </button>
            </div>
        </div>
    </div>
    @endif

    <livewire:components.goal-modal />
</div>
