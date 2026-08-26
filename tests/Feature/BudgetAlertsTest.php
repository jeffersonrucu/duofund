<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Volt\Volt;

/**
 * O dado de estouro sempre existiu, mas só aparecia em /orcamento — o
 * usuário precisava ir procurar. O painel agora avisa a partir de 80%.
 */
function gastar(User $dono, string $categoria, float $limite, float $gasto): void
{
    Category::create([
        'user_id' => $dono->id, 'name' => $categoria,
        'limit' => $limite, 'scope' => 'personal',
    ]);

    Transaction::create([
        'user_id' => $dono->id,
        'description' => "Gasto em {$categoria}",
        'amount' => $gasto,
        'type' => 'expense',
        'category' => $categoria,
        'scope' => 'personal',
        'date' => now()->startOfMonth()->addDay(),
    ]);
}

beforeEach(function () {
    $this->eu = User::factory()->create();
    $this->actingAs($this->eu);
});

it('não alerta abaixo de 80%', function () {
    gastar($this->eu, 'Mercado', 1000, 790);

    expect(Volt::test('pages.dashboard')->get('summary')['alerts'])->toBeEmpty();
});

it('alerta a partir de 80%', function () {
    gastar($this->eu, 'Mercado', 1000, 800);

    $alerts = Volt::test('pages.dashboard')->get('summary')['alerts'];

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['pct'])->toBe(80)
        ->and($alerts[0]['name'])->toBe('Mercado');
});

it('mostra o aviso de "perto do limite" quando nada estourou', function () {
    gastar($this->eu, 'Mercado', 1000, 850);

    Volt::test('pages.dashboard')
        ->assertSee('categoria está perto do limite')
        ->assertDontSee('estourou o limite');
});

it('vira aviso de estouro quando passa de 100%', function () {
    gastar($this->eu, 'Mercado', 1000, 1300);

    Volt::test('pages.dashboard')
        ->assertSee('categoria estourou o limite')
        ->assertDontSee('está perto do limite');
});

it('ordena por percentual e concorda em número/plural', function () {
    gastar($this->eu, 'Mercado', 1000, 850);   // 85%
    gastar($this->eu, 'Farmácia', 100, 300);   // 300%
    gastar($this->eu, 'Transporte', 200, 190); // 95%

    $alerts = Volt::test('pages.dashboard')->get('summary')['alerts'];

    expect($alerts->pluck('name')->all())->toBe(['Farmácia', 'Transporte', 'Mercado']);

    Volt::test('pages.dashboard')->assertSee('1 categoria estourou o limite');
});

it('ignora categoria sem limite definido', function () {
    gastar($this->eu, 'Sem limite', 0, 5000);

    expect(Volt::test('pages.dashboard')->get('summary')['alerts'])->toBeEmpty();
});

it('resume quando passam de três categorias', function () {
    foreach (['A', 'B', 'C', 'D'] as $nome) {
        gastar($this->eu, $nome, 100, 95);
    }

    Volt::test('pages.dashboard')->assertSee('e mais 1');
});
