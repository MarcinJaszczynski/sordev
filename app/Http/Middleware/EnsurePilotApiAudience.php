<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audience API pilota: tylko rola pilot + kontekst panelu pilot
 * (spójne z PilotAccessService poza Filament UI).
 */
class EnsurePilotApiAudience
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('pilot')) {
            abort(403, 'Endpoint dostępny tylko dla pilota.');
        }

        Filament::setCurrentPanel(Filament::getPanel('pilot'));
        Filament::setServingStatus(true);

        return $next($request);
    }
}
