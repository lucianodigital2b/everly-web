<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\GuestPackPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuestPackPurchase>
 */
class GuestPackPurchaseFactory extends Factory
{
    protected $model = GuestPackPurchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => null,
            'rc_event_id' => (string) Str::uuid(),
            'transaction_id' => (string) fake()->unique()->numerify('GPA.####-####-####'),
            'type' => GuestPackPurchase::TYPE_PURCHASE,
            'product_id' => 'everly_guest_pack_intimate',
            'participants_delta' => 30,
            'grants_unlimited' => false,
            'status' => GuestPackPurchase::STATUS_APPLIED,
            'source' => GuestPackPurchase::SOURCE_WEBHOOK,
            'raw' => ['event' => ['type' => GuestPackPurchase::TYPE_PURCHASE]],
        ];
    }
}
