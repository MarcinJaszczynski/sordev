<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Storage;
use App\Models\EventTemplate;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = (int)($argv[1] ?? 31);
$et = EventTemplate::find($id);

if (!$et) {
    echo "EventTemplate not found: {$id}" . PHP_EOL;
    exit(1);
}

echo "ID: {$et->id}" . PHP_EOL;
echo "featured_image (raw): " . ($et->getRawOriginal('featured_image') ?? 'null') . PHP_EOL;
echo "featured_image (normalized): " . ($et->featured_image ?? 'null') . PHP_EOL;

$fi = $et->featured_image;
if ($fi) {
    $diskUrl = rtrim((string) config('filesystems.disks.public.url'), '/');
    $fullUrl = $diskUrl . '/' . ltrim($fi, '/');
    echo "featured_image URL: " . $fullUrl . PHP_EOL;
    $path = storage_path('app/public/' . ltrim($fi, '/'));
    echo "featured_image exists: " . (file_exists($path) ? 'YES' : 'NO') . ' -> ' . $path . PHP_EOL;
}

$galleryRaw = $et->getRawOriginal('gallery');
echo "gallery (raw): " . ($galleryRaw ?? 'null') . PHP_EOL;
$gallery = $et->gallery ?? [];
echo "gallery (normalized): " . json_encode($gallery) . PHP_EOL;

foreach ($gallery as $idx => $g) {
    $diskUrl = rtrim((string) config('filesystems.disks.public.url'), '/');
    $fullUrl = $diskUrl . '/' . ltrim($g, '/');
    echo " - [{$idx}] URL: " . $fullUrl . PHP_EOL;
    $gPath = storage_path('app/public/' . ltrim($g, '/'));
    echo "   exists: " . (file_exists($gPath) ? 'YES' : 'NO') . ' -> ' . $gPath . PHP_EOL;
}

echo "Disk public root: " . config('filesystems.disks.public.root') . PHP_EOL;
echo "Disk public url: " . config('filesystems.disks.public.url') . PHP_EOL;
echo "APP_URL: " . config('app.url') . PHP_EOL;

echo "Done." . PHP_EOL;
