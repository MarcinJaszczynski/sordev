<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dostęp do tras /admin/* poza panelem Filament — tylko personel biura.
 *
 * Te trasy (PDF, Word, eksporty) nie uruchamiają panelu Filament, a
 * PilotAccessService poza panelem traktuje konto admin+pilot jak pilota.
 * Ustawiamy kontekst admin, żeby Gate::authorize('view') było spójne z panelem.
 */
class EnsureOfficeStaff
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(['admin', 'super_admin', 'biuro', 'ksiegowosc'])) {
            abort(403, 'Brak uprawnień biurowych.');
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setServingStatus(true);

        return $next($request);
    }
}
