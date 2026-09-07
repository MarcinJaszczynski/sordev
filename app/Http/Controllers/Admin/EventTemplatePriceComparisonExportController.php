<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\EventTemplatePriceComparisonPage;
use App\Http\Controllers\Controller;
use App\Services\EventTemplatePriceComparisonService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Eksport porównawczy CSV (wiele kolumn cen) — poza Livewire. */
final class EventTemplatePriceComparisonExportController extends Controller
{
    public function __invoke(Request $request, EventTemplatePriceComparisonService $service): StreamedResponse
    {
        abort_unless(EventTemplatePriceComparisonPage::canAccess(), 403);

        $templateId = $request->integer('template_id') ?: null;
        $startPlaceId = $request->integer('start_place_id') ?: null;
        $mode = (string) $request->query('mode', 'sources');

        if ($mode !== 'environments') {
            abort_if(! $templateId && ! $startPlaceId, 422, 'Wybierz szablon lub miejsce wyjazdu.');
        }

        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $filters = [
            'template_id' => $templateId,
            'start_place_id' => $startPlaceId,
            'only_active' => $request->query('only_active', '1') !== '0',
        ];

        $threshold = (float) ($request->query('threshold') ?: config('price-comparison.default_threshold', 1.0));
        $onlyDiffs = $request->query('only_diffs', '1') !== '0';

        if ($mode === 'environments') {
            $environments = $this->resolveEnvironmentTargets($request);
            $probeErrors = $service->validateEnvironmentEndpoints($environments);
            abort_if($probeErrors !== [], 422, implode("\n\n", $probeErrors));

            $suffix = collect([
                $templateId ? 'szablon-'.$templateId : null,
                $startPlaceId ? 'start-'.$startPlaceId : null,
            ])->filter()->implode('_');

            $filename = 'porownanie-srodowisk'.($suffix !== '' ? '-'.$suffix : '-pelna').'-'.now()->format('Y-m-d_His').'.csv';

            return response()->streamDownload(function () use ($service, $environments, $filters, $onlyDiffs, $threshold): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }

                $service->streamMultiEnvironmentComparisonCsv($handle, $environments, $filters, [
                    'only_diffs' => $onlyDiffs,
                    'threshold' => $threshold,
                ]);

                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $remote = [];
        if ($request->boolean('prod')) {
            $remote[] = [
                'key' => 'prod',
                'label' => 'prod',
                'url' => $request->query('prod_url') ?: config('price-comparison.environments.prod.base_url'),
                'is_local' => false,
            ];
        }
        if ($request->boolean('dev')) {
            $remote[] = [
                'key' => 'dev',
                'label' => 'dev',
                'url' => $request->query('dev_url') ?: config('price-comparison.environments.dev.base_url'),
                'is_local' => false,
            ];
        }

        $filename = 'porownanie-cen-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($service, $templateId, $startPlaceId, $request, $remote, $onlyDiffs, $threshold): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            $service->streamComparisonCsv($handle, [
                'template_id' => $templateId,
                'start_place_id' => $startPlaceId,
                'include_stored' => $request->query('no_stored') !== '1',
                'include_calculated' => $request->query('no_calculated') !== '1',
                'only_diffs' => $onlyDiffs,
                'threshold' => $threshold,
                'remote_environments' => $remote,
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return list<array{key: string, label: string, url: string, is_local: bool}> */
    private function resolveEnvironmentTargets(Request $request): array
    {
        $localUrl = rtrim((string) config('price-comparison.environments.local.base_url', config('app.url')), '/');
        $prodUrl = rtrim((string) ($request->query('prod_url') ?: config('price-comparison.environments.prod.base_url')), '/');
        $devUrl = rtrim((string) ($request->query('dev_url') ?: config('price-comparison.environments.dev.base_url')), '/');

        return [
            [
                'key' => 'local',
                'label' => 'local',
                'url' => $localUrl,
                'is_local' => true,
            ],
            [
                'key' => 'prod',
                'label' => 'prod',
                'url' => $prodUrl,
                'is_local' => false,
            ],
            [
                'key' => 'dev',
                'label' => 'dev',
                'url' => $devUrl,
                'is_local' => false,
            ],
        ];
    }
}
