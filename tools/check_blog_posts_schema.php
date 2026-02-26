<?php
$db = new SQLite3(__DIR__ . '/../database/database.sqlite');
$res = $db->query("PRAGMA table_info('blog_posts')");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
    echo $r['cid'] . "\t" . $r['name'] . "\t" . $r['type'] . PHP_EOL;
}
