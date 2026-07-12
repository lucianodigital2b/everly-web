<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'price_cents' => 4900,
            'currency' => 'brl',
            'max_uploads' => 300,
            'max_participants' => 30,
            'allow_download' => true,
            'allow_slideshow' => false,
            'white_label' => false,
            'duration_days' => 7,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Free',
            'price_cents' => 0,
            'max_uploads' => 50,
            'max_participants' => 5,
            'allow_download' => false,
            'allow_slideshow' => false,
            'duration_days' => 1,
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Grand',
            'price_cents' => 19900,
            'max_uploads' => null,
            'max_participants' => null,
            'allow_download' => true,
            'allow_slideshow' => true,
            'white_label' => true,
            'duration_days' => 90,
        ]);
    }
}
