<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Uma assinatura relançada à mão fica fora da série recorrente. Ao excluir
 * esse avulso, o app deve oferecer remover também as ocorrências futuras da
 * série igual — senão sobram meses de lançamento "fantasma".
 */
class OrphanRecurringDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->groupId = (string) Str::uuid();

        // Série: maio e junho (passado), setembro em diante (futuro)
        foreach (['2026-05-01', '2026-06-01', '2026-09-01', '2026-10-01', '2026-11-01'] as $date) {
            $this->crunchyroll($date, $this->groupId);
        }
    }

    private function crunchyroll(string $date, ?string $groupId): Transaction
    {
        return Transaction::create([
            'user_id' => $this->user->id,
            'description' => 'Crunchyroll',
            'amount' => 19.99,
            'type' => 'expense',
            'category' => 'Assinaturas',
            'scope' => 'personal',
            'date' => $date,
            'is_recurring' => $groupId !== null,
            'recurring_group_id' => $groupId,
        ]);
    }

    public function test_orphan_with_matching_future_series_opens_the_recurring_modal(): void
    {
        $orphan = $this->crunchyroll('2026-08-01', null);

        Volt::test('pages.expenses')
            ->call('confirmDelete', $orphan->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('isRecurringDelete', true)
            ->assertSet('relatedGroupId', $this->groupId);
    }

    public function test_forward_delete_removes_orphan_and_future_series_but_keeps_past(): void
    {
        $orphan = $this->crunchyroll('2026-08-01', null);

        Volt::test('pages.expenses')
            ->call('confirmDelete', $orphan->id)
            ->set('deleteMode', 'forward')
            ->call('deleteTransaction');

        $this->assertDatabaseMissing('transactions', ['id' => $orphan->id]);
        $this->assertSame(
            ['2026-05-01', '2026-06-01'],
            Transaction::where('description', 'Crunchyroll')->orderBy('date')->pluck('date')->map->toDateString()->all(),
        );
    }

    public function test_single_delete_removes_only_the_orphan(): void
    {
        $orphan = $this->crunchyroll('2026-08-01', null);

        Volt::test('pages.expenses')
            ->call('confirmDelete', $orphan->id)
            ->set('deleteMode', 'single')
            ->call('deleteTransaction');

        $this->assertDatabaseMissing('transactions', ['id' => $orphan->id]);
        $this->assertSame(5, Transaction::where('recurring_group_id', $this->groupId)->count());
    }

    public function test_orphan_without_similar_series_is_deleted_directly(): void
    {
        $orphan = Transaction::create([
            'user_id' => $this->user->id,
            'description' => 'Pizza',
            'amount' => 45,
            'type' => 'expense',
            'category' => 'Restaurantes',
            'scope' => 'personal',
            'date' => '2026-08-01',
        ]);

        Volt::test('pages.expenses')
            ->call('confirmDelete', $orphan->id)
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('transactions', ['id' => $orphan->id]);
        $this->assertSame(5, Transaction::where('recurring_group_id', $this->groupId)->count());
    }
}
