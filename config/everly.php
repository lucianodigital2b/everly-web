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

];
