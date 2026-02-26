<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find user by email and set as currently authenticated for the facade
$user = App\Models\User::where('email','m.jaszczynski@gmail.com')->first();
if (! $user) {
    echo "user not found\n";
    exit(1);
}

Illuminate\Support\Facades\Auth::loginUsingId($user->id);

// Call the resource canCreate()
$can = App\Filament\Resources\BlogPostResource::canCreate();

echo 'BlogPostResource::canCreate() => ' . ($can ? 'true' : 'false') . "\n";
