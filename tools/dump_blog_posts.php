<?php
$dbFile = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbFile)) {
    fwrite(STDERR, "Database file not found: $dbFile\n");
    exit(1);
}
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->query("SELECT id, title, slug, status, published_at, created_at FROM blog_posts ORDER BY id DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "No blog posts found.\n";
    exit(0);
}
foreach ($rows as $r) {
    echo sprintf("%4d | %-30s | %-40s | %-8s | %-19s | %-19s\n", $r['id'], substr($r['title'] ?? '', 0, 30), $r['slug'] ?? '', $r['status'] ?? 'NULL', $r['published_at'] ?? 'NULL', $r['created_at'] ?? 'NULL');
}
