<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'max_uploads' => $this->max_uploads,
            'max_participants' => $this->max_participants,
            'allow_download' => $this->allow_download,
            'allow_slideshow' => $this->allow_slideshow,
            'white_label' => $this->white_label,
            'duration_days' => $this->duration_days,
        ];
    }
}
