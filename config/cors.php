<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Without this file Laravel falls back to allowing any origin ("*") to read
    | API responses in a browser. HybridCore is a single-origin app (Inertia is
    | served from the same host), so the browser never needs to make a
    | cross-origin API call to it. Locking the allowed origins to the site's own
    | URL means another website cannot script a visitor's browser into reading
    | responses from this API.
    |
    | Server-to-server callers — the game-server bridge, a mobile app — are not
    | affected: CORS is a browser mechanism, and a non-browser client sends no
    | Origin header to enforce. Set CORS_ALLOWED_ORIGINS to a comma-separated
    | list only if a trusted separate front-end genuinely needs browser access.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('APP_URL', ''))),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Token auth carries the credential in the Authorization header, not a
    // cookie, so cross-origin credentialed requests are never needed.
    'supports_credentials' => false,

];
