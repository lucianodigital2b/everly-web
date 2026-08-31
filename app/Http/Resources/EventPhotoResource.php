<?php

namespace App\Http\Resources;

use App\Models\EventPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventPhoto
 */
class EventPhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'media_type' => $this->media_type,
            'duration_seconds' => $this->duration_seconds,
            'width' => $this->width,
            'height' => $this->height,
            // Who uploaded it. Null when the guest never named themselves —
            // the gallery then credits the tile to a plain "Guest".
            'guest_name' => $this->guest?->name,
            'created_at' => EventResource::iso($this->created_at),
        ];
    }
}
