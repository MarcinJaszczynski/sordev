<?php
$dbPath = __DIR__ . '/../database/database.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$sql = "SELECT parent_id, child_id FROM event_template_program_point_parent WHERE child_id IN (97,105)";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
