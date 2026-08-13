<?php

namespace App\Http\Middleware;

use App\Models\EventPortalAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ClientPreviewMiddleware
{
    public const SESSION_MODE = 'client_preview_mode';

    public const SESSION_ACCESS_ID = 'client_preview_access_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $canPreview = $user && $user->hasRole(['admin', 'super_admin', 'biuro']);

        if ($canPreview && $request->boolean('preview')) {
            session([self::SESSION_MODE => true]);

            if ($request->filled('access') && Schema::hasTable('event_portal_accesses')) {
                $accessId = (int) $request->query('access');
                $access = EventPortalAccess::query()->active()->find($accessId);
                if ($access) {
                    session([self::SESSION_ACCESS_ID => $access->id]);
                    Log::info('client_portal_preview_start', [
                        'staff_user_id' => $user->id,
                        'access_id' => $access->id,
                        'event_id' => $access->event_id,
                        'role' => $access->role,
                        'client_user_id' => $access->user_id,
                    ]);
                }
            }
        }

        // Wyjście z podglądu: ?preview=0 / exit_preview=1 albo wejście na portal bez flagi preview
        if ($request->query->has('preview') && ! $request->boolean('preview')) {
            $this->clearPreviewSession();
        }

        if ($request->boolean('exit_preview')) {
            $this->clearPreviewSession();
        }

        if ($request->routeIs('filament.portal.*')
            && ! $request->boolean('preview')
            && ! $request->boolean('exit_preview')
            && ! $request->query->has('preview')
        ) {
            // Nie trzymaj preview przy normalnym korzystaniu z portalu jako klient.
            if ($user?->hasRole(['client_participant', 'client_guardian'])) {
                $this->clearPreviewSession();
            }
        }

        return $next($request);
    }

    public static function isActive(): bool
    {
        return (bool) session(self::SESSION_MODE, false);
    }

    public static function accessId(): ?int
    {
        $id = session(self::SESSION_ACCESS_ID);

        return $id ? (int) $id : null;
    }

    public static function clearPreviewSession(): void
    {
        session()->forget([self::SESSION_MODE, self::SESSION_ACCESS_ID]);
    }
}
