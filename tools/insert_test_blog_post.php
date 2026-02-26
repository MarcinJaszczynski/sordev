<?php
require __DIR__ . '/../vendor/autoload.php';

// bootstrap application minimal
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;

$post = new BlogPost();
$post->title = 'Test insertion ' . time();
$post->slug = 'test-insert-' . time();
$post->excerpt = 'Quick test excerpt';
$post->content = '<p>Test content</p>';
$post->featured_image = null;
$post->gallery = [];
$post->is_featured = false;
$post->is_published = true;
$post->published_at = now();
$post->save();

echo "Inserted post id: " . $post->id . PHP_EOL;
