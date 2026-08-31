<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GuestPackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives RevenueCat webhooks for one-time guest-pack purchases and refunds.
 *
 * Unauthenticated in the Sanctum sense: RevenueCat proves itself with a shared
 * secret in the Authorization header (set on the RevenueCat webhook screen).
 * The endpoint fails closed when that secret isn't configured.
 */
class RevenueCatWebhookController extends Controller
{
    public function __construct(private readonly GuestPackService $guestPacks) {}

    public function __invoke(Request $request): JsonResponse
    {
        $expected = config('services.revenuecat.webhook_auth');

        // Fail closed: never process a payload when the secret is unset.
        if (empty($expected) || ! hash_equals((string) $expected, (string) $request->header('Authorization'))) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $row = $this->guestPacks->handleWebhookEvent($request->all());

        // 200 on everything we accepted — including no-ops we've recorded — so
        // RevenueCat stops retrying. A retry (duplicate event id) returns null.
        if ($row === null) {
            return response()->json(['status' => 'duplicate']);
        }

        Log::info('guest-pack webhook processed', [
            'rc_event_id' => $row->rc_event_id,
            'status' => $row->status,
            'event_id' => $row->event_id,
        ]);

        return response()->json(['status' => $row->status]);
    }
}
