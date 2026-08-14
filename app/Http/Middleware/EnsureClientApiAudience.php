<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audience API klienta: uczestnik/opiekun + kontekst panelu portal.
 */
class EnsureClientApiAudience
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(['client_participant', 'client_guardian'])) {
            abort(403, 'Endpoint dostępny tylko dla konta portalu klienta.');
        }

        Filament::setCurrentPanel(Filament::getPanel('portal'));
        Filament::setServingStatus(true);

        return $next($request);
    }
}
