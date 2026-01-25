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
};

$deleteGoal = function ($id) {
    $goal = Goal::find($id);
    if ($goal && $goal->user_id === auth()->id()) {
        $goal->delete();
        $this->dispatch('notify', 'Meta excluída com sucesso.');
    }
};
?>

<div x-data="{ goalModalOpen: false }" @close-modal-goal.window="goalModalOpen = false">

    {{-- HEADER UNIFICADO --}}
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

        <button @click="Livewire.dispatch('reset-modal'); goalModalOpen = true;"
            class="hidden md:flex bg-gray-900 hover:bg-gray-800 text-white font-medium py-2 px-4 rounded-xl shadow-lg shadow-gray-200 items-center transition transform hover:scale-105">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Nova Meta
        </button>
    </div>

    {{-- CONTEÚDO --}}
    <div class="flex flex-col md:flex-row justify-between items-end mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Meus Objetivos</h2>
            <p class="text-sm text-gray-500">Defina alvos financeiros e acompanhe seu progresso.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($this->goals as $goal)
            @php
                $target = $goal->target > 0 ? $goal->target : 1;
                $percent = min(100, ($goal->current / $target) * 100);
                $remaining = max(0, $target - $goal->current);

                // Cores de Progresso
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

            <div class="bg-white border border-gray-200 rounded-2xl p-6 hover:shadow-lg transition group relative flex flex-col h-full">

                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 {{ $percent >= 100 ? 'bg-green-50' : 'bg-blue-50' }} rounded-xl flex items-center justify-center">
                        <i data-lucide="{{ $icon }}" class="w-6 h-6 {{ $iconColor }}"></i>
                    </div>

                    {{-- Ações --}}
                    @if($goal->user_id === auth()->id())
                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="Livewire.dispatch('edit-goal', { id: {{ $goal->id }} }); goalModalOpen = true"
                                class="p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-blue-600 transition">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                            <button wire:click="deleteGoal({{ $goal->id }})" wire:confirm="Excluir meta?"
                                class="p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-red-600 transition">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    @endif
                </div>

                <div class="mb-4">
                    <h3 class="font-bold text-lg text-gray-900 leading-tight mb-1">{{ $goal->name }}</h3>
                    <div class="flex items-center gap-2">
                         <span class="text-xs font-bold px-2 py-0.5 rounded {{ $badgeColor }}">
                            {{ number_format($percent, 0) }}% Concluído
                        </span>
                        @if($view === 'shared' && $goal->user_id !== auth()->id())
                            <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded flex items-center">
                                <i data-lucide="user" class="w-3 h-3 mr-1"></i> {{ $goal->user->name ?? 'User' }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="mt-auto">
                    <div class="flex justify-between items-end text-sm mb-2">
                        <span class="text-gray-500">Atual</span>
                        <span class="font-bold text-gray-900 text-lg">R$ {{ number_format($goal->current, 2, ',', '.') }}</span>
                    </div>

                    <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden mb-3">
                        <div class="{{ $barColor }} h-3 rounded-full transition-all duration-1000 ease-out relative overflow-hidden" style="width: {{ $percent }}%">
                            <div class="absolute inset-0 bg-white/20" style="background-image: linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent); background-size: 1rem 1rem;"></div>
                        </div>
                    </div>

                    <div class="flex justify-between items-center text-xs text-gray-500 border-t border-gray-100 pt-3">
                        <span>Alvo: <b>R$ {{ number_format($goal->target, 2, ',', '.') }}</b></span>
                        @if($remaining > 0)
                            <span class="text-blue-600 font-medium">Falta: R$ {{ number_format($remaining, 2, ',', '.') }}</span>
                        @else
                            <span class="text-green-600 font-bold flex items-center"><i data-lucide="party-popper" class="w-3 h-3 mr-1"></i> Atingida!</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center py-16 text-gray-400 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-sm">
                    <i data-lucide="flag" class="w-8 h-8 opacity-40"></i>
                </div>
                <h3 class="font-bold text-gray-600 text-lg">Nenhuma meta definida</h3>
                <p class="text-sm mt-1 mb-4 text-gray-400">Defina objetivos para poupar dinheiro e realizar sonhos.</p>
                <button @click="Livewire.dispatch('reset-modal'); goalModalOpen = true;" class="bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-secondary transition shadow-md">
                    Criar Primeira Meta
                </button>
            </div>
        @endforelse
    </div>

    {{-- ATALHO MOBILE --}}
    <div class="md:hidden fixed bottom-6 right-6 z-40">
        <button @click="Livewire.dispatch('reset-modal'); goalModalOpen = true;" class="w-14 h-14 bg-gray-900 rounded-full text-white shadow-xl flex items-center justify-center">
            <i data-lucide="plus" class="w-6 h-6"></i>
        </button>
    </div>

    <livewire:components.goal-modal />
</div>
