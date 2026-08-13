<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Bramka płatności online
    |--------------------------------------------------------------------------
    | driver: fake | tpay | payu
    | fake — lokalny symulator (zalecany do dev); tpay/payu — produkcja po konfiguracji kluczy.
    */
    'driver' => env('PAYMENTS_DRIVER', 'fake'),

    'currency' => env('PAYMENTS_CURRENCY', 'PLN'),

    'session_ttl_hours' => (int) env('PAYMENTS_SESSION_TTL_HOURS', 24),

    'tpay' => [
        'client_id' => env('TPAY_CLIENT_ID'),
        'secret' => env('TPAY_SECRET'),
        'api_url' => env('TPAY_API_URL', 'https://api.tpay.com'),
    ],

    'payu' => [
        'pos_id' => env('PAYU_POS_ID'),
        'second_key' => env('PAYU_SECOND_KEY'),
        'client_id' => env('PAYU_CLIENT_ID'),
        'client_secret' => env('PAYU_CLIENT_SECRET'),
        'sandbox' => (bool) env('PAYU_SANDBOX', true),
    ],

    /*
    | Cadence przypomnień o zaległościach (dni po terminie).
    */
    'reminder_cadence_days' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('PAYMENTS_REMINDER_CADENCE_DAYS', '3,7,14'))
    ))),

    'reminder_schedule_at' => env('PAYMENTS_REMINDER_SCHEDULE_AT', '09:00'),
];
