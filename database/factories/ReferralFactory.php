<?php

namespace Database\Factories;

use App\Models\Referral;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'user_id' => null,
            'reward' => ['type' => 'free_event', 'plan' => 'Intimate'],
            'redeemed_at' => null,
        ];
    }

    public function redeemed(): static
    {
        return $this->state(fn (): array => [
            'redeemed_at' => Date::now(),
        ]);
    }
}
