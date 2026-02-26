<?php
$dbPath = __DIR__ . '/../database/database.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$sql = <<<'SQL'
SELECT p.id as point_id, p.name as point_name, p.parent_id,
       piv.id as pivot_id, piv.include_in_calculation, piv.include_in_program, piv.day, piv."order"
FROM event_template_program_points p
LEFT JOIN event_template_event_template_program_point piv ON piv.event_template_program_point_id = p.id AND piv.event_template_id = 146
WHERE p.parent_id = 104
ORDER BY p.id;
SQL;
$stmt = $db->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "No children found for parent 104\n";
    exit(0);
}
foreach ($rows as $r) {
    echo sprintf("point_id=%d | name=%s | pivot_id=%s | day=%s | order=%s | include_in_calc=%s | include_in_program=%s\n",
        $r['point_id'], $r['point_name'] ?? 'NULL', $r['pivot_id'] ?? 'NULL', $r['day'] ?? 'NULL', $r['order'] ?? 'NULL', $r['include_in_calculation'] ?? 'NULL', $r['include_in_program'] ?? 'NULL'
    );
}
