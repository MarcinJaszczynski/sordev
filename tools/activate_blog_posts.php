<?php
// Simple script to mark blog posts as active and set published_at to now when missing or in the future.
// Run: php tools/activate_blog_posts.php

$dbFile = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbFile)) {
    fwrite(STDERR, "Database file not found: $dbFile\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Update status -> active where not active
    $stmt = $pdo->prepare("UPDATE blog_posts SET status = 'active' WHERE status IS NULL OR status != 'active'");
    $stmt->execute();
    $updatedStatus = $stmt->rowCount();

    // Update published_at -> now where null or in future
    $stmt2 = $pdo->prepare("UPDATE blog_posts SET published_at = datetime('now') WHERE published_at IS NULL OR published_at > datetime('now')");
    $stmt2->execute();
    $updatedPublished = $stmt2->rowCount();

    echo "Updated status rows: {$updatedStatus}\n";
    echo "Updated published_at rows: {$updatedPublished}\n";

    // Count active posts
    $count = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'active' AND (published_at <= datetime('now'))")->fetchColumn();
    echo "Active & published posts count: {$count}\n";

} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

exit(0);
