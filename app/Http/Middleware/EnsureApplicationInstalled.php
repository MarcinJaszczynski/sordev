<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('install', 'install/*', 'up')) {
            return $next($request);
        }

        if (! Installer::isInstalled()) {
            return redirect()->route('install.index');
        }

        return $next($request);
    }
}
