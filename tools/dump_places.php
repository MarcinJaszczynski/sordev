<?php
$db = __DIR__ . '/../database/database.sqlite';
$pdo = new PDO('sqlite:' . $db);
$stmt = $pdo->query('SELECT id, name FROM places ORDER BY id');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo $r['id'] . ' | ' . $r['name'] . "\n";
}
