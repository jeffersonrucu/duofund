<?php
use function Livewire\Volt\{state, computed, layout, protect};
use App\Models\Goal;

layout('components.layouts.app');

state(['view' => session('view_mode', 'personal')])->url();

$goals = computed(function () {
    $user = auth()->user();
    $familyIds = $user->getFamilyUserIds();

    $query = Goal::query();

    if ($this->view === 'personal') {
        $query->where('user_id', $user->id)->where('scope', 'personal');
    } else {
        $query->whereIn('user_id', $familyIds)->where('scope', 'shared');
    }

    return $query->orderBy('created_at', 'desc')->get();
});

$setView = function ($mode) {
    $this->view = $mode;
    session(['view_mode' => $mode]);
    $this->dispatch('scope-changed', $mode);
};

$deleteGoal = function ($id) {
    $goal = Goal::find($id);
    if ($goal && $goal->user_id === auth()->id()) {
        $goal->delete();
        $this->dispatch('notify', 'Meta excluída com sucesso.');
    }
};
?>

<div x-data="{ goalModalOpen: false, depositModalOpen: false }"
     @close-modal-goal.window="goalModalOpen = false"
     @open-deposit-modal.window="depositModalOpen = true"
     @goal-updated.window="depositModalOpen = false">

    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 sm:mb-6 gap-3">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <div class="bg-white p-0.5 rounded-lg shadow-sm border border-gray-200 inline-flex">
                <button wire:click="setView('personal')"
                    class="px-3 py-1.5 rounded-md text-[11px] sm:text-sm font-medium transition flex items-center {{ $view === 'personal' ? 'bg-primary text-white shadow' : 'text-gray-500 hover:bg-gray-50' }}">
                    <i data-lucide="user" class="w-3 h-3 sm:w-4 sm:h-4 mr-1"></i> Minhas Metas
                </button>
                <button wire:click="setView('shared')"
                    class="px-3 py-1.5 rounded-md text-[11px] sm:text-sm font-medium transition flex items-center {{ $view === 'shared' ? 'bg-purple-600 text-white shadow' : 'text-gray-500 hover:bg-gray-50' }}">
                    <i data-lucide="users" class="w-3 h-3 sm:w-4 sm:h-4 mr-1"></i> Nossas Metas
                </button>
            </div>
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
                class="bg-gray-900 hover:bg-gray-800 text-white font-medium py-2 px-3 rounded-lg shadow items-center transition text-sm flex">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i> Nova Meta
            </button>
        </div>
    </div>

    {{-- SUBTÍTULO --}}
    <div class="flex flex-col md:flex-row justify-between items-end mb-4">
        <div>
            <h2 class="text-base sm:text-lg font-bold text-gray-900">Meus Objetivos</h2>
            <p class="text-xs text-gray-500">Defina alvos financeiros e acompanhe seu progresso.</p>
        </div>
    </div>

    {{-- GRID DE METAS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
        @forelse($this->goals as $goal)
            @php
                $target = $goal->target > 0 ? $goal->target : 1;
                $percent = min(100, ($goal->current / $target) * 100);
                $remaining = max(0, $target - $goal->current);

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
                <div class="flex justify-between items-start mb-3">
                    <div class="w-10 h-10 {{ $percent >= 100 ? 'bg-green-50' : 'bg-blue-50' }} rounded-lg flex items-center justify-center">
                        <i data-lucide="{{ $icon }}" class="w-5 h-5 {{ $iconColor }}"></i>
                    </div>

                    @if($goal->user_id === auth()->id())
                        <div class="flex gap-0.5 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                            <button @click="Livewire.dispatch('edit-goal', { id: {{ $goal->id }} }); goalModalOpen = true"
                                class="p-1.5 hover:bg-gray-100 rounded-md text-gray-400 hover:text-blue-600 transition">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <button wire:click="deleteGoal({{ $goal->id }})" wire:confirm="Excluir meta?"
                                class="p-1.5 hover:bg-gray-100 rounded-md text-gray-400 hover:text-red-600 transition">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <h3 class="font-semibold text-sm text-gray-900 leading-tight mb-1">{{ $goal->name }}</h3>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $badgeColor }}">
                            {{ number_format($percent, 0) }}%
                        </span>
                        @if($view === 'shared' && $goal->user_id !== auth()->id())
                            <span class="text-[9px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded flex items-center">
                                <i data-lucide="user" class="w-2.5 h-2.5 mr-0.5"></i> {{ $goal->user->name ?? 'User' }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="mt-auto">
                    <div class="flex justify-between items-end text-[11px] mb-1.5">
                        <span class="text-gray-500">Atual</span>
                        <span class="font-bold text-gray-900 text-sm">R$ {{ number_format($goal->current, 2, ',', '.') }}</span>
                    </div>

                    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden mb-2">
                        <div class="{{ $barColor }} h-2 rounded-full transition-all duration-1000 ease-out" style="width: {{ $percent }}%"></div>
                    </div>

                    <div class="flex justify-between items-center text-[10px] text-gray-500 border-t border-gray-100 pt-2">
                        <span>Alvo: <b>R$ {{ number_format($goal->target, 2, ',', '.') }}</b></span>
                        @if($remaining > 0)
                            <span class="text-blue-600 font-medium">Falta: R$ {{ number_format($remaining, 2, ',', '.') }}</span>
                        @else
                            <span class="text-green-600 font-bold flex items-center"><i data-lucide="party-popper" class="w-2.5 h-2.5 mr-0.5"></i> Atingida!</span>
                        @endif
                    </div>

                    @if($remaining > 0)
                        <button wire:click="$dispatch('open-deposit-modal', { id: {{ $goal->id }}, name: '{{ $goal->name }}' })"
                            class="mt-2 w-full py-2 bg-green-50 text-green-700 rounded-lg text-xs font-medium hover:bg-green-100 transition flex items-center justify-center">
                            <i data-lucide="piggy-bank" class="w-3.5 h-3.5 mr-1.5"></i> Reservar Valor
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center py-10 text-gray-400 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mb-3 shadow-sm">
                    <i data-lucide="flag" class="w-6 h-6 opacity-40"></i>
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

    <livewire:components.goal-modal />
</div>
