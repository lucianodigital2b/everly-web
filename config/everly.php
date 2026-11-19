<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media storage
    |--------------------------------------------------------------------------
    |
    | Guest uploads (originals + generated thumbnails) are written to this disk.
    | Production uses "r2"; local development can fall back to "public" so the
    | app runs without Cloudflare credentials. Either way the API hands the
    | client an absolute URL, which is all the contract in BACKEND.md promises.
    |
    */

    'media' => [
        'disk' => env('MEDIA_DISK', 'r2'),

        // Longest edge of the generated thumbnail, in pixels.
        'thumbnail_size' => 480,

        // Longest edge of a stored event cover. Covers are only ever shown as
        // a card or a full-bleed backdrop, so there is nothing to gain from
        // keeping a 12-megapixel original around.
        'cover_size' => 1600,

        // Rejected before anything touches the disk.
        'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 51200), // 50 MB
    ],

    /*
    |--------------------------------------------------------------------------
    | PIX checkout
    |--------------------------------------------------------------------------
    |
    | Which gateway backs POST /events/{id}/checkout. "fake" issues a stub
    | invoice URL and marks the payment paid on the next poll, so the whole
    | paid-plan flow is exercisable end to end without a provider account.
    |
    */

    'pix' => [
        'driver' => env('PIX_DRIVER', 'fake'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest invite page
    |--------------------------------------------------------------------------
    |
    | GET /upload/{token} — the page a shared invite link lands on. It is the
    | only route a guest without the app ever sees, so the store link has to be
    | right: an `everly://` deep link does nothing at all on a phone that
    | doesn't have Everly installed, and this is the only way out of that.
    |
    */

    'invite' => [
        'app_store_url' => env('INVITE_APP_STORE_URL', 'https://apps.apple.com/app/everly'),

        // Apple's campaign provider token (App Store Connect > Analytics >
        // Campaigns). With it, /app adds `pt` + `ct` to the store link so App
        // Store Connect attributes installs to the campaign; without it, App
        // Store Connect still sees this site as the web referrer.
        'apple_provider_token' => env('APPLE_PROVIDER_TOKEN'),

        // Shown when the event has no cover of its own.
        'fallback_cover' => '/join-cover.jpg',
    ],

    /*
    |--------------------------------------------------------------------------
    | Web analytics
    |--------------------------------------------------------------------------
    |
    | PostHog on the public pages (home, /app, /privacy, /support), in the same
    | project as the app so a click on the site and the install that follows
    | live in one place. The key is the public project key the app already
    | ships with. Only rendered in production. The guest invite pages are left
    | out on purpose: their URL carries the event's qr_code_token, which is the
    | credential, and it must not end up in a third party's $current_url.
    |
    */

    'web' => [
        // Adobe Fonts web project id for New Kansas on the landing page.
        'adobe_fonts_kit' => env('ADOBE_FONTS_KIT'),
    ],

    'analytics' => [
        'posthog_key' => env('POSTHOG_KEY', 'phc_JqPDRS6CSbUKx1UCw2ov870xYvU6R4ySMJGfWVDm3zt'),
        'posthog_host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Support contact
    |--------------------------------------------------------------------------
    |
    | Shown on the public /privacy and /support pages the App Store listing
    | links to.
    |
    */

    'support_email' => env('EVERLY_SUPPORT_EMAIL', 'luciano@getclevernote.app'),

];
