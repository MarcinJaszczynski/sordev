<?php

return [
    'name' => env('COMPANY_NAME', config('app.name', 'Biuro Podróży RAFA')),
    'address_line_1' => env('COMPANY_ADDRESS_LINE_1', 'ul. Przykładowa 1'),
    'address_line_2' => env('COMPANY_ADDRESS_LINE_2', '00-000 Warszawa, Polska'),
    'phone' => env('COMPANY_PHONE', '+48 000 000 000'),
    'email' => env('COMPANY_EMAIL', env('MAIL_FROM_ADDRESS', 'kontakt@example.com')),
    'website' => env('COMPANY_WEBSITE', config('app.url', 'https://example.com')),
    'logo_path' => env('COMPANY_LOGO_PATH', 'uploads/logo.png'),
];
