<?php
// Usage: php tools/test_request.php /warszawa/blog/test-insert-1756042852
$path = $argv[1] ?? '/';
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create($path, 'GET');
try {
    $response = $kernel->handle($request);
    $code = $response->getStatusCode();
    echo "Status: $code\n";
    $content = $response->getContent();
    echo "Content length: " . strlen($content) . "\n";
    echo substr($content, 0, 1000) . "\n";
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo "Exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
