<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Run migrations to ensure table exists
try {
    \Artisan::call('migrate', ['--force' => true]);
    echo "Migrations run.\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}

// Run ContractorsSeeder programmatically
$seeder = new \Database\Seeders\ContractorsSeeder();
$seeder->run();

echo "ContractorsSeeder finished.\n";
