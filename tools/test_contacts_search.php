<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contractor;

$contractor = Contractor::find(1950);
if (! $contractor) {
    echo "No contractor\n";
    exit(0);
}

$rel = $contractor->contacts();
$query = $rel->getQuery();

try {
    $res = (clone $query)->where('contacts.first_name','like','%hote%')->limit(10)->get();
    echo 'OK count: '.count($res)."\n";
    foreach ($res as $r) {
        echo $r->id.' '.($r->first_name ?? '').' '.($r->last_name ?? '')."\n";
    }
} catch (Exception $e) {
    echo 'ERR '.get_class($e).": ".$e->getMessage()."\n";
}
