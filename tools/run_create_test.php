<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\EventTemplate;
use App\Services\EventPriceCalculator;

$t = EventTemplate::find(1);
if (!$t) {
    echo "No template 1\n";
    exit(1);
}

$e = App\Models\Event::createFromTemplate($t, [
    'name' => 'Test from template',
    'start_date' => now()->toDateString(),
    'end_date' => now()->addDays(2)->toDateString(),
    'participant_count' => 20,
]);

echo "Event created: {$e->id}\n";

$calc = new EventPriceCalculator();
$calc->calculateForEvent($e);

$count = App\Models\EventPricePerPerson::where('event_id', $e->id)->count();

echo "Prices count: {$count}\n";
