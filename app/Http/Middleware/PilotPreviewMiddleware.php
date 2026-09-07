<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\User;
use App\Services\PilotContractorAssignmentService;
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
        $canPreview = $user && app(\App\Services\PilotAccessService::class)->isOfficeStaff($user);

        if ($canPreview && $request->boolean('preview')) {
            session([self::SESSION_MODE => true]);

            $pilotId = $request->filled('pilot')
                ? (int) $request->query('pilot')
                : $this->resolvePilotIdFromRoute($request);

            if ($pilotId) {
                $pilot = User::query()->find($pilotId);
                if ($pilot && $pilot->hasRole('pilot')) {
                    session([self::SESSION_USER_ID => $pilot->id]);
                    Log::info('pilot_portal_preview_start', [
                        'staff_user_id' => $user->id,
                        'pilot_user_id' => $pilot->id,
                    ]);
                } else {
                    session()->forget(self::SESSION_USER_ID);
                }
            } else {
                // Bez ?pilot= nie trzymaj poprzedniego pilota z innej imprezy / sesji.
                session()->forget(self::SESSION_USER_ID);
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
            if ($user?->hasRole('pilot') && ! app(\App\Services\PilotAccessService::class)->isOfficeStaff($user)) {
                $this->clearPreviewSession();
            }
        }

        return $next($request);
    }

    /**
     * Gdy ?preview=1 bez ?pilot=, spróbuj wyciągnąć pilota z imprezy w URL
     * (np. /pilot/pilot-events/{record}?preview=1).
     */
    private function resolvePilotIdFromRoute(Request $request): ?int
    {
        foreach (['record', 'event'] as $param) {
            $value = $request->route($param);
            if ($value instanceof Event) {
                return app(PilotContractorAssignmentService::class)
                    ->resolvePortalUserIdForEvent($value);
            }

            if (is_numeric($value)) {
                $event = Event::query()->find((int) $value);

                return $event
                    ? app(PilotContractorAssignmentService::class)->resolvePortalUserIdForEvent($event)
                    : null;
            }
        }

        return null;
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
