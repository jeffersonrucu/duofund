<?php
use function Livewire\Volt\{state, computed, layout, uses};
use App\Livewire\Concerns\HasMonthNavigation;
use App\Livewire\Concerns\HasScopeToggle;
use App\Models\Card;
use App\Models\Transaction;
use Carbon\Carbon;

layout('components.layouts.app');
uses([HasMonthNavigation::class, HasScopeToggle::class]);

state([
    'view' => session('view_mode', 'personal'),
    'currentMonth' => session('current_month', now()->startOfMonth()->format('Y-m-d')),
    'confirmDeleteId' => null,
    'confirmDeleteName' => '',
])->url();

$cards = computed(function() {
    return Card::forView(auth()->user(), $this->view)
        ->with('user')
        ->orderBy('label')->orderBy('last4')->get();
});

// Gasto por cartão exibido no mês [card_id => ['total'=>x, 'combined'=>bool]]
// Regra: cartões com o MESMO last4 nas contas pessoal e do casal somam os gastos das duas.
$spendByCard = computed(function() {
    $displayed = $this->cards;
    if ($displayed->isEmpty()) return collect();

    $user = auth()->user();
    $familyIds = $user->getFamilyUserIds();

    // Universo de cartões: pessoais do usuário + compartilhados da família
    $allCards = Card::where(function($q) use ($user, $familyIds) {
        $q->where(fn($q2) => $q2->where('user_id', $user->id)->where('scope', 'personal'))
          ->orWhere(fn($q2) => $q2->whereIn('user_id', $familyIds)->where('scope', 'shared'));
    })->get(['id', 'last4', 'scope']);

    $sumByCardId = Transaction::whereIn('card_id', $allCards->pluck('id'))
        ->where('type', 'expense')
        ->inMonth(Carbon::parse($this->currentMonth))
        ->selectRaw('card_id, sum(amount) as total')
        ->groupBy('card_id')
        ->pluck('total', 'card_id');

    // Total somado por last4
    $totalByLast4 = $allCards->groupBy('last4')->map(
        fn($group) => $group->sum(fn($c) => (float) ($sumByCardId[$c->id] ?? 0))
    );

    // last4 presentes em pessoal E compartilhado ao mesmo tempo
    $combined = $allCards->where('scope', 'personal')->pluck('last4')->unique()
        ->intersect($allCards->where('scope', 'shared')->pluck('last4')->unique());

    return $displayed->mapWithKeys(fn($c) => [$c->id => [
        'total' => $totalByLast4[$c->last4] ?? 0,
        'combined' => $combined->contains($c->last4),
    ]]);
});

$setConfirmDelete = function($id) {
    $card = Card::find($id);
    $this->confirmDeleteId = $id;
    $this->confirmDeleteName = $card?->display_name ?? '';
};

$deleteCard = function($id) {
    $card = Card::find($id);
    if ($card && $card->manageableBy(auth()->user())) {
        // Desvincula transações (mantém a despesa, só perde o cartão)
        Transaction::where('card_id', $card->id)->update(['card_id' => null]);
        $card->delete();
        $this->dispatch('notify', 'Cartão removido.');
    }
    $this->reset(['confirmDeleteId', 'confirmDeleteName']);
};
?>

