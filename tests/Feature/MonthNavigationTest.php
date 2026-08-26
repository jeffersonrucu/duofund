<?php

use App\Models\User;
use Livewire\Volt\Volt;

/**
 * As páginas expõem `view` e `currentMonth` na URL (state ->url()), então
 * qualquer valor pode chegar. Antes dos traits HasMonthNavigation /
 * HasScopeToggle isso virava 500 no Carbon::parse.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

dataset('paginas com mês', [
    'painel'     => ['painel', 'pages.dashboard'],
    'transacoes' => ['transacoes', 'pages.expenses'],
    'orcamento'  => ['orcamento', 'pages.budget'],
    'relatorio'  => ['relatorio', 'pages.report'],
    'cartoes'    => ['cartoes', 'pages.cards'],
    'parcelas'   => ['parcelas', 'pages.installments'],
]);

dataset('paginas com escopo', [
    'painel'     => 'painel',
    'transacoes' => 'transacoes',
    'orcamento'  => 'orcamento',
    'relatorio'  => 'relatorio',
    'cartoes'    => 'cartoes',
    'parcelas'   => 'parcelas',
    'metas'      => 'metas',
    'desejos'    => 'desejos',
]);

it('não quebra com mês inválido na URL', function (string $rota) {
    $this->get("/{$rota}?currentMonth=abc")->assertOk();
})->with(['painel', 'transacoes', 'orcamento', 'relatorio', 'cartoes', 'parcelas']);

it('não quebra com escopo inválido na URL', function (string $rota) {
    $this->get("/{$rota}?view=' or 1=1")->assertOk();
})->with('paginas com escopo');

it('normaliza mês inválido para o mês atual', function (string $rota, string $componente) {
    Volt::test($componente)
        ->set('currentMonth', 'abc')
        ->assertSet('currentMonth', now()->startOfMonth()->format('Y-m-d'));
})->with('paginas com mês');

it('normaliza escopo inválido para personal', function () {
    Volt::test('pages.dashboard')
        ->set('view', 'admin')
        ->assertSet('view', 'personal');
});

it('aceita Y-m do input type=month e normaliza para o primeiro dia', function () {
    Volt::test('pages.report')
        ->call('selectMonth', '2026-03')
        ->assertSet('currentMonth', '2026-03-01');
});

it('navega entre meses e persiste na sessão', function () {
    Volt::test('pages.report')
        ->set('currentMonth', '2026-03-01')
        ->call('prevMonth')
        ->assertSet('currentMonth', '2026-02-01')
        ->call('nextMonth')
        ->call('nextMonth')
        ->assertSet('currentMonth', '2026-04-01')
        ->call('today')
        ->assertSet('currentMonth', now()->startOfMonth()->format('Y-m-d'));

    expect(session('current_month'))->toBe(now()->startOfMonth()->format('Y-m-d'));
});

it('setView troca o escopo, persiste na sessão e avisa o layout', function () {
    Volt::test('pages.goals')
        ->call('setView', 'shared')
        ->assertSet('view', 'shared')
        ->assertDispatched('scope-changed');

    expect(session('view_mode'))->toBe('shared');
});

it('setView recusa escopo desconhecido', function () {
    Volt::test('pages.goals')
        ->call('setView', 'tudo')
        ->assertSet('view', 'personal');
});
