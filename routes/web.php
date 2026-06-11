<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use Illuminate\Http\Request;
use App\Models\Family;

// 1. Rota Raiz — landing para visitantes, dashboard para logados
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
})->name('home');

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
})->name('invite.accept');

// 2. Grupo de Rotas Autenticadas
Route::middleware(['auth', 'verified'])->group(function () {

    // --- Rotas do Projeto Financeiro (DuoFund) ---
    Volt::route('dashboard', 'pages.dashboard')->name('dashboard');
    Volt::route('expenses', 'pages.expenses')->name('expenses');
    Volt::route('budget', 'pages.budget')->name('budget');
    Volt::route('goals', 'pages.goals')->name('goals');
    Volt::route('wishlist', 'pages.wishlist')->name('wishlist');
    Volt::route('installments', 'pages.installments')->name('installments');
    Volt::route('report', 'pages.report')->name('report');
    Volt::route('help', 'pages.help')->name('help');

    // --- Rotas de Configuração ---
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    // Lógica de 2FA
    Volt::route('settings/two-factor', 'settings.two-factor')
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