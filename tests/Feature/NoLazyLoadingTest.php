<?php

use App\Models\Card;
use App\Models\Category;
use App\Models\Family;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WishlistItem;

/**
 * `Model::preventLazyLoading()` só acusa N+1 quando as relações são
 * realmente percorridas — página vazia não acessa nada.
 *
 * E o Laravel só arma o guard em resultado com mais de uma linha
 * (`Builder::hydrate()`: `if (count($items) > 1)`), porque lazy load em
 * modelo único não é N+1. Por isso cada escopo precisa de 2+ registros
 * de cada modelo: com 1 só, o guard fica desarmado e o teste passa em falso.
 */
beforeEach(function () {
    $familia = Family::create(['name' => 'Casal']);
    $eu = User::factory()->create(['family_id' => $familia->id]);
    $parceiro = User::factory()->create(['family_id' => $familia->id]);

    foreach ([$eu, $parceiro] as $dono) {
        foreach (['personal', 'shared'] as $escopo) {
            $cartao = null;

            foreach ([1, 2] as $n) {
                $cartao ??= Card::create([
                    'user_id' => $dono->id, 'scope' => $escopo,
                    'last4' => "123{$n}", 'label' => "Cartão {$n}",
                ]);

                Card::create([
                    'user_id' => $dono->id, 'scope' => $escopo,
                    'last4' => "999{$n}", 'label' => "Extra {$n}",
                ]);

                Category::create([
                    'user_id' => $dono->id, 'name' => "Categoria {$n}",
                    'limit' => 800, 'scope' => $escopo,
                ]);

                Goal::create([
                    'user_id' => $dono->id, 'name' => "Meta {$n}", 'target' => 5000,
                    'current' => 1200, 'scope' => $escopo, 'is_private' => false,
                ]);

                WishlistItem::create([
                    'user_id' => $dono->id, 'name' => "Desejo {$n}", 'price' => 2500,
                    'priority' => 'high', 'scope' => $escopo, 'status' => 'pending',
                ]);
            }

            foreach (['income', 'expense', 'savings'] as $tipo) {
                Transaction::create([
                    'user_id' => $dono->id,
                    'description' => "Lançamento {$tipo}",
                    'amount' => 150,
                    'type' => $tipo,
                    'category' => 'Categoria 1',
                    'scope' => $escopo,
                    'date' => now()->startOfMonth()->addDays(3),
                    'payment_method' => $tipo === 'expense' ? 'card' : null,
                    'card_id' => $tipo === 'expense' ? $cartao->id : null,
                ]);
            }

            // Parcelada, para a página de parcelas ter o que agrupar
            Transaction::create([
                'user_id' => $dono->id,
                'description' => 'Notebook (2/10)',
                'amount' => 300,
                'type' => 'expense',
                'category' => 'Eletrônicos',
                'scope' => $escopo,
                'date' => now()->startOfMonth()->addDays(5),
                'payment_method' => 'card',
                'card_id' => $cartao->id,
                'is_installment' => true,
                'installment_current' => 2,
                'installment_count' => 10,
            ]);
        }
    }

    $this->euId = $eu->id;
});

it('renderiza com dados sem disparar lazy loading', function (string $rota, string $escopo) {
    $this->actingAs(User::find($this->euId))
        ->withSession(['view_mode' => $escopo])
        ->get("{$rota}?view={$escopo}")
        ->assertOk();
})->with([
    '/painel', '/transacoes', '/orcamento', '/metas',
    '/desejos', '/parcelas', '/cartoes', '/relatorio',
])->with(['personal', 'shared']);
