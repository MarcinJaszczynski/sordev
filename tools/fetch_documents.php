<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \Illuminate\Http\Request::create('/documents', 'GET');
$response = $app->handle($request);

file_put_contents(__DIR__ . '/front_documents.html', (string)$response->getContent());
echo "Saved HTML to tools/front_documents.html\n";
