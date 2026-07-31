<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Browser Security Headers
    |--------------------------------------------------------------------------
    |
    | These headers are intentionally compatible with the server-rendered
    | Classic MVC shell. Executable inline scripts are blocked by default;
    | required inline-style exceptions should remain narrowly reviewed.
    |
    */

    'headers' => [
        'X-Frame-Options' => env('SECURITY_HEADER_FRAME_OPTIONS', 'SAMEORIGIN'),
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => env('SECURITY_HEADER_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'Cross-Origin-Opener-Policy' => env('SECURITY_HEADER_COOP', 'same-origin'),
        'Permissions-Policy' => env(
            'SECURITY_HEADER_PERMISSIONS_POLICY',
            'camera=(), microphone=(), geolocation=(self), payment=(), usb=(), browsing-topics=()',
        ),
        'Content-Security-Policy' => env(
            'SECURITY_HEADER_CSP',
            "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; connect-src 'self' ws: wss:; upgrade-insecure-requests",
        ),
    ],

    'hsts' => [
        'enabled' => env('SECURITY_HEADER_HSTS_ENABLED', true),
        'max_age' => env('SECURITY_HEADER_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => env('SECURITY_HEADER_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => env('SECURITY_HEADER_HSTS_PRELOAD', false),
    ],

    'authenticated_cache' => [
        'no_store_enabled' => env('SECURITY_AUTHENTICATED_NO_STORE_ENABLED', true),
        'cache_control' => env('SECURITY_AUTHENTICATED_CACHE_CONTROL', 'private, no-store, max-age=0, must-revalidate'),
    ],

    'exception_responses' => [
        'json_request_id_enabled' => env('SECURITY_EXCEPTION_JSON_REQUEST_ID_ENABLED', true),
        'include_debug_details' => env('SECURITY_EXCEPTION_INCLUDE_DEBUG_DETAILS', false),
        'generic_server_error_message' => env(
            'SECURITY_EXCEPTION_GENERIC_SERVER_MESSAGE',
            'An unexpected server error occurred. Provide the request_id to support.',
        ),
    ],

    'password_policy' => [
        'min_length' => (int) env('SECURITY_PASSWORD_MIN_LENGTH', 10),
        'max_length' => (int) env('SECURITY_PASSWORD_MAX_LENGTH', 255),
        'require_mixed_case' => filter_var(env('SECURITY_PASSWORD_REQUIRE_MIXED_CASE', true), FILTER_VALIDATE_BOOL),
        'require_numbers' => filter_var(env('SECURITY_PASSWORD_REQUIRE_NUMBERS', true), FILTER_VALIDATE_BOOL),
        'require_symbols' => filter_var(env('SECURITY_PASSWORD_REQUIRE_SYMBOLS', true), FILTER_VALIDATE_BOOL),
        'uncompromised' => filter_var(env('SECURITY_PASSWORD_UNCOMPROMISED', false), FILTER_VALIDATE_BOOL),
        'max_compromised_threshold' => (int) env('SECURITY_PASSWORD_MAX_COMPROMISED_THRESHOLD', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | ERP Route Rate Limits
    |--------------------------------------------------------------------------
    |
    | Authenticated ERP users can trigger sensitive state changes across sales,
    | finance, HR, payroll, procurement and compliance workflows. These limits
    | provide a central abuse-control layer in addition to authorization,
    | validation, CSRF protection and per-domain workflow rules.
    |
    */

    'rate_limits' => [
        'erp_read_per_minute' => (int) env('SECURITY_RATE_LIMIT_ERP_READ_PER_MINUTE', 1200),
        'erp_write_per_minute' => (int) env('SECURITY_RATE_LIMIT_ERP_WRITE_PER_MINUTE', 600),
        'readiness_min_erp_read_per_minute' => (int) env('SECURITY_RATE_LIMIT_ERP_READ_MIN_PER_MINUTE', 1),
        'readiness_max_erp_read_per_minute' => (int) env('SECURITY_RATE_LIMIT_ERP_READ_MAX_PER_MINUTE', 5000),
        'readiness_min_erp_write_per_minute' => (int) env('SECURITY_RATE_LIMIT_ERP_WRITE_MIN_PER_MINUTE', 1),
        'readiness_max_erp_write_per_minute' => (int) env('SECURITY_RATE_LIMIT_ERP_WRITE_MAX_PER_MINUTE', 2500),
    ],
];
