<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo route('blog.global') . PHP_EOL;
echo route('blog.post.global', 'test-insert-1756042852') . PHP_EOL;
