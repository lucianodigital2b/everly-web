<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Event;
use App\Models\Payment;
use App\Services\Pix\PixGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

class CheckoutController extends Controller
{
    public function __construct(private readonly PixGateway $pix) {}

    /**
     * Open (or re-use) a PIX charge for the event.
     */
    public function checkout(Request $request, Event $event): JsonResponse
    {
        $this->authorizeOwner($request, $event);

        $event->load('plan');

        // Nothing to charge for: a free plan is already live, and re-activating
        // a paid event that's already active would just move its expiry.
        if ($event->plan->isFree() || $event->isActive()) {
            return response()->json([
                'status' => Payment::STATUS_PAID,
                'invoiceUrl' => null,
            ]);
        }

        // The client can hit checkout more than once (backgrounded app, retry).
        // Hand back the open invoice instead of opening a second charge.
        $payment = $event->payments()
            ->where('status', Payment::STATUS_PENDING)
            ->latest()
            ->first();

        if (! $payment) {
            $payment = $event->payments()->create([
                'status' => Payment::STATUS_PENDING,
                'amount_cents' => $event->plan->price_cents,
                'currency' => $event->plan->currency,
                'provider' => config('everly.pix.driver'),
            ]);

            $charge = $this->pix->createCharge($payment);

            $payment->fill([
                'status' => $charge->status,
                'invoice_url' => $charge->invoiceUrl,
                'provider_reference' => $charge->reference,
            ])->save();
        }

        return response()->json([
            'status' => $payment->status,
            'invoiceUrl' => $payment->invoice_url,
        ]);
    }

    /**
     * Polled by the client until the charge settles.
     */
    public function payment(Request $request, Event $event): JsonResponse
    {
        $this->authorizeOwner($request, $event);

        $event->load('plan');

        $payment = $event->payments()->latest()->first();

        if (! $payment) {
            return response()->json(['payment' => null]);
        }

        if ($payment->isPending()) {
            $this->reconcile($event, $payment);
        }

        return response()->json([
            'payment' => new PaymentResource($payment),
        ]);
    }

    /**
     * Ask the provider where the charge stands and, if it settled, flip the
     * event live. This is the only place a paid event becomes active.
     */
    private function reconcile(Event $event, Payment $payment): void
    {
        $status = $this->pix->status($payment);

        if ($status === $payment->status) {
            return;
        }

        $payment->status = $status;

        if ($status === Payment::STATUS_PAID) {
            $payment->paid_at = Date::now();
            $payment->save();

            $event->activate($payment->paid_at);

            return;
        }

        $payment->save();
    }

    private function authorizeOwner(Request $request, Event $event): void
    {
        abort_unless($event->user_id === $request->user()->id, 404);
    }
}
