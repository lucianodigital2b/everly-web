<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 *
 * Note the camelCase `invoiceUrl`: the client reads it that way (unlike the
 * snake_case fields elsewhere), so the key is deliberate, not a slip.
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'invoiceUrl' => $this->invoice_url,
            'created_at' => EventResource::iso($this->created_at),
        ];
    }
}
