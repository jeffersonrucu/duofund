<?php

namespace Tests\Feature\Unit;

use App\Models\User;
use App\Livewire\Components\TransactionModal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionBalanceUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_shared_income_transaction_creates_a_personal_expense_transaction(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('components.transaction-modal')
            ->set('description', 'Test Income')
            ->set('amount', '100,00')
            ->set('type', 'income')
            ->set('scope', 'shared')
            ->set('date', '2026-01-26')
            ->set('repetition', 'single')
            ->call('save');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'description' => 'Test Income',
            'amount' => 100.00,
            'type' => 'income',
            'scope' => 'shared',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'description' => 'Transferido para conta conjunta',
            'amount' => 100.00,
            'type' => 'expense',
            'scope' => 'personal',
        ]);
    }
}