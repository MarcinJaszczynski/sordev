<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;

$slug = $argv[1] ?? 'test-insert-1756042852';
$q = BlogPost::where('slug', $slug)
    ->where('status', 'active')
    ->where('published_at', '<=', now());

echo "SQL: " . $q->toSql() . PHP_EOL;
echo "Bindings: " . json_encode($q->getBindings()) . PHP_EOL;
$post = $q->first();
if ($post) {
    echo "Found post id={$post->id}, title={$post->title}, deleted_at=" . ($post->deleted_at ?? 'NULL') . PHP_EOL;
    echo "Model class: " . get_class($post) . PHP_EOL;
    echo "Attributes: " . json_encode($post->getAttributes()) . PHP_EOL;
} else {
    echo "No post found for slug={$slug}.\n";
    // show counts matching individual clauses
    $countSlug = BlogPost::where('slug',$slug)->count();
    $countStatus = BlogPost::where('slug',$slug)->where('status','active')->count();
    $countPublished = BlogPost::where('slug',$slug)->where('status','active')->where('published_at','<=',now())->count();
    echo "Counts: by_slug={$countSlug}, by_status={$countStatus}, by_status_published={$countPublished}\n";
}
