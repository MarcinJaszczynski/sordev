<?php
$dst = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dst)) {
    echo "Destination DB not found: $dst\n";
    exit(1);
}
$pdo = new PDO('sqlite:' . $dst);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$id = $argv[1] ?? 282;
function q($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

echo "Checking event_template id={$id} in $dst\n\n";

// event_templates
$stmt = q($pdo, 'SELECT * FROM event_templates WHERE id = ?', [$id]);
$tpl = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tpl) {
    echo "event_templates: NOT FOUND (id={$id})\n";
} else {
    echo "event_templates: FOUND\n";
    echo " - id: " . ($tpl['id'] ?? '') . "\n";
    echo " - name: " . ($tpl['name'] ?? '') . "\n";
    echo " - slug: " . ($tpl['slug'] ?? '') . "\n";
    echo " - duration_days: " . ($tpl['duration_days'] ?? '') . "\n";
}

$tables = [
    'event_template_price_per_person',
    'event_template_event_template_program_point',
    'event_template_starting_place_availability',
    'event_template_day_insurance',
    'event_template_program_points',
    'event_template_qties',
    'event_template_tag'
];

foreach ($tables as $t) {
    $stmt = q($pdo, "SELECT COUNT(*) as cnt FROM $t WHERE event_template_id = ?", [$id]);
    $cnt = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    echo "{$t}: rows for event_template_id={$id}: {$cnt}\n";
    if ($cnt > 0) {
        $s = q($pdo, "SELECT * FROM $t WHERE event_template_id = ? LIMIT 5", [$id]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            echo " - sample: ";
            $k = [];
            foreach ($r as $col => $val) {
                $k[] = "$col=" . (is_null($val) ? 'NULL' : (strlen($val) > 60 ? substr($val,0,60)."..." : $val));
            }
            echo implode(', ', $k) . "\n";
        }
    }
}

// Additionally check program point pivots by program point id
if ($tpl) {
    $stmt = q($pdo, 'SELECT id FROM event_template_program_points WHERE event_template_id = ? LIMIT 5', [$id]);
    $pp = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\nevent_template_program_points ids (sample): " . implode(',', $pp) . "\n";
}

echo "\nPRAGMA integrity_check: ";
$res = $pdo->query('PRAGMA integrity_check')->fetchColumn();
echo $res . "\n";

echo "Done.\n";
