<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->handle(
    new Symfony\Component\Console\Input\ArgvInput([]),
    new Symfony\Component\Console\Output\NullOutput()
);

$contacts = Illuminate\Support\Facades\DB::table('contacts')->limit(10)->get();
echo "Contacts sample:\n";
foreach ($contacts as $c) {
    echo json_encode($c) . "\n";
}

echo "\nPivot sample:\n";
$piv = Illuminate\Support\Facades\DB::table('contractor_contact')->limit(10)->get();
foreach ($piv as $p) {
    echo json_encode($p) . "\n";
}
