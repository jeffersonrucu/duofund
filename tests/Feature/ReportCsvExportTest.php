<?php

use App\Models\Transaction;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->eu = User::factory()->create(['name' => 'Jefferson']);
    $this->actingAs($this->eu);
});

/** O Livewire embute o download no payload em base64, não devolve StreamedResponse. */
function csvDoMes(string $mes = '2026-06'): string
{
    $componente = Volt::test('pages.report')
        ->set('currentMonth', "{$mes}-01")
        ->call('exportCsv')
        ->assertFileDownloaded();

    return base64_decode(data_get($componente->effects, 'download.content'));
}

function lancamento(User $dono, array $dados = []): Transaction
{
    return Transaction::create(array_merge([
        'user_id' => $dono->id,
        'description' => 'Mercado',
        'amount' => 150.5,
        'type' => 'expense',
        'category' => 'Alimentação',
        'scope' => 'personal',
        'date' => '2026-06-10',
    ], $dados));
}

it('exporta as transações do mês', function () {
    lancamento($this->eu);
    lancamento($this->eu, ['description' => 'Salário', 'type' => 'income', 'amount' => 5000, 'category' => 'Receita']);

    $csv = csvDoMes();

    expect($csv)->toContain('Mercado')
        ->and($csv)->toContain('Salário')
        ->and($csv)->toContain('Jefferson');
});

it('usa ponto-e-vírgula e BOM para o Excel pt-BR', function () {
    lancamento($this->eu);

    $csv = csvDoMes();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('Data;Descrição;Categoria;Tipo;Quem;Valor');
});

it('escreve o valor com vírgula decimal', function () {
    lancamento($this->eu, ['amount' => 1234.5]);

    expect(csvDoMes())->toContain('1234,50');
});

it('neutraliza fórmula na descrição', function () {
    lancamento($this->eu, ['description' => '=1+1']);
    lancamento($this->eu, ['description' => '@SUM(A1:A9)']);

    $csv = csvDoMes();

    expect($csv)->toContain("'=1+1")
        ->and($csv)->toContain("'@SUM(A1:A9)");
});

it('não inclui transação de outro mês', function () {
    lancamento($this->eu, ['description' => 'Deste mês']);
    lancamento($this->eu, ['description' => 'Do mês passado', 'date' => '2026-05-10']);

    $csv = csvDoMes();

    expect($csv)->toContain('Deste mês')
        ->and($csv)->not->toContain('Do mês passado');
});

it('não inclui transação de outra família', function () {
    lancamento($this->eu, ['description' => 'Minha']);
    lancamento(User::factory()->create(), ['description' => 'Alheia']);

    expect(csvDoMes())->not->toContain('Alheia');
});
