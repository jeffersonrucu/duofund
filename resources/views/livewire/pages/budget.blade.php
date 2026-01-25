<?php
use function Livewire\Volt\{state, computed, layout};
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;

layout('components.layouts.app');

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => now()->startOfMonth()->format('Y-m-d')
])->url();

$data = computed(function() {
    $user = auth()->user();
    $familyIds = $user->getFamilyUserIds();
    $date = Carbon::parse($this->currentMonth);

    $queryCat = Category::query();
    $queryTx = Transaction::query();

    if ($this->view === 'personal') {
        $queryCat->where('user_id', $user->id)->where('scope', 'personal');
        $queryTx->where('user_id', $user->id)->where('scope', 'personal');
    } else {
        $queryCat->whereIn('user_id', $familyIds)->where('scope', 'shared');
        $queryTx->whereIn('user_id', $familyIds)->where('scope', 'shared');
    }

    $categories = $queryCat->orderBy('name')->get();

    // Filtra gastos APENAS do mês selecionado
    $usage = $queryTx->where('type', 'expense')
        ->whereYear('date', $date->year)
        ->whereMonth('date', $date->month)
        ->selectRaw('category, sum(amount) as total')
        ->groupBy('category')
        ->pluck('total', 'category');

    $totalBudget = $categories->sum('limit');
    // $totalSpent removido pois não exibiremos mais o global aqui,
    // mas mantemos o usage para as barras individuais

    return compact('categories', 'usage', 'totalBudget');
});

$setView = function ($mode) {
    $this->view = $mode;
    session(['view_mode' => $mode]);
};

$deleteCat = function($id) {
    $cat = Category::find($id);
    if($cat && ($cat->user_id === auth()->id() || in_array($cat->user_id, auth()->user()->getFamilyUserIds()))) {
        $cat->delete();
        $this->dispatch('notify', 'Categoria removida.');
    }
};

// Navegação
$prevMonth = function() { $this->currentMonth = Carbon::parse($this->currentMonth)->subMonth()->format('Y-m-d'); };
$nextMonth = function() { $this->currentMonth = Carbon::parse($this->currentMonth)->addMonth()->format('Y-m-d'); };
$today = function() { $this->currentMonth = now()->startOfMonth()->format('Y-m-d'); };
?>

