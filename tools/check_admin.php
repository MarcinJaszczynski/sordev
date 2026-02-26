<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = App\Models\User::where('email', 'admin@example.com')->first();
if ($u) {
    $roles = implode(', ', $u->roles->pluck('name')->toArray());
    $has = $u->hasPermissionTo('create blog post') ? 'yes' : 'no';
    echo "exists; roles: {$roles}; has create blog post? {$has}\n";
} else {
    echo "no user\n";
}
