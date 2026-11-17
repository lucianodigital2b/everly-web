<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventStoreRequest;
use App\Http\Requests\EventUpdateRequest;
use App\Http\Resources\EventPhotoResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\PaymentResource;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Plan;
use App\Services\EventGates;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;

class EventController extends Controller
{
    public function __construct(
        private readonly EventGates $gates,
        private readonly MediaStorage $media,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $events = $request->user()->events()
            ->with('plan')
            ->latest()
            ->get()
            ->each(fn (Event $event) => $this->refreshReveal($event));

        return EventResource::collection($events);
    }

    public function store(EventStoreRequest $request): JsonResponse
    {
        // The app still sends the guest tier the host picked as `plan_id`, but
        // that tier is bought afterwards as a guest pack and credited by the
        // RevenueCat webhook. So every event starts on the base plan with the
        // free guest allowance and goes live straight away; a paid tier the
        // host backs out of costs nothing and changes nothing.
        $plan = Plan::base();

        // `cover_image` is a file, not a column — it is exchanged for a URL
        // below, once the event has an id to file it under.
        $event = new Event($request->safe()->except(['plan_id', 'cover_image']));
        $event->user_id = $request->user()->id;
        $event->plan()->associate($plan);

        $event->tier ??= $plan->name;
        $event->participant_limit = (int) config('guest_packs.baseline_participants');
        $event->save();

        if ($request->hasFile('cover_image')) {
            $event->cover_image_url = $this->media->storeCover($event, $request->file('cover_image'));
            $event->save();
        }

        $event->activate();

        return (new EventResource($event->load('plan')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorizeOwner($request, $event);

        $event->load('plan');
        $this->refreshReveal($event);

        $uploadCount = $event->photos()->count();
        $latestPayment = $event->payments()->latest()->first();

        return response()->json([
            'event' => new EventResource($event),
            'gates' => $this->gates->for($event, $uploadCount),
            'uploadCount' => $uploadCount,
            'latestPayment' => $latestPayment ? new PaymentResource($latestPayment) : null,
        ]);
    }

    public function update(EventUpdateRequest $request, Event $event): EventResource
    {
        $this->authorizeOwner($request, $event);

        $event->fill($request->safe()->except(['plan_id', 'cover_image']));

        // A multipart PATCH doesn't parse in PHP, so the client posts these
        // with `_method=PATCH` — by the time this runs it is a normal request
        // with a file on it either way.
        if ($request->hasFile('cover_image')) {
            $event->cover_image_url = $this->media->storeCover($event, $request->file('cover_image'));
        }

        // `plan_id` is accepted and ignored, as on create: the app sends it when
        // a host backs out of a guest pack, and capacity only ever changes
        // through a verified purchase.

        $event->syncRevealState();
        $event->save();

        return new EventResource($event->load('plan'));
    }

    public function destroy(Request $request, Event $event): Response
    {
        $this->authorizeOwner($request, $event);

        $event->delete();

        return response()->noContent();
    }

    public function photos(Request $request, Event $event): JsonResponse
    {
        $this->authorizeOwner($request, $event);

        $event->load('plan');
        $this->refreshReveal($event);

        // The gallery stays sealed until the reveal fires. The client already
        // hides it, but the photos must not be on the wire either.
        if (! $event->is_revealed) {
            return response()->json(['photos' => []]);
        }

        // `with('guest')` keeps the per-photo credit from becoming an N+1.
        $photos = $event->photos()->with('guest')->latest()->get();

        return response()->json([
            'photos' => EventPhotoResource::collection($photos),
        ]);
    }

    /**
     * The host takes a photo out of their event. With `block_guest`, every
     * photo from the same guest goes with it and that guest can't upload to
     * the event again. This is the moderation App Review asks of an app whose
     * users see each other's photos (guideline 1.2).
     */
    public function destroyPhoto(Request $request, Event $event, EventPhoto $photo): Response
    {
        $this->authorizeOwner($request, $event);
        abort_unless($photo->event_id === $event->id, 404);

        $guest = $photo->guest;

        if ($guest !== null && $request->boolean('block_guest')) {
            $guest->blocked_at ??= Date::now();
            $guest->save();

            // One by one rather than a bulk delete, so each photo's files go too.
            $guest->photos()->get()->each->delete();
        } else {
            $photo->delete();
        }

        return response()->noContent();
    }

    /**
     * Reveal is time-based, so it can come due between requests. Recompute it on
     * read and persist only when it actually flips.
     */
    private function refreshReveal(Event $event): void
    {
        $event->syncRevealState();

        if ($event->isDirty('is_revealed')) {
            $event->save();
        }
    }

    private function authorizeOwner(Request $request, Event $event): void
    {
        abort_unless($event->user_id === $request->user()->id, 404);
    }
}
