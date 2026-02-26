<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Login first available user if exists
$user = \App\Models\User::first();
if ($user) {
    \Illuminate\Support\Facades\Auth::login($user);
}

$request = \Illuminate\Http\Request::create(
    '/radom/5-dniowe/561/ryga-tallin-sztokholm',
    'POST',
    [
        'organization_name' => 'Testowa szkoła',
        'contact_person' => 'Jan Kowalski',
        'contact_phone' => '600000000',
        'contact_email' => 'test@example.com',
        'additional_notes' => "Linia 1\nLinia 2",
    ]
);

$controller = $app->make(\App\Http\Controllers\Front\FrontController::class);
$response = $controller->packagePrettyWord($request, 'radom', '5-dniowe', 561, 'ryga-tallin-sztokholm');

$file = $response->getFile();
$path = $file->getPathname();

fwrite(STDOUT, "Generated file: {$path}\n");
$handle = fopen($path, 'rb');
$prefix = bin2hex(fread($handle, 4));
fclose($handle);

fwrite(STDOUT, "First bytes: {$prefix}\n");
