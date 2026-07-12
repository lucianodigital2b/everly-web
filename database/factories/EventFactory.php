<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'name' => fake()->words(2, true),
            'title' => null,
            'status' => Event::STATUS_PENDING_PAYMENT,
            'public' => false,
            'reveal_time' => Event::REVEAL_INSTANT,
            'filter' => 'none',
            'is_revealed' => false,
        ];
    }

    /**
     * A live event: activated now, expiring in the future.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => Event::STATUS_ACTIVE,
            'activated_at' => Date::now(),
            'expires_at' => Date::now()->addDays(7),
            'is_revealed' => true,
        ]);
    }

    /**
     * Live but already past its end — uploads closed, "End of Event" reveal due.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => Event::STATUS_ACTIVE,
            'activated_at' => Date::now()->subDays(8),
            'expires_at' => Date::now()->subDay(),
        ]);
    }
}
