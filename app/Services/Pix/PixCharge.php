<?php

namespace App\Services\Pix;

/**
 * A charge as returned by a PIX provider.
 */
readonly class PixCharge
{
    public function __construct(
        public string $status,
        public string $invoiceUrl,
        public string $reference,
    ) {}
}
