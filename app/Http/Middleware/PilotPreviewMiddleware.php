<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PilotPreviewMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->boolean('preview') && Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole(['admin', 'super_admin', 'biuro'])) {
                session(['pilot_preview_mode' => true]);
            }
        }

        if (! $request->boolean('preview') && $request->routeIs('filament.pilot.*') && ! session('pilot_preview_mode')) {
            session()->forget('pilot_preview_mode');
        }

        return $next($request);
    }

    public static function isActive(): bool
    {
        return (bool) session('pilot_preview_mode', false);
    }
}
