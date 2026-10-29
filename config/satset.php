<?php

return [
    // Submission stays closed until the workflow, approval and SLA release gates are verified.
    'it' => [
        'requests_enabled' => filter_var(env('SATSET_IT_REQUESTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'bootstrap_actor_id' => env('SATSET_IT_BOOTSTRAP_ACTOR_ID'),
        'pilot' => [
            'required' => true,
            'version_id' => (int) env('SATSET_IT_PILOT_VERSION_ID', 0),
            'user_ids' => array_map(fn ($v) => preg_match('/^[1-9][0-9]*$/', trim($v)) ? (int) trim($v) : 0, array_values(array_filter(explode(',', (string) env('SATSET_IT_PILOT_USER_IDS', '')), fn ($v) => trim($v) !== ''))),
            'catalog_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('SATSET_IT_PILOT_CATALOG_KEYS', ''))))),
            'business_approved' => filter_var(env('SATSET_IT_PILOT_BUSINESS_APPROVED', false), FILTER_VALIDATE_BOOLEAN),
            'uat_approved' => filter_var(env('SATSET_IT_PILOT_UAT_APPROVED', false), FILTER_VALIDATE_BOOLEAN),
            'restore_verified' => filter_var(env('SATSET_IT_PILOT_RESTORE_VERIFIED', false), FILTER_VALIDATE_BOOLEAN),
            'support_ready' => filter_var(env('SATSET_IT_PILOT_SUPPORT_READY', false), FILTER_VALIDATE_BOOLEAN),
        ],
    ],
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

    'ticket_email_notifications' => [
        'enabled' => filter_var(env('SATSET_TICKET_EMAIL_NOTIFICATIONS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'web_push' => [
        'enabled' => filter_var(env('SATSET_WEB_PUSH_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'subject' => env('SATSET_WEB_PUSH_SUBJECT', 'mailto:teamga@lrtjakarta.co.id'),
        'public_key' => env('SATSET_WEB_PUSH_PUBLIC_KEY'),
        'private_key' => env('SATSET_WEB_PUSH_PRIVATE_KEY'),
        'ttl' => (int) env('SATSET_WEB_PUSH_TTL', 86400),
        'timeout' => (int) env('SATSET_WEB_PUSH_TIMEOUT', 10),
    ],

    'approval_resolver' => [
        'base_url' => rtrim(env('LRTJ_SPACE_BASE_URL', 'https://portal.lrtjakarta.co.id'), '/'),
        'endpoint' => env('LRTJ_SPACE_APPROVAL_RESOLVER_ENDPOINT', '/api/v1/approval/resolve'),
        'shared_secret' => env('LRTJ_SPACE_SATSET_SHARED_SECRET'),
        'timeout' => (int) env('LRTJ_SPACE_APPROVAL_RESOLVER_TIMEOUT', 15),
        'verify_ssl' => filter_var(env('LRTJ_SPACE_APPROVAL_RESOLVER_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'consumption_bum_position' => env('SATSET_CONSUMPTION_BUM_POSITION', 'General Affair Department Head'),
        'consumption_bum_group' => env('SATSET_CONSUMPTION_BUM_GROUP', 'BUM'),
        'direct_ga_requester_emails' => array_values(array_filter(array_map(
            fn ($email) => strtolower(trim($email)),
            explode(',', env('SATSET_DIRECT_GA_REQUESTER_EMAILS', 'teamga@lrtjakarta.co.id'))
        ))),
    ],

    'portal_signatures' => [
        'base_url' => rtrim(env('LRTJ_SPACE_BASE_URL', 'https://portal.lrtjakarta.co.id'), '/'),
        'endpoint' => env('LRTJ_SPACE_SIGNATURE_ENDPOINT', '/api/v1/signatures'),
        'shared_secret' => env('LRTJ_SPACE_SATSET_SHARED_SECRET'),
        'timeout' => (int) env('LRTJ_SPACE_SIGNATURE_TIMEOUT', 15),
        'verify_ssl' => filter_var(env('LRTJ_SPACE_SIGNATURE_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'direct_ga_fallback_signer' => [
            'id' => env('SATSET_DIRECT_GA_SIGNATURE_FALLBACK_ID'),
            'name' => env('SATSET_DIRECT_GA_SIGNATURE_FALLBACK_NAME', 'Azwita'),
            'email' => env('SATSET_DIRECT_GA_SIGNATURE_FALLBACK_EMAIL', 'azwita@lrtjakarta.co.id'),
        ],
    ],

    'intranet_api' => [
        'shared_secret' => env('LRTJ_SPACE_SATSET_SHARED_SECRET'),
        'signature_tolerance_seconds' => (int) env('LRTJ_SPACE_SATSET_SIGNATURE_TOLERANCE', 300),
    ],
];
