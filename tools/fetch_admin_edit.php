<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$id = $argv[1] ?? 3;
$request = Illuminate\Http\Request::create('/admin/blog-posts/' . $id . '/edit', 'GET');
$response = $app->handle($request);
$html = $response->getContent();
file_put_contents(__DIR__ . '/admin_blog_posts_edit.html', $html);
echo "Saved HTML to tools/admin_blog_posts_edit.html\n";
