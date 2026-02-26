<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = 'm.jaszczynski@gmail.com';
$u = App\Models\User::where('email', $email)->first();
if ($u) {
    $roles = implode(', ', $u->roles->pluck('name')->toArray());
    $perms = implode(', ', $u->getAllPermissions()->pluck('name')->toArray());
    $has = $u->hasPermissionTo('create blog post') ? 'yes' : 'no';
    echo "user: {$u->name} <{$u->email}>\nroles: {$roles}\npermissions contain 'create blog post': {$has}\nall permissions: {$perms}\n";
} else {
    echo "no user with email {$email}\n";
}
