<?php

return [
    /*
    |--------------------------------------------------------------------
    | Marketplace boundary
    |--------------------------------------------------------------------
    | Domains treated as official marketplace links. Anything else is
    | rejected in import payloads and blocked from runner navigation.
    */
    'allowed_marketplace_hosts' => [
        'amazon.in' => 'amazon_in',
        'www.amazon.in' => 'amazon_in',
        'flipkart.com' => 'flipkart',
        'www.flipkart.com' => 'flipkart',
    ],

    /*
    |--------------------------------------------------------------------
    | Image hosts
    |--------------------------------------------------------------------
    | Real product images are served from separate CDN subdomains, not the
    | primary site (e.g. a real amazon.in order-history page's <img> src is
    | m.media-amazon.com, never www.amazon.in). Images are never navigated
    | to by clicking, so this list is only consulted for image fields
    | (MarketplaceUrlValidator::isAllowed($url, isImage: true)) - it must
    | never be merged into the link allowlist used for clickable order/
    | product/invoice URLs.
    */
    'allowed_image_hosts' => [
        'm.media-amazon.com' => 'amazon_in',
        'images-na.ssl-images-amazon.com' => 'amazon_in',
        'images-eu.ssl-images-amazon.com' => 'amazon_in',
        'images-fe.ssl-images-amazon.com' => 'amazon_in',
        'rukminim1.flixcart.com' => 'flipkart',
        'rukminim2.flixcart.com' => 'flipkart',
        'rukminim3.flixcart.com' => 'flipkart',
        'img1a.flixcart.com' => 'flipkart',
    ],

    /*
    |--------------------------------------------------------------------
    | Runner (Node.js Playwright process) connection
    |--------------------------------------------------------------------
    | The runner binds to 127.0.0.1 only and is never exposed publicly.
    | Requests are authenticated with an HMAC-SHA256 signature, not a
    | bearer token, so no long-lived shared secret is sent on the wire.
    */
    'runner' => [
        'base_url' => env('RUNNER_BASE_URL', 'http://127.0.0.1:3010'),
        'shared_secret' => env('RUNNER_SHARED_SECRET'),
        // A fresh Chromium install's very first launch can be slow (e.g.
        // Windows Defender scanning the newly-extracted binary) even
        // though later launches are fast - keep this comfortably above a
        // "warm" launch's real duration rather than tuned to it.
        'request_timeout_seconds' => (int) env('RUNNER_REQUEST_TIMEOUT_SECONDS', 30),
        // false on Windows/local dev: Chromium opens headed on the developer's
        // own screen and Laravel just shows status text. true in the Ubuntu
        // demo deployment, where the browser is streamed in via noVNC.
        'stream_enabled' => (bool) env('RUNNER_STREAM_ENABLED', false),
        'max_timestamp_skew_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------
    | Import session lifecycle
    |--------------------------------------------------------------------
    */
    'import_session' => [
        'max_lifetime_minutes' => 15,
        'inactivity_timeout_minutes' => 5,
        'view_token_ttl_seconds' => 60,
        'max_pages_per_scan' => 5,
        'page_navigation_delay_ms' => 1500,
    ],

    /*
    |--------------------------------------------------------------------
    | Import limits
    |--------------------------------------------------------------------
    | Hard caps enforced server-side regardless of what the runner
    | reports having scanned.
    */
    'imports' => [
        'max_orders_per_scan' => 100,
        'max_items_per_order' => 100,
        'max_field_length' => 500,
        'max_url_length' => 2048,
    ],

    /*
    |--------------------------------------------------------------------
    | Forbidden payload keys
    |--------------------------------------------------------------------
    | Any submitted key (at any depth) containing one of these substrings
    | (case-insensitive) causes the whole payload to be rejected. This is
    | a marketplace-data boundary; it does not apply to Laravel's own
    | authenticated session handling.
    */
    'forbidden_field_substrings' => [
        'cookie',
        'session_id',
        'password',
        'passwd',
        'otp',
        'csrf',
        'authorization',
        'bearer',
        'access_token',
        'refresh_token',
        'localstorage',
    ],

    /*
    |--------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------
    */
    'retention' => [
        'default_days' => (int) env('SAVV_DEFAULT_RETENTION_DAYS', 730),
        'expired_import_session_purge_after_minutes' => 60,
    ],
];
