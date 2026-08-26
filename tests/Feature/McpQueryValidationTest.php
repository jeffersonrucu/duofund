<?php

use App\Models\User;

/**
 * `scope` e `month` chegam pela query string. Antes, `month` inválido
 * estourava no Carbon::parse (500) e `scope` desconhecido caía
 * silenciosamente na visão do casal.
 */
beforeEach(function () {
    $user = User::factory()->create();

    config([
        'services.duofund_mcp.token' => 'test-token',
        'services.duofund_mcp.user_id' => $user->id,
    ]);

    $this->headers = ['Authorization' => 'Bearer test-token'];
});

dataset('endpoints com mês', [
    '/api/mcp/summary',
    '/api/mcp/categories',
    '/api/mcp/transactions',
]);

dataset('endpoints com escopo', [
    '/api/mcp/summary',
    '/api/mcp/categories',
    '/api/mcp/goals',
    '/api/mcp/transactions',
    '/api/mcp/wishlist',
]);

it('recusa month inválido com 422', function (string $endpoint) {
    $this->getJson("{$endpoint}?month=abc", $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('month');
})->with('endpoints com mês');

it('recusa mês irreal com 422', function () {
    $this->getJson('/api/mcp/summary?month=2026-13', $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('month');
});

it('aceita month válido', function (string $endpoint) {
    $this->getJson("{$endpoint}?month=2026-03", $this->headers)
        ->assertStatus(200);
})->with('endpoints com mês');

it('recusa scope inválido com 422', function (string $endpoint) {
    $this->getJson("{$endpoint}?scope=admin", $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('scope');
})->with('endpoints com escopo');

it('recusa type inválido em transactions', function () {
    $this->getJson('/api/mcp/transactions?type=qualquer', $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('type');
});

it('devolve erro de validação em JSON mesmo sem header Accept', function () {
    $response = $this->get('/api/mcp/summary?month=abc', $this->headers);

    $response->assertStatus(422);
    expect($response->headers->get('content-type'))->toContain('application/json');
});
