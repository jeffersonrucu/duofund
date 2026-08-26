<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ScopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function couple(): array
    {
        $family = Family::create(['name' => 'Família Teste']);
        $a = User::factory()->create(['family_id' => $family->id]);
        $b = User::factory()->create(['family_id' => $family->id]);

        return [$family, $a, $b];
    }

    public function test_stranger_cannot_delete_personal_transaction(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $tx = Transaction::create([
            'user_id' => $owner->id,
            'description' => 'Particular',
            'amount' => 50,
            'type' => 'expense',
            'category' => 'Geral',
            'scope' => 'personal',
            'date' => '2026-07-01',
        ]);

        $this->actingAs($stranger);
        Volt::test('pages.expenses')->call('confirmDelete', $tx->id);

        $this->assertDatabaseHas('transactions', ['id' => $tx->id]);
    }

    public function test_partner_cannot_manage_personal_goal_of_other(): void
    {
        [, $a, $b] = $this->couple();

        $goal = Goal::create([
            'user_id' => $a->id, 'name' => 'Só minha', 'target' => 100,
            'current' => 0, 'scope' => 'personal', 'is_private' => true,
        ]);

        $this->actingAs($b);
        Volt::test('pages.goals')->call('deleteGoal', $goal->id);

        $this->assertDatabaseHas('goals', ['id' => $goal->id]);
    }

    public function test_partner_can_manage_shared_goal(): void
    {
        [, $a, $b] = $this->couple();

        $goal = Goal::create([
            'user_id' => $a->id, 'name' => 'Nossa viagem', 'target' => 100,
            'current' => 0, 'scope' => 'shared', 'is_private' => false,
        ]);

        $this->actingAs($b);
        Volt::test('pages.goals')->call('deleteGoal', $goal->id);

        $this->assertDatabaseMissing('goals', ['id' => $goal->id]);
    }

    public function test_third_member_cannot_join_full_family(): void
    {
        [$family] = $this->couple();
        $third = User::factory()->create();

        $url = URL::signedRoute('invite.accept', ['family' => $family->id]);

        $this->actingAs($third)->get($url);

        // Todo usuário ganha família própria ao ser criado (User::created);
        // o que importa é não entrar na família cheia do casal.
        $this->assertNotEquals($family->id, $third->fresh()->family_id);
    }
}
