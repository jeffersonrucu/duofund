<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Categorias duplicadas por grafia ("Restaurante" x "Restaurantes",
 * "lazer" x "Lazer") foi o que bagunçou o orçamento em produção.
 */
class CategoryDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function category(string $name, string $scope = 'personal'): Category
    {
        return Category::create(['user_id' => $this->user->id, 'name' => $name, 'limit' => 0, 'scope' => $scope]);
    }

    private function saveExpense(string $category): \Livewire\Features\SupportTesting\Testable
    {
        return Volt::test('components.transaction-modal')
            ->set('description', 'Compra')
            ->set('amount', '20,00')
            ->set('type', 'expense')
            ->set('scope', 'personal')
            ->set('category', $category)
            ->set('date', now()->startOfMonth()->addDay()->toDateString())
            ->set('repetition', 'single')
            ->call('save');
    }

    public function test_transaction_modal_reuses_existing_category_ignoring_case(): void
    {
        $this->category('Restaurantes');

        $this->saveExpense(' restaurantes ')->assertHasNoErrors();

        $this->assertSame(1, Category::count());
        $this->assertSame('Restaurantes', Transaction::firstOrFail()->category);
    }

    public function test_transaction_modal_rejects_near_duplicate_category(): void
    {
        $this->category('Restaurantes');

        $this->saveExpense('Restaurante')->assertHasErrors('category');

        $this->assertSame(1, Category::count());
        $this->assertSame(0, Transaction::count());
    }

    public function test_transaction_modal_still_creates_a_genuinely_new_category(): void
    {
        $this->category('Restaurantes');

        $this->saveExpense('Transporte')->assertHasNoErrors();

        $this->assertSame(2, Category::count());
    }

    public function test_category_modal_rejects_duplicate_name_in_same_scope(): void
    {
        $this->category('Lazer');

        Volt::test('components.category-modal')
            ->set('name', 'lazer')
            ->set('limit', '100')
            ->set('scope', 'personal')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame(1, Category::count());
    }

    public function test_category_modal_allows_same_name_in_other_scope(): void
    {
        $this->category('Lazer');

        Volt::test('components.category-modal')
            ->set('name', 'Lazer')
            ->set('limit', '100')
            ->set('scope', 'shared')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Category::count());
    }
}
