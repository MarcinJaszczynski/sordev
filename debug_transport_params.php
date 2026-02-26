<?php
use App\Models\EventTemplate;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$eventTemplateId = 10;
$template = EventTemplate::with('bus')->find($eventTemplateId);

if (!$template) {
    echo "Brak szablonu!\n";
    exit(1);
}

$bus = $template->bus;
echo "Szablon: {$template->name}\n";
echo "transfer_km: {$template->transfer_km}\n";
echo "program_km: {$template->program_km}\n";
echo "duration_days: {$template->duration_days}\n";
if ($bus) {
    echo "--- Autobus ---\n";
    echo "capacity: {$bus->capacity}\n";
    echo "package_km_per_day: {$bus->package_km_per_day}\n";
    echo "package_price_per_day: {$bus->package_price_per_day}\n";
    echo "extra_km_price: {$bus->extra_km_price}\n";
} else {
    echo "Brak autobusu przypisanego do szablonu!\n";
}
