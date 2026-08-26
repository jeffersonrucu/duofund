<?php

use App\Models\Card;
use App\Models\Family;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Volt\Volt;

/**
 * `card_id` só era validado com `exists:cards,id`, então dava para anexar
 * o cartão de qualquer outra família e ver o apelido + últimos 4 dígitos
 * na listagem de transações.
 */
function preencherDespesa($component, Card $card, string $scope = 'personal')
{
    return $component
        ->set('type', 'expense')
        ->set('scope', $scope)
        ->set('description', 'Mercado')
        ->set('amount', '150,00')
        ->set('date', now()->format('Y-m-d'))
        ->set('category', 'Alimentação')
        ->set('payment_method', 'card')
        ->set('card_id', $card->id);
}

it('recusa cartão de outra família', function () {
    $eu = User::factory()->create();
    $estranho = User::factory()->create();

    $cartaoAlheio = Card::create([
        'user_id' => $estranho->id, 'scope' => 'personal',
        'last4' => '1234', 'label' => 'Cartão do estranho',
    ]);

    $this->actingAs($eu);

    preencherDespesa(Volt::test('components.transaction-modal'), $cartaoAlheio)
        ->call('save')
        ->assertHasErrors('card_id');

    expect(Transaction::count())->toBe(0);
});

it('aceita cartão próprio', function () {
    $eu = User::factory()->create();

    $meuCartao = Card::create([
        'user_id' => $eu->id, 'scope' => 'personal',
        'last4' => '9999', 'label' => 'Meu',
    ]);

    $this->actingAs($eu);

    preencherDespesa(Volt::test('components.transaction-modal'), $meuCartao)
        ->call('save')
        ->assertHasNoErrors();

    expect(Transaction::where('card_id', $meuCartao->id)->count())->toBe(1);
});

it('aceita cartão shared do parceiro na visão do casal', function () {
    $familia = Family::create(['name' => 'Casal']);
    $eu = User::factory()->create(['family_id' => $familia->id]);
    $parceiro = User::factory()->create(['family_id' => $familia->id]);

    $cartaoDoCasal = Card::create([
        'user_id' => $parceiro->id, 'scope' => 'shared',
        'last4' => '4321', 'label' => 'Conjunto',
    ]);

    $this->actingAs($eu);

    preencherDespesa(Volt::test('components.transaction-modal'), $cartaoDoCasal, 'shared')
        ->call('save')
        ->assertHasNoErrors();

    expect(Transaction::where('card_id', $cartaoDoCasal->id)->count())->toBe(1);
});

it('recusa cartão pessoal do parceiro na visão pessoal', function () {
    $familia = Family::create(['name' => 'Casal']);
    $eu = User::factory()->create(['family_id' => $familia->id]);
    $parceiro = User::factory()->create(['family_id' => $familia->id]);

    $cartaoPessoalDele = Card::create([
        'user_id' => $parceiro->id, 'scope' => 'personal',
        'last4' => '5555', 'label' => 'Só dele',
    ]);

    $this->actingAs($eu);

    preencherDespesa(Volt::test('components.transaction-modal'), $cartaoPessoalDele)
        ->call('save')
        ->assertHasErrors('card_id');
});

it('limpa o cartão selecionado ao trocar de escopo', function () {
    $eu = User::factory()->create();

    $meuCartao = Card::create([
        'user_id' => $eu->id, 'scope' => 'personal',
        'last4' => '9999', 'label' => 'Meu',
    ]);

    $this->actingAs($eu);

    Volt::test('components.transaction-modal')
        ->set('scope', 'personal')
        ->set('card_id', $meuCartao->id)
        ->set('scope', 'shared')
        ->call('loadCats')
        ->assertSet('card_id', '');
});
