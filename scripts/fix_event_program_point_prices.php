<?php

// scripts/fix_event_program_point_prices.php

use App\Models\EventProgramPoint;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Fix all event program points
$fixed = 0;
EventProgramPoint::chunk(100, function ($points) use (&$fixed) {
    foreach ($points as $point) {
        // Wywołaj logikę modelu (saving) przez save()
        $point->save();
        $fixed++;
    }
});
echo "Zaktualizowano ceny dla {$fixed} punktów programu.\n";
