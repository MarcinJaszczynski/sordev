<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contractor;

$contractor = Contractor::query()->first();
if (! $contractor) { echo "No contractors found\n"; exit(0); }

echo "Contractor: {$contractor->id} - {$contractor->name}\n";
$types = $contractor->types()->pluck('name')->toArray();
if (empty($types)) {
    echo "No types assigned\n";
} else {
    echo "Types: " . implode(', ', $types) . "\n";
}

// list first 10 contractors with types
$rows = Contractor::with('types')->limit(10)->get();
foreach ($rows as $r) {
    echo $r->id . ' - ' . $r->name . ' => ' . ($r->types->pluck('name')->join(', ') ?: '[none]') . "\n";
}
