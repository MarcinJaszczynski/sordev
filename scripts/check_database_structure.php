<?php
$path = __DIR__ . '/../database/database.sqlite';
if (!file_exists($path)) { echo "DB not found: $path\n"; exit(2); }
$size = filesize($path);
echo "DB: $path\nSize: $size bytes\n\n";
try {
    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "-> PRAGMA integrity_check:\n";
    $res = $db->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($res as $r) echo " - $r\n";
    echo "\n";

    $tables = $db->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    echo "Tables found: " . count($tables) . "\n\n";

    foreach ($tables as $t) {
        $name = $t['name'];
        $cols = $db->query("PRAGMA table_info('" . $name . "')")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_map(fn($c) => $c['name'] . '(' . $c['type'] . ')', $cols);
        echo "- $name: " . count($cols) . " cols -> " . implode(', ', array_slice($colNames,0,10));
        if (count($colNames) > 10) echo " ... (" . (count($colNames)-10) . " more)";
        echo "\n";
    }

    $critical = ['event_templates','event_template_program_points','event_template_event_template_program_point','event_template_price_per_person','event_template_day_insurance'];
    echo "\nDetailed inspection for critical tables:\n";
    foreach ($critical as $ct) {
        echo "\n-- $ct --\n";
        $cols = $db->query("PRAGMA table_info('" . $ct . "')")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo sprintf("%s\t%s\tdefault=%s\n", $c['name'], $c['type'], $c['dflt_value']);
        }
        $count = $db->query("SELECT COUNT(*) FROM \"$ct\"")->fetchColumn();
        echo "Rows: $count\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(3);
}
