<?php
$db = new SQLite3(__DIR__ . '/../database/database.sqlite');
$res = $db->query('SELECT id, title, gallery, created_at FROM blog_posts ORDER BY created_at DESC LIMIT 5');
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
