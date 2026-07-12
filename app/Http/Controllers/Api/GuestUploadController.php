<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
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
     */
    public function show(string $token): JsonResponse
    {
        $event = $this->resolveEvent($token);
        $canUpload = $event->acceptsUploads();

        return response()->json([
            'active' => $canUpload,
            'event' => [
                'name' => $event->name,
                'title' => $event->title ?? $event->name,
                'cover_image_url' => $event->cover_image_url,
                'canUpload' => $canUpload,
            ],
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
        ]);

        if (! $event->acceptsUploads()) {
            throw ValidationException::withMessages([
                'photo' => ['This event is no longer accepting uploads.'],
            ]);
        }

        $guest = $this->resolveGuest($request, $event);

        if ($guest->hasReachedShotLimit()) {
            throw ValidationException::withMessages([
                'photo' => ["You've used all of your photos for this event."],
            ]);
        }

        $this->media->storeUpload($event, $request->file('photo'), $guest->id);
        $guest->increment('upload_count');

        return response()->json(['guest_token' => $guest->guest_token], 201);
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
