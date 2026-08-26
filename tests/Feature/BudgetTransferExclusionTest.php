<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MonthlySummaryService;
use App\Services\TransactionMirrorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A transferência para a conta conjunta sai do caixa pessoal (conta em
 * saídas/saldo) mas não é gasto orçado — senão o % do orçamento e o
 * ranking de categorias ficam sem sentido.
 */
class BudgetTransferExclusionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Carbon $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->day = now()->startOfMonth()->addDay();
        $this->actingAs($this->user);
    }

    private function expense(string $category, float $amount): Transaction
    {
        return Transaction::create([
            'user_id' => $this->user->id,
            'description' => "Gasto em {$category}",
            'amount' => $amount,
            'type' => 'expense',
            'category' => $category,
            'scope' => 'personal',
            'date' => $this->day,
        ]);
    }

    /** Cria a receita shared e devolve o espelho personal gerado. */
    private function transferToShared(float $amount): Transaction
    {
        $income = Transaction::create([
            'user_id' => $this->user->id,
            'description' => 'Parte minha',
            'amount' => $amount,
            'type' => 'income',
            'category' => 'Receita',
            'scope' => 'shared',
            'date' => $this->day,
        ]);

        return app(TransactionMirrorService::class)->createFor($income);
    }

    public function test_summary_separates_transfer_but_keeps_it_in_expense_and_balance(): void
    {
        $this->expense('Mercado', 300);
        $this->transferToShared(1000);

        $totals = app(MonthlySummaryService::class)->for($this->user, 'personal', $this->day);

        $this->assertEquals(1300.0, $totals['expense']);
        $this->assertEquals(1000.0, $totals['transfer']);
        $this->assertEquals(-1300.0, $totals['balance']);
    }

    public function test_dashboard_budget_percentage_ignores_transfer(): void
    {
        Category::create(['user_id' => $this->user->id, 'name' => 'Mercado', 'limit' => 1000, 'scope' => 'personal']);
        $this->expense('Mercado', 500);
        $this->transferToShared(6000);

        $summary = Volt::test('pages.dashboard')->get('summary');

        $this->assertSame(50.0, (float) $summary['pctUsed']);
        $this->assertEquals(6500.0, $summary['expense']);
        $this->assertEquals(6000.0, $summary['transfer']);
    }

    public function test_mcp_summary_budget_percentage_ignores_transfer(): void
    {
        config([
            'services.duofund_mcp.token' => 'test-token',
            'services.duofund_mcp.user_id' => $this->user->id,
        ]);
        Category::create(['user_id' => $this->user->id, 'name' => 'Mercado', 'limit' => 1000, 'scope' => 'personal']);
        $this->expense('Mercado', 500);
        $this->transferToShared(6000);

        $this->getJson('/api/mcp/summary?scope=personal', ['Authorization' => 'Bearer test-token'])
            ->assertOk()
            ->assertJsonPath('expenses', 6500.0)
            ->assertJsonPath('transfer', 6000.0)
            ->assertJsonPath('budget_used_pct', 50.0);
    }

    public function test_report_category_ranking_excludes_transfer(): void
    {
        $this->expense('Mercado', 300);
        $this->transferToShared(1000);

        $report = Volt::test('pages.report')->get('report');

        $this->assertSame(['Mercado'], $report['catUsage']->pluck('category')->all());
        $this->assertEquals(1300.0, $report['expense']);
    }
}
