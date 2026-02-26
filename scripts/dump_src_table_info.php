<?php
$path = __DIR__ . '/../database/database.sqlite';
$src = __DIR__ . '/../database/database_26_08.sqlite';
$db = new PDO('sqlite:' . $path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("ATTACH DATABASE '$src' AS src");
$tables = ['event_template_price_per_person','event_template_event_template_program_point','event_template_program_point_child_pivot','jobs','event_template_starting_place_availability'];
foreach ($tables as $t) {
    echo "Table src.$t\n";
    $cols = $db->query("PRAGMA src.table_info('$t')")->fetchAll(PDO::FETCH_ASSOC);
    if (!$cols) { echo " - no cols or table missing in src\n\n"; continue; }
    foreach ($cols as $c) {
        echo sprintf("  %s (%s) pk=%s default=%s\n", $c['name'], $c['type'], $c['pk'], $c['dflt_value']);
    }
    echo "\n";
}
$db->exec("DETACH DATABASE src");
