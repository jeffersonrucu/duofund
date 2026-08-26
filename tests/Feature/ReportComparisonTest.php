<?php

use App\Models\Transaction;
use App\Models\User;
use Livewire\Volt\Volt;

/**
 * O relatório era um extrato: mostrava o mês e pronto. O comparativo com o
 * mês anterior é o que transforma isso em base de decisão.
 */
function lancar(User $dono, string $tipo, float $valor, string $categoria, string $mes): void
{
    Transaction::create([
        'user_id' => $dono->id,
        'description' => "{$tipo} {$categoria}",
        'amount' => $valor,
        'type' => $tipo,
        'category' => $categoria,
        'scope' => 'personal',
        'date' => "{$mes}-10",
    ]);
}

beforeEach(function () {
    $this->eu = User::factory()->create();
    $this->actingAs($this->eu);
    $this->mes = '2026-06';
    $this->anterior = '2026-05';
});

function relatorioDe(string $mes)
{
    return Volt::test('pages.report')
        ->set('currentMonth', "{$mes}-01")
        ->get('report');
}

it('calcula a variação de receitas e despesas', function () {
    lancar($this->eu, 'income', 1000, 'Salário', $this->anterior);
    lancar($this->eu, 'expense', 200, 'Mercado', $this->anterior);

    lancar($this->eu, 'income', 1500, 'Salário', $this->mes);
    lancar($this->eu, 'expense', 150, 'Mercado', $this->mes);

    $r = relatorioDe($this->mes);

    expect($r['delta']['income'])->toBe(50.0)   // 1000 -> 1500
        ->and($r['delta']['expense'])->toBe(-25.0); // 200 -> 150
});

it('devolve null quando não há base de comparação', function () {
    lancar($this->eu, 'income', 1000, 'Salário', $this->mes);

    $r = relatorioDe($this->mes);

    expect($r['delta']['income'])->toBeNull()
        ->and($r['delta']['expense'])->toBeNull();
});

it('indexa a variação por categoria, não por posição', function () {
    lancar($this->eu, 'expense', 100, 'Mercado', $this->anterior);
    lancar($this->eu, 'expense', 400, 'Farmácia', $this->anterior);

    lancar($this->eu, 'expense', 300, 'Mercado', $this->mes);
    lancar($this->eu, 'expense', 200, 'Farmácia', $this->mes);

    $r = relatorioDe($this->mes);

    expect($r['catDelta']['Mercado'])->toBe(200.0)    // 100 -> 300
        ->and($r['catDelta']['Farmácia'])->toBe(-50.0); // 400 -> 200
});

it('marca categoria nova como estreia', function () {
    lancar($this->eu, 'expense', 100, 'Mercado', $this->anterior);
    lancar($this->eu, 'expense', 250, 'Pet', $this->mes);

    $r = relatorioDe($this->mes);

    expect($r['catDelta']['Pet'])->toBeNull();

    Volt::test('pages.report')
        ->set('currentMonth', "{$this->mes}-01")
        ->assertSee('estreia');
});

it('não deixa o mês anterior vazar para os totais do mês', function () {
    lancar($this->eu, 'expense', 999, 'Mercado', $this->anterior);
    lancar($this->eu, 'expense', 100, 'Mercado', $this->mes);

    $r = relatorioDe($this->mes);

    expect((float) $r['expense'])->toBe(100.0)
        ->and((float) $r['prev']['expense'])->toBe(999.0);
});

it('atravessa a virada de ano ao buscar o mês anterior', function () {
    lancar($this->eu, 'expense', 100, 'Mercado', '2025-12');
    lancar($this->eu, 'expense', 300, 'Mercado', '2026-01');

    $r = relatorioDe('2026-01');

    expect((float) $r['prev']['expense'])->toBe(100.0)
        ->and($r['catDelta']['Mercado'])->toBe(200.0);
});
