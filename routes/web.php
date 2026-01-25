<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use Illuminate\Http\Request;
use App\Models\Family;

// 1. Rota Raiz
Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

// --- ROTA DE CONVITE (Pública, mas assinada) ---
Route::get('/invite/accept/{family}', function (Request $request, Family $family) {
    if (! $request->hasValidSignature()) {
        abort(403, 'Link de convite inválido ou expirado.');
    }

    // Guarda o ID da família na sessão
    session(['invite_family_id' => $family->id]);

    // Redireciona para o cadastro
    return redirect()->route('register');
})->name('invite.accept');

// 2. Grupo de Rotas Autenticadas
Route::middleware(['auth', 'verified'])->group(function () {

    // --- Rotas do Projeto Financeiro (DuoFund) ---
    Volt::route('dashboard', 'pages.dashboard')->name('dashboard');
    Volt::route('expenses', 'pages.expenses')->name('expenses');
    Volt::route('budget', 'pages.budget')->name('budget');
    Volt::route('goals', 'pages.goals')->name('goals');

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