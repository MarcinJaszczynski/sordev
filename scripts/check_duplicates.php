<?php
// scripts/check_duplicates.php
// Finds duplicate price rows grouped by event_template_id,event_template_qty_id,currency_id,start_place_id
$dbFile = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbFile)) {
    echo json_encode(['error'=>'database file not found: '.$dbFile]);
    exit(1);
}
try {
    $pdo = new PDO('sqlite:'.$dbFile);
    $sql = "SELECT event_template_id, event_template_qty_id, currency_id, start_place_id, COUNT(*) AS cnt\n"
         . "FROM event_template_price_per_person\n"
         . "GROUP BY event_template_id, event_template_qty_id, currency_id, start_place_id\n"
         . "HAVING cnt>1\n"
         . "ORDER BY cnt DESC\n"
         . "LIMIT 50;";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error'=>$e->getMessage()]);
    exit(1);
}
