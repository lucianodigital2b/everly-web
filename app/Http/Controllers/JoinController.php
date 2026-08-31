<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\View\View;

/**
 * The guest invite flow — the web half of the QR flow, in two screens.
 *
 * `show()` is the invitation: what the shared link lands on, where a guest
 * gives their name. `album()` is the event itself, where they actually add
 * photos. The split follows the app: you are invited, then you are inside.
 *
 * Same credential as the API's guest upload routes: possession of the event's
 * `qr_code_token` is the whole authorisation. Both screens therefore expose
 * only what a guest holding the link is already entitled to see — the event's
 * name, its cover, who is hosting, how many people are in, and how many photos
 * exist. Never the photos themselves: guests don't see each other's shots
 * before the reveal, and neither does the host. That is the product.
 */
class JoinController extends Controller
{
    /** Screen one — the invitation. */
    public function show(string $token): View
    {
        return view('join', $this->payload($token));
    }

    /** Screen two — the album, where photos are added. */
    public function album(string $token): View
    {
        return view('join-album', $this->payload($token));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $token): array
    {
        $event = Event::with('user')
            ->withCount(['photos', 'guests'])
            ->where('qr_code_token', $token)
            ->firstOrFail();

        $open = $event->acceptsUploads();

        // `title` is nullable and the API falls back to `name`, so the two are
        // equal far more often than not. A subhead that repeats the headline
        // reads as a bug, so it only earns its line when it says something new.
        $subtitle = filled($event->title) && trim($event->title) !== trim($event->name)
            ? $event->title
            : null;

        return [
            'event' => $event,
            'token' => $token,
            'open' => $open,
            'photoCount' => $event->photos_count,
            'guestCount' => $event->guests_count,

            // Per-guest allowance; null means unlimited. The web album turns
            // this into a live "photos left" counter, so a guest finds out
            // before picking ten files rather than after the server refuses.
            'shotLimit' => $event->shot_limit,

            // The event is out of participant slots, so a guest who is not
            // already one cannot upload at all. Existing guests are unaffected,
            // and the page can only tell the two apart client-side — it has the
            // guest token, this render does not.
            'participantFull' => $event->participant_limit !== null
                && $event->participant_limit > 0
                && $event->guests_count >= $event->participant_limit,
            'host' => $event->user?->name,
            'subtitle' => $subtitle,
            'revealNote' => $this->revealNote($event),
            'coverUrl' => $event->cover_image_url ?: url(config('everly.invite.fallback_cover')),
            'storeUrl' => config('everly.invite.app_store_url'),
            'deepLink' => "everly://upload/{$token}",
            'inviteUrl' => route('join', $token),
            'albumUrl' => route('join.album', $token),

            // Same origin as these pages, so the upload posts without CORS and
            // without a CSRF token — Laravel's `api` group is stateless.
            'uploadUrl' => url("api/upload/{$token}"),
            // The guest's own photos, so a reload doesn't lose what they sent.
            'photosUrl' => url("api/upload/{$token}/photos"),
            'maxUploadKb' => (int) config('everly.media.max_upload_kb'),
        ];
    }

    /**
     * The reveal, in a sentence a guest can act on.
     *
     * This is the whole reason the album is worth joining rather than texting
     * the photos, so it is stated on the page instead of being left for the
     * guest to infer from the word "Everly". Describes the event's rule, not
     * `is_revealed` — that flag tracks what the *owner* can see, and a guest
     * arriving after the reveal still needs to know what happens to a photo
     * they add now.
     */
    private function revealNote(Event $event): string
    {
        return match ($event->reveal_time) {
            Event::REVEAL_INSTANT => 'Your photos appear in the album right away.',
            Event::REVEAL_END_OF_EVENT => "Everyone's photos unlock together when the event ends.",
            Event::REVEAL_SCHEDULED => $event->reveal_at
                ? "Everyone's photos unlock on {$event->reveal_at->format('M j')} at {$event->reveal_at->format('g:ia')}."
                : "Everyone's photos unlock together at the reveal.",
            default => "Everyone's photos unlock together at the reveal.",
        };
    }
}
