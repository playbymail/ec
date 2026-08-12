<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GameController;
use App\Http\Controllers\Admin\GameSeatController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ImpersonationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::put('invitations/{invitation}', [InvitationController::class, 'update'])->name('invitations.update');
    Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::post('games', [GameController::class, 'store'])->name('games.store');
    Route::get('games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::put('games/{game}', [GameController::class, 'update'])->name('games.update');
    Route::delete('games/{game}', [GameController::class, 'destroy'])->name('games.destroy');

    // Scoped bindings keep a seat from one game out of another game's URLs.
    Route::scopeBindings()->group(function () {
        Route::post('games/{game}/seats', [GameSeatController::class, 'store'])->name('games.seats.store');
        Route::put('games/{game}/seats/{seat}', [GameSeatController::class, 'update'])->name('games.seats.update');
    });

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])->name('users.impersonate');

    Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::delete('sessions', [SessionController::class, 'destroyAll'])->name('sessions.destroy-all');
    Route::delete('sessions/{digest}', [SessionController::class, 'destroy'])->name('sessions.destroy');
});
