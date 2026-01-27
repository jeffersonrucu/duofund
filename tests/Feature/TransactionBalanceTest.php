<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_shared_income_transaction_creates_a_personal_expense_transaction(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/livewire/update/EhjAI3v59V7IFf63aN6v', [
            'state' => [
                'transactionId' => null,
                'isEditing' => false,
                'description' => 'Test Income',
                'amount' => '100,00',
                'type' => 'income',
                'category' => 'Receita',
                'date' => '2026-01-26',
                'repetition' => 'single',
                'installments' => '',
                'scope' => 'shared',
                'categories_list' => [],
                'hasGroupId' => false,
                'editAll' => false,
                'showEditConfirmation' => false,
                'pendingEditData' => null,
                'affectedCount' => 0
            ],
            'actionQueue' => [
                [
                    'type' => 'callMethod',
                    'payload' => [
                        'method' => 'save',
                        'params' => [],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);

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
