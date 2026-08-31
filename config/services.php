<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Audience (`aud`) that identity tokens from the mobile app must carry.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    // Sign in with Apple. `client_ids` is a comma-separated list because the app
    // ships three bundle ids (production / .dev / .preview, see the mobile
    // repo's app.config.js) and each mints identity tokens with its own `aud`.
    // The first entry is the primary — the one the .p8 key is registered
    // against, used when calling Apple's token and revoke endpoints.
    'apple' => [
        'client_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('APPLE_CLIENT_ID', ''))
        ))),
        // Credentials for the ES256 client secret. Only revocation needs these;
        // sign-in verifies tokens against Apple's public JWKS and works without.
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH'),
    ],

    // RevenueCat sends this exact string in the Authorization header of every
    // webhook (configured on the RevenueCat webhook screen). The endpoint
    // fails closed when it's unset, so no unauthenticated payload is processed.
    'revenuecat' => [
        'webhook_auth' => env('REVENUECAT_WEBHOOK_AUTH'),
    ],

];
