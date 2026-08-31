<?php

use App\Http\Controllers\JoinController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// The guest invite page. Unauthenticated by design — the qr_code_token in the
// URL is the credential, exactly as it is for the API's /api/upload/{token}.
// Same path shape as that endpoint on purpose: the app's deep link, the QR and
// this page then all read as one address with different prefixes.
Route::middleware('throttle:guest-uploads')->group(function (): void {
    Route::get('upload/{token}', [JoinController::class, 'show'])->name('join');
    Route::get('upload/{token}/album', [JoinController::class, 'album'])->name('join.album');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
