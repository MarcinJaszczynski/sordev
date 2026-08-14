<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Egzekwuje abilities Sanctum tylko dla Personal Access Token.
 * Sesja SPA / actingAs (TransientToken lub brak tokena) przechodzi —
 * audience (api.pilot / api.client) i Gate i tak pilnują roli.
 */
class EnsureApiTokenAbility
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $token = method_exists($user, 'currentAccessToken')
            ? $user->currentAccessToken()
            : null;

        if ($token === null || $token instanceof TransientToken) {
            return $next($request);
        }

        foreach ($abilities as $ability) {
            if (! $user->tokenCan($ability)) {
                abort(403, 'Token nie ma wymaganego uprawnienia: '.$ability);
            }
        }

        return $next($request);
    }
}
