<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PilotPreviewMiddleware
{
    public const SESSION_MODE = 'pilot_preview_mode';

    public const SESSION_USER_ID = 'pilot_preview_user_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $canPreview = $user && $user->hasRole(['admin', 'super_admin', 'biuro']);

        if ($canPreview && $request->boolean('preview')) {
            session([self::SESSION_MODE => true]);

            if ($request->filled('pilot')) {
                $pilotId = (int) $request->query('pilot');
                $pilot = User::query()->find($pilotId);
                if ($pilot && $pilot->hasRole('pilot')) {
                    session([self::SESSION_USER_ID => $pilot->id]);
                    Log::info('pilot_portal_preview_start', [
                        'staff_user_id' => $user->id,
                        'pilot_user_id' => $pilot->id,
                    ]);
                }
            }
        }

        if ($request->query->has('preview') && ! $request->boolean('preview')) {
            $this->clearPreviewSession();
        }

        if ($request->boolean('exit_preview')) {
            $this->clearPreviewSession();
        }

        if ($request->routeIs('filament.pilot.*')
            && ! $request->boolean('preview')
            && ! $request->boolean('exit_preview')
            && ! $request->query->has('preview')
        ) {
            if ($user?->hasRole('pilot') && ! $user->hasRole(['admin', 'super_admin', 'biuro'])) {
                $this->clearPreviewSession();
            }
        }

        return $next($request);
    }

    public static function isActive(): bool
    {
        return (bool) session(self::SESSION_MODE, false);
    }

    public static function previewUserId(): ?int
    {
        $id = session(self::SESSION_USER_ID);

        return $id ? (int) $id : null;
    }

    public static function clearPreviewSession(): void
    {
        session()->forget([self::SESSION_MODE, self::SESSION_USER_ID]);
    }
}
