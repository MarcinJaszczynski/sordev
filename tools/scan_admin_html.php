<?php
$h = file_get_contents(__DIR__ . '/admin_blog_posts.html');
$patterns = ['Utworz','Utwórz','/create','/blog-posts/create','Create Blog','Create','add','Dodaj'];
foreach ($patterns as $p) {
    if (stripos($h, $p) !== false) {
        echo "Found pattern: $p\n";
    }
}
// show snippet around possible action buttons (search for "href=\"/admin/blog-posts/create" or data-action)
if (preg_match('/href="([^"]*\/create[^"]*)"/i', $h, $m)) {
    echo "Found href create: {$m[1]}\n";
}
if (preg_match('/data-action="create"/i', $h, $m)) {
    echo "Found data-action create\n";
}

// show top 300 chars
echo "---TOP---\n" . substr($h,0,1000) . "\n---END---\n";
