<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        config([
            'services.duofund_mcp.token' => 'test-token',
            'services.duofund_mcp.user_id' => $this->user->id,
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-token'];
    }

    public function test_request_without_token_is_unauthorized(): void
    {
        $this->getJson('/api/mcp/summary')->assertStatus(401);
    }

    public function test_request_with_wrong_token_is_unauthorized(): void
    {
        $this->getJson('/api/mcp/summary', ['Authorization' => 'Bearer errado'])
            ->assertStatus(401);
    }

    public function test_summary_with_valid_token(): void
    {
        $this->getJson('/api/mcp/summary', $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonStructure(['income', 'expenses', 'balance', 'budget_total']);
    }

    public function test_cannot_deposit_to_goal_of_another_family(): void
    {
        $other = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $other->id, 'name' => 'Alheia', 'target' => 100,
            'current' => 0, 'scope' => 'personal', 'is_private' => true,
        ]);

        $this->postJson("/api/mcp/goals/{$goal->id}/deposit", ['amount' => 10], $this->authHeaders())
            ->assertStatus(403);

        $this->assertEquals(0.0, (float) $goal->fresh()->current);
    }

    public function test_deposit_creates_savings_transaction(): void
    {
        $goal = Goal::create([
            'user_id' => $this->user->id, 'name' => 'Minha', 'target' => 100,
            'current' => 0, 'scope' => 'personal', 'is_private' => true,
        ]);

        $this->postJson("/api/mcp/goals/{$goal->id}/deposit", ['amount' => 25], $this->authHeaders())
            ->assertStatus(200);

        $this->assertEquals(25.0, (float) $goal->fresh()->current);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'type' => 'savings',
            'scope' => 'personal',
        ]);
    }
}
