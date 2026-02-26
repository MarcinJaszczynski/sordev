<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// login as user
$user = App\Models\User::where('email','m.jaszczynski@gmail.com')->first();
if (! $user) {
    echo "no user\n"; exit(1);
}
Illuminate\Support\Facades\Auth::loginUsingId($user->id);

// create request and handle
$request = Illuminate\Http\Request::create('/admin/blog-posts', 'GET');
$response = app()->handle($request);
$html = $response->getContent();
file_put_contents(__DIR__ . '/admin_blog_posts.html', $html);
echo "Saved HTML to tools/admin_blog_posts.html\n";
