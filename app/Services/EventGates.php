<?php

namespace App\Services;

use App\Models\Event;

/**
 * Derives the `gates` block of EventDetail from the event's plan and limits.
 *
 * The client treats these as authoritative — it hides download/slideshow
 * affordances when they're false — but they are not a security boundary on
 * their own; the endpoints re-check the same conditions.
 */
class EventGates
{
    /**
     * @return array{canUpload: bool, canDownload: bool, canSlideshow: bool, whiteLabel: bool}
     */
    public function for(Event $event, ?int $uploadCount = null): array
    {
        $plan = $event->plan;
        $uploadCount ??= $event->photos()->count();
        $cap = $plan->uploadCap();

        $live = $event->isActive() && ! $event->hasExpired();
        $hasRoom = $cap === null || $uploadCount < $cap;

        return [
            'canUpload' => $live && $hasRoom,

            // Paid features stay gated even after the event ends — the owner
            // keeps downloading their gallery, but only if the plan allows it.
            'canDownload' => $plan->allow_download && $event->is_revealed,
            'canSlideshow' => $plan->allow_slideshow && $event->is_revealed,
            'whiteLabel' => $plan->white_label,
        ];
    }
}
