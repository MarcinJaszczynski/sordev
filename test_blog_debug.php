<?php
// Quick debug - sprawdź co zwraca kontroler blog
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$controller = new App\Http\Controllers\Front\FrontController();
$request = new Illuminate\Http\Request();
$response = $controller->blog($request);
$data = $response->getData();

echo "=== BLOG DEBUG ===\n";
echo "Posts count: " . $data['posts']->count() . "\n";
echo "Featured count: " . $data['featuredPosts']->count() . "\n";
echo "\nPosts:\n";
foreach($data['posts'] as $post) {
    echo "  - ID: {$post->id} | Title: {$post->title}\n";
    echo "    Image: " . ($post->featured_image ?? 'NULL') . "\n";
    echo "    URL: " . asset('storage/' . $post->featured_image) . "\n";
}

echo "\n=== TEMPLATE CHECK ===\n";
echo "View name: " . $response->name() . "\n";
echo "View path: " . $response->getPath() . "\n";
echo "File exists: " . (file_exists($response->getPath()) ? 'YES' : 'NO') . "\n";
echo "File modified: " . date('Y-m-d H:i:s', filemtime($response->getPath())) . "\n";
