<?php

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InvitationAcceptanceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::inertia('docs', 'docs')->name('docs');

Route::middleware('guest')->group(function () {
    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'create'])
        ->name('invitations.accept');

    Route::post('invitations/{token}', [InvitationAcceptanceController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitations.accept.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Deliberately outside the admin group: an impersonated session is signed in as
// a member, so the way back has to be reachable without administrator access.
Route::delete('impersonate', [ImpersonationController::class, 'destroy'])
    ->middleware('auth')
    ->name('impersonate.destroy');

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
