<?php

return [
    'mobile_sso' => [
        'shared_secret' => env('LRTJ_SPACE_SATSET_SHARED_SECRET'),
        'signature_tolerance_seconds' => (int) env('LRTJ_SPACE_SATSET_SIGNATURE_TOLERANCE', 300),
        'token_ttl_minutes' => (int) env('SATSET_MOBILE_TOKEN_TTL_MINUTES', 60 * 24 * 90),
    ],
];
