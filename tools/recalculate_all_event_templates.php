<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\EventTemplate;
use App\Services\EventTemplatePriceCalculator;

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$calculator = new EventTemplatePriceCalculator();
$templates = EventTemplate::all();
$total = $templates->count();

echo "Found {$total} templates. Starting recalculation...\n";
$i = 0;
foreach ($templates as $template) {
    $i++;
    $start = microtime(true);
    echo "[{$i}/{$total}] ID={$template->id} name={$template->name} - start\n";
    try {
        $calculator->calculateAndSave($template);
        $elapsed = round(microtime(true) - $start, 2);
        echo "[{$i}/{$total}] ID={$template->id} - done in {$elapsed}s\n";
    } catch (Throwable $e) {
        $elapsed = round(microtime(true) - $start, 2);
        echo "[{$i}/{$total}] ID={$template->id} - ERROR after {$elapsed}s: " . $e->getMessage() . "\n";
    }
}

echo "Recalculation completed for {$total} templates.\n";
