<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\EventTemplatePriceComparisonPage;
use App\Http\Controllers\Controller;
use App\Services\EventTemplatePriceComparisonService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bezpośredni eksport CSV — poza Livewire (unika limitu pamięci przy ~200k wierszy).
 */
final class EventTemplatePriceExportController extends Controller
{
    public function __invoke(Request $request, EventTemplatePriceComparisonService $service): StreamedResponse
    {
        abort_unless(EventTemplatePriceComparisonPage::canAccess(), 403);

        @set_time_limit(600);
        @ini_set('memory_limit', '256M');

        $filters = [
            'template_id' => $request->integer('template_id') ?: null,
            'start_place_id' => $request->integer('start_place_id') ?: null,
            'only_active' => $request->query('only_active', '1') !== '0',
        ];

        $suffix = collect([
            $filters['template_id'] ? 'szablon-'.$filters['template_id'] : null,
            $filters['start_place_id'] ? 'start-'.$filters['start_place_id'] : null,
        ])->filter()->implode('_');

        $filename = 'cennik-oferty'.($suffix !== '' ? '-'.$suffix : '-pelny').'-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($service, $filters): void {
            $handle = fopen('php://output', 'w');
            if ($handle !== false) {
                $service->streamStoredPricesCsv($handle, $filters);
                fclose($handle);
            }
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