<div x-data="{ cardModalOpen: false }" @close-modal-card.window="cardModalOpen = false">

    {{-- HEADER --}}
    <div class="grid grid-cols-1 md:grid-cols-3 items-center mb-4 sm:mb-6 gap-3">
        <div class="flex flex-col items-center md:items-start justify-self-center md:justify-self-start">
            <x-ui.view-toggle :view="$view" personal-label="Meus Cartões" shared-label="Cartões do Casal" />
            <p class="text-[10px] text-gray-400 mt-1">
                @if($view === 'personal')
                    Cartões visíveis só para você
                @else
                    Cartões compartilhados do casal
                @endif
            </p>
        </div>

        {{-- Navegador de mês --}}
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
            <button @click="cardModalOpen = true; Livewire.dispatch('open-new-card', { scope: '{{ $view }}' })"
                class="bg-primary hover:bg-secondary text-white font-medium py-2 px-4 rounded-lg shadow-md shadow-primary/25 items-center transition text-sm flex">
                <x-lucide-plus class="w-4 h-4 mr-1.5" /> Novo Cartão
            </button>
        </div>
    </div>

    {{-- GRID DE CARTÕES --}}
    @php $mesAbrev = \Carbon\Carbon::parse($currentMonth)->locale('pt_BR')->isoFormat('MMM/YY'); @endphp
    <div class="flex flex-wrap gap-3 sm:gap-4">
        @forelse($this->cards as $card)
            <div class="relative group aspect-[1.6/1] w-full sm:w-[340px] flex-shrink-0 rounded-2xl p-4 sm:p-5 shadow-lg text-white overflow-hidden flex flex-col justify-between
                        {{ $card->scope === 'shared'
                            ? 'bg-gradient-to-br from-purple-600 via-indigo-700 to-indigo-900'
                            : 'bg-gradient-to-br from-slate-700 via-slate-800 to-slate-900' }}">
                {{-- brilhos decorativos --}}
                <div class="absolute -right-8 -top-10 w-32 h-32 rounded-full bg-white/10"></div>
                <div class="absolute -right-12 top-6 w-36 h-36 rounded-full bg-white/5"></div>

                {{-- topo: chip + ações --}}
                <div class="flex justify-between items-start relative z-10">
                    <div class="w-9 h-7 rounded-md bg-gradient-to-br from-yellow-200 to-yellow-400 shadow-inner flex items-center justify-center">
                        <div class="w-5 h-3.5 rounded-sm border border-yellow-600/40"></div>
                    </div>
                    <div class="flex space-x-0.5 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                        <button @click="cardModalOpen = true; Livewire.dispatch('edit-card', { id: {{ $card->id }}, last4: @js($card->last4), label: @js($card->label), scope: '{{ $card->scope }}' })"
                            class="w-9 h-9 flex items-center justify-center hover:bg-white/15 rounded-lg text-white/70 hover:text-white transition" aria-label="Editar">
                            <x-lucide-pencil class="w-4 h-4" />
                        </button>
                        <button wire:click="setConfirmDelete({{ $card->id }})"
                            class="w-9 h-9 flex items-center justify-center hover:bg-white/15 rounded-lg text-white/70 hover:text-red-300 transition" aria-label="Excluir">
                            <x-lucide-trash-2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {{-- número mascarado --}}
                <div class="relative z-10 font-mono text-base sm:text-lg tracking-[0.2em] text-white/90">
                    <span class="opacity-50">••••</span> <span class="opacity-50">••••</span> <span class="opacity-50">••••</span> {{ $card->last4 }}
                </div>

                {{-- rodapé: apelido (+ dono) à esquerda · gasto do mês à direita --}}
                @php
                    $info = $this->spendByCard[$card->id] ?? ['total' => 0, 'combined' => false];
                    $spent = $info['total'];
                    $combined = $info['combined'];
                @endphp
                <div class="relative z-10 flex items-end justify-between gap-2">
                    <div class="min-w-0">
                        @if($view === 'shared' && $card->user_id !== auth()->id())
                            <span class="text-[9px] text-white/70 inline-flex items-center gap-0.5 bg-white/10 px-1.5 py-0.5 rounded-full mb-1">
                                <x-lucide-user class="w-2.5 h-2.5" /> {{ substr($card->user->name ?? '?', 0, 10) }}
                            </span>
                        @endif
                        <p class="text-[9px] uppercase tracking-wider text-white/50">{{ $card->label ? 'Apelido' : 'Cartão' }}</p>
                        <p class="text-sm font-semibold truncate">{{ $card->label ?: 'Final ' . $card->last4 }}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-[9px] uppercase tracking-wider text-white/50 flex items-center justify-end gap-1">
                            @if($combined)
                                <span class="inline-flex items-center gap-0.5 bg-white/15 px-1 py-px rounded text-white/80" title="Soma das contas pessoal e do casal">
                                    <x-lucide-layers class="w-2.5 h-2.5" /> P+C
                                </span>
                            @endif
                            Gasto · {{ $mesAbrev }}
                        </p>
                        <p class="text-sm font-bold {{ $spent > 0 ? 'text-white' : 'text-white/40' }}">R$ {{ number_format($spent, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        @empty
            <div class="w-full flex flex-col items-center justify-center p-8 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 text-gray-400">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mb-3 shadow-sm">
                    <x-lucide-credit-card class="w-6 h-6 opacity-50" />
                </div>
                <span class="font-semibold text-gray-600 text-sm">Nenhum cartão cadastrado</span>
                <p class="text-xs mt-1 text-gray-400 text-center">Cadastre seus cartões para escolher a origem dos gastos.</p>
                <button @click="cardModalOpen = true; Livewire.dispatch('open-new-card', { scope: '{{ $view }}' })"
                    class="mt-3 px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:border-primary hover:text-primary transition shadow-sm">
                    Cadastrar Primeiro Cartão
                </button>
            </div>
        @endforelse

        @if(count($this->cards) > 0)
            <button @click="cardModalOpen = true; Livewire.dispatch('open-new-card', { scope: '{{ $view }}' })"
                class="flex flex-col items-center justify-center p-4 rounded-2xl border-2 border-dashed border-gray-300 text-gray-400 hover:border-primary hover:text-primary hover:bg-blue-50/50 transition aspect-[1.6/1] w-full sm:w-[340px] flex-shrink-0 group">
                <div class="w-10 h-10 rounded-full bg-gray-100 group-hover:bg-white flex items-center justify-center mb-1.5 transition">
                    <x-lucide-plus class="w-5 h-5" />
                </div>
                <span class="font-medium text-xs">Novo Cartão</span>
            </button>
        @endif
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
                <h3 class="text-sm font-bold text-gray-900">Excluir cartão?</h3>
                <p class="text-xs text-gray-500 mt-1 font-medium">{{ $confirmDeleteName }}</p>
                <p class="text-[10px] text-gray-400 mt-2">As despesas pagas com ele continuam, mas perdem o vínculo do cartão.</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="$set('confirmDeleteId', null)"
                    class="flex-1 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-200 transition">
                    Cancelar
                </button>
                <button wire:click="deleteCard({{ $confirmDeleteId }})"
                    class="flex-1 py-2.5 bg-red-600 text-white rounded-lg font-semibold text-sm hover:bg-red-700 transition">
                    Excluir
                </button>
            </div>
        </div>
    </div>
    @endif

    <livewire:components.card-modal />
</div>
