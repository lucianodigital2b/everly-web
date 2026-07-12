<?php

namespace App\Http\Resources;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'qr_code_token' => $this->qr_code_token,
            'status' => $this->status,
            'public' => $this->public,
            'name' => $this->name,
            'title' => $this->title ?? $this->name,
            'event_date' => self::iso($this->event_date),
            'shot_limit' => $this->shot_limit,
            'participant_limit' => $this->participant_limit,
            'reveal_time' => $this->reveal_time,
            'reveal_at' => self::iso($this->reveal_at),
            'filter' => $this->filter,
            'tier' => $this->tier,
            'is_revealed' => $this->is_revealed,
            'cover_image_url' => $this->cover_image_url,
            'activated_at' => self::iso($this->activated_at),
            'expires_at' => self::iso($this->expires_at),
            'created_at' => self::iso($this->created_at),
            'updated_at' => self::iso($this->updated_at),

            // The client always expects the plan inlined.
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }

    /**
     * The app configures Date::use(CarbonImmutable), so casts hand back
     * CarbonImmutable rather than Illuminate\Support\Carbon — accept the
     * interface both satisfy.
     */
    public static function iso(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601ZuluString();
    }
}
