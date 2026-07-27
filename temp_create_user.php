<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::firstOrCreate(
    ['email' => 'aleksander.jaszcz@gmail.com'],
    [
        'name' => 'Aleksander',
        'password' => Hash::make('admin1234'),
        'type' => 'user',
        'status' => 'active',
    ]
);

if (! $user->hasRole('admin')) {
    $user->assignRole('admin');
}

echo 'DONE';
