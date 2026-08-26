<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * O limite de uma categoria muda como uma recorrência: só num mês, daqui
 * em diante ou em todos. Meses passados nunca são reescritos por engano.
 */
class CategoryMonthlyLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->category = Category::create([
            'user_id' => $this->user->id, 'name' => 'Lazer', 'limit' => 300, 'scope' => 'personal',
        ]);
    }

    private function limitIn(string $month): float
    {
        return $this->category->fresh()->limitFor(Carbon::parse($month));
    }

    public function test_from_month_on_keeps_previous_months(): void
    {
        $this->category->setLimitFrom(Carbon::parse('2026-09-01'), 500);

        $this->assertSame(300.0, $this->limitIn('2026-08-15'));
        $this->assertSame(500.0, $this->limitIn('2026-09-15'));
        $this->assertSame(500.0, $this->limitIn('2027-03-01'));
    }

    public function test_from_month_on_replaces_later_changes(): void
    {
        $this->category->setLimitFrom(Carbon::parse('2026-11-01'), 900);
        $this->category->setLimitFrom(Carbon::parse('2026-09-01'), 500);

        $this->assertSame(500.0, $this->limitIn('2026-12-01'));
    }

    public function test_single_month_reverts_on_the_next_one(): void
    {
        $this->category->setLimitForMonth(Carbon::parse('2026-09-01'), 100);

        $this->assertSame(300.0, $this->limitIn('2026-08-01'));
        $this->assertSame(100.0, $this->limitIn('2026-09-01'));
        $this->assertSame(300.0, $this->limitIn('2026-10-01'));
    }

    public function test_single_month_reverts_to_the_value_in_force_not_the_base(): void
    {
        $this->category->setLimitFrom(Carbon::parse('2026-07-01'), 450);
        $this->category->setLimitForMonth(Carbon::parse('2026-09-01'), 100);

        $this->assertSame(450.0, $this->limitIn('2026-10-01'));
    }

    public function test_all_months_wipes_history(): void
    {
        $this->category->setLimitFrom(Carbon::parse('2026-09-01'), 500);
        $this->category->setLimitForAll(800);

        $this->assertSame(800.0, $this->limitIn('2026-05-01'));
        $this->assertSame(800.0, $this->limitIn('2026-12-01'));
        $this->assertSame(0, $this->category->limits()->count());
    }

    public function test_budget_page_shows_the_limit_in_force_for_the_month(): void
    {
        $this->category->setLimitFrom(Carbon::parse('2026-09-01'), 500);

        $september = Volt::test('pages.budget')->set('currentMonth', '2026-09-01')->get('data');
        $august = Volt::test('pages.budget')->set('currentMonth', '2026-08-01')->get('data');

        $this->assertEquals(500.0, $september['categories']->first()->limit);
        $this->assertEquals(500.0, $september['totalBudget']);
        $this->assertEquals(300.0, $august['categories']->first()->limit);
    }

    public function test_deleting_the_category_removes_its_limit_history(): void
    {
        $this->category->setLimitFrom(Carbon::parse('2026-09-01'), 500);

        $this->category->delete();

        $this->assertDatabaseCount('category_limits', 0);
    }

    public function test_modal_edit_applies_only_to_the_chosen_month(): void
    {
        session(['current_month' => '2026-09-01']);

        Volt::test('components.category-modal')
            ->dispatch('edit-category', $this->category->id, 'Lazer', '300', 'personal')
            ->set('limit', '100,00')
            ->set('applyTo', 'month')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(100.0, $this->limitIn('2026-09-01'));
        $this->assertSame(300.0, $this->limitIn('2026-10-01'));
        $this->assertSame(300.0, $this->limitIn('2026-08-01'));
    }

    public function test_modal_edit_defaults_to_from_this_month_on(): void
    {
        session(['current_month' => '2026-09-01']);

        Volt::test('components.category-modal')
            ->dispatch('edit-category', $this->category->id, 'Lazer', '300', 'personal')
            ->set('limit', '500,00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(300.0, $this->limitIn('2026-08-01'));
        $this->assertSame(500.0, $this->limitIn('2026-09-01'));
        $this->assertSame(500.0, $this->limitIn('2027-01-01'));
    }

    public function test_modal_edit_for_all_months_rewrites_the_base(): void
    {
        session(['current_month' => '2026-09-01']);

        Volt::test('components.category-modal')
            ->dispatch('edit-category', $this->category->id, 'Lazer', '300', 'personal')
            ->set('limit', '800,00')
            ->set('applyTo', 'all')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(800.0, (float) $this->category->fresh()->limit);
        $this->assertSame(800.0, $this->limitIn('2026-01-01'));
    }
}
