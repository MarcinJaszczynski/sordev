<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use App\Models\Contractor;

$wanted = ['first_name','last_name','company_name','email','phone'];
$cols = Schema::hasTable('contacts') ? Schema::getColumnListing('contacts') : [];
$searchCols = array_values(array_intersect($cols, $wanted));

echo 'Detected search columns: '.implode(',',$searchCols)."\n";

$contractor = Contractor::find(1950);
if (! $contractor) { echo "No contractor\n"; exit(0); }

$rel = $contractor->contacts();
$query = $rel->getQuery();

$search = 'hote';
try {
    $q = (clone $query);
    if (count($searchCols)) {
        $q->where(function($q2) use ($searchCols, $search) {
            $first=true;
            foreach ($searchCols as $col) {
                $method = $first ? 'where' : 'orWhere';
                $q2->{$method}('contacts.'.$col, 'like', "%$search%");
                $first = false;
            }
        });
    }
    $res = $q->limit(10)->get();
    echo 'OK count: '.count($res)."\n";
    foreach ($res as $r) echo $r->id.' '.($r->first_name ?? '').' '.($r->last_name ?? '')."\n";
} catch (Exception $e) {
    echo 'ERR '.get_class($e).': '.$e->getMessage()."\n";
}
