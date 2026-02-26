<?php

// Bootstrap Laravel and fetch the admin documents index to detect server exceptions.
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/admin/resources/documents', 'GET');
$response = $kernel->handle($request);

$status = $response->getStatusCode();
$body = $response->getContent();

file_put_contents(__DIR__ . '/admin_documents.html', $body);

echo "Status: $status, saved to tools/admin_documents.html\n";

$kernel->terminate($request, $response);
