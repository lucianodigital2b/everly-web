<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GuestPackController;
use App\Http\Controllers\Api\GuestUploadController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\RevenueCatWebhookController;
use App\Http\Controllers\Api\SocialAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Everly API
|--------------------------------------------------------------------------
|
| Mounted under /api (see bootstrap/app.php), which is what the mobile app's
| EXPO_PUBLIC_API_URL points at. Contract: BACKEND.md.
|
*/

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::post('google/callback', [SocialAuthController::class, 'google']);
    Route::post('apple/callback', [SocialAuthController::class, 'apple']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
        Route::delete('user', [AuthController::class, 'destroy']);
    });
});

// Guest QR upload. Deliberately unauthenticated: the event's qr_code_token is
// the credential. Throttled, since anyone with the link can reach it.
Route::middleware('throttle:guest-uploads')->group(function (): void {
    Route::get('upload/{token}', [GuestUploadController::class, 'show']);
    Route::post('upload/{token}', [GuestUploadController::class, 'store']);
    // The requesting guest's own photos only — never the rest of the album.
    Route::get('upload/{token}/photos', [GuestUploadController::class, 'mine']);
});

// RevenueCat guest-pack webhook. Unauthenticated in the Sanctum sense — the
// controller checks the shared secret in the Authorization header itself.
Route::post('webhooks/revenuecat', RevenueCatWebhookController::class)
    ->middleware('throttle:revenuecat-webhook');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('plans', [PlanController::class, 'index']);

    Route::apiResource('events', EventController::class)->except('update');
    Route::patch('events/{event}', [EventController::class, 'update']);
    Route::get('events/{event}/photos', [EventController::class, 'photos']);

    Route::post('events/{event}/checkout', [CheckoutController::class, 'checkout']);
    Route::get('events/{event}/payment', [CheckoutController::class, 'payment']);

    Route::post('events/{event}/guest-packs/sync', [GuestPackController::class, 'sync']);

    Route::post('referrals/redeem', [ReferralController::class, 'redeem']);
});
