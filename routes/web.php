<?php

use App\Http\Controllers\JoinController;
use App\Http\Controllers\StoreLinkController;
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

// The link for bios and posts: records the visit (utm_* included), then opens
// the App Store. Example: /app?utm_source=instagram&utm_medium=social&utm_campaign=ig_bio
Route::get('app', StoreLinkController::class)->name('store-link');

// Public pages the App Store listing links to (privacy policy and support URL).
Route::view('privacy', 'legal.privacy')->name('privacy');
Route::view('support', 'legal.support')->name('support');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
