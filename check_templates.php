<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventTemplate;

echo "=== Sprawdzanie szablonów wydarzeń ===\n";

$templates = EventTemplate::latest()->take(10)->get();

echo "Liczba szablonów w bazie: " . EventTemplate::count() . "\n";
echo "Ostatnie 10 szablonów:\n";

foreach ($templates as $template) {
    echo "ID: {$template->id}, Nazwa: {$template->name}\n";
}

// Sprawdź czy są szablony z ID = null
$nullIdTemplates = EventTemplate::whereNull('id')->count();
echo "\nSzablony z ID = null: {$nullIdTemplates}\n";

// Sprawdź najwyższe ID
$maxId = EventTemplate::max('id');
echo "Najwyższe ID: {$maxId}\n";
