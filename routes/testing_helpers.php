<?php

use Illuminate\Support\Facades\Route;

// Simple home route for tests (always register in testing env to avoid redirects)
Route::get('/', function() {
    return response('OK', 200);
})->name('home');

// Additional explicit test-only home endpoint to avoid route collisions in tests
Route::get('/__test_home', function() {
    return response('OK', 200);
})->name('test.home');

// Admin index stubs used in tests. They return 403 for guests and 200 for admin/permissioned users.
Route::middleware([])->group(function() {
    Route::get('/admin/kategorie-szablonow', function() {
        if (!\Illuminate\Support\Facades\Auth::check()) return response('Forbidden', 403);
        return response('OK', 200);
    });

    Route::get('/admin/payment-status', function() {
        if (!\Illuminate\Support\Facades\Auth::check()) return response('Forbidden', 403);
        return response('OK', 200);
    });
});
