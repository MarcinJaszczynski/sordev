<?php
$dbPath = __DIR__ . '/../database/database.sqlite';
$db = new PDO('sqlite:' . $dbPath);

echo "-- children via parent_id in event_template_program_points --\n";
$sql1 = <<<'SQL'
SELECT id, name, parent_id FROM event_template_program_points WHERE parent_id = 104;
SQL;
$rows1 = $db->query($sql1)->fetchAll(PDO::FETCH_ASSOC);
if (!$rows1) {
    echo "(none)\n";
} else {
    foreach ($rows1 as $r) {
        echo sprintf("point_id=%d | name=%s | parent_id=%s\n", $r['id'], $r['name'], $r['parent_id']);
    }
}

echo "\n-- children via event_template_program_point_parent table --\n";
$sql2 = <<<'SQL'
SELECT p.parent_id, p.child_id, etpp.name as child_name
FROM event_template_program_point_parent p
LEFT JOIN event_template_program_points etpp ON etpp.id = p.child_id
WHERE p.parent_id = 104;
SQL;
$rows2 = $db->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
if (!$rows2) {
    echo "(none)\n";
} else {
    foreach ($rows2 as $r) {
        echo sprintf("parent_id=%s | child_id=%s | child_name=%s\n", $r['parent_id'], $r['child_id'], $r['child_name']);
    }
}

// If children found, list their pivots for template 146
$childIds = array_map(fn($r) => $r['child_id'], $rows2);
if (!empty($childIds)) {
    echo "\n-- pivot rows for these children in template 146 --\n";
    $in = implode(',', array_map('intval', $childIds));
    $sql3 = "SELECT * FROM event_template_event_template_program_point WHERE event_template_id = 146 AND event_template_program_point_id IN ($in) ORDER BY day, \"order\"";
    $rows3 = $db->query($sql3)->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows3) {
        echo "(no pivots)\n";
    } else {
        foreach ($rows3 as $r) {
            echo sprintf("pivot_id=%d | point_id=%d | day=%s | order=%s | include_in_calc=%s | include_in_program=%s\n", $r['id'], $r['event_template_program_point_id'], $r['day'] ?? 'NULL', $r['order'] ?? 'NULL', $r['include_in_calculation'] ?? 'NULL', $r['include_in_program'] ?? 'NULL');
        }
    }
}
