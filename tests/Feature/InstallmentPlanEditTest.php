<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

/** Cria um parcelamento mensal; $offset desloca o mês da 1ª parcela. */
function criaParcelamento(User $user, int $count, float $amount = 100, int $offset = 0): string
{
    $groupId = (string) Str::uuid();
    $start   = now()->startOfMonth()->addMonths($offset)->addDays(4);

    for ($i = 0; $i < $count; $i++) {
        Transaction::create([
            'user_id' => $user->id,
            'description' => 'Sofá',
            'amount' => $amount,
            'type' => 'expense',
            'category' => 'Casa',
            'scope' => 'personal',
            'date' => $start->copy()->addMonths($i),
            'is_installment' => true,
            'installment_current' => $i + 1,
            'installment_count' => $count,
            'recurring_group_id' => $groupId,
        ]);
    }

    return $groupId;
}

function paginaParcelas()
{
    return Volt::test('pages.installments')
        ->set('view', 'personal')
        ->set('currentMonth', now()->startOfMonth()->format('Y-m-d'));
}

it('abre a edição com os dados do parcelamento', function () {
    $user = User::factory()->create();
    $group = criaParcelamento($user, 12);
    $this->actingAs($user);

    paginaParcelas()
        ->call('openEdit', $group)
        ->assertSet('editInstallments', 12)
        ->assertSet('editDescription', 'Sofá')
        ->assertSet('editCategory', 'Casa')
        ->assertSet('editDueDay', 5)
        ->assertSet('editPaid', 0)
        ->assertSee('Editar parcelamento')
        ->assertSee('Quantidade de parcelas');
});

it('preenche as parcelas já pagas pelo mês em exibição', function () {
    $user = User::factory()->create();
    $group = criaParcelamento($user, 6, 100, -2);
    $this->actingAs($user);

    paginaParcelas()
        ->call('openEdit', $group)
        ->assertSet('editPaid', 2);
});

it('desloca a série ao corrigir quantas parcelas já foram pagas', function () {
    $user = User::factory()->create();
    $group = criaParcelamento($user, 6);
    $this->actingAs($user);

    paginaParcelas()
        ->call('openEdit', $group)
        ->set('editPaid', 3)
        ->call('saveEdit');

    $items = Transaction::where('recurring_group_id', $group)->orderBy('date')->get();
    $mesAtual = $items->first(fn ($tx) => $tx->date->isSameMonth(now()));

    expect($items)->toHaveCount(6);
    expect($items->first()->date->format('Y-m'))->toBe(now()->subMonths(3)->format('Y-m'));
    expect($mesAtual->installment_current)->toBe(4);
});

it('limita as parcelas pagas à quantidade do parcelamento', function () {
    $user = User::factory()->create();
    $group = criaParcelamento($user, 4);
    $this->actingAs($user);

    paginaParcelas()
        ->call('openEdit', $group)
        ->set('editPaid', 10)
        ->call('saveEdit');

    $items = Transaction::where('recurring_group_id', $group)->orderBy('date')->get();

    // A última parcela é a do mês em exibição; as 3 anteriores ficam no passado
    expect($items->last()->date->format('Y-m'))->toBe(now()->format('Y-m'));
    expect($items->last()->installment_current)->toBe(4);
});

it('diminui a quantidade removendo as últimas parcelas', function () {
    $user = User::factory()->create();
    $group = criaParcelamento($user, 12);
    $this->actingAs($user);

    paginaParcelas()
        ->call('openEdit', $group)
        ->set('editInstallments', 4)
        ->call('saveEdit')
        ->assertSet('showEditModal', false);

    $items = Transaction::where('recurring_group_id', $group)->orderBy('date')->get();

    expect($items)->toHaveCount(4);
    expect($items->pluck('installment_current')->all())->toBe([1, 2, 3, 4]);
    expect($items->pluck('installment_count')->unique()->all())->toBe([4]);
    expect($items->last()->date->format('Y-m'))->toBe(now()->startOfMonth()->addMonths(3)->format('Y-m'));
});

it('aumenta a quantidade criando as parcelas seguintes', function () {
    $user = User::factory()->create();
    $group = criaParcelamento($user, 3);
    $this->actingAs($user);

    paginaParcelas()
        ->call('openEdit', $group)
        ->set('editInstallments', 5)
        ->call('saveEdit');

    $items = Transaction::where('recurring_group_id', $group)->orderBy('date')->get();

    expect($items)->toHaveCount(5);
    expect($items->pluck('installment_current')->all())->toBe([1, 2, 3, 4, 5]);
    expect($items->last()->date->format('Y-m-d'))
        ->toBe(now()->startOfMonth()->addMonths(4)->addDays(4)->format('Y-m-d'));
});

it('aplica valor, descrição e vencimento em todas as parcelas', function () {
    $user = User::factory()->create();
    $group = criaParcelamento($user, 3);
    $this->actingAs($user);

    paginaParcelas()
        ->call('openEdit', $group)
        ->set('editDescription', 'Geladeira')
        ->set('editAmount', '250,50')
        ->set('editDueDay', 31)
        ->call('saveEdit');

    $items = Transaction::where('recurring_group_id', $group)->get();

    expect($items->pluck('description')->unique()->all())->toBe(['Geladeira']);
    expect($items->pluck('amount')->map(fn ($v) => (float) $v)->unique()->all())->toBe([250.50]);

    // Dia 31 é limitado ao último dia do mês
    $items->each(fn ($tx) => expect($tx->date->day)->toBe(min(31, $tx->date->daysInMonth)));
});

it('exclui todas as parcelas do parcelamento', function () {
    $user = User::factory()->create();
    $group = criaParcelamento($user, 6);
    $this->actingAs($user);

    paginaParcelas()
        ->call('confirmDelete', $group)
        ->assertSet('showDeleteModal', true)
        ->call('deletePlan', 'all')
        ->assertSet('showDeleteModal', false);

    expect(Transaction::where('recurring_group_id', $group)->count())->toBe(0);
});

it('exclui as parcelas deste mês em diante e renumera as pagas', function () {
    $user = User::factory()->create();
    $group = criaParcelamento($user, 6);
    $this->actingAs($user);

    Volt::test('pages.installments')
        ->set('view', 'personal')
        ->set('currentMonth', now()->startOfMonth()->addMonths(2)->format('Y-m-d'))
        ->call('confirmDelete', $group)
        ->call('deletePlan', 'forward');

    $items = Transaction::where('recurring_group_id', $group)->orderBy('date')->get();

    expect($items)->toHaveCount(2);
    expect($items->pluck('installment_count')->unique()->all())->toBe([2]);
});

it('não deixa editar parcelamento de outra pessoa', function () {
    $dono = User::factory()->create();
    $group = criaParcelamento($dono, 4);
    $intruso = User::factory()->create();
    $this->actingAs($intruso);

    paginaParcelas()
        ->call('openEdit', $group)
        ->assertSet('showEditModal', false);

    expect(Transaction::where('recurring_group_id', $group)->count())->toBe(4);
});
