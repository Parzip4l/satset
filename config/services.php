<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'signal_organization' => [
        'tree_url' => env('SIGNAL_ORGANIZATION_TREE_URL'),
        'divisions_url' => env('SIGNAL_ORGANIZATION_DIVISIONS_URL'),
        'departments_url' => env('SIGNAL_ORGANIZATION_DEPARTMENTS_URL'),
        'timeout' => (int) env('SIGNAL_ORGANIZATION_TIMEOUT', 15),
        'verify_ssl' => env('SIGNAL_ORGANIZATION_VERIFY_SSL', true),
    ],

    'microsoft_sso' => [
        'enabled' => env('MICROSOFT_SSO_ENABLED', false),
        'tenant_id' => env('AZURE_TENANT_ID', env('MICROSOFT_TENANT_ID')),
        'client_id' => env('AZURE_CLIENT_ID', env('MICROSOFT_CLIENT_ID')),
        'client_secret' => env('AZURE_CLIENT_SECRET', env('MICROSOFT_CLIENT_SECRET')),
        'redirect_uri' => env('AZURE_REDIRECT_URI', env('MICROSOFT_REDIRECT_URI')),
        'allowed_domain' => env('MICROSOFT_ALLOWED_DOMAIN'),
        'domain_hint' => env('MICROSOFT_DOMAIN_HINT'),
        'prompt' => env('MICROSOFT_PROMPT'),
    ],

];
