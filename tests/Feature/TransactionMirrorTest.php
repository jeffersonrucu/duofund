<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionMirrorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TransactionMirrorTest extends TestCase
{
    use RefreshDatabase;

    private function sharedIncome(User $user, array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'user_id' => $user->id,
            'description' => 'Salário',
            'amount' => 1000,
            'type' => 'income',
            'category' => 'Receita',
            'scope' => 'shared',
            'date' => '2026-07-01',
        ], $overrides));
    }

    public function test_shared_income_via_modal_creates_linked_mirror(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('components.transaction-modal')
            ->set('description', 'Salário')
            ->set('amount', '1.000,00')
            ->set('type', 'income')
            ->set('scope', 'shared')
            ->set('date', '2026-07-01')
            ->set('repetition', 'single')
            ->call('save');

        $income = Transaction::where('type', 'income')->firstOrFail();
        $mirror = Transaction::where('mirror_transaction_id', $income->id)->first();

        $this->assertNotNull($mirror, 'Espelho não foi criado');
        $this->assertSame('expense', $mirror->type);
        $this->assertSame('personal', $mirror->scope);
        $this->assertEquals(1000.0, (float) $mirror->amount);
        $this->assertSame(TransactionMirrorService::DESCRIPTION, $mirror->description);
    }

    public function test_deleting_shared_income_removes_mirror_via_cascade(): void
    {
        $user = User::factory()->create();
        $income = $this->sharedIncome($user);
        $mirror = app(TransactionMirrorService::class)->createFor($income);

        $income->delete();

        $this->assertDatabaseMissing('transactions', ['id' => $mirror->id]);
    }

    public function test_deleting_recurring_series_removes_all_mirrors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $mirrors = app(TransactionMirrorService::class);
        $groupId = (string) Str::uuid();

        foreach (['2026-07-01', '2026-08-01', '2026-09-01'] as $date) {
            $tx = $this->sharedIncome($user, [
                'date' => $date,
                'is_recurring' => true,
                'recurring_group_id' => $groupId,
            ]);
            $mirrors->createFor($tx);
        }

        $this->assertSame(6, Transaction::count());

        $first = Transaction::where('recurring_group_id', $groupId)->orderBy('date')->first();

        Volt::test('pages.expenses')
            ->call('confirmDelete', $first->id)
            ->call('deleteGroup');

        $this->assertSame(0, Transaction::count(), 'Série ou espelhos ficaram órfãos');
    }

    public function test_deleting_recurring_series_forward_keeps_past_occurrences(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $mirrors = app(TransactionMirrorService::class);
        $groupId = (string) Str::uuid();

        foreach (['2026-07-01', '2026-08-01', '2026-09-01'] as $date) {
            $tx = $this->sharedIncome($user, [
                'date' => $date,
                'is_recurring' => true,
                'recurring_group_id' => $groupId,
            ]);
            $mirrors->createFor($tx);
        }

        $this->assertSame(6, Transaction::count());

        // Apaga a partir de agosto (inclusive) — julho deve permanecer.
        $august = Transaction::where('recurring_group_id', $groupId)
            ->whereDate('date', '2026-08-01')->firstOrFail();

        Volt::test('pages.expenses')
            ->call('confirmDelete', $august->id)
            ->set('deleteMode', 'forward')
            ->call('deleteTransaction');

        // Sobra só julho: o original + o espelho = 2 registros.
        $this->assertSame(2, Transaction::count());
        $this->assertSame(2, Transaction::whereDate('date', '2026-07-01')->count());
        $this->assertSame(0, Transaction::whereDate('date', '2026-08-01')->count());
        $this->assertSame(0, Transaction::whereDate('date', '2026-09-01')->count());
    }

    public function test_editing_income_to_personal_removes_mirror(): void
    {
        $user = User::factory()->create();
        $income = $this->sharedIncome($user);
        $mirror = app(TransactionMirrorService::class)->createFor($income);

        $income->update(['scope' => 'personal']);
        app(TransactionMirrorService::class)->reconcile($income);

        $this->assertDatabaseMissing('transactions', ['id' => $mirror->id]);
    }

    public function test_reconcile_updates_mirror_amount(): void
    {
        $user = User::factory()->create();
        $income = $this->sharedIncome($user);
        $mirror = app(TransactionMirrorService::class)->createFor($income);

        $income->update(['amount' => 2500]);
        app(TransactionMirrorService::class)->reconcile($income);

        $this->assertEquals(2500.0, (float) $mirror->fresh()->amount);
    }
}
