<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\EventTemplate;
use App\Models\Place;
use App\Services\EventTemplatePriceComparisonService;
use App\Support\FilamentNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventTemplatePriceComparisonPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Porównanie cen szablonów';

    protected static ?string $title = 'Porównanie cen szablonów imprez';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EVENT_TEMPLATES;

    protected static ?int $navigationSort = 25;

    protected static string $view = 'filament.pages.event-template-price-comparison';

    public ?int $selectedTemplateId = null;

    public ?int $selectedStartPlaceId = null;

    public bool $includeStored = true;

    public bool $includeCalculated = true;

    public bool $onlyDiffs = true;

    public float $threshold = 1.0;

    /** @var array<string, array{label: string, url: string, compare: bool, recalc: bool, is_local: bool}> */
    public array $environmentSettings = [];

    /** @var list<array<string, mixed>> */
    public array $comparisonRows = [];

    /** @var array<string, mixed> */
    public array $summary = [];

    /** @var list<string> */
    public array $errors = [];

    /** @var list<array<string, mixed>> */
    public array $recalcResults = [];

    public bool $isRunning = false;

    public bool $isRecalculating = false;

    public bool $recalcOnlyDiffs = true;

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['admin', 'super_admin', 'biuro'])) {
            return true;
        }

        return $user->can('view_any_event::template')
            || $user->can('view_any_event_template');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->threshold = (float) config('price-comparison.default_threshold', 1.0);
        $this->loadEnvironmentSettings();
    }

    public function updatedEnvironmentSettings(): void
    {
        $this->persistEnvironmentSettings();
    }

    public function runComparison(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->persistEnvironmentSettings();

        $this->isRunning = true;
        $this->errors = [];
        $this->comparisonRows = [];
        $this->summary = [];
        $this->recalcResults = [];

        try {
            $result = app(EventTemplatePriceComparisonService::class)->runComparison([
                'template_id' => $this->selectedTemplateId,
                'start_place_id' => $this->selectedStartPlaceId,
                'include_stored' => $this->includeStored,
                'include_calculated' => $this->includeCalculated,
                'only_diffs' => $this->onlyDiffs,
                'threshold' => $this->threshold,
                'remote_environments' => $this->remoteCompareTargets(),
            ]);

            $this->comparisonRows = $result['rows'];
            $this->summary = $result['summary'];
            $this->errors = $result['errors'];

            if ($this->comparisonRows === [] && $this->errors === []) {
                Notification::make()
                    ->title('Brak różnic')
                    ->body('Nie znaleziono rozbieżności dla wybranych filtrów i źródeł.')
                    ->success()
                    ->send();
            } elseif ($this->comparisonRows === [] && $this->errors !== []) {
                Notification::make()
                    ->title('Porównanie niemożliwe')
                    ->body(implode(' ', $this->errors))
                    ->warning()
                    ->send();
            } elseif ($this->errors !== []) {
                Notification::make()
                    ->title('Porównanie zakończone z ostrzeżeniami')
                    ->body(implode(' ', $this->errors))
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title('Porównanie gotowe')
                    ->body(sprintf('Znaleziono %d wierszy z różnicami.', count($this->comparisonRows)))
                    ->success()
                    ->send();
            }
        } catch (\Throwable $e) {
            $this->errors[] = $e->getMessage();

            Notification::make()
                ->title('Błąd porównania')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isRunning = false;
        }
    }

    public function bulkRecalculate(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->persistEnvironmentSettings();

        $targets = $this->recalcTargets();
        if ($targets === []) {
            Notification::make()
                ->title('Wybierz środowiska')
                ->body('Zaznacz co najmniej jedno środowisko w sekcji „Przelicz ceny” (kolumna Przelicz).')
                ->warning()
                ->send();

            return;
        }

        if ($this->comparisonRows === [] && ! $this->selectedTemplateId) {
            Notification::make()
                ->title('Wybierz szablon')
                ->body('Bez wyników porównania musisz wskazać szablon (i opcjonalnie miejsce wyjazdu), który chcesz przeliczyć.')
                ->warning()
                ->send();

            return;
        }

        $this->isRecalculating = true;
        $this->recalcResults = [];

        try {
            $result = app(EventTemplatePriceComparisonService::class)->recalculateOnEnvironments(
                targets: $targets,
                rows: $this->comparisonRows,
                threshold: $this->threshold,
                templateId: $this->selectedTemplateId,
                startPlaceId: $this->selectedStartPlaceId,
                onlyDiffsFromRows: $this->recalcOnlyDiffs,
            );

            $this->recalcResults = $result['results'];
            if ($result['errors'] !== []) {
                $this->errors = array_merge($this->errors, $result['errors']);
            }

            if ($this->recalcResults === [] && $result['errors'] !== []) {
                Notification::make()
                    ->title('Przeliczanie nieudane')
                    ->body(implode("\n", $result['errors']))
                    ->danger()
                    ->send();

                return;
            }

            $lines = [];
            foreach ($this->recalcResults as $row) {
                $lines[] = sprintf(
                    '%s: %d/%d par',
                    $row['label'],
                    $row['recalculated'],
                    $row['pairs'],
                );
            }

            Notification::make()
                ->title('Przeliczanie zakończone')
                ->body(implode(' · ', $lines))
                ->success()
                ->send();

            if ($this->includeStored || $this->includeCalculated || $this->hasCompareTargets()) {
                $this->runComparison();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Błąd przeliczania')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isRecalculating = false;
        }
    }

    public function exportCsv(): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        if ($this->comparisonRows === []) {
            Notification::make()
                ->title('Brak danych do eksportu')
                ->body('Uruchom porównanie, aby wygenerować plik CSV.')
                ->warning()
                ->send();

            return null;
        }

        $sources = $this->summary['sources'] ?? $this->resolveActiveSourceKeys();
        $content = app(EventTemplatePriceComparisonService::class)->buildCsvContent(
            $this->comparisonRows,
            $sources,
            $this->threshold,
        );

        $filename = 'porownanie-cen-szablonow-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadComparisonCsv(): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $this->persistEnvironmentSettings();

        if (! $this->canExportComparison()) {
            Notification::make()
                ->title('Nie można eksportować porównania')
                ->body($this->exportComparisonBlockedReason())
                ->warning()
                ->send();

            return null;
        }

        $filename = 'porownanie-cen-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            app(EventTemplatePriceComparisonService::class)->streamComparisonCsv($handle, [
                'template_id' => $this->selectedTemplateId,
                'start_place_id' => $this->selectedStartPlaceId,
                'include_stored' => $this->includeStored,
                'include_calculated' => $this->includeCalculated,
                'only_diffs' => $this->onlyDiffs,
                'threshold' => $this->threshold,
                'remote_environments' => $this->remoteCompareTargets(),
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadEnvironmentComparisonCsv(): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $this->persistEnvironmentSettings();

        if (! $this->canExportEnvironmentComparison()) {
            Notification::make()
                ->title('Nie można eksportować porównania 3 środowisk')
                ->body($this->environmentComparisonBlockedReason())
                ->warning()
                ->send();

            return null;
        }

        $service = app(EventTemplatePriceComparisonService::class);
        $environments = $this->environmentComparisonTargets();
        $probeErrors = $service->validateEnvironmentEndpoints($environments);

        if ($probeErrors !== []) {
            Notification::make()
                ->title('Zdalne środowiska niedostępne')
                ->body(implode("\n", $probeErrors))
                ->danger()
                ->persistent()
                ->send();

            return null;
        }

        $suffix = collect([
            $this->selectedTemplateId ? 'szablon-'.$this->selectedTemplateId : null,
            $this->selectedStartPlaceId ? 'start-'.$this->selectedStartPlaceId : null,
        ])->filter()->implode('_');

        $filename = 'porownanie-srodowisk'.($suffix !== '' ? '-'.$suffix : '-pelna').'-'.now()->format('Y-m-d_His').'.csv';

        $filters = [
            'template_id' => $this->selectedTemplateId,
            'start_place_id' => $this->selectedStartPlaceId,
            'only_active' => true,
        ];

        return response()->streamDownload(function () use ($service, $environments, $filters): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            $service->streamMultiEnvironmentComparisonCsv($handle, $environments, $filters, [
                'only_diffs' => $this->onlyDiffs,
                'threshold' => $this->threshold,
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function getFullCatalogExportUrl(): string
    {
        return route('admin.event-template-prices.export', array_filter([
            'template_id' => $this->selectedTemplateId,
            'start_place_id' => $this->selectedStartPlaceId,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function getEnvironmentComparisonExportUrl(): string
    {
        return route('admin.event-template-prices.export-comparison', array_filter([
            'mode' => 'environments',
            'template_id' => $this->selectedTemplateId,
            'start_place_id' => $this->selectedStartPlaceId,
            'only_diffs' => $this->onlyDiffs ? 1 : 0,
            'threshold' => $this->threshold,
            'prod_url' => $this->environmentSettings['prod']['url'] ?? null,
            'dev_url' => $this->environmentSettings['dev']['url'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function canExportEnvironmentComparison(): bool
    {
        return (bool) config('price-comparison.token')
            && rtrim((string) ($this->environmentSettings['prod']['url'] ?? ''), '/') !== ''
            && rtrim((string) ($this->environmentSettings['dev']['url'] ?? ''), '/') !== '';
    }

    public function environmentComparisonBlockedReason(): string
    {
        if (! config('price-comparison.token')) {
            return 'Ustaw PRICE_COMPARE_TOKEN w .env (ten sam token na local, prod i dev).';
        }

        if (rtrim((string) ($this->environmentSettings['prod']['url'] ?? ''), '/') === '') {
            return 'Uzupełnij adres produkcji w tabeli środowisk.';
        }

        if (rtrim((string) ($this->environmentSettings['dev']['url'] ?? ''), '/') === '') {
            return 'Uzupełnij adres dev w tabeli środowisk.';
        }

        return '';
    }

    public function getComparisonExportUrl(): string
    {
        return route('admin.event-template-prices.export-comparison', array_filter([
            'template_id' => $this->selectedTemplateId,
            'start_place_id' => $this->selectedStartPlaceId,
            'prod' => $this->environmentSettings['prod']['compare'] ?? false ? 1 : null,
            'dev' => $this->environmentSettings['dev']['compare'] ?? false ? 1 : null,
            'no_calculated' => $this->includeCalculated ? null : 1,
            'no_stored' => $this->includeStored ? null : 1,
            'only_diffs' => $this->onlyDiffs ? 1 : 0,
            'threshold' => $this->threshold,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function canExportComparison(): bool
    {
        return ($this->selectedTemplateId || $this->selectedStartPlaceId)
            && $this->countActiveSources() >= 2;
    }

    public function countActiveSources(): int
    {
        return count($this->resolveActiveSourceKeys());
    }

    /** @return list<string> */
    public function getActiveSourceLabels(): array
    {
        $labels = [];
        if ($this->includeStored) {
            $labels[] = 'Zapisane (przed kalkulacją)';
        }
        if ($this->includeCalculated) {
            $labels[] = 'Kalkulacja (po przeliczeniu)';
        }
        foreach ($this->environmentSettings as $key => $env) {
            if (($env['compare'] ?? false) && ! ($env['is_local'] ?? false)) {
                $labels[] = (string) ($env['label'] ?? $key);
            }
        }

        return $labels;
    }

    public function exportComparisonBlockedReason(): string
    {
        if (! $this->selectedTemplateId && ! $this->selectedStartPlaceId) {
            return 'Wybierz szablon lub miasto wyjazdu w filtrach poniżej.';
        }

        if ($this->countActiveSources() < 2) {
            return 'Zaznacz co najmniej 2 źródła do porównania — np. „Zapisane” + „Kalkulacja”, albo prod/dev w tabeli środowisk.';
        }

        return '';
    }

    public function resetEnvironmentUrls(): void
    {
        session()->forget($this->environmentSessionKey());
        $this->loadEnvironmentSettings(fromConfig: true);

        Notification::make()
            ->title('Przywrócono adresy z .env')
            ->success()
            ->send();
    }

    /** @return array<int, string> */
    public function getTemplateOptions(): array
    {
        return EventTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    public function getStartPlaceOptions(): array
    {
        return Place::startingPlaceSelectOptions();
    }

    public function formatPrice(?float $price): string
    {
        if ($price === null) {
            return '—';
        }

        return number_format($price, 0, ',', ' ').' zł';
    }

    public function formatDelta(?float $delta): string
    {
        if ($delta === null) {
            return '';
        }

        if ($delta === 0.0) {
            return '0 zł';
        }

        $sign = $delta > 0 ? '+' : '−';

        return $sign.number_format(abs($delta), 0, ',', ' ').' zł';
    }

    public function templateEditUrl(int $templateId): string
    {
        return route('filament.admin.resources.event-templates.calculation', ['record' => $templateId]);
    }

    public function templateWwwUrl(int $templateId, ?int $startPlaceId): ?string
    {
        $template = EventTemplate::query()->find($templateId);
        if (! $template) {
            return null;
        }

        $base = $this->environmentSettings['local']['url']
            ?? config('price-comparison.environments.local.base_url', config('app.url'));

        return rtrim((string) $base, '/').$template->prettyUrl($startPlaceId);
    }

    /** @return list<array{key: string, label: string, url: string, is_local: bool}> */
    private function environmentComparisonTargets(): array
    {
        return [
            [
                'key' => 'local',
                'label' => 'local',
                'url' => rtrim((string) ($this->environmentSettings['local']['url'] ?? config('app.url')), '/'),
                'is_local' => true,
            ],
            [
                'key' => 'prod',
                'label' => 'prod',
                'url' => rtrim((string) ($this->environmentSettings['prod']['url'] ?? ''), '/'),
                'is_local' => false,
            ],
            [
                'key' => 'dev',
                'label' => 'dev',
                'url' => rtrim((string) ($this->environmentSettings['dev']['url'] ?? ''), '/'),
                'is_local' => false,
            ],
        ];
    }

    /** @return list<string> */
    private function resolveActiveSourceKeys(): array
    {
        $sources = [];
        if ($this->includeStored) {
            $sources[] = 'stored';
        }
        if ($this->includeCalculated) {
            $sources[] = 'calculated';
        }
        foreach ($this->environmentSettings as $key => $env) {
            if (($env['compare'] ?? false) && ! ($env['is_local'] ?? false)) {
                $sources[] = $key;
            }
        }

        return $sources;
    }

    /** @return list<array{key: string, label: string, url: string, is_local: bool}> */
    private function remoteCompareTargets(): array
    {
        $targets = [];
        foreach ($this->environmentSettings as $key => $env) {
            if (! ($env['compare'] ?? false) || ($env['is_local'] ?? false)) {
                continue;
            }
            $targets[] = [
                'key' => $key,
                'label' => (string) ($env['label'] ?? $key),
                'url' => rtrim((string) ($env['url'] ?? ''), '/'),
                'is_local' => false,
            ];
        }

        return $targets;
    }

    /** @return list<array{key: string, label: string, url: string, is_local: bool}> */
    private function recalcTargets(): array
    {
        $targets = [];
        foreach ($this->environmentSettings as $key => $env) {
            if (! ($env['recalc'] ?? false)) {
                continue;
            }
            $targets[] = [
                'key' => $key,
                'label' => (string) ($env['label'] ?? $key),
                'url' => rtrim((string) ($env['url'] ?? ''), '/'),
                'is_local' => (bool) ($env['is_local'] ?? false),
            ];
        }

        return $targets;
    }

    private function hasCompareTargets(): bool
    {
        return $this->remoteCompareTargets() !== [];
    }

    private function loadEnvironmentSettings(bool $fromConfig = false): void
    {
        $defaults = $this->defaultEnvironmentSettings();
        $stored = $fromConfig ? [] : (session()->get($this->environmentSessionKey(), []) ?? []);

        $merged = [];
        foreach ($defaults as $key => $default) {
            $saved = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $merged[$key] = [
                'label' => (string) ($default['label'] ?? $key),
                'url' => (string) ($saved['url'] ?? $default['url'] ?? ''),
                'compare' => (bool) ($saved['compare'] ?? $default['compare'] ?? false),
                'recalc' => (bool) ($saved['recalc'] ?? $default['recalc'] ?? false),
                'is_local' => (bool) ($default['is_local'] ?? false),
            ];
        }

        $this->environmentSettings = $merged;
    }

    /** @return array<string, array{label: string, url: string, compare: bool, recalc: bool, is_local: bool}> */
    private function defaultEnvironmentSettings(): array
    {
        $settings = [];
        foreach (config('price-comparison.environments', []) as $key => $env) {
            $settings[$key] = [
                'label' => (string) ($env['label'] ?? $key),
                'url' => rtrim((string) ($env['base_url'] ?? ''), '/'),
                'compare' => $key === 'local',
                'recalc' => $key === 'local',
                'is_local' => (bool) ($env['is_local'] ?? $key === 'local'),
            ];
        }

        return $settings;
    }

    private function persistEnvironmentSettings(): void
    {
        $payload = [];
        foreach ($this->environmentSettings as $key => $env) {
            $payload[$key] = [
                'url' => rtrim((string) ($env['url'] ?? ''), '/'),
                'compare' => (bool) ($env['compare'] ?? false),
                'recalc' => (bool) ($env['recalc'] ?? false),
            ];
        }

        session()->put($this->environmentSessionKey(), $payload);
    }

    private function environmentSessionKey(): string
    {
        return 'event_template_price_comparison.environments';
    }
}
