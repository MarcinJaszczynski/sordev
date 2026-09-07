<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\Place;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Porównywanie cen szablonów imprez między źródłami:
 * zapisane w DB, kalkulacja live (UnifiedPriceCalculator), snapshoty zdalne (HTTP).
 */
final class EventTemplatePriceComparisonService
{
    /** Maks. wierszy cennika ładowanych do pamięci (bez filtra szablon/miejsce). */
    private const MAX_PRICE_ROWS = 8000;

    /** @var array<int, true>|null */
    private ?array $plnCurrencyIds = null;

    public function __construct(
        private readonly UnifiedPriceCalculator $calculator,
    ) {}

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @return array{generated_at: string, environment: string, prices: list<array<string, mixed>>}
     */
    public function exportStoredSnapshot(string $environment = 'local', array $filters = []): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'environment' => $environment,
            'prices' => $this->collectStoredPrices($filters),
        ];
    }

    /**
     * Stronicowany snapshot (API / eksport zdalny) — bez ładowania całego katalogu do RAM.
     *
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @return array{
     *     generated_at: string,
     *     environment: string,
     *     prices: list<array<string, mixed>>,
     *     meta: array{page: int, per_page: int, total: int, last_page: int},
     * }
     */
    public function exportStoredSnapshotPage(string $environment, array $filters, int $page, int $perPage): array
    {
        $perPage = max(100, min(10_000, $perPage));
        $page = max(1, $page);

        $query = $this->storedPricesBuilder($filters);
        $total = (clone $query)->count();

        $rows = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($row) => $this->mapStoredPriceRow($row))
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'environment' => $environment,
            'prices' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     */
    public function countStoredPrices(array $filters = []): int
    {
        return $this->storedPricesBuilder($filters)->count();
    }

    /**
     * Zapisuje cennik PLN do CSV (strumieniowo, bez limitu wierszy w panelu).
     *
     * @param  resource  $handle
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     */
    public function streamStoredPricesCsv($handle, array $filters = []): int
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->storedPricesCsvHeaders(), ';');

        $count = 0;
        foreach ($this->storedPricesBuilder($filters)->cursor() as $row) {
            fputcsv($handle, $this->storedPriceRowToCsv($row), ';');
            $count++;
        }

        return $count;
    }

    /**
     * Pobiera zdalny katalog stronicowo i zapisuje do CSV.
     *
     * @return array{rows: int, errors: list<string>}|null
     */
    public function streamRemoteStoredPricesCsv($handle, string $baseUrl, ?string $token, array $filters = []): ?array
    {
        $token = $token ?: config('price-comparison.token');
        if (! $token) {
            return null;
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->storedPricesCsvHeaders(), ';');

        $baseUrl = rtrim($baseUrl, '/');
        $page = 1;
        $totalRows = 0;
        $errors = [];
        $lastPage = 1;

        do {
            try {
                $response = Http::timeout(120)
                    ->acceptJson()
                    ->get("{$baseUrl}/internal/event-template-prices", array_filter([
                        'token' => $token,
                        'template_id' => $filters['template_id'] ?? null,
                        'start_place_id' => $filters['start_place_id'] ?? null,
                        'only_active' => isset($filters['only_active']) ? ($filters['only_active'] ? '1' : '0') : '1',
                        'page' => $page,
                        'per_page' => 5000,
                    ], fn ($v) => $v !== null && $v !== ''));

                if (! $response->successful()) {
                    $errors[] = "Strona {$page}: HTTP {$response->status()}";

                    break;
                }

                $data = $response->json();
                if (! is_array($data)) {
                    $errors[] = "Strona {$page}: nieprawidłowa odpowiedź JSON";

                    break;
                }

                foreach ($data['prices'] ?? [] as $priceRow) {
                    if (! is_array($priceRow)) {
                        continue;
                    }
                    fputcsv($handle, $this->storedPriceArrayToCsv($priceRow), ';');
                    $totalRows++;
                }

                $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
                $lastPage = (int) ($meta['last_page'] ?? 1);
                $page++;
            } catch (ConnectionException $e) {
                $errors[] = "Strona {$page}: {$e->getMessage()}";

                break;
            }
        } while ($page <= $lastPage);

        return ['rows' => $totalRows, 'errors' => $errors];
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @return array{generated_at: string, environment: string, prices: list<array<string, mixed>>}
     */
    public function exportCalculatedSnapshot(string $environment = 'local_calc', array $filters = []): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'environment' => $environment,
            'prices' => $this->collectCalculatedPrices($filters),
        ];
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @return array{generated_at: string, environment: string, prices: list<array<string, mixed>>}|null
     */
    public function fetchRemoteSnapshot(string $baseUrl, ?string $token, array $filters = [], string $environment = 'remote'): ?array
    {
        $token = $token ?: config('price-comparison.token');
        if (! $token) {
            return null;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $query = array_filter([
            'token' => $token,
            'template_id' => $filters['template_id'] ?? null,
            'start_place_id' => $filters['start_place_id'] ?? null,
            'only_active' => isset($filters['only_active']) ? ($filters['only_active'] ? '1' : '0') : '1',
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::timeout(120)
                ->acceptJson()
                ->get("{$baseUrl}/internal/event-template-prices", $query);

            if (! $response->successful()) {
                Log::warning('Price comparison remote fetch failed', [
                    'url' => $baseUrl,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            if (! is_array($data) || ! isset($data['prices']) || ! is_array($data['prices'])) {
                return null;
            }

            $data['environment'] = $environment;

            return $data;
        } catch (ConnectionException $e) {
            Log::warning('Price comparison remote connection failed', [
                'url' => $baseUrl,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, array{generated_at: string, environment: string, prices: list<array<string, mixed>>}>  $sources  klucz => snapshot
     * @param  array{
     *     only_diffs?: bool,
     *     threshold?: float,
     * }  $options
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, mixed>,
     * }
     */
    public function compareSnapshots(array $sources, array $options = []): array
    {
        $onlyDiffs = (bool) ($options['only_diffs'] ?? true);
        $threshold = (float) ($options['threshold'] ?? config('price-comparison.default_threshold', 1.0));

        $indexed = [];
        foreach ($sources as $sourceKey => $snapshot) {
            foreach ($snapshot['prices'] ?? [] as $row) {
                $key = $this->rowKey($row);
                $indexed[$key]['meta'] ??= [
                    'template_id' => (int) ($row['template_id'] ?? 0),
                    'template_name' => (string) ($row['template_name'] ?? ''),
                    'template_slug' => (string) ($row['template_slug'] ?? ''),
                    'start_place_id' => isset($row['start_place_id']) ? (int) $row['start_place_id'] : null,
                    'start_place_name' => (string) ($row['start_place_name'] ?? ''),
                    'qty' => (int) ($row['qty'] ?? 0),
                ];
                $indexed[$key]['sources'][$sourceKey] = [
                    'price_per_person' => isset($row['price_per_person']) ? (float) $row['price_per_person'] : null,
                    'price_base' => isset($row['price_base']) ? (float) $row['price_base'] : null,
                    'transport_cost' => isset($row['transport_cost']) ? (float) $row['transport_cost'] : null,
                ];
            }
        }

        $rows = [];
        $diffCount = 0;
        $missingCount = 0;

        foreach ($indexed as $key => $entry) {
            $prices = collect($entry['sources'] ?? [])
                ->pluck('price_per_person')
                ->filter(fn ($p) => $p !== null)
                ->map(fn ($p) => (float) $p);

            $maxDiff = 0.0;
            if ($prices->count() >= 2) {
                $maxDiff = (float) ($prices->max() - $prices->min());
            }

            $hasMissing = count($entry['sources'] ?? []) < count($sources);
            if ($hasMissing) {
                $missingCount++;
            }

            $isDiff = $maxDiff > $threshold || $hasMissing;
            if ($onlyDiffs && ! $isDiff) {
                continue;
            }

            if ($isDiff && $maxDiff > $threshold) {
                $diffCount++;
            }

            $row = $entry['meta'];
            $row['key'] = $key;
            $row['max_diff'] = round($maxDiff, 2);
            $row['has_missing'] = $hasMissing;

            foreach ($sources as $sourceKey => $_snapshot) {
                $row["{$sourceKey}_price"] = $entry['sources'][$sourceKey]['price_per_person'] ?? null;
            }

            $rows[] = $this->enrichComparisonRowWithDeltas($row, array_keys($sources));
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['max_diff'] !== $b['max_diff']) {
                return $b['max_diff'] <=> $a['max_diff'];
            }

            return [$a['template_name'], $a['start_place_name'], $a['qty']]
                <=> [$b['template_name'], $b['start_place_name'], $b['qty']];
        });

        return [
            'rows' => $rows,
            'summary' => [
                'total_keys' => count($indexed),
                'shown_rows' => count($rows),
                'diff_rows' => $diffCount,
                'missing_rows' => $missingCount,
                'sources' => array_keys($sources),
                'threshold' => $threshold,
                'only_diffs' => $onlyDiffs,
            ],
        ];
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     *     include_stored?: bool,
     *     include_calculated?: bool,
     *     include_prod?: bool,
     *     include_dev?: bool,
     *     only_diffs?: bool,
     *     threshold?: float,
     * }  $options
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     errors: list<string>,
     * }
     */
    public function runComparison(array $options = []): array
    {
        $filters = [
            'template_id' => $options['template_id'] ?? null,
            'start_place_id' => $options['start_place_id'] ?? null,
            'only_active' => $options['only_active'] ?? true,
        ];

        $validationError = $this->validateComparisonFilters($filters, $options);
        if ($validationError !== null) {
            return [
                'rows' => [],
                'summary' => ['total_keys' => 0, 'shown_rows' => 0, 'sources' => []],
                'errors' => [$validationError],
            ];
        }

        @set_time_limit(120);

        $sources = [];
        $errors = [];

        if ($options['include_stored'] ?? true) {
            $sources['stored'] = $this->exportStoredSnapshot('local_stored', $filters);
        }

        if ($options['include_calculated'] ?? true) {
            $sources['calculated'] = $this->exportCalculatedSnapshot('local_calc', $filters);
        }

        $token = config('price-comparison.token');

        $remoteEnvironments = $options['remote_environments'] ?? null;
        if (is_array($remoteEnvironments)) {
            foreach ($remoteEnvironments as $env) {
                $key = (string) ($env['key'] ?? '');
                $url = rtrim((string) ($env['url'] ?? ''), '/');
                if ($key === '' || $url === '' || ($env['is_local'] ?? false)) {
                    continue;
                }
                $remote = $this->fetchRemoteSnapshot($url, $token, $filters, $key);
                if ($remote) {
                    $sources[$key] = $remote;
                } else {
                    $label = (string) ($env['label'] ?? $key);
                    $errors[] = "Nie udało się pobrać cen z {$label} ({$url}). Sprawdź token i endpoint GET /internal/event-template-prices.";
                }
            }
        } else {
            if ($options['include_prod'] ?? false) {
                $prodUrl = config('price-comparison.environments.prod.base_url');
                $remote = $this->fetchRemoteSnapshot($prodUrl, $token, $filters, 'prod');
                if ($remote) {
                    $sources['prod'] = $remote;
                } else {
                    $errors[] = "Nie udało się pobrać cen z produkcji ({$prodUrl}). Sprawdź token PRICE_COMPARE_TOKEN i czy endpoint jest wdrożony.";
                }
            }

            if ($options['include_dev'] ?? false) {
                $devUrl = config('price-comparison.environments.dev.base_url');
                $remote = $this->fetchRemoteSnapshot($devUrl, $token, $filters, 'dev');
                if ($remote) {
                    $sources['dev'] = $remote;
                } else {
                    $errors[] = "Nie udało się pobrać cen z dev ({$devUrl}). Sprawdź token PRICE_COMPARE_TOKEN i czy endpoint jest wdrożony.";
                }
            }
        }

        if ($sources === []) {
            return [
                'rows' => [],
                'summary' => ['total_keys' => 0, 'shown_rows' => 0, 'sources' => []],
                'errors' => ['Brak aktywnych źródeł porównania.'],
            ];
        }

        $result = $this->compareSnapshots($sources, [
            'only_diffs' => $options['only_diffs'] ?? true,
            'threshold' => $options['threshold'] ?? config('price-comparison.default_threshold', 1.0),
        ]);

        return [
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'errors' => $errors,
        ];
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @return list<array<string, mixed>>
     */
    private function collectStoredPrices(array $filters): array
    {
        $plnIds = array_keys($this->plnCurrencyIds());

        $query = EventTemplatePricePerPerson::query()
            ->with(['eventTemplateQty:id,qty', 'startPlace:id,name'])
            ->whereIn('currency_id', $plnIds)
            ->where('price_per_person', '>', 0)
            ->whereHas('eventTemplate', function ($q) use ($filters) {
                if ($filters['only_active'] ?? true) {
                    $q->where('is_active', true);
                }
                if (! empty($filters['template_id'])) {
                    $q->where('id', (int) $filters['template_id']);
                }
            });

        if (! empty($filters['start_place_id'])) {
            $query->where('start_place_id', (int) $filters['start_place_id']);
        }

        $estimated = (clone $query)->count();
        if ($estimated > self::MAX_PRICE_ROWS) {
            throw new \RuntimeException(sprintf(
                'Zbyt duży wynik (%s wierszy cennika). Wybierz konkretny szablon lub jedno miejsce wyjazdu.',
                number_format($estimated, 0, ',', ' '),
            ));
        }

        $templates = $this->templateMetaMap($filters);

        $rows = [];
        foreach ($query->cursor() as $price) {
            $templateId = (int) $price->event_template_id;
            $meta = $templates[$templateId] ?? ['name' => '', 'slug' => ''];

            $rows[] = [
                'template_id' => $templateId,
                'template_name' => $meta['name'],
                'template_slug' => $meta['slug'],
                'start_place_id' => $price->start_place_id ? (int) $price->start_place_id : null,
                'start_place_name' => $price->startPlace?->name ?? '',
                'qty' => (int) ($price->eventTemplateQty?->qty ?? 0),
                'price_per_person' => (float) $price->price_per_person,
                'price_base' => $price->price_base !== null ? (float) $price->price_base : null,
                'transport_cost' => $price->transport_cost !== null ? (float) $price->transport_cost : null,
                'source' => 'stored',
            ];
        }

        return $rows;
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @return list<array<string, mixed>>
     */
    private function collectCalculatedPrices(array $filters): array
    {
        $templates = $this->templatesForCalculation($filters);
        $startPlaces = $this->startPlacesForFilters($filters);
        $templatesMeta = $this->templateMetaMap($filters);
        $rows = [];

        foreach ($templates as $template) {
            foreach ($startPlaces as $startPlace) {
                $startPlaceId = (int) $startPlace->id;

                if (! $this->templateAvailableForStartPlace($template, $startPlaceId)) {
                    continue;
                }

                $calculated = $this->calculator->calculate($template, $startPlaceId);

                foreach ($calculated as $qty => $data) {
                    $pln = $data['currencies']['PLN'] ?? null;
                    if (! $pln) {
                        continue;
                    }

                    $price = $pln['final']['price_per_person'] ?? $pln['raw']['price_per_person'] ?? null;
                    if ($price === null || (float) $price <= 0) {
                        continue;
                    }

                    $meta = $templatesMeta[(int) $template->id] ?? ['name' => $template->name, 'slug' => $template->slug];

                    $rows[] = [
                        'template_id' => (int) $template->id,
                        'template_name' => $meta['name'],
                        'template_slug' => $meta['slug'],
                        'start_place_id' => $startPlaceId,
                        'start_place_name' => $startPlace->name,
                        'qty' => (int) $qty,
                        'price_per_person' => (float) $price,
                        'price_base' => isset($pln['raw']['price_base']) ? (float) $pln['raw']['price_base'] : null,
                        'transport_cost' => isset($pln['raw']['transport_cost']) ? (float) $pln['raw']['transport_cost'] : null,
                        'source' => 'calculated',
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @return Collection<int, EventTemplate>
     */
    private function templatesForCalculation(array $filters): Collection
    {
        $query = EventTemplate::query()->with(['markup', 'taxes', 'bus', 'programPoints', 'hotelDays', 'dayInsurances']);

        if ($filters['only_active'] ?? true) {
            $query->where('is_active', true);
        }

        if (! empty($filters['template_id'])) {
            return $query->where('id', (int) $filters['template_id'])->get();
        }

        // Ogranicz do szablonów z zapisanym cennikiem PLN — szybsze domyślne porównanie.
        $plnIds = $this->plnCurrencyIds();
        $templateIds = EventTemplatePricePerPerson::query()
            ->whereIn('currency_id', $plnIds)
            ->where('price_per_person', '>', 0)
            ->distinct()
            ->pluck('event_template_id');

        return $query->whereIn('id', $templateIds)->orderBy('name')->get();
    }

    /**
     * @param  array{start_place_id?: int|null}  $filters
     * @return Collection<int, Place>
     */
    private function startPlacesForFilters(array $filters): Collection
    {
        if (! empty($filters['start_place_id'])) {
            $place = Place::query()->find((int) $filters['start_place_id']);

            return $place ? collect([$place]) : collect();
        }

        return Place::query()->startingPlaces()->orderBy('name')->get();
    }

    private function templateAvailableForStartPlace(EventTemplate $template, int $startPlaceId): bool
    {
        if (! method_exists($template, 'resolveAvailableStartPlaceIds')) {
            return true;
        }

        $allowed = $template->resolveAvailableStartPlaceIds();
        if ($allowed === null || $allowed->isEmpty()) {
            return true;
        }

        return $allowed->contains($startPlaceId);
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @return array<int, array{name: string, slug: string}>
     */
    private function templateMetaMap(array $filters): array
    {
        $query = EventTemplate::query()->select(['id', 'name', 'slug']);

        if ($filters['only_active'] ?? true) {
            $query->where('is_active', true);
        }

        if (! empty($filters['template_id'])) {
            $query->where('id', (int) $filters['template_id']);
        }

        return $query->get()->mapWithKeys(fn (EventTemplate $t) => [
            (int) $t->id => ['name' => (string) $t->name, 'slug' => (string) $t->slug],
        ])->all();
    }

    /** @return array<int, true> */
    private function plnCurrencyIds(): array
    {
        if ($this->plnCurrencyIds !== null) {
            return $this->plnCurrencyIds;
        }

        $ids = Currency::query()
            ->where(function ($q) {
                $q->where('name', 'like', '%polski%złoty%')
                    ->orWhere('name', 'like', '%złoty%polski%')
                    ->orWhere('name', '=', 'Polski złoty')
                    ->orWhere('name', '=', 'Złoty polski')
                    ->orWhere('code', '=', 'PLN');
            })
            ->pluck('id')
            ->all();

        $this->plnCurrencyIds = array_fill_keys(array_map('intval', $ids), true);

        return $this->plnCurrencyIds;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $sources
     */
    public function buildCsvContent(array $rows, array $sources, float $threshold): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        $this->writeComparisonCsv($handle, $rows, $sources, $threshold);

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }

    /**
     * @param  resource  $handle
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $sources
     */
    public function writeComparisonCsv($handle, array $rows, array $sources, float $threshold): int
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->comparisonCsvHeaders($sources), ';');

        $count = 0;
        foreach ($rows as $row) {
            fputcsv($handle, $this->comparisonCsvLine($row, $sources, $threshold), ';');
            $count++;
        }

        return $count;
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $options
     */
    public function streamComparisonCsv($handle, array $options): int
    {
        $result = $this->runComparison($options);
        $sources = $result['summary']['sources'] ?? [];
        $threshold = (float) ($options['threshold'] ?? config('price-comparison.default_threshold', 1.0));

        return $this->writeComparisonCsv($handle, $result['rows'], $sources, $threshold);
    }

    /**
     * Porównanie zapisanych cen między środowiskami (local + prod + dev) — strumieniowo, bez RAM na cały katalog.
     *
     * @param  resource  $handle
     * @param  list<array{key: string, label?: string, url: string, is_local: bool}>  $environments
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @param  array{
     *     only_diffs?: bool,
     *     threshold?: float,
     * }  $options
     * @return array{rows: int, sources: list<string>, errors: list<string>}
     */
    public function streamMultiEnvironmentComparisonCsv($handle, array $environments, array $filters = [], array $options = []): array
    {
        $token = config('price-comparison.token');
        $threshold = (float) ($options['threshold'] ?? config('price-comparison.default_threshold', 1.0));
        $onlyDiffs = (bool) ($options['only_diffs'] ?? true);
        $errors = $this->validateEnvironmentEndpoints($environments);

        if ($errors !== []) {
            return ['rows' => 0, 'sources' => [], 'errors' => $errors];
        }

        /** @var array<string, \Generator<int, array{key: string, meta: array<string, mixed>, price: float}>|null> $iterators */
        $iterators = [];
        $sourceKeys = [];

        foreach ($environments as $env) {
            $key = (string) ($env['key'] ?? '');
            if ($key === '') {
                continue;
            }

            if ($env['is_local'] ?? false) {
                $iterators[$key] = $this->iterateLocalStoredPrices($filters);
                $sourceKeys[] = $key;

                continue;
            }

            $url = rtrim((string) ($env['url'] ?? ''), '/');
            if ($url === '') {
                $errors[] = "Brak URL dla środowiska „{$key}”.";

                continue;
            }

            $iterators[$key] = $this->iterateRemoteStoredPrices($url, $token, $filters);
            $sourceKeys[] = $key;
        }

        if (count($iterators) < 2) {
            return [
                'rows' => 0,
                'sources' => $sourceKeys,
                'errors' => array_merge($errors, ['Potrzeba co najmniej 2 środowisk z poprawnym adresem URL.']),
            ];
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->comparisonCsvHeaders($sourceKeys), ';');

        /** @var array<string, array{key: string, meta: array<string, mixed>, price: float}|null> $heads */
        $heads = [];
        foreach ($iterators as $key => $iterator) {
            if ($iterator === null) {
                $heads[$key] = null;

                continue;
            }

            try {
                $heads[$key] = $iterator->valid() ? $iterator->current() : null;
            } catch (\Throwable $e) {
                Log::warning('Price comparison iterator init failed', ['source' => $key, 'message' => $e->getMessage()]);
                $errors[] = "[{$key}] {$e->getMessage()}";
                $heads[$key] = null;
            }
        }

        $written = 0;

        while ($this->hasAnyPriceHead($heads)) {
            $mergeKey = $this->minPriceHeadKey($heads);
            if ($mergeKey === null) {
                break;
            }

            $meta = null;
            $prices = [];

            foreach ($sourceKeys as $sourceKey) {
                $head = $heads[$sourceKey] ?? null;
                if ($head !== null && $head['key'] === $mergeKey) {
                    $meta ??= $head['meta'];
                    $prices[$sourceKey] = $head['price'];
                    $iterators[$sourceKey]->next();
                    $heads[$sourceKey] = $iterators[$sourceKey]->valid()
                        ? $iterators[$sourceKey]->current()
                        : null;
                } else {
                    $prices[$sourceKey] = null;
                }
            }

            if ($meta === null) {
                continue;
            }

            $row = [
                'template_id' => $meta['template_id'],
                'template_name' => $meta['template_name'],
                'template_slug' => $meta['template_slug'],
                'start_place_id' => $meta['start_place_id'],
                'start_place_name' => $meta['start_place_name'],
                'qty' => $meta['qty'],
            ];

            foreach ($sourceKeys as $sourceKey) {
                $row["{$sourceKey}_price"] = $prices[$sourceKey] ?? null;
            }

            $numericPrices = collect($prices)->filter(fn ($p) => $p !== null)->map(fn ($p) => (float) $p);
            $maxDiff = $numericPrices->count() >= 2
                ? (float) ($numericPrices->max() - $numericPrices->min())
                : 0.0;
            $hasMissing = collect($sourceKeys)->contains(fn ($s) => ! array_key_exists($s, $prices) || $prices[$s] === null);
            $isDiff = $maxDiff > $threshold || $hasMissing;

            if ($onlyDiffs && ! $isDiff) {
                continue;
            }

            $row = $this->enrichComparisonRowWithDeltas($row, $sourceKeys);
            $row['max_diff'] = round($maxDiff, 2);
            $row['has_missing'] = $hasMissing;

            fputcsv($handle, $this->comparisonCsvLine($row, $sourceKeys, $threshold), ';');
            $written++;
        }

        return ['rows' => $written, 'sources' => $sourceKeys, 'errors' => $errors];
    }

    /**
     * Sprawdza dostępność endpointów zdalnych przed rozpoczęciem eksportu.
     *
     * @param  list<array{key?: string, label?: string, url: string, is_local?: bool}>  $environments
     * @return list<string>
     */
    public function validateEnvironmentEndpoints(array $environments): array
    {
        $errors = [];
        $token = config('price-comparison.token');

        if (! $token) {
            return ['Brak PRICE_COMPARE_TOKEN w .env — wymagany do pobrania cen z prod/dev.'];
        }

        foreach ($environments as $env) {
            if ($env['is_local'] ?? false) {
                continue;
            }

            $key = (string) ($env['key'] ?? 'remote');
            $url = rtrim((string) ($env['url'] ?? ''), '/');
            if ($url === '') {
                $errors[] = "[{$key}] Brak adresu URL środowiska.";

                continue;
            }

            $probeError = $this->probeRemoteStoredPricesEndpoint($url, $token);
            if ($probeError !== null) {
                $errors[] = "[{$key}] {$probeError}";
            }
        }

        return $errors;
    }

    public function probeRemoteStoredPricesEndpoint(string $baseUrl, ?string $token): ?string
    {
        $token = $token ?: config('price-comparison.token');
        if (! $token) {
            return 'Brak PRICE_COMPARE_TOKEN.';
        }

        $baseUrl = rtrim($baseUrl, '/');

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get("{$baseUrl}/internal/event-template-prices", [
                    'token' => $token,
                    'page' => 1,
                    'per_page' => 1,
                    'only_active' => '1',
                ]);

            if ($response->status() === 404) {
                return "Brak endpointu GET /internal/event-template-prices na {$baseUrl} (404). Wdróż najnowszy kod na serwerze i ustaw ten sam PRICE_COMPARE_TOKEN w .env.";
            }

            if ($response->status() === 403 || $response->status() === 401) {
                return "Odrzucono token na {$baseUrl} (HTTP {$response->status()}). Upewnij się, że PRICE_COMPARE_TOKEN jest identyczny na obu serwerach.";
            }

            if (! $response->successful()) {
                return "HTTP {$response->status()} z {$baseUrl}.";
            }

            $data = $response->json();
            if (! is_array($data) || ! isset($data['prices']) || ! is_array($data['prices'])) {
                return "Nieprawidłowa odpowiedź JSON z {$baseUrl}.";
            }

            return null;
        } catch (ConnectionException $e) {
            return "Brak połączenia z {$baseUrl}: {$e->getMessage()}";
        }
    }

    /**
     * Scala wiele plików CSV (po jednym na środowisko) w jeden arkusz porównawczy.
     *
     * @param  list<array{label: string, path: string}>  $files
     * @return array{rows: int, sources: list<string>, errors: list<string>}
     */
    public function mergePriceExportFiles(array $files, string $outputPath, float $threshold = 1.0, bool $onlyDiffs = true): array
    {
        /** @var array<string, array{meta: array<string, mixed>, prices: array<string, float|null>}> $indexed */
        $indexed = [];
        $sources = [];
        $errors = [];

        foreach ($files as $file) {
            $label = (string) ($file['label'] ?? 'source');
            $path = (string) ($file['path'] ?? '');
            $sources[] = $label;

            if ($path === '' || ! is_readable($path)) {
                $errors[] = "Nie można odczytać pliku: {$path}";

                continue;
            }

            $handle = fopen($path, 'r');
            if ($handle === false) {
                $errors[] = "Nie można otworzyć: {$path}";

                continue;
            }

            $headers = fgetcsv($handle, 0, ';');
            if ($headers === false) {
                fclose($handle);
                $errors[] = "Pusty plik: {$path}";

                continue;
            }

            if (isset($headers[0]) && str_starts_with((string) $headers[0], "\xEF\xBB\xBF")) {
                $headers[0] = substr((string) $headers[0], 3);
            }

            $priceColumn = $this->detectPriceColumn($headers, $label);

            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                if (count($data) < 3) {
                    continue;
                }
                $row = array_combine($headers, array_pad($data, count($headers), ''));
                if ($row === false) {
                    continue;
                }

                $key = $this->csvRowKey($row);
                if ($key === '') {
                    continue;
                }

                $indexed[$key]['meta'] ??= [
                    'template_id' => (int) ($row['template_id'] ?? 0),
                    'template_name' => (string) ($row['template_name'] ?? ''),
                    'template_slug' => (string) ($row['template_slug'] ?? ''),
                    'start_place_id' => $row['start_place_id'] ?? '',
                    'start_place_name' => (string) ($row['start_place_name'] ?? ''),
                    'qty' => (int) ($row['qty'] ?? 0),
                ];

                $priceRaw = $row[$priceColumn] ?? null;
                $indexed[$key]['prices'][$label] = ($priceRaw === null || $priceRaw === '')
                    ? null
                    : (float) str_replace(',', '.', (string) $priceRaw);
            }

            fclose($handle);
        }

        $handleOut = fopen($outputPath, 'w');
        if ($handleOut === false) {
            return ['rows' => 0, 'sources' => $sources, 'errors' => array_merge($errors, ['Nie można zapisać: '.$outputPath])];
        }

        fwrite($handleOut, "\xEF\xBB\xBF");
        fputcsv($handleOut, $this->comparisonCsvHeaders($sources), ';');

        $written = 0;
        foreach ($indexed as $entry) {
            $row = [
                'template_id' => $entry['meta']['template_id'],
                'template_name' => $entry['meta']['template_name'],
                'template_slug' => $entry['meta']['template_slug'],
                'start_place_id' => $entry['meta']['start_place_id'],
                'start_place_name' => $entry['meta']['start_place_name'],
                'qty' => $entry['meta']['qty'],
            ];

            foreach ($sources as $source) {
                $row["{$source}_price"] = $entry['prices'][$source] ?? null;
            }

            $prices = collect($row)
                ->filter(fn ($_, $k) => str_ends_with((string) $k, '_price') && $row[$k] !== null)
                ->map(fn ($p) => (float) $p);

            $maxDiff = $prices->count() >= 2 ? (float) ($prices->max() - $prices->min()) : 0.0;
            $hasMissing = collect($sources)->contains(fn ($s) => ! array_key_exists($s, $entry['prices'] ?? []) || ($entry['prices'][$s] ?? null) === null);
            $isDiff = $maxDiff > $threshold || $hasMissing;

            if ($onlyDiffs && ! $isDiff) {
                continue;
            }

            $row = $this->enrichComparisonRowWithDeltas($row, $sources);
            $row['max_diff'] = round($maxDiff, 2);
            $row['has_missing'] = $hasMissing;

            fputcsv($handleOut, $this->comparisonCsvLine($row, $sources, $threshold), ';');
            $written++;
        }

        fclose($handleOut);

        return ['rows' => $written, 'sources' => $sources, 'errors' => $errors];
    }

    /** @param  list<string>  $headers */
    private function detectPriceColumn(array $headers, string $label): string
    {
        $candidates = [
            "{$label}_price",
            'cena_za_osobe_pln',
            'price_per_person',
            'cena',
            'price',
        ];
        foreach ($candidates as $column) {
            if (in_array($column, $headers, true)) {
                return $column;
            }
        }

        return 'price_per_person';
    }

    /** @param  array<string, mixed>  $row */
    private function csvRowKey(array $row): string
    {
        $templateId = (int) ($row['template_id'] ?? 0);
        $startPlaceId = (int) ($row['start_place_id'] ?? 0);
        $qty = (int) ($row['qty'] ?? 0);
        if ($templateId <= 0 || $qty <= 0) {
            return '';
        }

        return implode('|', [$templateId, $startPlaceId, $qty]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{pairs: int, recalculated: int, skipped: int, errors: list<string>}
     */
    public function recalculateStoredPricesFromRows(array $rows, float $threshold = 1.0): array
    {
        $pairs = $this->resolveRecalcPairs(
            rows: $rows,
            threshold: $threshold,
            templateId: null,
            startPlaceId: null,
            onlyDiffs: true,
        );

        return $this->recalculatePairs($pairs);
    }

    /**
     * @param  list<array{template_id: int, start_place_id: int}>  $pairs
     * @return array{pairs: int, recalculated: int, skipped: int, errors: list<string>}
     */
    public function recalculatePairs(array $pairs): array
    {
        $recalculated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($pairs as $pair) {
            $template = EventTemplate::query()->find($pair['template_id']);
            if (! $template) {
                $skipped++;
                $errors[] = "Szablon #{$pair['template_id']} nie istnieje — pominięto.";

                continue;
            }

            try {
                $this->calculator->calculateAndPersist($template, $pair['start_place_id'], false);
                $recalculated++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Szablon #{$pair['template_id']}, start #{$pair['start_place_id']}: {$e->getMessage()}";
                Log::error('Price comparison bulk recalc failed', [
                    'template_id' => $pair['template_id'],
                    'start_place_id' => $pair['start_place_id'],
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return [
            'pairs' => count($pairs),
            'recalculated' => $recalculated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{template_id: int, start_place_id: int}>|null  $explicitPairs
     * @return list<array{template_id: int, start_place_id: int}>
     */
    public function resolveRecalcPairs(
        array $rows,
        float $threshold,
        ?int $templateId,
        ?int $startPlaceId,
        bool $onlyDiffs,
        mixed $explicitPairs = null,
    ): array {
        if (is_array($explicitPairs) && $explicitPairs !== []) {
            return $this->normalizePairs($explicitPairs);
        }

        /** @var array<string, array{template_id: int, start_place_id: int}> $pairs */
        $pairs = [];

        if ($rows !== []) {
            foreach ($rows as $row) {
                if ($onlyDiffs) {
                    $maxDiff = (float) ($row['max_diff'] ?? 0);
                    $hasMissing = (bool) ($row['has_missing'] ?? false);
                    if ($maxDiff <= $threshold && ! $hasMissing) {
                        continue;
                    }
                }

                $pair = $this->pairFromRow($row);
                if ($pair) {
                    $pairs["{$pair['template_id']}|{$pair['start_place_id']}"] = $pair;
                }
            }
        }

        if ($pairs === [] && $templateId) {
            $template = EventTemplate::query()->find($templateId);
            if ($template) {
                $startPlaces = $startPlaceId
                    ? Place::query()->whereKey($startPlaceId)->get()
                    : Place::query()->startingPlaces()->orderBy('name')->get();

                foreach ($startPlaces as $place) {
                    if (! $this->templateAvailableForStartPlace($template, (int) $place->id)) {
                        continue;
                    }
                    $pairs["{$templateId}|{$place->id}"] = [
                        'template_id' => $templateId,
                        'start_place_id' => (int) $place->id,
                    ];
                }
            }
        }

        return array_values($pairs);
    }

    /**
     * @param  list<array{key: string, label: string, url: string, is_local?: bool}>  $targets
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     results: list<array{key: string, label: string, url: string, recalculated: int, pairs: int, skipped: int, errors: list<string>}>,
     *     errors: list<string>,
     * }
     */
    public function recalculateOnEnvironments(
        array $targets,
        array $rows,
        float $threshold,
        ?int $templateId,
        ?int $startPlaceId,
        bool $onlyDiffsFromRows,
    ): array {
        $pairs = $this->resolveRecalcPairs(
            rows: $rows,
            threshold: $threshold,
            templateId: $templateId,
            startPlaceId: $startPlaceId,
            onlyDiffs: $onlyDiffsFromRows && $rows !== [],
        );

        if ($pairs === []) {
            return [
                'results' => [],
                'errors' => ['Brak par do przeliczenia. Wybierz szablon lub uruchom porównanie z różnicami.'],
            ];
        }

        $token = config('price-comparison.token');
        $results = [];
        $globalErrors = [];

        foreach ($targets as $target) {
            $key = (string) ($target['key'] ?? '');
            $label = (string) ($target['label'] ?? $key);
            $url = rtrim((string) ($target['url'] ?? ''), '/');
            $isLocal = (bool) ($target['is_local'] ?? false);

            if ($url === '') {
                $globalErrors[] = "{$label}: brak adresu URL.";

                continue;
            }

            if ($isLocal) {
                $result = $this->recalculatePairs($pairs);
                $results[] = [
                    'key' => $key,
                    'label' => $label,
                    'url' => $url,
                    'recalculated' => $result['recalculated'],
                    'pairs' => $result['pairs'],
                    'skipped' => $result['skipped'],
                    'errors' => $result['errors'],
                ];

                continue;
            }

            if (! $token) {
                $globalErrors[] = "{$label}: brak PRICE_COMPARE_TOKEN — nie można wywołać zdalnego przeliczenia.";

                continue;
            }

            $remote = $this->triggerRemoteRecalculate($url, $token, $pairs);
            if ($remote === null) {
                $globalErrors[] = "{$label} ({$url}): nie udało się przeliczyć — sprawdź token i endpoint POST /internal/event-template-prices/recalculate.";

                continue;
            }

            $results[] = [
                'key' => $key,
                'label' => $label,
                'url' => $url,
                'recalculated' => (int) ($remote['recalculated'] ?? 0),
                'pairs' => (int) ($remote['pairs'] ?? count($pairs)),
                'skipped' => (int) ($remote['skipped'] ?? 0),
                'errors' => is_array($remote['errors'] ?? null) ? $remote['errors'] : [],
            ];
        }

        return ['results' => $results, 'errors' => $globalErrors];
    }

    /**
     * @param  list<array{template_id: int, start_place_id: int}>  $pairs
     * @return array<string, mixed>|null
     */
    public function triggerRemoteRecalculate(string $baseUrl, string $token, array $pairs): ?array
    {
        $baseUrl = rtrim($baseUrl, '/');

        try {
            $response = Http::timeout(180)
                ->acceptJson()
                ->post("{$baseUrl}/internal/event-template-prices/recalculate", [
                    'token' => $token,
                    'pairs' => array_values($pairs),
                ]);

            if (! $response->successful()) {
                Log::warning('Remote price recalc failed', [
                    'url' => $baseUrl,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (ConnectionException $e) {
            Log::warning('Remote price recalc connection failed', [
                'url' => $baseUrl,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array{template_id: int, start_place_id: int}|null */
    private function pairFromRow(array $row): ?array
    {
        $startPlaceId = $row['start_place_id'] ?? null;
        if ($startPlaceId === null || (int) $startPlaceId <= 0) {
            return null;
        }

        $templateId = (int) ($row['template_id'] ?? 0);
        if ($templateId <= 0) {
            return null;
        }

        return [
            'template_id' => $templateId,
            'start_place_id' => (int) $startPlaceId,
        ];
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<array{template_id: int, start_place_id: int}>
     */
    private function normalizePairs(array $raw): array
    {
        $pairs = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $templateId = (int) ($item['template_id'] ?? 0);
            $startPlaceId = (int) ($item['start_place_id'] ?? 0);
            if ($templateId <= 0 || $startPlaceId <= 0) {
                continue;
            }
            $pairs["{$templateId}|{$startPlaceId}"] = [
                'template_id' => $templateId,
                'start_place_id' => $startPlaceId,
            ];
        }

        return array_values($pairs);
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     * }  $filters
     * @param  array<string, mixed>  $options
     */
    private function validateComparisonFilters(array $filters, array $options): ?string
    {
        $hasTemplate = ! empty($filters['template_id']);
        $hasStartPlace = ! empty($filters['start_place_id']);

        if (! $hasTemplate && ! $hasStartPlace) {
            return 'Wybierz szablon lub miejsce wyjazdu. Pełny katalog (~200 tys. cen) nie mieści się w pamięci panelu.';
        }

        if (($options['include_calculated'] ?? true) && ! $hasTemplate) {
            return 'Kalkulacja live wymaga wyboru konkretnego szablonu — odznacz ją albo wybierz szablon z listy.';
        }

        return null;
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     */
    private function storedPricesBuilder(array $filters): \Illuminate\Database\Query\Builder
    {
        $plnIds = array_keys($this->plnCurrencyIds());

        $query = DB::table('event_template_price_per_person as p')
            ->join('event_templates as et', 'et.id', '=', 'p.event_template_id')
            ->join('event_template_qties as q', 'q.id', '=', 'p.event_template_qty_id')
            ->leftJoin('places as pl', 'pl.id', '=', 'p.start_place_id')
            ->whereIn('p.currency_id', $plnIds)
            ->where('p.price_per_person', '>', 0)
            ->whereNull('et.deleted_at')
            ->select([
                'p.event_template_id as template_id',
                'et.name as template_name',
                'et.slug as template_slug',
                'et.duration_days',
                'et.is_active',
                'p.start_place_id',
                'pl.name as start_place_name',
                'q.qty',
                'p.price_per_person',
                'p.price_base',
                'p.markup_amount',
                'p.tax_amount',
                'p.transport_cost',
                'p.updated_at',
            ])
            ->orderBy('p.event_template_id')
            ->orderBy('p.start_place_id')
            ->orderBy('q.qty');

        if ($filters['only_active'] ?? true) {
            $query->where('et.is_active', true);
        }

        if (! empty($filters['template_id'])) {
            $query->where('p.event_template_id', (int) $filters['template_id']);
        }

        if (! empty($filters['start_place_id'])) {
            $query->where('p.start_place_id', (int) $filters['start_place_id']);
        }

        return $query;
    }

    /** @return list<string> */
    private function storedPricesCsvHeaders(): array
    {
        return [
            'template_id',
            'template_name',
            'template_slug',
            'duration_days',
            'is_active',
            'start_place_id',
            'start_place_name',
            'qty',
            'price_per_person',
            'price_base',
            'markup_amount',
            'tax_amount',
            'transport_cost',
            'updated_at',
        ];
    }

    /** @return list<string|int|float|null> */
    private function storedPriceRowToCsv(object $row): array
    {
        return [
            (int) $row->template_id,
            (string) $row->template_name,
            (string) $row->template_slug,
            (int) $row->duration_days,
            (int) $row->is_active,
            $row->start_place_id ?? '',
            (string) ($row->start_place_name ?? ''),
            (int) $row->qty,
            number_format((float) $row->price_per_person, 2, '.', ''),
            $row->price_base !== null ? number_format((float) $row->price_base, 2, '.', '') : '',
            $row->markup_amount !== null ? number_format((float) $row->markup_amount, 2, '.', '') : '',
            $row->tax_amount !== null ? number_format((float) $row->tax_amount, 2, '.', '') : '',
            $row->transport_cost !== null ? number_format((float) $row->transport_cost, 2, '.', '') : '',
            (string) ($row->updated_at ?? ''),
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function storedPriceArrayToCsv(array $row): array
    {
        return [
            (int) ($row['template_id'] ?? 0),
            (string) ($row['template_name'] ?? ''),
            (string) ($row['template_slug'] ?? ''),
            (int) ($row['duration_days'] ?? 0),
            (int) ($row['is_active'] ?? 0),
            $row['start_place_id'] ?? '',
            (string) ($row['start_place_name'] ?? ''),
            (int) ($row['qty'] ?? 0),
            isset($row['price_per_person']) ? number_format((float) $row['price_per_person'], 2, '.', '') : '',
            isset($row['price_base']) ? number_format((float) $row['price_base'], 2, '.', '') : '',
            isset($row['markup_amount']) ? number_format((float) $row['markup_amount'], 2, '.', '') : '',
            isset($row['tax_amount']) ? number_format((float) $row['tax_amount'], 2, '.', '') : '',
            isset($row['transport_cost']) ? number_format((float) $row['transport_cost'], 2, '.', '') : '',
            (string) ($row['updated_at'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private function mapStoredPriceRow(object $row): array
    {
        return [
            'template_id' => (int) $row->template_id,
            'template_name' => (string) $row->template_name,
            'template_slug' => (string) $row->template_slug,
            'duration_days' => (int) $row->duration_days,
            'is_active' => (bool) $row->is_active,
            'start_place_id' => $row->start_place_id ? (int) $row->start_place_id : null,
            'start_place_name' => (string) ($row->start_place_name ?? ''),
            'qty' => (int) $row->qty,
            'price_per_person' => (float) $row->price_per_person,
            'price_base' => $row->price_base !== null ? (float) $row->price_base : null,
            'markup_amount' => $row->markup_amount !== null ? (float) $row->markup_amount : null,
            'tax_amount' => $row->tax_amount !== null ? (float) $row->tax_amount : null,
            'transport_cost' => $row->transport_cost !== null ? (float) $row->transport_cost : null,
            'updated_at' => (string) ($row->updated_at ?? ''),
            'source' => 'stored',
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function rowKey(array $row): string
    {
        return implode('|', [
            (int) ($row['template_id'] ?? 0),
            (int) ($row['start_place_id'] ?? 0),
            (int) ($row['qty'] ?? 0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $sourceKeys
     * @return array<string, mixed>
     */
    private function enrichComparisonRowWithDeltas(array $row, array $sourceKeys): array
    {
        $baselineKey = $this->comparisonBaselineSourceKey($sourceKeys);
        $baseline = $baselineKey !== null ? ($row["{$baselineKey}_price"] ?? null) : null;
        $baseline = $baseline !== null ? (float) $baseline : null;

        foreach ($sourceKeys as $source) {
            if ($source === $baselineKey) {
                continue;
            }

            $price = $row["{$source}_price"] ?? null;
            $row["delta_{$source}"] = ($baseline !== null && $price !== null)
                ? round((float) $price - $baseline, 2)
                : null;
        }

        return $row;
    }

    /** @param  list<string>  $sources */
    private function comparisonBaselineSourceKey(array $sources): ?string
    {
        if (in_array('stored', $sources, true)) {
            return 'stored';
        }

        if (in_array('local', $sources, true)) {
            return 'local';
        }

        return $sources[0] ?? null;
    }

    private function comparisonPriceColumnLabel(string $source): string
    {
        return match ($source) {
            'stored' => 'cena_zapisana_pln',
            'calculated' => 'cena_kalkulacja_pln',
            default => 'cena_'.$source.'_pln',
        };
    }

    private function comparisonDeltaColumnLabel(string $source): string
    {
        return match ($source) {
            'calculated' => 'roznica_kalkulacja_pln',
            'stored' => 'roznica_zapisana_pln',
            default => 'roznica_'.$source.'_pln',
        };
    }

    /** @param  list<string>  $sources */
    private function comparisonCsvHeaders(array $sources): array
    {
        $headers = [
            'template_id',
            'template_name',
            'template_slug',
            'start_place_id',
            'start_place_name',
            'qty',
        ];

        foreach ($sources as $source) {
            $headers[] = $this->comparisonPriceColumnLabel($source);
        }

        $baseline = $this->comparisonBaselineSourceKey($sources);
        foreach ($sources as $source) {
            if ($source === $baseline) {
                continue;
            }
            $headers[] = $this->comparisonDeltaColumnLabel($source);
        }

        $headers[] = 'max_roznica_pln';
        $headers[] = 'brak_w_zrodle';
        $headers[] = 'powyzej_progu';

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $sources
     * @return list<string|int>
     */
    private function comparisonCsvLine(array $row, array $sources, float $threshold): array
    {
        $maxDiff = (float) ($row['max_diff'] ?? 0);
        $hasMissing = (bool) ($row['has_missing'] ?? false);

        $line = [
            (int) ($row['template_id'] ?? 0),
            (string) ($row['template_name'] ?? ''),
            (string) ($row['template_slug'] ?? ''),
            $row['start_place_id'] ?? '',
            (string) ($row['start_place_name'] ?? ''),
            (int) ($row['qty'] ?? 0),
        ];

        foreach ($sources as $source) {
            $price = $row["{$source}_price"] ?? null;
            $line[] = $price !== null ? $this->formatCsvPrice((float) $price) : '';
        }

        $baseline = $this->comparisonBaselineSourceKey($sources);
        foreach ($sources as $source) {
            if ($source === $baseline) {
                continue;
            }
            $delta = $row["delta_{$source}"] ?? null;
            $line[] = $delta !== null ? $this->formatCsvDelta((float) $delta) : '';
        }

        $line[] = $this->formatCsvPrice($maxDiff);
        $line[] = $hasMissing ? '1' : '0';
        $line[] = ($maxDiff > $threshold || $hasMissing) ? '1' : '0';

        return $line;
    }

    private function formatCsvPrice(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function formatCsvDelta(float $value): string
    {
        $formatted = number_format(abs($value), 2, '.', '');

        if ($value > 0) {
            return '+'.$formatted;
        }

        if ($value < 0) {
            return '-'.$formatted;
        }

        return '0.00';
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @return \Generator<int, array{key: string, meta: array<string, mixed>, price: float}>
     */
    private function iterateLocalStoredPrices(array $filters): \Generator
    {
        foreach ($this->storedPricesBuilder($filters)->cursor() as $row) {
            yield $this->mapStoredPriceIteratorRow($row);
        }
    }

    /**
     * @param  array{
     *     template_id?: int|null,
     *     start_place_id?: int|null,
     *     only_active?: bool,
     * }  $filters
     * @return \Generator<int, array{key: string, meta: array<string, mixed>, price: float}>
     */
    private function iterateRemoteStoredPrices(string $baseUrl, ?string $token, array $filters): \Generator
    {
        $token = $token ?: config('price-comparison.token');
        if (! $token) {
            return;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $page = 1;
        $lastPage = 1;

        do {
            $response = Http::timeout(120)
                ->acceptJson()
                ->get("{$baseUrl}/internal/event-template-prices", array_filter([
                    'token' => $token,
                    'template_id' => $filters['template_id'] ?? null,
                    'start_place_id' => $filters['start_place_id'] ?? null,
                    'only_active' => isset($filters['only_active']) ? ($filters['only_active'] ? '1' : '0') : '1',
                    'page' => $page,
                    'per_page' => 5000,
                ], fn ($v) => $v !== null && $v !== ''));

            if (! $response->successful()) {
                Log::warning('Price comparison remote page fetch failed', [
                    'url' => $baseUrl,
                    'page' => $page,
                    'status' => $response->status(),
                ]);

                return;
            }

            $data = $response->json();
            if (! is_array($data)) {
                Log::warning('Price comparison remote invalid JSON', ['url' => $baseUrl, 'page' => $page]);

                return;
            }

            foreach ($data['prices'] ?? [] as $priceRow) {
                if (! is_array($priceRow)) {
                    continue;
                }
                yield $this->mapStoredPriceIteratorRow($priceRow);
            }

            $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
            $lastPage = (int) ($meta['last_page'] ?? 1);
            $page++;
        } while ($page <= $lastPage);
    }

    /**
     * @return array{key: string, meta: array<string, mixed>, price: float}
     */
    private function mapStoredPriceIteratorRow(object|array $row): array
    {
        if (is_array($row)) {
            $templateId = (int) ($row['template_id'] ?? 0);
            $startPlaceId = isset($row['start_place_id']) ? (int) $row['start_place_id'] : 0;
            $qty = (int) ($row['qty'] ?? 0);
            $price = (float) ($row['price_per_person'] ?? 0);

            return [
                'key' => $this->rowKey($row),
                'meta' => [
                    'template_id' => $templateId,
                    'template_name' => (string) ($row['template_name'] ?? ''),
                    'template_slug' => (string) ($row['template_slug'] ?? ''),
                    'start_place_id' => $row['start_place_id'] ?? '',
                    'start_place_name' => (string) ($row['start_place_name'] ?? ''),
                    'qty' => $qty,
                ],
                'price' => $price,
            ];
        }

        $templateId = (int) $row->template_id;
        $startPlaceId = $row->start_place_id ? (int) $row->start_place_id : 0;
        $qty = (int) $row->qty;

        return [
            'key' => implode('|', [$templateId, $startPlaceId, $qty]),
            'meta' => [
                'template_id' => $templateId,
                'template_name' => (string) $row->template_name,
                'template_slug' => (string) $row->template_slug,
                'start_place_id' => $row->start_place_id ?? '',
                'start_place_name' => (string) ($row->start_place_name ?? ''),
                'qty' => $qty,
            ],
            'price' => (float) $row->price_per_person,
        ];
    }

    /** @param  array<string, array{key: string, meta: array<string, mixed>, price: float}|null>  $heads */
    private function hasAnyPriceHead(array $heads): bool
    {
        foreach ($heads as $head) {
            if ($head !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array{key: string, meta: array<string, mixed>, price: float}|null>  $heads
     */
    private function minPriceHeadKey(array $heads): ?string
    {
        $minKey = null;

        foreach ($heads as $head) {
            if ($head === null) {
                continue;
            }

            if ($minKey === null || $this->comparePriceKeys($head['key'], $minKey) < 0) {
                $minKey = $head['key'];
            }
        }

        return $minKey;
    }

    private function comparePriceKeys(string $a, string $b): int
    {
        return $this->parsePriceKey($a) <=> $this->parsePriceKey($b);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function parsePriceKey(string $key): array
    {
        $parts = explode('|', $key);

        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        ];
    }
}
