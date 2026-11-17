<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * The four reference tiers from the product spec. Seeded idempotently by
     * name so re-running the seeder updates pricing instead of duplicating
     * plans that live events already point at.
     *
     * Only Free is still handed out: it is the base plan every event is
     * created on (Plan::base), and guest capacity is sold separately as
     * guest packs. So Free carries no photo cap and no locked features — a
     * 150-guest event lives on it too. The paid three are kept for events
     * created before that change.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'price_cents' => 0,
                'currency' => 'brl',
                'max_participants' => 5,
                // null = unlimited; each guest is held to the event's shot limit.
                'max_uploads' => null,
                'allow_download' => true,
                'allow_slideshow' => true,
                'white_label' => false,
                'duration_days' => 1,
            ],
            [
                'name' => 'Intimate',
                'price_cents' => 4900,
                'currency' => 'brl',
                'max_participants' => 30,
                'max_uploads' => 300,
                'allow_download' => true,
                'allow_slideshow' => false,
                'white_label' => false,
                'duration_days' => 7,
            ],
            [
                'name' => 'Celebration',
                'price_cents' => 9900,
                'currency' => 'brl',
                'max_participants' => 100,
                'max_uploads' => 1000,
                'allow_download' => true,
                'allow_slideshow' => true,
                'white_label' => false,
                'duration_days' => 30,
            ],
            [
                'name' => 'Grand',
                'price_cents' => 19900,
                'currency' => 'brl',
                // null = unlimited
                'max_participants' => null,
                'max_uploads' => null,
                'allow_download' => true,
                'allow_slideshow' => true,
                'white_label' => true,
                'duration_days' => 90,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
