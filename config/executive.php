<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pełny dostęp (wszyscy zalogowani) — tylko awaryjnie
    |--------------------------------------------------------------------------
    */
    'open_access' => env('EXECUTIVE_OPEN_ACCESS', false),

    /*
    |--------------------------------------------------------------------------
    | Role z dostępem do P&L / zysków / wyników końcowych
    |--------------------------------------------------------------------------
    | Biuro (rola biuro) NIE powinno tu być — tylko właściciele / adminowie.
    */
    'owner_roles' => [
        'super_admin',
        'admin',
        'wlasciciel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role z dostępem do panelu statystycznego i raportów portfolio
    |--------------------------------------------------------------------------
    */
    'statistics_roles' => [
        'super_admin',
        'admin',
        'wlasciciel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Konta właścicielskie (P&L / statystyki / wrażliwe finanse)
    |--------------------------------------------------------------------------
    */
    'owner_emails' => [
        'm.jaszczynski@gmail.com',
        'rafa@bprafa.pl',
        'aleksanderjaszcz@gmail.com',
        'system@bprafa.pl',
    ],

    /*
    |--------------------------------------------------------------------------
    | Konta biura (operacje bez P&L / statystyk)
    |--------------------------------------------------------------------------
    */
    'office_emails' => [
        'm.paciej@bprafa.pl',
        'rezerwacje@bprafa.pl',
        'a.kalisz@bprafa.pl',
    ],

];