<div x-data="{ categoryModalOpen: false }" @close-modal-category.window="categoryModalOpen = false">

    {{-- HEADER DE NAVEGAÇÃO --}}
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

        <button @click="categoryModalOpen = true; Livewire.dispatch('open-new-category', { scope: '{{ $view }}' })"
            class="hidden md:flex bg-gray-900 hover:bg-gray-800 text-white font-medium py-2 px-4 rounded-xl shadow-lg shadow-gray-200 items-center transition transform hover:scale-105">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Nova Categoria
        </button>
    </div>

    {{-- RESUMO DO ORÇAMENTO (Apenas Total Planejado) --}}
    @php
        $totalBudget = $this->data['totalBudget'];
    @endphp

    <div class="mb-8">
        <div class="bg-gradient-to-r from-blue-50 to-white p-6 rounded-xl border border-blue-100 shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-blue-100 text-blue-600 rounded-lg">
                    <i data-lucide="calculator" class="w-6 h-6"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Total Planejado para o Mês</p>
                    <p class="text-3xl font-bold text-gray-900">R$ {{ number_format($totalBudget, 2, ',', '.') }}</p>
                </div>
            </div>

            {{-- Texto auxiliar (opcional) --}}
            <div class="hidden md:block text-right">
                <p class="text-xs text-gray-400">Soma de todos os limites de categoria</p>
            </div>
        </div>
    </div>

    {{-- GRID DE CATEGORIAS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($this->data['categories'] as $cat)
            @php
                $used = $this->data['usage'][$cat->name] ?? 0;
                $limit = $cat->limit ?: 1;
                $pct = round(($used / $limit) * 100);

                // Cores baseadas no consumo
                if($pct > 100) {
                    $barColor = 'bg-red-500';
                    $textColor = 'text-red-600';
                    $bgColor = 'bg-red-50 border-red-100';
                    $iconColor = 'text-red-500';
                } elseif($pct > 80) {
                    $barColor = 'bg-yellow-500';
                    $textColor = 'text-yellow-700';
                    $bgColor = 'bg-yellow-50 border-yellow-100';
                    $iconColor = 'text-yellow-600';
                } else {
                    $barColor = 'bg-primary';
                    $textColor = 'text-primary';
                    $bgColor = 'bg-white border-gray-200';
                    $iconColor = 'text-gray-400';
                }
            @endphp

            <div class="{{ $bgColor }} p-5 rounded-xl border shadow-sm hover:shadow-md transition relative group flex flex-col h-full">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-white border border-gray-100 flex items-center justify-center shadow-sm">
                            <span class="font-bold text-lg {{ $iconColor }}">{{ substr($cat->name, 0, 1) }}</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 leading-tight">{{ $cat->name }}</h3>
                            @if($view === 'shared' && $cat->user_id !== auth()->id())
                                <span class="text-[10px] text-gray-400 flex items-center mt-0.5">
                                    <i data-lucide="user" class="w-3 h-3 mr-1"></i> {{ substr($cat->user->name ?? '?', 0, 10) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Menu de Ações (Aparece no Hover) --}}
                    <div class="flex space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button @click="categoryModalOpen = true; Livewire.dispatch('edit-category', { id: {{ $cat->id }}, name: '{{ $cat->name }}', limit: {{ $cat->limit }}, scope: '{{ $cat->scope 
}}' })"
                            class="p-1.5 hover:bg-gray-100 rounded-md text-gray-400 hover:text-blue-500 transition">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                        <button wire:click="deleteCat({{ $cat->id }})" wire:confirm="Excluir a categoria {{ $cat->name }}?"
                            class="p-1.5 hover:bg-gray-100 rounded-md text-gray-400 hover:text-red-500 transition">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <div class="mt-auto">
                    <div class="flex justify-between items-end mb-2">
                        <div>
                            <span class="text-xs text-gray-500 font-medium">Gasto</span>
                            <p class="font-bold text-gray-900">R$ {{ number_format($used, 2, ',', '.') }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold {{ $textColor }}">{{ $pct }}%</span>
                        </div>
                    </div>

                    <div class="w-full bg-gray-200/50 rounded-full h-2.5 overflow-hidden">
                        <div class="{{ $barColor }} h-2.5 rounded-full transition-all duration-1000 ease-out" style="width: {{ min(100, $pct) }}%"></div>
                    </div>

                    <div class="mt-2 text-xs text-gray-400 flex justify-between">
                        <span>Limite: <b>R$ {{ number_format($cat->limit, 2, ',', '.') }}</b></span>
                        <span class="{{ $cat->limit - $used < 0 ? 'text-red-500 font-bold' : '' }}">Restam: R$ {{ number_format($cat->limit - $used, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center p-12 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200 text-gray-400">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-sm">
                    <i data-lucide="layers" class="w-8 h-8 opacity-50"></i>
                </div>
                <span class="font-bold text-gray-600 text-lg">Nenhuma categoria configurada</span>
                <p class="text-sm mt-1 text-gray-400">Categorias ajudam você a planejar seus limites de gastos.</p>
                <button @click="categoryModalOpen = true; Livewire.dispatch('open-new-category', { scope: '{{ $view }}' })" class="mt-4 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm 
font-medium hover:border-primary hover:text-primary transition shadow-sm">
                    Criar Primeira Categoria
                </button>
            </div>
        @endforelse

        {{-- Atalho Rápido para Adicionar --}}
        @if(count($this->data['categories']) > 0)
            <button @click="categoryModalOpen = true; Livewire.dispatch('open-new-category', { scope: '{{ $view }}' })"
                class="flex flex-col items-center justify-center p-5 rounded-xl border-2 border-dashed border-gray-300 text-gray-400 hover:border-primary hover:text-primary hover:bg-blue-50/50 
transition h-full min-h-[160px] group">
                <div class="w-12 h-12 rounded-full bg-gray-100 group-hover:bg-white flex items-center justify-center mb-2 transition">
                    <i data-lucide="plus" class="w-6 h-6"></i>
                </div>
                <span class="font-medium text-sm">Nova Categoria</span>
            </button>
        @endif
    </div>

    <livewire:components.category-modal />
</div>

