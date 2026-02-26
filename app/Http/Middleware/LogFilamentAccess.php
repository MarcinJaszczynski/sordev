<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogFilamentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/*')) {
            Log::info('Filament Access Attempt', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'has_session' => $request->hasSession(),
                'session_id' => $request->session()->getId() ?? 'none',
            ]);
        }

        $response = $next($request);

        if ($request->is('admin/*')) {
            Log::info('Filament Response', [
                'status' => $response->status(),
                'url' => $request->fullUrl(),
            ]);
        }

        return $response;
    }
}
