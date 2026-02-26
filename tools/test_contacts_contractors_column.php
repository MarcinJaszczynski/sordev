<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$contacts = DB::table('contacts')->limit(10)->get();
foreach ($contacts as $c) {
    echo $c->id.' - '.($c->first_name ?? '').' '.($c->last_name ?? '')."\n";
}

// load with model to check relation
use App\Models\Contact;
$models = Contact::with('contractors')->limit(10)->get();
foreach ($models as $m) {
    echo $m->id.' contractors: '.($m->contractors->pluck('name')->implode(', ') ?: '[none]')."\n";
}
