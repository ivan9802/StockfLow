<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Routes de StockFlow.
|
| Chaque route protégée porte un middleware "can:" qui vérifie le droit du profil
| côté serveur. Masquer un bouton dans une vue ne protège rien : c'est ici, et dans
| les Form Requests, que l'accès se décide.
*/

// ---------------------------------------------------------------- Public

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});

Route::view('/legal', 'legal')->name('legal');

// ---------------------------------------------------------------- Session ouverte

Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');

    // Page d'accueil selon le profil : point de vente pour le vendeur, tableau de bord sinon.
    Route::get('/', fn () => redirect()->route(
        auth()->user()->isSalesAgent() && Route::has('sales.create') ? 'sales.create' : 'dashboard'
    ))->name('home');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    require __DIR__.'/modules.php';
});
