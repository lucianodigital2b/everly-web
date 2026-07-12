<?php

namespace App\Services\Pix;

use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Local-development driver. It never calls out to a provider: it mints a stub
 * invoice URL and reports the charge as paid once it's a few seconds old, so
 * the client's "poll until paid" loop resolves and the whole paid-plan flow —
 * checkout, poll, activation — is exercisable without a provider account.
 *
 * Never select this in production; PIX_DRIVER is what picks it.
 */
class FakePixGateway implements PixGateway
{
    /**
     * How long a fake charge stays pending, so the client actually sees the
     * pending state rather than an instantly-paid one.
     */
    private const SETTLES_AFTER_SECONDS = 5;

    public function createCharge(Payment $payment): PixCharge
    {
        $reference = 'fake_'.Str::lower(Str::random(20));

        return new PixCharge(
            status: Payment::STATUS_PENDING,
            invoiceUrl: url('/pix/fake/'.$reference),
            reference: $reference,
        );
    }

    public function status(Payment $payment): string
    {
        if ($payment->isPaid()) {
            return Payment::STATUS_PAID;
        }

        return $payment->created_at?->addSeconds(self::SETTLES_AFTER_SECONDS)->isPast()
            ? Payment::STATUS_PAID
            : Payment::STATUS_PENDING;
    }
}
