<?php

use function Livewire\Volt\{state, layout};

layout('components.layouts.app');

state(['activeSection' => 'intro']);

$setSection = function($section) {
    $this->activeSection = $section;
};
?>

<div class="max-w-4xl mx-auto" x-data="{ mobileMenuOpen: false }">
    {{-- HEADER --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                <i data-lucide="book-open" class="w-6 h-6 text-primary"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Como usar o DuoFund</h1>
                <p class="text-gray-500 text-sm">Guia completo para gerenciar suas finanças a dois</p>
            </div>
        </div>
    </div>

    @php
        $sections = [
            'intro'        => ['icon' => 'rocket',           'label' => 'Primeiros Passos'],
            'dashboard'    => ['icon' => 'layout-dashboard', 'label' => 'Painel Principal'],
            'transactions' => ['icon' => 'arrow-left-right', 'label' => 'Transações'],
            'installments' => ['icon' => 'credit-card',      'label' => 'Parcelas'],
            'budget'       => ['icon' => 'pie-chart',        'label' => 'Orçamento'],
            'goals'        => ['icon' => 'target',           'label' => 'Metas'],
            'wishlist'     => ['icon' => 'gift',             'label' => 'Desejos'],
            'report'       => ['icon' => 'bar-chart-3',      'label' => 'Relatório'],
            'family'       => ['icon' => 'users',            'label' => 'Finanças a Dois'],
            'tips'         => ['icon' => 'lightbulb',        'label' => 'Dicas Úteis'],
        ];
    @endphp

    <div class="flex flex-col lg:flex-row gap-8">
        {{-- SIDEBAR MOBILE --}}
        <div class="lg:hidden">
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="w-full flex items-center justify-between bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <span class="font-medium text-gray-700">Navegar pelo guia</span>
                <i data-lucide="chevron-down" class="w-5 h-5 text-gray-400" :class="mobileMenuOpen && 'rotate-180'" style="transition: transform 0.2s"></i>
            </button>
            <div x-show="mobileMenuOpen" x-collapse class="mt-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                @foreach($sections as $key => $item)
                    <button wire:click="setSection('{{ $key }}')" @click="mobileMenuOpen = false"
                        class="w-full flex items-center gap-3 p-3 text-left hover:bg-gray-50 transition {{ $activeSection === $key ? 'bg-primary/5 text-primary border-l-2 border-primary' : 'text-gray-600' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                        <span class="text-sm font-medium">{{ $item['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- SIDEBAR DESKTOP --}}
        <aside class="hidden lg:block w-64 flex-shrink-0">
            <nav class="bg-white rounded-xl border border-gray-200 shadow-sm p-2 sticky top-24">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider px-3 py-2">Guia</p>
                @foreach($sections as $key => $item)
                    <button wire:click="setSection('{{ $key }}')"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition {{ $activeSection === $key ? 'bg-primary text-white shadow-md' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                        <span class="text-sm font-medium">{{ $item['label'] }}</span>
                    </button>
                @endforeach
            </nav>
        </aside>

        {{-- CONTEÚDO --}}
        <main class="flex-1 min-w-0">
            {{-- PRIMEIROS PASSOS --}}
            @if($activeSection === 'intro')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-primary to-blue-600 p-6 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i data-lucide="rocket" class="w-6 h-6"></i> Bem-vindo ao DuoFund!
                    </h2>
                    <p class="text-blue-100 mt-2">O aplicativo para casais organizarem suas finanças juntos.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-600 leading-relaxed">
                            O DuoFund foi criado para ajudar casais a gerenciarem suas finanças de forma simples e transparente. 
                            Você pode controlar tanto seu dinheiro pessoal quanto as finanças compartilhadas com seu parceiro(a).
                        </p>
                    </div>

                    <div class="grid gap-4">
                        <div class="flex gap-4 p-4 bg-blue-50 rounded-xl border border-blue-100">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <span class="text-blue-600 font-bold">1</span>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Crie sua conta</h3>
                                <p class="text-sm text-gray-600 mt-1">Ao se cadastrar, uma "família" é criada automaticamente para você.</p>
                            </div>
                        </div>

                        <div class="flex gap-4 p-4 bg-purple-50 rounded-xl border border-purple-100">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <span class="text-purple-600 font-bold">2</span>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Convide seu parceiro(a)</h3>
                                <p class="text-sm text-gray-600 mt-1">No modo "Nosso Dinheiro", gere um link de convite para seu parceiro(a) entrar na família.</p>
                            </div>
                        </div>

                        <div class="flex gap-4 p-4 bg-green-50 rounded-xl border border-green-100">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <span class="text-green-600 font-bold">3</span>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Comece a registrar</h3>
                                <p class="text-sm text-gray-600 mt-1">Adicione suas transações, crie categorias de orçamento e defina metas financeiras.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex gap-3">
                            <i data-lucide="lightbulb" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"></i>
                            <div>
                                <h4 class="font-semibold text-amber-900">Conceito Principal</h4>
                                <p class="text-sm text-amber-800 mt-1">
                                    <strong>Meu Dinheiro</strong> = suas finanças pessoais (só você vê)<br>
                                    <strong>Nosso Dinheiro</strong> = finanças do casal (ambos veem)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- PAINEL PRINCIPAL --}}
            @if($activeSection === 'dashboard')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-gray-800 to-gray-900 p-6 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i data-lucide="layout-dashboard" class="w-6 h-6"></i> Painel Principal
                    </h2>
                    <p class="text-gray-300 mt-2">Visão geral das suas finanças em um só lugar.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <i data-lucide="toggle-left" class="w-5 h-5 text-primary"></i> Alternador de Visão
                        </h3>
                        <p class="text-gray-600 text-sm">
                            No topo da página, você encontra dois botões:
                        </p>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
                                <div class="flex items-center gap-2 mb-1">
                                    <i data-lucide="user" class="w-4 h-4 text-primary"></i>
                                    <span class="font-semibold text-gray-900">Meu Dinheiro</span>
                                </div>
                                <p class="text-xs text-gray-600">Mostra apenas suas transações, categorias e metas pessoais.</p>
                            </div>
                            <div class="p-3 bg-purple-50 rounded-lg border border-purple-100">
                                <div class="flex items-center gap-2 mb-1">
                                    <i data-lucide="users" class="w-4 h-4 text-purple-600"></i>
                                    <span class="font-semibold text-gray-900">Nosso Dinheiro</span>
                                </div>
                                <p class="text-xs text-gray-600">Mostra as finanças compartilhadas do casal.</p>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <i data-lucide="credit-card" class="w-5 h-5 text-primary"></i> Cards de Resumo
                        </h3>
                        <div class="space-y-3">
                            <div class="flex gap-3 items-start">
                                <div class="w-3 h-3 rounded-full bg-green-500 mt-1.5 flex-shrink-0"></div>
                                <div>
                                    <span class="font-semibold text-gray-900">Entradas</span>
                                    <p class="text-sm text-gray-600">Total de receitas recebidas no mês selecionado.</p>
                                </div>
                            </div>
                            <div class="flex gap-3 items-start">
                                <div class="w-3 h-3 rounded-full bg-red-500 mt-1.5 flex-shrink-0"></div>
                                <div>
                                    <span class="font-semibold text-gray-900">Saídas</span>
                                    <p class="text-sm text-gray-600">Total de despesas no mês, com indicador de % do orçamento usado.</p>
                                </div>
                            </div>
                            <div class="flex gap-3 items-start">
                                <div class="w-3 h-3 rounded-full bg-blue-500 mt-1.5 flex-shrink-0"></div>
                                <div>
                                    <span class="font-semibold text-gray-900">Resultado do Mês</span>
                                    <p class="text-sm text-gray-600">Diferença entre entradas e saídas. Verde = sobrou, laranja = gastou mais.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <i data-lucide="calendar" class="w-5 h-5 text-primary"></i> Navegação de Mês
                        </h3>
                        <p class="text-gray-600 text-sm">
                            Use as setas para navegar entre os meses e ver o histórico. 
                            Clique em "Voltar para hoje" para retornar ao mês atual.
                        </p>
                    </div>
                </div>
            </div>
            @endif

            {{-- TRANSAÇÕES --}}
            @if($activeSection === 'transactions')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-6 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i data-lucide="arrow-left-right" class="w-6 h-6"></i> Transações
                    </h2>
                    <p class="text-indigo-100 mt-2">Registre todas as suas movimentações financeiras.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900">Tipos de Transação</h3>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="p-4 bg-green-50 rounded-xl border border-green-100">
                                <div class="flex items-center gap-2 mb-2">
                                    <i data-lucide="arrow-up" class="w-5 h-5 text-green-600"></i>
                                    <span class="font-bold text-green-800">Receita</span>
                                </div>
                                <p class="text-sm text-green-700">Salário, freelances, vendas, presentes recebidos, etc.</p>
                            </div>
                            <div class="p-4 bg-red-50 rounded-xl border border-red-100">
                                <div class="flex items-center gap-2 mb-2">
                                    <i data-lucide="arrow-down" class="w-5 h-5 text-red-600"></i>
                                    <span class="font-bold text-red-800">Despesa</span>
                                </div>
                                <p class="text-sm text-red-700">Contas, compras, alimentação, lazer, etc.</p>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900">Frequência da Transação</h3>
                        <div class="space-y-3">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <span class="font-semibold text-gray-900">Única</span>
                                <p class="text-sm text-gray-600 mt-1">Acontece apenas uma vez (ex: compra de um produto).</p>
                            </div>
                            <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900">Recorrente</span>
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-bold">
                                        <i data-lucide="repeat" class="w-3 h-3 inline"></i>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">Repete todos os meses automaticamente (ex: aluguel, Netflix).</p>
                            </div>
                            <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900">Parcelada</span>
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded text-xs font-bold">3/12</span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">Compra parcelada no cartão (ex: geladeira em 10x).</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex gap-3">
                            <i data-lucide="info" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"></i>
                            <div>
                                <h4 class="font-semibold text-amber-900">Editando Recorrentes/Parceladas</h4>
                                <p class="text-sm text-amber-800 mt-1">
                                    Ao editar, você pode escolher alterar apenas aquela parcela ou toda a série futura.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-violet-50 border border-violet-200 rounded-xl p-4">
                        <div class="flex gap-3">
                            <i data-lucide="arrow-right-left" class="w-5 h-5 text-violet-600 flex-shrink-0 mt-0.5"></i>
                            <div>
                                <h4 class="font-semibold text-violet-900">Receita do casal vira transferência</h4>
                                <p class="text-sm text-violet-800 mt-1">
                                    Ao registrar uma <strong>receita</strong> em "Nosso Dinheiro", o DuoFund cria automaticamente
                                    uma saída de <strong>"Transferido para conta conjunta"</strong> no seu dinheiro pessoal —
                                    refletindo que o valor saiu da sua conta e entrou na conta do casal.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- PARCELAS --}}
            @if($activeSection === 'installments')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-yellow-500 to-amber-600 p-6 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-6 h-6"></i> Parcelas
                    </h2>
                    <p class="text-amber-100 mt-2">Acompanhe suas compras parceladas no cartão.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-600">
                            Quando você cria uma despesa marcada como <strong>Parcelada</strong>, ela aparece aqui agrupada.
                            Assim você enxerga tudo que ainda vai pesar no orçamento nos próximos meses.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <h3 class="font-bold text-gray-900">Os três cards do topo</h3>
                        <div class="flex gap-3 items-start">
                            <div class="w-3 h-3 rounded-full bg-amber-500 mt-1.5 flex-shrink-0"></div>
                            <div>
                                <span class="font-semibold text-gray-900">Parcelas</span>
                                <p class="text-sm text-gray-600">Quantas parcelas vencem no mês selecionado.</p>
                            </div>
                        </div>
                        <div class="flex gap-3 items-start">
                            <div class="w-3 h-3 rounded-full bg-red-500 mt-1.5 flex-shrink-0"></div>
                            <div>
                                <span class="font-semibold text-gray-900">Total do Mês</span>
                                <p class="text-sm text-gray-600">Soma das parcelas que caem neste mês.</p>
                            </div>
                        </div>
                        <div class="flex gap-3 items-start">
                            <div class="w-3 h-3 rounded-full bg-orange-500 mt-1.5 flex-shrink-0"></div>
                            <div>
                                <span class="font-semibold text-gray-900">Próximo Mês</span>
                                <p class="text-sm text-gray-600">Quanto você já compromete para o mês seguinte.</p>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="space-y-3">
                        <h3 class="font-bold text-gray-900">Progresso da compra</h3>
                        <p class="text-gray-600 text-sm">
                            Cada item mostra o número da parcela atual (ex: <span class="px-1.5 py-0.5 bg-yellow-100 text-yellow-700 rounded text-xs font-bold">3/12</span>)
                            e quantas já foram pagas, para você saber quanto falta para quitar.
                        </p>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex gap-3">
                            <i data-lucide="plus-circle" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"></i>
                            <p class="text-sm text-amber-800">
                                <strong>Como adicionar:</strong> use "Novo Parcelamento" e informe o valor total e o número de parcelas.
                                O DuoFund cria as parcelas dos meses seguintes automaticamente.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ORÇAMENTO --}}
            @if($activeSection === 'budget')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-6 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i data-lucide="pie-chart" class="w-6 h-6"></i> Orçamento
                    </h2>
                    <p class="text-emerald-100 mt-2">Defina limites de gastos por categoria.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-600">
                            As categorias de orçamento ajudam você a planejar quanto quer gastar em cada área da sua vida.
                            Conforme você registra despesas, as barras de progresso mostram quanto já foi gasto.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900">Como funciona</h3>
                        <ol class="space-y-3">
                            <li class="flex gap-3">
                                <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">1</span>
                                <span class="text-gray-600">Crie categorias como "Alimentação", "Transporte", "Lazer"</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">2</span>
                                <span class="text-gray-600">Defina um limite mensal para cada categoria</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">3</span>
                                <span class="text-gray-600">Ao criar transações, selecione a categoria correspondente</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">4</span>
                                <span class="text-gray-600">Acompanhe o consumo pela barra de progresso</span>
                            </li>
                        </ol>
                    </div>

                    <div class="space-y-3">
                        <h3 class="font-bold text-gray-900">Cores da Barra</h3>
                        <div class="space-y-2">
                            <div class="flex items-center gap-3">
                                <div class="w-20 h-2 bg-primary rounded-full"></div>
                                <span class="text-sm text-gray-600">Até 75% - Tudo bem!</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-20 h-2 bg-yellow-500 rounded-full"></div>
                                <span class="text-sm text-gray-600">75% a 100% - Atenção</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-20 h-2 bg-red-500 rounded-full"></div>
                                <span class="text-sm text-gray-600">Acima de 100% - Estourou!</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- METAS --}}
            @if($activeSection === 'goals')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-orange-500 to-red-500 p-6 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i data-lucide="target" class="w-6 h-6"></i> Metas
                    </h2>
                    <p class="text-orange-100 mt-2">Poupe dinheiro para realizar seus sonhos.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-600">
                            Metas são objetivos financeiros que você quer alcançar. Pode ser uma viagem, 
                            um celular novo, reserva de emergência, ou qualquer outro sonho.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900">Criando uma Meta</h3>
                        <div class="grid gap-3">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <span class="font-semibold text-gray-900">Nome</span>
                                <p class="text-sm text-gray-600">Ex: "Viagem para Paris", "iPhone novo"</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <span class="font-semibold text-gray-900">Valor da Meta</span>
                                <p class="text-sm text-gray-600">Quanto você precisa juntar no total</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <span class="font-semibold text-gray-900">Valor Inicial</span>
                                <p class="text-sm text-gray-600">Se já tem algo guardado, informe aqui</p>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900">Reservando e Retirando Valor</h3>
                        <p class="text-gray-600 text-sm">
                            Clique em <strong>"Reservar Valor"</strong> em qualquer meta para adicionar dinheiro ao progresso.
                            Precisou usar o dinheiro? Use <strong>"Retirar Valor"</strong> para diminuir o valor guardado.
                            A barra mostra o progresso e o quanto ainda falta para o alvo.
                        </p>
                        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                            <div class="flex gap-3">
                                <i data-lucide="piggy-bank" class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5"></i>
                                <p class="text-sm text-green-800">
                                    <strong>Dica:</strong> Defina um valor fixo para depositar todo mês e crie o hábito de poupar!
                                </p>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900">Meta Pessoal vs Meta do Casal</h3>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
                                <span class="font-semibold text-gray-900">Só Minha</span>
                                <p class="text-sm text-gray-600 mt-1">Apenas você vê e pode depositar nesta meta.</p>
                            </div>
                            <div class="p-3 bg-purple-50 rounded-lg border border-purple-100">
                                <span class="font-semibold text-gray-900">Do Casal</span>
                                <p class="text-sm text-gray-600 mt-1">Ambos podem ver e contribuir para a meta.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- DESEJOS --}}
            @if($activeSection === 'wishlist')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-pink-500 to-rose-500 p-6 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i data-lucide="gift" class="w-6 h-6"></i> Desejos
                    </h2>
                    <p class="text-pink-100 mt-2">Sua lista de compras dos sonhos, organizada por prioridade.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-600">
                            Anote o que você quer comprar antes de gastar por impulso. Cada item tem preço,
                            link opcional e uma prioridade para te ajudar a decidir o que vem primeiro.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900">Prioridades</h3>
                        <div class="grid sm:grid-cols-3 gap-3">
                            <div class="p-3 bg-red-50 rounded-lg border border-red-100 text-center">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">Alta</span>
                                <p class="text-xs text-gray-600 mt-2">Quero muito / preciso logo</p>
                            </div>
                            <div class="p-3 bg-amber-50 rounded-lg border border-amber-100 text-center">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Média</span>
                                <p class="text-xs text-gray-600 mt-2">Seria bom ter</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 text-center">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Baixa</span>
                                <p class="text-xs text-gray-600 mt-2">Pode esperar</p>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="space-y-3">
                        <h3 class="font-bold text-gray-900">Simulador de compra</h3>
                        <p class="text-gray-600 text-sm">
                            Antes de comprar, simule o impacto no seu bolso: escolha <strong>à vista</strong> ou
                            <strong>parcelado</strong> e veja se cabe no orçamento do mês considerando suas entradas e saídas.
                        </p>
                    </div>

                    <hr class="border-gray-100">

                    <div class="space-y-3">
                        <h3 class="font-bold text-gray-900">Marcar como comprado</h3>
                        <p class="text-gray-600 text-sm">
                            Comprou? Toque em <strong>"Marcar como comprado"</strong>. O item sai do topo da lista
                            (que mostra os pendentes primeiro). Mudou de ideia? É só desfazer.
                        </p>
                    </div>
                </div>
            </div>
            @endif

            {{-- RELATÓRIO --}}
            @if($activeSection === 'report')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-slate-600 to-gray-800 p-6 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i data-lucide="bar-chart-3" class="w-6 h-6"></i> Relatório
                    </h2>
                    <p class="text-gray-300 mt-2">O extrato mensal completo das suas finanças.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-600">
                            O relatório reúne todas as entradas e saídas do mês em um só lugar, com os totais de
                            receitas, despesas, saldo e quantidade de transações no topo.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <h3 class="font-bold text-gray-900">Entradas e Saídas detalhadas</h3>
                        <p class="text-gray-600 text-sm">
                            As transações aparecem agrupadas por data, separadas entre entradas e saídas, para você
                            revisar exatamente para onde o dinheiro foi no mês.
                        </p>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <div class="flex gap-3">
                            <i data-lucide="printer" class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5"></i>
                            <div>
                                <h4 class="font-semibold text-blue-900">Imprimir ou salvar em PDF</h4>
                                <p class="text-sm text-blue-800 mt-1">
                                    O botão <strong>"Imprimir"</strong> abre a janela de impressão do navegador —
                                    de lá você pode imprimir em papel ou escolher "Salvar como PDF".
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- FINANÇAS A DOIS --}}
            @if($activeSection === 'family')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-violet-600 to-purple-600 p-6 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i data-lucide="users" class="w-6 h-6"></i> Finanças a Dois
                    </h2>
                    <p class="text-violet-100 mt-2">Gerencie o dinheiro do casal de forma transparente.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900">Como Convidar seu Parceiro(a)</h3>
                        <ol class="space-y-3">
                            <li class="flex gap-3">
                                <span class="w-6 h-6 bg-violet-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">1</span>
                                <span class="text-gray-600">Acesse o Painel e selecione "Nosso Dinheiro"</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="w-6 h-6 bg-violet-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">2</span>
                                <span class="text-gray-600">Clique em "Gerar Link de Convite"</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="w-6 h-6 bg-violet-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">3</span>
                                <span class="text-gray-600">Copie e envie o link para seu parceiro(a)</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="w-6 h-6 bg-violet-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">4</span>
                                <span class="text-gray-600">Ele(a) pode criar conta nova ou fazer login com conta existente</span>
                            </li>
                        </ol>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex gap-3">
                            <i data-lucide="clock" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"></i>
                            <p class="text-sm text-amber-800">
                                <strong>Atenção:</strong> O link expira em 24 horas. Se expirar, gere um novo.
                            </p>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="space-y-4">
                        <h3 class="font-bold text-gray-900">O que fica compartilhado?</h3>
                        <div class="space-y-2">
                            <div class="flex items-center gap-3 text-gray-600">
                                <i data-lucide="check" class="w-5 h-5 text-green-500"></i>
                                <span>Transações marcadas como "Do Casal"</span>
                            </div>
                            <div class="flex items-center gap-3 text-gray-600">
                                <i data-lucide="check" class="w-5 h-5 text-green-500"></i>
                                <span>Categorias marcadas como "Do Casal"</span>
                            </div>
                            <div class="flex items-center gap-3 text-gray-600">
                                <i data-lucide="check" class="w-5 h-5 text-green-500"></i>
                                <span>Metas marcadas como "Do Casal"</span>
                            </div>
                            <div class="flex items-center gap-3 text-gray-400">
                                <i data-lucide="x" class="w-5 h-5 text-red-400"></i>
                                <span>Itens pessoais permanecem privados</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- DICAS ÚTEIS --}}
            @if($activeSection === 'tips')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-amber-500 to-orange-500 p-6 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i data-lucide="lightbulb" class="w-6 h-6"></i> Dicas Úteis
                    </h2>
                    <p class="text-amber-100 mt-2">Aproveite ao máximo o DuoFund.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="space-y-4">
                        <div class="p-4 bg-gradient-to-r from-blue-50 to-white rounded-xl border border-blue-100">
                            <div class="flex gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="smartphone" class="w-5 h-5 text-blue-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Registre na hora</h4>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Acesse pelo celular e registre gastos imediatamente. Não deixe para depois!
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-gradient-to-r from-green-50 to-white rounded-xl border border-green-100">
                            <div class="flex gap-3">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="repeat" class="w-5 h-5 text-green-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Use transações recorrentes</h4>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Configure salário, aluguel e contas fixas como recorrentes para não esquecer.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-gradient-to-r from-purple-50 to-white rounded-xl border border-purple-100">
                            <div class="flex gap-3">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="message-circle" class="w-5 h-5 text-purple-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Conversem sobre dinheiro</h4>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Use o DuoFund como ponto de partida para discussões financeiras saudáveis.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-gradient-to-r from-amber-50 to-white rounded-xl border border-amber-100">
                            <div class="flex gap-3">
                                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="target" class="w-5 h-5 text-amber-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Defina metas juntos</h4>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Sonhar junto motiva! Criem metas do casal e acompanhem o progresso.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-gradient-to-r from-red-50 to-white rounded-xl border border-red-100">
                            <div class="flex gap-3">
                                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Respeite os limites</h4>
                                    <p class="text-sm text-gray-600 mt-1">
                                        As barras amarelas e vermelhas são alertas. Repense antes de gastar mais!
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- BOTÃO VOLTAR (Mobile) --}}
            <div class="mt-6 lg:hidden">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center justify-center gap-2 w-full py-3 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar ao Painel
                </a>
            </div>
        </main>
    </div>
</div>
