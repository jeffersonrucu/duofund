<?php

use App\Models\User;
use App\Models\WishlistItem;
use Livewire\Volt\Volt;

it('abre o simulador com a data de hoje', function () {
    $user = User::factory()->create();
    $item = WishlistItem::create([
        'user_id' => $user->id, 'name' => 'Notebook', 'price' => 4500,
        'priority' => 'high', 'scope' => 'personal', 'status' => 'pending',
    ]);

    $this->actingAs($user);

    Volt::test('pages.wishlist')
        ->call('openSim', $item->id)
        ->assertSet('simItemId', $item->id)
        ->assertSet('simDate', now()->format('Y-m-d'));
});
