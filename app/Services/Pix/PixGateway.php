<?php

namespace App\Services\Pix;

use App\Models\Payment;

/**
 * The seam between checkout and whichever PIX provider is wired up.
 *
 * Implement this against a real provider (Mercado Pago, Asaas, Pagar.me, …) and
 * bind it in AppServiceProvider; nothing in the HTTP layer needs to change.
 */
interface PixGateway
{
    /**
     * Open a charge and return the invoice the guest pays.
     */
    public function createCharge(Payment $payment): PixCharge;

    /**
     * Current provider-side status of a charge: pending | paid | failed.
     *
     * Polled by GET /events/{id}/payment. A production driver would also accept
     * a provider webhook and mark the payment paid there; this poll is the
     * fallback the client already relies on.
     */
    public function status(Payment $payment): string;
}
