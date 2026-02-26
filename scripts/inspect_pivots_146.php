<?php
$dbPath = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbPath)) {
    echo "Database not found: $dbPath\n";
    exit(1);
}
$db = new PDO('sqlite:' . $dbPath);
$sql = <<<'SQL'
SELECT p.id as pivot_id, p.event_template_program_point_id, etpp.name as point_name,
       p.include_in_calculation, p.include_in_program, p.day, p."order", etpp.parent_id
FROM event_template_event_template_program_point p
LEFT JOIN event_template_program_points etpp ON etpp.id = p.event_template_program_point_id
WHERE p.event_template_id = 146
ORDER BY p.day, p."order";
SQL;
$stmt = $db->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "No pivot rows found for template 146\n";
    exit(0);
}
foreach ($rows as $r) {
    echo sprintf("pivot_id=%d | point_id=%d | name=%s | parent_id=%s | day=%s | order=%s | include_in_calc=%s | include_in_program=%s\n",
        $r['pivot_id'], $r['event_template_program_point_id'], $r['point_name'] ?? 'NULL', $r['parent_id'] ?? 'NULL', $r['day'] ?? 'NULL', $r['order'] ?? 'NULL', $r['include_in_calculation'] ?? 'NULL', $r['include_in_program'] ?? 'NULL'
    );
}
