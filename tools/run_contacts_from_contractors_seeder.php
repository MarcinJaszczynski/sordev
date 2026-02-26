<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->handle(
    new Symfony\Component\Console\Input\ArgvInput([]),
    new Symfony\Component\Console\Output\NullOutput()
);

// run seeder
$seeder = new Database\Seeders\ContactsFromContractorsSeeder();
$seeder->setContainer($app);
$seeder->run();

echo "ContactsFromContractorsSeeder finished\n";
