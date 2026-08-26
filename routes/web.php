<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use Illuminate\Http\Request;
use App\Models\Family;

// 1. Rota Raiz — landing page (acessível logado ou não)
Route::view('/', 'welcome')->name('home');

// Política de Privacidade (pública)
Route::view('/privacidade', 'privacy')->name('privacy');

// --- ROTA DE CONVITE (Pública, mas assinada) ---
Route::get('/invite/accept/{family}', function (Request $request, Family $family) {
    if (! $request->hasValidSignature()) {
        abort(403, 'Link de convite inválido ou expirado.');
    }

    // Se o usuário já estiver logado...
    if (Auth::check()) {
        $user = Auth::user();

        // Se a família já estiver cheia
        if ($family->users()->count() >= 2) {
            return redirect()->route('dashboard')->with('notification', [
                'type' => 'error',
                'message' => 'Esta família já está completa.'
            ]);
        }

        // Se ele já tiver uma família, não faz nada
        if ($user->family_id) {
            return redirect()->route('dashboard')->with('notification', [
                'type' => 'error',
                'message' => 'Você já faz parte de uma família.'
            ]);
        }

        // Associa o usuário à família e redireciona
        $user->family_id = $family->id;
        $user->save();

        return redirect()->route('dashboard')->with('notification', [
            'type' => 'success',
            'message' => 'Você entrou na família com sucesso!'
        ]);
    }

    // Se não estiver logado, guarda o convite na sessão
    // Usuário pode fazer login OU criar conta nova
    session(['invite_family_id' => $family->id]);

    // Redireciona para página de escolha (login ou registro)
    return redirect()->route('login')->with('invite_pending', true);
})->middleware('throttle:10,1')->name('invite.accept');

// 2. Grupo de Rotas Autenticadas
Route::middleware(['auth', 'verified'])->group(function () {

    // --- Rotas do Projeto Financeiro (DuoFund) ---
    // URLs em pt-BR; nomes de rota mantidos para não quebrar route() e redirects.
    Volt::route('painel', 'pages.dashboard')->name('dashboard');
    Volt::route('transacoes', 'pages.expenses')->name('expenses');
    Volt::route('orcamento', 'pages.budget')->name('budget');
    Volt::route('metas', 'pages.goals')->name('goals');
    Volt::route('desejos', 'pages.wishlist')->name('wishlist');
    Volt::route('parcelas', 'pages.installments')->name('installments');
    Volt::route('cartoes', 'pages.cards')->name('cards');
    Volt::route('relatorio', 'pages.report')->name('report');
    // Guia estático: navegação por Alpine, sem roundtrip de Livewire
    Route::view('ajuda', 'pages.help')->name('help');

    // --- Rotas de Configuração ---
    Route::redirect('configuracoes', 'configuracoes/perfil');

    Volt::route('configuracoes/perfil', 'settings.profile')->name('profile.edit');
    Volt::route('configuracoes/senha', 'settings.password')->name('user-password.edit');
    Volt::route('configuracoes/aparencia', 'settings.appearance')->name('appearance.edit');

    // Lógica de 2FA
    Volt::route('configuracoes/duas-etapas', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

// Redireciona URLs antigas (inglês) para as novas em pt-BR
foreach ([
    'dashboard' => 'painel',
    'expenses' => 'transacoes',
    'budget' => 'orcamento',
    'goals' => 'metas',
    'wishlist' => 'desejos',
    'installments' => 'parcelas',
    'report' => 'relatorio',
    'help' => 'ajuda',
    'settings' => 'configuracoes',
    'settings/profile' => 'configuracoes/perfil',
    'settings/password' => 'configuracoes/senha',
] as $old => $new) {
    Route::redirect($old, $new);
}