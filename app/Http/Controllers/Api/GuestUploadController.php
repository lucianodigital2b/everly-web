<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Guest;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The QR flow. No authentication: possession of the event's `qr_code_token` is
 * the credential, and a guest is identified across uploads by the opaque
 * `X-Guest-Token` we hand back on their first one.
 */
class GuestUploadController extends Controller
{
    public function __construct(private readonly MediaStorage $media) {}

    /**
     * Is this event accepting uploads?
     *
     * Reads `X-Guest-Token` when it is sent, purely to answer "how many have I
     * got left". Optional on purpose: a guest who has not uploaded yet has no
     * token, and the endpoint still has to work for them — they simply get the
     * event's full per-guest allowance back.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $event = $this->resolveEvent($token);
        $canUpload = $event->acceptsUploads();
        $guest = $this->knownGuest($request, $event);

        return response()->json([
            'active' => $canUpload,
            'event' => [
                'name' => $event->name,
                'title' => $event->title ?? $event->name,
                'cover_image_url' => $event->cover_image_url,
                'canUpload' => $canUpload,
                // Photos this guest may still upload; null means unlimited.
                'shotLimit' => $this->remainingFor($event, $guest),
                // The event's allowance per guest, so a client can render
                // "3 of 10 left" rather than a bare remainder.
                'shotsPerGuest' => $event->shot_limit,
            ],
        ]);
    }

    /**
     * The requesting guest's own photos — and *only* ever their own.
     *
     * This is what lets a guest reload the page and still see what they sent,
     * which is otherwise unknowable to them: the client keeps nothing but an
     * opaque token.
     *
     * The `where('guest_id', ...)` is the whole safety property of this
     * endpoint, and it is deliberately not conditional on anything. There is no
     * flag, no reveal state and no query parameter that widens it to the rest of
     * the album — guests do not see each other's shots before the reveal, and
     * the owner's gallery is a different, authenticated endpoint. If a guest
     * gallery is ever wanted, it should be a new route with its own reasoning,
     * not a loosened `where` on this one.
     */
    public function mine(Request $request, string $token): JsonResponse
    {
        $event = $this->resolveEvent($token);
        $guest = $this->knownGuest($request, $event);

        if (! $guest) {
            // No token, or one that belongs to another event. Not an error:
            // a guest who has never uploaded simply has nothing here.
            return response()->json(['photos' => [], 'revealed' => $event->shouldBeRevealed()]);
        }

        $photos = $event->photos()
            ->where('guest_id', $guest->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (EventPhoto $photo): array => [
                'id' => $photo->id,
                'url' => $photo->url(),
                'thumbnail_url' => $photo->thumbnailUrl(),
                'media_type' => $photo->media_type,
            ])
            ->all();

        return response()->json([
            'photos' => $photos,
            // Whether the album as a whole has opened. Says nothing about the
            // list above, which is the guest's own either way — it is here so a
            // client can tell them whether anyone else can see these yet.
            'revealed' => $event->shouldBeRevealed(),
        ]);
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $event = $this->resolveEvent($token);

        $request->validate([
            'photo' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/heic,image/heif,image/webp,video/mp4,video/quicktime',
                'max:'.config('everly.media.max_upload_kb'),
            ],
            // The credit the gallery prints on the photo. Optional: a guest can
            // upload without naming themselves.
            'guest_name' => ['nullable', 'string', 'max:40'],
        ]);

        if (! $event->acceptsUploads()) {
            throw ValidationException::withMessages([
                'photo' => ['This event is no longer accepting uploads.'],
            ]);
        }

        $guest = $this->resolveGuest($request, $event);
        $this->rememberName($guest, $request->input('guest_name'));

        if ($guest->hasReachedShotLimit()) {
            throw ValidationException::withMessages([
                'photo' => ["You've used all of your photos for this event."],
            ]);
        }

        $this->media->storeUpload($event, $request->file('photo'), $guest->id);
        $guest->increment('upload_count');

        return response()->json([
            'guest_token' => $guest->guest_token,
            // Saves the client a round trip to keep a "photos left" counter
            // honest between uploads. Null means unlimited.
            'remaining' => $this->remainingFor($event, $guest),
        ], 201);
    }

    /**
     * The guest behind `X-Guest-Token`, if the header is present and the token
     * belongs to this event. Never creates one — an unrecognised token is the
     * same as no token, and a read shouldn't consume a participant slot.
     */
    private function knownGuest(Request $request, Event $event): ?Guest
    {
        $token = $request->header('X-Guest-Token');

        if (! $token) {
            return null;
        }

        return Guest::where('guest_token', $token)
            ->where('event_id', $event->id)
            ->first();
    }

    /**
     * Photos this guest may still upload. Null means unlimited — mirroring
     * `Guest::hasReachedShotLimit()`, which treats a null or non-positive
     * `shot_limit` as no limit at all.
     */
    private function remainingFor(Event $event, ?Guest $guest): ?int
    {
        $limit = $event->shot_limit;

        if ($limit === null || $limit <= 0) {
            return null;
        }

        // `->` rather than `?->`: `??` already suppresses the read on a null
        // guest, and the nullsafe operator makes the null branch unreachable
        // twice over.
        return max(0, $limit - ($guest->upload_count ?? 0));
    }

    /**
     * The name rides on *every* upload, not just the first: a guest can fill it
     * in after already sending a shot, so the latest non-empty value wins. An
     * absent or blank part never clears a name that was already given.
     */
    private function rememberName(Guest $guest, ?string $name): void
    {
        $name = trim((string) $name);

        if ($name === '' || $name === $guest->name) {
            return;
        }

        $guest->update(['name' => $name]);
    }

    /**
     * Reuse the guest behind X-Guest-Token when it belongs to this event;
     * otherwise admit a new one, subject to the participant cap.
     */
    private function resolveGuest(Request $request, Event $event): Guest
    {
        $token = $request->header('X-Guest-Token');

        if ($token) {
            $guest = Guest::where('guest_token', $token)
                ->where('event_id', $event->id)
                ->first();

            if ($guest) {
                return $guest;
            }
        }

        // Two guests scanning at once could both slip past a plain count check,
        // so admit new guests one at a time and re-count inside the lock.
        return DB::transaction(function () use ($event): Guest {
            $limit = $event->participant_limit;

            if ($limit !== null && $limit > 0) {
                $count = Guest::where('event_id', $event->id)->lockForUpdate()->count();

                if ($count >= $limit) {
                    throw ValidationException::withMessages([
                        'photo' => ['This event has reached its guest limit.'],
                    ]);
                }
            }

            return Guest::create(['event_id' => $event->id]);
        });
    }

    private function resolveEvent(string $token): Event
    {
        return Event::with('plan')->where('qr_code_token', $token)->firstOrFail();
    }
}
