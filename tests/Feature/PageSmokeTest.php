<?php

use App\Models\User;

/**
 * Renderiza toda página que serve HTML. Guarda principal contra ícone
 * inexistente: com SVG server-side, um `<x-lucide-nao-existe>` é
 * InvalidArgumentException (500), não um espaço em branco silencioso.
 */
it('renderiza as páginas públicas', function (string $rota) {
    $this->get($rota)->assertOk();
})->with([
    'landing' => '/',
    'privacidade' => '/privacidade',
    'login' => '/login',
    'registro' => '/register',
]);

it('renderiza as páginas autenticadas', function (string $rota) {
    $this->actingAs(User::factory()->create())
        ->get($rota)
        ->assertOk();
})->with([
    'painel' => '/painel',
    'transacoes' => '/transacoes',
    'orcamento' => '/orcamento',
    'metas' => '/metas',
    'desejos' => '/desejos',
    'parcelas' => '/parcelas',
    'cartoes' => '/cartoes',
    'relatorio' => '/relatorio',
    'ajuda' => '/ajuda',
    'perfil' => '/configuracoes/perfil',
    'senha' => '/configuracoes/senha',
    'aparencia' => '/configuracoes/aparencia',
]);
