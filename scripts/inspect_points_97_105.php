<?php
$dbPath = __DIR__ . '/../database/database.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$sql = "SELECT id, name, currency_id, unit_price, group_size, convert_to_pln FROM event_template_program_points WHERE id IN (97,105)";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
