<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->handle(
    new Symfony\Component\Console\Input\ArgvInput([]),
    new Symfony\Component\Console\Output\NullOutput()
);

$contractorId = $argv[1] ?? 1950;
$db = Illuminate\Support\Facades\DB::table('contractors')->where('id', $contractorId)->first();
if (!$db) {
    echo "Contractor $contractorId not found\n";
    exit(0);
}
echo "Contractor: " . json_encode($db) . "\n\n";

$types = Illuminate\Support\Facades\DB::table('contractor_types')
    ->join('contractor_contractortype', 'contractor_types.id', '=', 'contractor_contractortype.contractor_type_id')
    ->where('contractor_contractortype.contractor_id', $contractorId)
    ->pluck('contractor_types.name');

echo "Types: " . json_encode($types) . "\n\n";

$contacts = Illuminate\Support\Facades\DB::table('contacts')
    ->join('contractor_contact', 'contacts.id', '=', 'contractor_contact.contact_id')
    ->where('contractor_contact.contractor_id', $contractorId)
    ->select('contacts.*', 'contractor_contact.id as pivot_id')
    ->get();

echo "Contacts linked: " . count($contacts) . "\n";
foreach ($contacts as $c) {
    echo json_encode($c) . "\n";
}
