<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Filament\Support\Services\RelationshipJoiner;
use App\Models\Contractor;

$contractor = Contractor::find(1950);
if (! $contractor) { echo "No contractor\n"; exit(0); }

$rel = $contractor->contacts();
$relationshipQuery = app(RelationshipJoiner::class)->prepareQueryForNoConstraints($rel);

$wanted = ['first_name','last_name','company_name','email','phone'];
$cols = Schema::hasTable('contacts') ? Schema::getColumnListing('contacts') : [];
$searchCols = array_values(array_intersect($cols, $wanted));

echo 'Search cols: '.implode(',', $searchCols)."\n";

$term = 'Hotel Horda';
$results = [];

// full name match
$connection = $relationshipQuery->getConnection();
$driver = $connection->getDriverName() ?? $connection->getConfig('driver');
if ($driver === 'sqlite') {
    $fullExpr = "contacts.first_name || ' ' || contacts.last_name";
} else {
    $fullExpr = "CONCAT(contacts.first_name, ' ', contacts.last_name)";
}

try {
    $exact = (clone $relationshipQuery)->whereRaw("{$fullExpr} = ?", [$term])->limit(10)->get();
    foreach ($exact as $r) $results[$r->getKey()] = $r;
    echo 'Exact: '.count($exact)."\n";
} catch (\Exception $e) { echo 'Exact error: '.$e->getMessage()."\n"; }

// partial
if (count($results) < 10 && count($searchCols)) {
    $partial = (clone $relationshipQuery)->where(function($q) use ($searchCols, $term) {
        $first=true;
        foreach ($searchCols as $col) {
            $method = $first ? 'where' : 'orWhere';
            $q->{$method}('contacts.'.$col, 'like', "%hote%");
            $first=false;
        }
    })->limit(10 - count($results))->get();

    foreach ($partial as $r) $results[$r->getKey()] = $r;
    echo 'Partial: '.count($partial)."\n";
}

echo 'Total results: '.count($results)."\n";
foreach ($results as $r) echo $r->id.' '.($r->first_name ?? '').' '.($r->last_name ?? '')."\n";
