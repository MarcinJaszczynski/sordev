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

    // Cloudflare Turnstile CAPTCHA
    'turnstile' => [
        'site_key' => env('CF_TURNSTILE_SITE_KEY'),
        'secret_key' => env('CF_TURNSTILE_SECRET_KEY'),
    ],

    // Fakturownia.pl — wystawianie FV VAT-marża z wniosków / SalesInvoice
    'fakturownia' => [
        'domain' => env('FAKTUROWNIA_DOMAIN'), // np. firma lub firma.fakturownia.pl
        'token' => env('FAKTUROWNIA_API_TOKEN'),
        'seller_tax_no' => env('FAKTUROWNIA_SELLER_TAX_NO', env('INVOICES_AGENCY_NIP')),
    ],

    // OpenRouteService — geokodowanie i odległości drogowe między miejscami
    // Klucz można też podać w panelu: System → OpenRouteService (ma pierwszeństwo).
    'openrouteservice' => [
        'key' => env('OPENROUTESERVICE_API_KEY'),
        'requests_per_minute' => (int) env('OPENROUTESERVICE_REQUESTS_PER_MINUTE', 35),
        'daily_limit' => (int) env('OPENROUTESERVICE_DAILY_LIMIT', 2000),
        'min_interval_ms' => (int) env('OPENROUTESERVICE_MIN_INTERVAL_MS', 1700),
    ],

];
