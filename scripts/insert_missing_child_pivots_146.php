<?php
$dbPath = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbPath)) {
    echo "Database not found: $dbPath\n";
    exit(1);
}
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$now = date('Y-m-d H:i:s');
$eventTemplateId = 146;
// Get parent pivot (point 104) to copy day/order
$stmt = $db->prepare('SELECT day, "order" FROM event_template_event_template_program_point WHERE event_template_id = ? AND event_template_program_point_id = ? LIMIT 1');
$stmt->execute([$eventTemplateId, 104]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);
$day = $parent['day'] ?? 2;
$baseOrder = is_numeric($parent['order']) ? floatval($parent['order']) : 4;
$children = [
    ['id' => 97, 'order' => $baseOrder + 0.1],
    ['id' => 105, 'order' => $baseOrder + 0.2],
];
$ins = $db->prepare("INSERT INTO event_template_event_template_program_point (event_template_id, event_template_program_point_id, day, \"order\", include_in_program, include_in_calculation, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$check = $db->prepare('SELECT COUNT(*) FROM event_template_event_template_program_point WHERE event_template_id = ? AND event_template_program_point_id = ?');
foreach ($children as $c) {
    $check->execute([$eventTemplateId, $c['id']]);
    $count = (int)$check->fetchColumn();
    if ($count > 0) {
        echo "Pivot already exists for point {$c['id']}\n";
        continue;
    }
    $ins->execute([$eventTemplateId, $c['id'], $day, $c['order'], 1, 1, 1, $now, $now]);
    echo "Inserted pivot for point {$c['id']} (day={$day}, order={$c['order']})\n";
}

echo "Done.\n";
