<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\EventTemplatePriceComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wewnętrzny endpoint JSON do porównywania cen między środowiskami.
 * Chroniony tokenem PRICE_COMPARE_TOKEN (query ?token= lub nagłówek X-Price-Compare-Token).
 *
 * Pełny katalog: użyj ?page=1&per_page=5000 (stronicowanie).
 */
final class EventTemplatePriceSnapshotController extends Controller
{
    public function __invoke(Request $request, EventTemplatePriceComparisonService $service): JsonResponse
    {
        $expected = config('price-comparison.token');
        if (! $expected) {
            return response()->json(['error' => 'Endpoint wyłączony — brak PRICE_COMPARE_TOKEN.'], 503);
        }

        $token = $request->query('token') ?: $request->header('X-Price-Compare-Token');
        if (! is_string($token) || ! hash_equals($expected, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $filters = [
            'template_id' => $request->integer('template_id') ?: null,
            'start_place_id' => $request->integer('start_place_id') ?: null,
            'only_active' => $request->query('only_active', '1') !== '0',
        ];

        $page = $request->integer('page') ?: null;
        if ($page) {
            $perPage = $request->integer('per_page') ?: 5000;

            return response()->json(
                $service->exportStoredSnapshotPage(app()->environment(), $filters, $page, $perPage),
            );
        }

        if (empty($filters['template_id']) && empty($filters['start_place_id'])) {
            return response()->json([
                'error' => 'Pełny katalog wymaga stronicowania — dodaj ?page=1&per_page=5000',
                'total' => $service->countStoredPrices($filters),
            ], 422);
        }

        $snapshot = $service->exportStoredSnapshot(app()->environment(), $filters);

        return response()->json($snapshot);
    }
}
