<?php
if ($argc < 2) {
    echo "Usage: php tools/check_slug.php slug\n";
    exit(1);
}
$slug = $argv[1];
$dbFile = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbFile)) { fwrite(STDERR, "DB not found\n"); exit(2); }
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->prepare("SELECT id, title, slug, status, published_at FROM blog_posts WHERE slug = :slug");
$stmt->execute([':slug' => $slug]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo "No row with slug: $slug\n"; exit(0); }
echo "Found: id={$row['id']}, title={$row['title']}, status={$row['status']}, published_at={$row['published_at']}\n";
// Check conditions
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = :slug AND status = 'active' AND published_at <= datetime('now')");
$stmt2->execute([':slug' => $slug]);
$ok = $stmt2->fetchColumn();
echo "Matches controller conditions (active & published<=now): " . ($ok ? 'YES' : 'NO') . "\n";
