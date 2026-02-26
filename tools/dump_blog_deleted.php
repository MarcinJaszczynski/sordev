<?php
$dbFile = __DIR__ . '/../database/database.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$stmt = $pdo->query("SELECT id, title, slug, status, published_at, deleted_at FROM blog_posts ORDER BY id DESC");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo sprintf("%4d | %-30s | %-40s | %-8s | %-19s | %-19s\n", $r['id'], substr($r['title'] ?? '',0,30), $r['slug'] ?? '', $r['status'] ?? 'NULL', $r['published_at'] ?? 'NULL', $r['deleted_at'] ?? 'NULL');
}
