<?php

declare(strict_types=1);

/**
 * Pełny HTTP smoke wszystkich ekranów (admin / portal / pilot / front).
 * Uruchomienie: php scripts/full-http-smoke.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$reportPath = storage_path('logs/full-http-smoke-'.date('Ymd-His').'.json');
$http = $app->make(HttpKernel::class);

$admin = User::query()->where('email', 'm.jaszczynski@gmail.com')->first()
    ?? User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super_admin']))->first();
$pilot = User::query()->where('email', 'a.kalisz@bprafa.pl')->first()
    ?? User::query()->where('type', 'pilot')->first()
    ?? User::query()->whereHas('roles', fn ($q) => $q->where('name', 'pilot'))->first();

$eventId = Event::query()
    ->whereNotNull('event_template_id')
    ->where('status', Event::STATUS_CONFIRMED)
    ->orderByDesc('id')
    ->value('id')
    ?? Event::query()->whereNotNull('event_template_id')->orderByDesc('id')->value('id')
    ?? Event::query()->orderByDesc('id')->value('id');

$templateId = EventTemplate::query()->where('is_active', true)->orderByDesc('id')->value('id')
    ?? EventTemplate::query()->orderByDesc('id')->value('id');

$regionSlug = 'warszawa';
if (class_exists(\App\Models\Place::class)) {
    $placeName = \App\Models\Place::query()->value('name');
    if (is_string($placeName) && $placeName !== '') {
        $regionSlug = \Illuminate\Support\Str::slug($placeName);
    }
}

$ids = [
    'event' => $eventId,
    'contract' => class_exists(\App\Models\Contract::class) ? \App\Models\Contract::query()->orderByDesc('id')->value('id') : null,
    'filename' => null,
    'regionSlug' => $regionSlug,
    'slug' => EventTemplate::query()->whereNotNull('slug')->value('slug'),
    'dayLength' => '3-dniowe',
    'id' => $templateId ?? $eventId,
    'token' => 'smoke-token',
];

$idOf = static function (string $class): mixed {
    return class_exists($class) ? $class::query()->value('id') : null;
};

$lookup = [
    'blog-posts' => fn () => $idOf(\App\Models\BlogPost::class),
    'buses' => fn () => $idOf(\App\Models\Bus::class),
    'checklist-templates' => fn () => $idOf(\App\Models\ChecklistTemplate::class),
    'contacts' => fn () => $idOf(\App\Models\Contact::class),
    'contract-templates' => fn () => $idOf(\App\Models\ContractTemplate::class),
    'contractors' => fn () => $idOf(\App\Models\Contractor::class),
    'contracts' => fn () => $ids['contract'],
    'conversations' => fn () => $idOf(\App\Models\Conversation::class),
    'currencies' => fn () => $idOf(\App\Models\Currency::class),
    'currency-rate-snapshots' => fn () => $idOf(\App\Models\CurrencyRateSnapshot::class),
    'document-sections' => fn () => $idOf(\App\Models\DocumentSection::class),
    'documents' => fn () => $idOf(\App\Models\Document::class),
    'event-price-descriptions' => fn () => $idOf(\App\Models\EventPriceDescription::class),
    'event-settlements' => fn () => $idOf(\App\Models\EventSettlement::class),
    'event-template-program-points' => fn () => $idOf(\App\Models\EventTemplateProgramPoint::class),
    'event-template-qties' => fn () => $idOf(\App\Models\EventTemplateQty::class),
    'event-templates' => fn () => $templateId,
    'event-types' => fn () => $idOf(\App\Models\EventType::class),
    'events' => fn () => $eventId,
    'hotel-rooms' => fn () => $idOf(\App\Models\HotelRoom::class),
    'insurances' => fn () => $idOf(\App\Models\Insurance::class),
    'kategoria-szablonus' => fn () => $idOf(\App\Models\KategoriaSzablonu::class),
    'legacy-events' => fn () => $idOf(\App\Models\LegacyEvent::class),
    'mail-templates' => fn () => $idOf(\App\Models\MailTemplate::class),
    'markups' => fn () => $idOf(\App\Models\Markup::class),
    'media' => fn () => $idOf(\App\Models\Media::class),
    'payers' => fn () => $idOf(\App\Models\Payer::class),
    'payment-statuses' => fn () => $idOf(\App\Models\PaymentStatus::class),
    'payment-types' => fn () => $idOf(\App\Models\PaymentType::class),
    'place-distances' => fn () => $idOf(\App\Models\PlaceDistance::class),
    'places' => fn () => $idOf(\App\Models\Place::class),
    'reservations' => fn () => $idOf(\App\Models\Reservation::class),
    'roles' => fn () => class_exists(\Spatie\Permission\Models\Role::class) ? \Spatie\Permission\Models\Role::query()->value('id') : null,
    'sales-invoices' => fn () => $idOf(\App\Models\SalesInvoice::class),
    'tags' => fn () => $idOf(\App\Models\Tag::class),
    'tasks' => fn () => $idOf(\App\Models\Task::class),
    'taxes' => fn () => $idOf(\App\Models\Tax::class),
    'tfg-dictionaries' => fn () => $idOf(\App\Models\TfgDictionary::class),
    'tfg-feed-logs' => fn () => $idOf(\App\Models\TfgFeedLog::class),
    'todo-statuses' => fn () => $idOf(\App\Models\TodoStatus::class),
    'transport-types' => fn () => $idOf(\App\Models\TransportType::class),
    'users' => fn () => User::query()->value('id'),
    'vendor-invoices' => fn () => $idOf(\App\Models\VendorInvoice::class),
    'pilot-events' => fn () => $eventId,
];

$skipExact = [
    '/install',
    '/install/admin',
    '/install/application',
    '/install/complete',
    '/install/database',
    '/auto-login',
    '/test-drag-drop',
    '/test-log',
    '/test-mail',
    '/check-data',
    '/api/v1/auth/me',
    '/api/v1/events',
    '/api/v1/notifications/counts',
    '/api/v1/tasks/board',
    '/sanctum/csrf-cookie',
];

$skipPrefix = [
    '/admin/backups/',
];

function resolveUri(string $uri, array $lookup, array $ids): ?string
{
    if (! str_contains($uri, '{')) {
        return $uri;
    }

    // specials
    $uri = str_replace('{format?}', 'xlsx', $uri);
    $uri = str_replace('{format}', 'xlsx', $uri);
    $uri = str_replace('{audience}', 'office', $uri);
    $uri = str_replace('{type}', 'attendance', $uri);

    if (preg_match_all('/\{([a-zA-Z_]+)\??\}/', $uri, $m)) {
        foreach ($m[1] as $param) {
            $value = null;

            // Prefer resource-specific lookup for {record}
            if ($param === 'record' || $param === 'event') {
                if (preg_match('#/([a-z0-9\-]+)/\{'.$param.'#', $uri, $seg)) {
                    $resource = $seg[1];
                    if (isset($lookup[$resource])) {
                        $value = $lookup[$resource]();
                    }
                }
            }

            if (! $value && isset($ids[$param]) && $ids[$param]) {
                $value = $ids[$param];
            }

            if (! $value && preg_match('#/([a-z0-9\-]+)/\{'.$param.'#', $uri, $seg)) {
                $resource = $seg[1];
                if (isset($lookup[$resource])) {
                    $value = $lookup[$resource]();
                }
            }

            if (! $value) {
                return null;
            }
            $uri = preg_replace('/\{'.$param.'\??\}/', (string) $value, $uri, 1);
        }
    }

    return str_contains($uri, '{') ? null : $uri;
}

function hit(HttpKernel $http, string $uri, ?User $user, string $panel): array
{
    Auth::logout();
    if ($user) {
        Auth::login($user);
    }

    try {
        if (class_exists(\Filament\Facades\Filament::class)) {
            $filamentPanel = match ($panel) {
                'admin' => 'admin',
                'pilot' => 'pilot',
                'portal' => 'portal',
                default => null,
            };
            if ($filamentPanel) {
                \Filament\Facades\Filament::setCurrentPanel(
                    \Filament\Facades\Filament::getPanel($filamentPanel)
                );
            }
        }
    } catch (Throwable) {
        // panel may be unavailable in some contexts
    }

    $accept = str_contains($uri, '/notifications/counts') || str_starts_with($uri, '/api/')
        ? 'application/json'
        : 'text/html';

    $req = Request::create($uri, 'GET', [], [], [], [
        'HTTP_ACCEPT' => $accept,
        'HTTP_HOST' => '127.0.0.1:8000',
        'HTTP_X_REQUESTED_WITH' => str_contains($uri, '/notifications/counts') ? 'XMLHttpRequest' : '',
    ]);

    $started = microtime(true);
    try {
        $res = $http->handle($req);
        $code = $res->getStatusCode();
        $raw = $res->getContent();
        $body = is_string($raw) ? $raw : '';
        $ms = (int) round((microtime(true) - $started) * 1000);
        $exceptionLike = $body !== '' && str_contains($body, 'Illuminate\\') && str_contains($body, 'Stack trace');
        $serverErrorText = $body !== '' && (str_contains($body, 'Server Error') || str_contains($body, 'Whoops!'));
        $brokenAlpine = $body !== '' && str_contains($body, 'meta[name=\\"csrf-token\\"]');
        $http->terminate($req, $res);

        // 2xx/3xx = OK; 401/403/404 = soft; 5xx / exception HTML = hard
        $hardFail = $code >= 500 || $exceptionLike || $serverErrorText || $brokenAlpine;
        $ok = ! $hardFail && $code < 400;

        return compact('uri', 'panel', 'code', 'ms', 'ok', 'hardFail', 'brokenAlpine') + [
            'user' => $user?->email,
            'location' => $res->headers->get('Location'),
        ];
    } catch (Throwable $e) {
        return [
            'uri' => $uri,
            'panel' => $panel,
            'code' => 0,
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'ok' => false,
            'hardFail' => true,
            'brokenAlpine' => false,
            'user' => $user?->email,
            'error' => $e->getMessage(),
        ];
    }
}

$candidates = [];
foreach (Route::getRoutes() as $route) {
    if (! in_array('GET', $route->methods(), true)) {
        continue;
    }
    $uri = '/'.ltrim($route->uri(), '/');
    if ($uri === '//') {
        $uri = '/';
    }
    if (str_contains($uri, 'livewire') || str_contains($uri, '_ignition') || str_contains($uri, 'horizon')) {
        continue;
    }
    if (in_array($uri, $skipExact, true)) {
        continue;
    }
    foreach ($skipPrefix as $prefix) {
        if (str_starts_with($uri, $prefix)) {
            continue 2;
        }
    }

    $panel = match (true) {
        str_starts_with($uri, '/admin') => 'admin',
        str_starts_with($uri, '/portal') => 'portal',
        str_starts_with($uri, '/pilot') => 'pilot',
        default => 'front',
    };

    $resolved = resolveUri($uri, $lookup, $ids);
    if ($resolved === null) {
        $candidates[] = ['uri' => $uri, 'panel' => $panel, 'skip' => 'missing-id'];
        continue;
    }
    $candidates[] = ['uri' => $resolved, 'panel' => $panel, 'skip' => null];
}

// dedupe
$seen = [];
$unique = [];
foreach ($candidates as $c) {
    $key = ($c['skip'] ?? '').'|'.$c['uri'];
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    $unique[] = $c;
}

$results = [];
$hardFails = [];
$soft = [];
$skipped = [];

foreach ($unique as $c) {
    if ($c['skip']) {
        $skipped[] = $c;
        echo sprintf("SKIP %-60s (%s)\n", $c['uri'], $c['skip']);
        continue;
    }

    $user = match ($c['panel']) {
        'admin' => $admin,
        'pilot' => $pilot ?? $admin,
        'portal' => $admin, // admin z rolą client może nie mieć dostępu — 403 OK
        default => null,
    };

    // login pages without auth
    if (str_ends_with($c['uri'], '/login') || $c['uri'] === '/login') {
        $user = null;
    }

    $row = hit($http, $c['uri'], $user, $c['panel']);
    $results[] = $row;

    $mark = $row['hardFail'] ? 'FAIL' : ($row['code'] >= 400 ? 'WARN' : 'OK  ');
    echo sprintf("%s %3d %-60s %4dms\n", $mark, $row['code'], $row['uri'], $row['ms']);

    if ($row['hardFail']) {
        $hardFails[] = $row;
    } elseif (($row['code'] ?? 0) >= 400) {
        $soft[] = $row;
    }
}

$summary = [
    'tested' => count($results),
    'hard_fails' => count($hardFails),
    'soft_warns' => count($soft),
    'skipped' => count($skipped),
    'admin_user' => $admin?->email,
    'pilot_user' => $pilot?->email,
    'event_id' => $eventId,
    'template_id' => $templateId,
    'hard_fail_uris' => array_map(fn ($r) => ['code' => $r['code'], 'uri' => $r['uri'], 'error' => $r['error'] ?? null], $hardFails),
    'soft_uris' => array_map(fn ($r) => ['code' => $r['code'], 'uri' => $r['uri']], $soft),
];

file_put_contents($reportPath, json_encode(['summary' => $summary, 'results' => $results, 'skipped' => $skipped], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "\n=== SUMMARY ===\n";
echo "tested={$summary['tested']} hard_fails={$summary['hard_fails']} warns={$summary['soft_warns']} skipped={$summary['skipped']}\n";
echo "report={$reportPath}\n";

if ($hardFails) {
    echo "\nHARD FAILS:\n";
    foreach ($hardFails as $f) {
        echo "  {$f['code']} {$f['uri']}".(isset($f['error']) ? ' :: '.$f['error'] : '')."\n";
    }
}

exit($summary['hard_fails'] > 0 ? 1 : 0);
