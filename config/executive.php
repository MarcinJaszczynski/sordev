<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tymczasowy pełny dostęp (wszyscy zalogowani użytkownicy panelu)
    |--------------------------------------------------------------------------
    | Ustaw na false, gdy chcesz wrócić do ograniczeń rolowych poniżej.
    */
    'open_access' => env('EXECUTIVE_OPEN_ACCESS', true),

    /*
    |--------------------------------------------------------------------------
    | Role z dostępem do wyników końcowych i panelu P&L
    |--------------------------------------------------------------------------
    */
    'owner_roles' => [
        'super_admin',
        'wlasciciel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role z dostępem do panelu statystycznego
    |--------------------------------------------------------------------------
    */
    'statistics_roles' => [
        'super_admin',
        'wlasciciel',
        'admin',
    ],

];
