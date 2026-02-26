<?php
$dbFile = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbFile)) { echo "DB not found: $dbFile\n"; exit(1); }
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "PRAGMA table_info(place_distances):\n";
$stmt = $pdo->query("PRAGMA table_info('place_distances')");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "  {$c['cid']} | {$c['name']} | {$c['type']} | notnull={$c['notnull']} | dflt_value={$c['dflt_value']} | pk={$c['pk']}\n";
}

echo "\nSample rows (limit 10):\n";
try {
    $s = $pdo->query('SELECT * FROM place_distances LIMIT 10');
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Exception $e) {
    echo "Cannot select from place_distances: " . $e->getMessage() . "\n";
}
