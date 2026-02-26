<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->handle(new Symfony\Component\Console\Input\ArgvInput([]), new Symfony\Component\Console\Output\NullOutput());

use Illuminate\Support\Facades\DB;

echo "Counting duplicate contractor_contact rows...\n";
$rows = DB::select("SELECT contractor_id, contact_id, COUNT(*) as cnt FROM contractor_contact GROUP BY contractor_id, contact_id HAVING cnt > 1");
$totalDup = 0;
foreach ($rows as $r) {
    $totalDup += ($r->cnt - 1);
}

echo "Found duplicates groups: " . count($rows) . ", total duplicate rows: $totalDup\n";

if ($totalDup === 0) {
    echo "No duplicates to remove.\n";
    exit(0);
}

// Delete duplicates keeping the lowest id per pair
$deleted = DB::delete("DELETE FROM contractor_contact WHERE id NOT IN (SELECT keep_id FROM (SELECT MIN(id) as keep_id FROM contractor_contact GROUP BY contractor_id, contact_id) as t)");

echo "Deleted $deleted duplicate rows.\n";

// Vacuum sqlite DB file if using sqlite (optional)
$driver = DB::getDriverName();
if ($driver === 'sqlite') {
    try {
        DB::statement('VACUUM');
        echo "Ran VACUUM on sqlite DB.\n";
    } catch (\Throwable $e) {
        echo "VACUUM failed: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
