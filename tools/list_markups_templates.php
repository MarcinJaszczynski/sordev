<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Markup;
use App\Models\EventTemplate;

$markups = Markup::all();
foreach ($markups as $m) {
    echo "Markup id={$m->id} percent={$m->percent} is_default=".($m->is_default? '1':'0')."\n";
    $templates = EventTemplate::where('markup_id', $m->id)->get();
    if ($templates->isEmpty()) {
        echo "  used by: (none)\n";
    } else {
        foreach ($templates as $t) {
            echo "  used by template id={$t->id} name={$t->name} markup_percent_field=".($t->markup_percent ?? 'null')."\n";
        }
    }
}

// also list templates with markup_percent field set explicitly
$templatesWithPercent = EventTemplate::whereNotNull('markup_percent')->where('markup_percent','!=',0)->get();
if (!$templatesWithPercent->isEmpty()) {
    echo "\nTemplates with explicit markup_percent set:\n";
    foreach ($templatesWithPercent as $t) {
        echo "  id={$t->id} name={$t->name} markup_percent={$t->markup_percent} markup_id=".($t->markup_id ?? 'null')."\n";
    }
}
