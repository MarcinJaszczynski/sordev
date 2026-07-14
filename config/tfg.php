<?php

return [
    'enabled' => env('TFG_ENABLED', false),

    'driver' => env('TFG_DRIVER', 'mock'),

    'base_url' => env('TFG_BASE_URL', 'https://portal-tfg.example.local'),

    'oauth' => [
        'token_url' => env('TFG_OAUTH_TOKEN_URL', '/oauth/token'),
        'username' => env('TFG_OAUTH_USERNAME'),
        'password' => env('TFG_OAUTH_PASSWORD'),
        'client_id' => env('TFG_OAUTH_CLIENT_ID'),
        'client_secret' => env('TFG_OAUTH_CLIENT_SECRET'),
        'token_ttl_seconds' => 840,
    ],

    'feed' => [
        'submit_path' => '/tfg/api/v1/pt/feeds',
        'max_contracts' => 1000,
        'max_file_mb' => 10,
    ],

    // CSV "Wykaz umów" - główny kanał przekazywania danych do TFG.
    // Format musi być zgodny bajt-w-bajt z oficjalnym (Excel "CSV UTF-8").
    'csv' => [
        'bom' => true,
        'delimiter' => ';',
        'eol' => "\r\n",
        'date_format' => 'Y-m-d',
        'max_contracts' => 5000,
        'max_locations' => 5,
        'max_transports' => 3,
        'storage_dir' => 'tfg/csv',
    ],

    'poll' => [
        'interval_minutes' => 5,
        'max_attempts' => 72,
    ],

    'operations' => [
        'NOWEDANE' => 'Nowe dane',
        'KOREKTA' => 'Korekta',
        'ROZWIAZANIE' => 'Rozwiązanie',
        'USUNIECIE' => 'Usunięcie',
    ],

    'correction_reasons' => [
        'BLAD' => 'Błąd',
        'ZMIANA' => 'Zmiana w umowie',
    ],

    'tfg_statuses' => [
        'Zawarta' => 'Zawarta',
    ],

    'scheduler' => [
        'monthly_submit_day' => (int) env('TFG_MONTHLY_SUBMIT_DAY', 10),
        'reminder_hour' => '06:00',
    ],
];
