<?php

return [

    'token' => env('PRICE_COMPARE_TOKEN'),

    /*
    | Środowiska do porównania i przeliczania cen.
    | base_url — adres aplikacji (bez końcowego slasha).
    | W panelu admin można nadpisać URL per sesja; tutaj są domyślne wartości z .env.
    */
    'environments' => [
        'local' => [
            'label' => 'Lokalnie',
            'base_url' => env('PRICE_COMPARE_LOCAL_URL', env('APP_URL', 'http://127.0.0.1:8000')),
            'is_local' => true,
        ],
        'prod' => [
            'label' => 'Produkcja (bprafa.pl)',
            'base_url' => env('PRICE_COMPARE_PROD_URL', 'https://bprafa.pl'),
            'is_local' => false,
        ],
        'dev' => [
            'label' => 'Dev (sor41.webgarage.pl)',
            'base_url' => env('PRICE_COMPARE_DEV_URL', 'https://sor41.webgarage.pl'),
            'is_local' => false,
        ],
    ],

    'default_threshold' => (float) env('PRICE_COMPARE_THRESHOLD', 1.0),

];
