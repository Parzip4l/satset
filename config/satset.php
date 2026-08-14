<?php

return [
    'mobile_sso' => [
        'shared_secret' => env('LRTJ_SPACE_SATSET_SHARED_SECRET'),
        'signature_tolerance_seconds' => (int) env('LRTJ_SPACE_SATSET_SIGNATURE_TOLERANCE', 300),
        'token_ttl_minutes' => (int) env('SATSET_MOBILE_TOKEN_TTL_MINUTES', 60 * 24 * 90),
    ],

    'lrtj_space_notifications' => [
        'enabled' => filter_var(env('LRTJ_SPACE_NOTIFICATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'base_url' => rtrim(env('LRTJ_SPACE_BASE_URL', 'https://portal.lrtjakarta.co.id'), '/'),
        'endpoint' => env('LRTJ_SPACE_SATSET_NOTIFICATION_ENDPOINT', '/api/mobile/v1/satset/notifications'),
        'shared_secret' => env('LRTJ_SPACE_SATSET_SHARED_SECRET'),
        'timeout' => (int) env('LRTJ_SPACE_NOTIFICATION_TIMEOUT', 15),
        'verify_ssl' => filter_var(env('LRTJ_SPACE_NOTIFICATION_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'approval_resolver' => [
        'base_url' => rtrim(env('LRTJ_SPACE_BASE_URL', 'https://portal.lrtjakarta.co.id'), '/'),
        'endpoint' => env('LRTJ_SPACE_APPROVAL_RESOLVER_ENDPOINT', '/api/v1/approval/resolve'),
        'shared_secret' => env('LRTJ_SPACE_SATSET_SHARED_SECRET'),
        'timeout' => (int) env('LRTJ_SPACE_APPROVAL_RESOLVER_TIMEOUT', 15),
        'verify_ssl' => filter_var(env('LRTJ_SPACE_APPROVAL_RESOLVER_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'intranet_api' => [
        'shared_secret' => env('LRTJ_SPACE_SATSET_SHARED_SECRET'),
        'signature_tolerance_seconds' => (int) env('LRTJ_SPACE_SATSET_SIGNATURE_TOLERANCE', 300),
    ],
];
