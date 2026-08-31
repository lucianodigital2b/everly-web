<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\GuestPackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestPackController extends Controller
{
    public function __construct(private readonly GuestPackService $guestPacks) {}

    /**
     * Instant, client-driven guest-pack credit. Optional accelerator for the
     * async webhook; shares its idempotent path, so a webhook that lands later
     * for the same transaction won't double-credit.
     */
    public function sync(Request $request, Event $event): JsonResponse
    {
        abort_unless(config('guest_packs.sync_enabled'), 404);
        abort_unless($event->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            // Only known SKUs can credit, and only their mapped amount.
            'product_id' => ['required', 'string', Rule::in(array_keys(config('guest_packs.packs')))],
            'transaction_id' => ['required', 'string', 'max:255'],
        ]);

        $row = $this->guestPacks->syncPurchase(
            $event,
            $request->user(),
            $validated['product_id'],
            $validated['transaction_id'],
        );

        return response()->json([
            'status' => $row->status,
            'event' => new EventResource($event->fresh()->load('plan')),
        ]);
    }
}
