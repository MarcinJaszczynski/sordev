<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Znajdź pierwszy punkt i dodaj HTML
$point = App\Models\EventTemplateProgramPoint::first();
if ($point) {
    $originalDescription = $point->description;
    echo "Original description: " . $originalDescription . PHP_EOL;
    
    // Dodaj HTML tags
    $point->description = '<p>Est autem dolor sequ.</p><strong>Test HTML content</strong><br>Nowa linia z formatowaniem.';
    $point->save();
    
    echo "Updated description: " . $point->description . PHP_EOL;
    echo "Point ID: " . $point->id . PHP_EOL;
    echo "Point Name: " . $point->name . PHP_EOL;
} else {
    echo "No points found" . PHP_EOL;
}
