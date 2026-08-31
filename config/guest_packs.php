<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baseline guest capacity
    |--------------------------------------------------------------------------
    |
    | Every event keeps at least this many participants. Guest packs add on top
    | of an event's own capacity; refunding a pack can never drop the effective
    | `participant_limit` below this floor. Matches the Free tier (5 guests).
    |
    */

    'baseline_participants' => (int) env('GUEST_PACK_BASELINE', 5),

    /*
    |--------------------------------------------------------------------------
    | Pack catalogue  (the "§2 map")
    |--------------------------------------------------------------------------
    |
    | RevenueCat product identifier => participants that pack grants.
    | `null` means unlimited (mirrors how the app encodes ∞ as a null
    | participant_limit). Capacity is intentionally decoupled from `plans`:
    | this map is the only source of truth for how much a purchase is worth.
    |
    | ⚠️ Replace these placeholder SKUs with the real product identifiers you
    |    registered in RevenueCat / App Store Connect / Play Console. The keys
    |    must match the `product_id` RevenueCat sends on the webhook exactly.
    |
    */

    'packs' => [
        'everly_guest_pack_intimate' => 30,
        'everly_guest_pack_celebration' => 100,
        'everly_guest_pack_grand' => null, // unlimited
    ],

    /*
    |--------------------------------------------------------------------------
    | Instant sync endpoint
    |--------------------------------------------------------------------------
    |
    | POST /events/{id}/guest-packs/sync lets the client credit a pack the
    | instant the store confirms a purchase, instead of waiting for the async
    | webhook. It shares the idempotent crediting path, so a later webhook for
    | the same transaction will not double-credit.
    |
    | It is OFF by default: the endpoint trusts the client's claimed
    | transaction_id, so only enable it once the client verifies receipts (or
    | you accept that the webhook is the real source of truth and sync is a
    | best-effort UX accelerator).
    |
    */

    'sync_enabled' => (bool) env('GUEST_PACK_SYNC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | RevenueCat subscriber attribute carrying the event id
    |--------------------------------------------------------------------------
    |
    | A user can own many events, so the purchase must say which event it tops
    | up. The client sets this RevenueCat subscriber attribute to the target
    | event id before calling purchase(); the webhook reads it back from
    | `event.subscriber_attributes.<name>.value`.
    |
    */

    'event_attribute' => env('GUEST_PACK_EVENT_ATTRIBUTE', 'everly_event_id'),

];
