<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventPhoto>
 */
class EventPhotoFactory extends Factory
{
    protected $model = EventPhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'events/'.fake()->numberBetween(1, 9).'/'.Str::uuid();

        return [
            'event_id' => Event::factory(),
            'guest_id' => null,
            'path' => $key.'.jpg',
            'thumbnail_path' => $key.'_thumb.jpg',
            'media_type' => EventPhoto::TYPE_IMAGE,
            'width' => 900,
            'height' => 675,
        ];
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'media_type' => EventPhoto::TYPE_VIDEO,
            'thumbnail_path' => null,
            'duration_seconds' => 12,
        ]);
    }
}
