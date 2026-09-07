<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\EventTemplatePriceComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /internal/event-template-prices/recalculate — przelicza ceny na danym środowisku.
 */
final class EventTemplatePriceRecalculateController extends Controller
{
    public function __invoke(Request $request, EventTemplatePriceComparisonService $service): JsonResponse
    {
        $expected = config('price-comparison.token');
        if (! $expected) {
            return response()->json(['error' => 'Endpoint wyłączony — brak PRICE_COMPARE_TOKEN.'], 503);
        }

        $token = $request->input('token') ?: $request->query('token') ?: $request->header('X-Price-Compare-Token');
        if (! is_string($token) || ! hash_equals($expected, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $pairs = $service->resolveRecalcPairs(
            rows: [],
            threshold: 0,
            templateId: $request->integer('template_id') ?: null,
            startPlaceId: $request->integer('start_place_id') ?: null,
            onlyDiffs: false,
            explicitPairs: $request->input('pairs'),
        );

        if ($pairs === []) {
            return response()->json([
                'error' => 'Brak par do przeliczenia — podaj template_id (+ opcjonalnie start_place_id) lub tablicę pairs.',
            ], 422);
        }

        $result = $service->recalculatePairs($pairs);

        return response()->json([
            'environment' => app()->environment(),
            'pairs' => count($pairs),
            'recalculated' => $result['recalculated'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
        ]);
    }
}
