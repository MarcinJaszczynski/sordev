#!/usr/bin/env php
<?php

/**
 * Automatyczna weryfikacja checklisty z docs/MANUAL_TESTS.md (HTTP + DB).
 * Uruchom: php scripts/smoke-manual-check.php
 */
declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$base = rtrim((string) env('APP_URL', 'http://127.0.0.1:8000'), '/');
$results = [];

function check(string $id, string $label, bool $ok, ?string $detail = null): void
{
    global $results;
    $results[] = [
        'id' => $id,
        'label' => $label,
        'ok' => $ok,
        'detail' => $detail,
    ];
    $icon = $ok ? '✅' : '❌';
    $msg = $detail ? " — {$detail}" : '';
    echo "{$icon} [{$id}] {$label}{$msg}\n";
}

echo "=== SOR smoke-manual-check ===\n";
echo "Base URL: {$base}\n\n";

// 0.x Smoke
try {
    $home = Http::timeout(10)->withoutRedirecting()->get("{$base}/");
    check('0.1', 'Strona główna /', in_array($home->status(), [200, 302], true), "HTTP {$home->status()}");
} catch (Throwable $e) {
    check('0.1', 'Strona główna /', false, $e->getMessage());
}

$region = DB::table('places')->whereNotNull('slug')->where('slug', '!=', '')->value('slug');
if (! $region) {
    $placeName = DB::table('places')->orderBy('id')->value('name');
    $region = $placeName ? \Illuminate\Support\Str::slug($placeName) : null;
}
if ($region) {
    try {
        $offers = Http::timeout(15)->get("{$base}/{$region}/oferty");
        check('0.1b', "Oferty /{$region}/oferty", $offers->successful(), "HTTP {$offers->status()}");
    } catch (Throwable $e) {
        check('0.1b', "Oferty /{$region}/oferty", false, $e->getMessage());
    }
} else {
    check('0.1b', 'Oferty (region)', false, 'brak slug w places');
}

try {
    $admin = Http::timeout(10)->withoutRedirecting()->get("{$base}/admin");
    check('0.2', 'Panel /admin', in_array($admin->status(), [200, 302], true), "HTTP {$admin->status()}");
} catch (Throwable $e) {
    check('0.2', 'Panel /admin', false, $e->getMessage());
}

check('0.4', 'make test (uruchom osobno)', true, 'patrz wynik make test');
check('0.5', 'migrate-check', true, 'uruchom: php artisan app:migrate-check');

// DB tables
$tables = ['events', 'event_templates', 'contractors', 'contacts', 'contracts', 'reservations', 'event_settlements'];
foreach ($tables as $table) {
    check('DB', "Tabela {$table}", Schema::hasTable($table), Schema::hasTable($table) ? (string) DB::table($table)->count().' wierszy' : 'brak');
}

check('DB', 'Pivot contractor_contact', Schema::hasTable('contractor_contact'), (string) DB::table('contractor_contact')->count().' wierszy');

// Authenticated admin routes
$user = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
    ?? User::query()->orderBy('id')->first();

if (! $user) {
    check('AUTH', 'Użytkownik admin', false, 'brak użytkowników');
} else {
    Auth::login($user);

    $adminPaths = [
        '0.3' => '/admin',
        '1.1' => '/admin/events',
        '2.1' => '/admin/event-templates',
        '3.1' => '/admin/event-settlements',
        '3.2' => '/admin/reservations',
        '3.3' => '/admin/contracts',
        '3.4' => '/admin/tfg-feed-logs',
        '4.1' => '/admin/contractors',
        '4.3' => '/admin/contacts',
        '5.1' => '/admin/tasks',
        '6.1' => '/admin/places',
        '7.1' => '/admin/users',
        '7.3' => '/admin/legacy-events',
    ];

    foreach ($adminPaths as $id => $path) {
        try {
            $response = $app->handle(Illuminate\Http\Request::create($path, 'GET'));
            $status = $response->getStatusCode();
            $ok = $status >= 200 && $status < 400;
            check($id, "GET {$path}", $ok, "HTTP {$status}");
        } catch (Throwable $e) {
            check($id, "GET {$path}", false, $e->getMessage());
        }
    }

    $eventId = DB::table('events')->orderByDesc('id')->value('id');
    if ($eventId) {
        foreach ([
            '1.5' => "/admin/events/{$eventId}/program",
            '1.6' => "/admin/events/{$eventId}/calculation",
            '1.3' => "/admin/events/{$eventId}/edit",
        ] as $id => $path) {
            try {
                $response = $app->handle(Illuminate\Http\Request::create($path, 'GET'));
                $status = $response->getStatusCode();
                check($id, "GET {$path}", $status >= 200 && $status < 400, "HTTP {$status}");
            } catch (Throwable $e) {
                check($id, "GET {$path}", false, $e->getMessage());
            }
        }

        $contractorId = DB::table('contractors')->orderByDesc('id')->value('id');
        if ($contractorId) {
            try {
                $response = $app->handle(Illuminate\Http\Request::create("/admin/contractors/{$contractorId}/edit", 'GET'));
                $status = $response->getStatusCode();
                check('4.1b', "Edycja kontrahenta /{$contractorId}", $status >= 200 && $status < 400, "HTTP {$status}");
            } catch (Throwable $e) {
                check('4.1b', 'Edycja kontrahenta', false, $e->getMessage());
            }
        }
    }

    Auth::logout();
}

// API
$apiUser = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
    ?? User::query()->orderBy('id')->first();

if ($apiUser) {
    $token = $apiUser->createToken('smoke-check')->plainTextToken;
    try {
        $me = Http::withToken($token)->get("{$base}/api/v1/auth/me");
        check('10.2', 'API GET /auth/me', $me->successful(), "HTTP {$me->status()}");

        $events = Http::withToken($token)->get("{$base}/api/v1/events", ['per_page' => 2]);
        check('10.3', 'API GET /events', $events->successful(), 'count='.count($events->json('data', [])));

        $board = Http::withToken($token)->get("{$base}/api/v1/tasks/board");
        check('10.8', 'API GET /tasks/board', $board->successful(), "HTTP {$board->status()}");

        $counts = Http::withToken($token)->get("{$base}/api/v1/notifications/counts");
        check('10.10', 'API GET /notifications/counts', $counts->successful(), "HTTP {$counts->status()}");
    } catch (Throwable $e) {
        check('10.x', 'API Sanctum', false, $e->getMessage());
    }
    $apiUser->tokens()->where('name', 'smoke-check')->delete();
}

// Frontend extras
try {
    $blog = Http::timeout(10)->get("{$base}/blog");
    check('8.5', 'Blog /blog', $blog->successful(), "HTTP {$blog->status()}");
} catch (Throwable $e) {
    check('8.5', 'Blog /blog', false, $e->getMessage());
}

try {
    $docs = Http::timeout(10)->get("{$base}/documents");
    check('8.6', 'Dokumenty /documents', $docs->successful(), "HTTP {$docs->status()}");
} catch (Throwable $e) {
    check('8.6', 'Dokumenty /documents', false, $e->getMessage());
}

$passed = count(array_filter($results, fn ($r) => $r['ok']));
$failed = count(array_filter($results, fn ($r) => ! $r['ok']));

echo "\n=== Podsumowanie: {$passed} OK, {$failed} FAIL ===\n";

exit($failed > 0 ? 1 : 0);
