<?php
$db = __DIR__ . '/../database/database.sqlite';
$pdo = new PDO('sqlite:'.$db);
$pairs = [[23,3],[3,23]];
foreach ($pairs as $p) {
    [$from,$to] = $p;
    $stmt = $pdo->prepare('SELECT * FROM place_distances WHERE from_place_id = ? AND to_place_id = ?');
    $stmt->execute([$from,$to]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "From $from to $to:\n";
    if (empty($rows)) echo "  NONE\n";
    foreach ($rows as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
}
