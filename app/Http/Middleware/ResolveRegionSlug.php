<?php

namespace App\Http\Middleware;

use App\Models\Place;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Resolve {regionSlug} to start_place_id (Place) and persist cookie.
 * Shares current_start_place_id and current_region_slug with all views.
 * Falls back to cookie or generic 'region'.
 */
class ResolveRegionSlug
{
    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        if (! $this->placesTableAvailable()) {
            $this->shareRegionContext(null, 'region');

            return $next($request);
        }

        $regionSlug = $request->route('regionSlug');
        $placeId = null;

        if ($regionSlug && $regionSlug !== 'region') {
            $place = Place::all()->first(function ($pl) use ($regionSlug) {
                return Str::slug($pl->name) === $regionSlug;
            });
            if ($place) {
                $placeId = $place->id;
                Cookie::queue('start_place_id', (string) $placeId, 60 * 24 * 365);
            } else {
                $regionSlug = 'region';
            }
        } else {
            // Try cookie -> canonicalize slug
            $cookieId = $request->cookie('start_place_id');
            if ($cookieId) {
                $place = Place::find($cookieId);
                if ($place) {
                    $placeId = $place->id;
                    $regionSlug = Str::slug($place->name);
                }
            }
        }

        $this->shareRegionContext($placeId, $regionSlug);

        // Canonicalize: if user passed start_place_id different from current slug -> redirect to proper slug
        if ($request->has('start_place_id')) {
            $requestedId = (int) $request->query('start_place_id');
            if ($requestedId && $requestedId !== (int) ($placeId ?: 0)) {
                $newPlace = Place::find($requestedId);
                if ($newPlace) {
                    $newSlug = Str::slug($newPlace->name);
                    // Build new path by swapping first segment (regionSlug)
                    $segments = $request->segments();
                    if (! empty($segments)) {
                        $segments[0] = $newSlug; // first segment is regionSlug group
                    } else {
                        $segments = [$newSlug];
                    }
                    // Remove start_place_id from query (not needed once slug encodes choice)
                    $query = $request->query();
                    unset($query['start_place_id']);
                    $qs = empty($query) ? '' : ('?'.http_build_query($query));
                    $newUrl = '/'.implode('/', $segments).($request->getPathInfo() === '/' ? '' : '');
                    // Append trailing slash if original had only region segment
                    if (count($segments) === 1 && $request->path() === $regionSlug) {
                        $newUrl .= '/';
                    }
                    $newUrl .= $qs.($request->getRequestUri() !== '/' && str_ends_with($request->getRequestUri(), '#') ? '#' : '');
                    // Ensure cookie reflects newly requested start place BEFORE redirect (avoid one-request lag)
                    Cookie::queue('start_place_id', (string) $requestedId, 60 * 24 * 365);

                    return redirect()->to($newUrl, 302);
                }
            }
        }

        return $next($request);
    }

    private function shouldSkip(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        return $request->is(
            'livewire/*',
            'admin/*',
            'pilot/*',
            'portal/*',
            'install',
            'install/*',
            'up',
            'api/*',
        ) || str_starts_with($routeName, 'filament.')
            || str_starts_with($routeName, 'install.');
    }

    private function placesTableAvailable(): bool
    {
        try {
            return Schema::hasTable('places');
        } catch (\Throwable) {
            return false;
        }
    }

    private function shareRegionContext(?int $placeId, ?string $regionSlug): void
    {
        URL::defaults(['regionSlug' => $regionSlug ?: 'region']);
        view()->share('current_start_place_id', $placeId);
        view()->share('current_region_slug', $regionSlug ?: 'region');
    }
}
