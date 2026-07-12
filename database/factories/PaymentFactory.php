<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => 4900,
            'currency' => 'brl',
            'invoice_url' => fake()->url(),
            'provider' => 'fake',
            'provider_reference' => 'fake_'.fake()->uuid(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => Payment::STATUS_PAID,
            'paid_at' => Date::now(),
        ]);
    }
}
