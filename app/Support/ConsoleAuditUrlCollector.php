<?php

namespace App\Support;

use App\Filament\Pilot\Resources\PilotEventResource;
use App\Models\Event;
use App\Models\User;
use App\Services\PilotAccessService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ConsoleAuditUrlCollector
{
    /** @var list<string> */
    private array $skipped = [];

    /**
     * @return array{urls: list<array{panel: string, group: string, label: string, path: string, auth: string}>, skipped: list<array{label: string, reason: string}>}
     */
    public function collect(?string $groupFilter = null): array
    {
        $this->skipped = [];
        $urls = [];

        foreach (['admin', 'pilot'] as $panelId) {
            if ($groupFilter !== null && $groupFilter !== 'pilot' && $panelId === 'pilot') {
                continue;
            }

            if ($groupFilter === 'pilot' && $panelId === 'admin') {
                continue;
            }

            $panel = Filament::getPanel($panelId);
            $urls = array_merge($urls, $this->collectPanelPages($panel, $groupFilter));
            $urls = array_merge($urls, $this->collectPanelResources($panel, $groupFilter));

            if ($panelId === 'pilot') {
                $urls = array_merge($urls, $this->collectPilotTripPages($groupFilter));
            }
        }

        $urls = $this->deduplicateUrls($urls);

        if ($groupFilter !== null) {
            $urls = array_values(array_filter(
                $urls,
                fn (array $entry): bool => $entry['group'] === $groupFilter || $groupFilter === 'pilot' && $entry['panel'] === 'pilot',
            ));
        }

        return [
            'urls' => $urls,
            'skipped' => $this->skipped,
        ];
    }

    /**
     * @return list<array{panel: string, group: string, label: string, path: string, auth: string}>
     */
    private function collectPanelPages(Panel $panel, ?string $groupFilter): array
    {
        $urls = [];

        foreach ($panel->getPages() as $pageClass) {
            if (! is_subclass_of($pageClass, Page::class)) {
                continue;
            }

            if (! $pageClass::isDiscovered() && ! in_array($pageClass, $panel->getPages(), true)) {
                continue;
            }

            $group = $this->resolvePageGroup($pageClass, $panel->getId());
            if ($groupFilter !== null && $groupFilter !== 'pilot' && $group !== $groupFilter) {
                continue;
            }

            $label = $pageClass::getNavigationLabel() ?? class_basename($pageClass);

            try {
                if ($panel->getId() === 'pilot' && $this->pilotTripPageNeedsEvent($pageClass)) {
                    continue;
                }

                $fullUrl = $pageClass::getUrl(panel: $panel->getId());
                $urls[] = $this->makeEntry($panel->getId(), $group, $label, $fullUrl, $panel->getId());
            } catch (Throwable $e) {
                $this->skip("{$panel->getId()} page: {$label}", $e->getMessage());
            }
        }

        return $urls;
    }

    /**
     * @return list<array{panel: string, group: string, label: string, path: string, auth: string}>
     */
    private function collectPanelResources(Panel $panel, ?string $groupFilter): array
    {
        $urls = [];

        foreach ($panel->getResources() as $resourceClass) {
            if (! is_subclass_of($resourceClass, Resource::class)) {
                continue;
            }

            $group = $this->resolveResourceGroup($resourceClass);
            if ($groupFilter !== null && $groupFilter !== 'pilot' && $group !== $groupFilter) {
                continue;
            }

            $resourceLabel = $resourceClass::getPluralModelLabel() ?? class_basename($resourceClass);
            $modelClass = $resourceClass::getModel();
            $record = $panel->getId() === 'pilot'
                ? $this->samplePilotEvent()
                : $this->sampleRecord($modelClass);

            foreach ($resourceClass::getPages() as $pageName => $pageRegistration) {
                $pageLabel = "{$resourceLabel} / {$pageName}";

                if ($pageName === 'create') {
                    try {
                        $fullUrl = $resourceClass::getUrl($pageName, panel: $panel->getId());
                        $urls[] = $this->makeEntry($panel->getId(), $group, $pageLabel, $fullUrl, $panel->getId());
                    } catch (Throwable $e) {
                        $this->skip($pageLabel, $e->getMessage());
                    }

                    continue;
                }

                if ($this->pageNeedsRecord($resourceClass, $pageName) && $record === null) {
                    $reason = $panel->getId() === 'pilot'
                        ? 'brak imprezy dla konta demo pilota (uruchom: php artisan pilot:setup-demo)'
                        : 'brak rekordu w bazie dla '.class_basename($modelClass);
                    $this->skip($pageLabel, $reason);

                    continue;
                }

                try {
                    $parameters = $record !== null ? ['record' => $record] : [];
                    $fullUrl = $resourceClass::getUrl($pageName, $parameters, panel: $panel->getId());
                    $urls[] = $this->makeEntry($panel->getId(), $group, $pageLabel, $fullUrl, $panel->getId());
                } catch (Throwable $e) {
                    $this->skip($pageLabel, $e->getMessage());
                }
            }
        }

        return $urls;
    }

    /**
     * @return list<array{panel: string, group: string, label: string, path: string, auth: string}>
     */
    private function collectPilotTripPages(?string $groupFilter): array
    {
        if ($groupFilter !== null && ! in_array($groupFilter, ['pilot', 'events'], true)) {
            return [];
        }

        $event = $this->samplePilotEvent();
        if ($event === null) {
            $this->skip('Pilot trip pages', 'brak imprezy dla pilota (uruchom: php artisan pilot:setup-demo)');

            return [];
        }

        $group = 'pilot';
        $eventId = $event->id;
        $pages = [
            ['label' => 'Pilot / program', 'path' => "/pilot/program/{$eventId}"],
            ['label' => 'Pilot / hotel', 'path' => "/pilot/hotel-plan/{$eventId}"],
            ['label' => 'Pilot / checklist', 'path' => "/pilot/checklist/{$eventId}"],
            ['label' => 'Pilot / rozliczenie', 'path' => "/pilot/settlement/{$eventId}"],
            ['label' => 'Pilot / gotówka i rozliczenie', 'path' => "/pilot/settlement/{$eventId}"],
        ];

        $urls = [];
        foreach ($pages as $page) {
            $urls[] = $this->makeEntry('pilot', $group, $page['label'], $page['path'], 'pilot');
        }

        try {
            $viewUrl = PilotEventResource::getUrl('view', ['record' => $event], panel: 'pilot');
            $urls[] = $this->makeEntry('pilot', $group, 'Pilot / podgląd imprezy', $viewUrl, 'pilot');
        } catch (Throwable $e) {
            $this->skip('Pilot / podgląd imprezy', $e->getMessage());
        }

        return $urls;
    }

    private function pageNeedsRecord(string $resourceClass, string $pageName): bool
    {
        return ! in_array($pageName, ['index', 'create'], true);
    }

    private function sampleRecord(string $modelClass): ?Model
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        try {
            $table = (new $modelClass)->getTable();
            if (! Schema::hasTable($table)) {
                return null;
            }

            return $modelClass::query()->orderByDesc('id')->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function samplePilotEvent(): ?Event
    {
        if (! Schema::hasTable('events')) {
            return null;
        }

        $pilot = User::query()
            ->where('email', PilotDemoSeederEmail::DEFAULT)
            ->first();

        if ($pilot === null) {
            return null;
        }

        $access = app(PilotAccessService::class);

        $events = Event::query()
            ->forPilot($pilot)
            ->where('status', '!=', Event::STATUS_CANCELLED)
            ->orderByDesc('id')
            ->get();

        foreach ($events as $event) {
            if ($access->hasFullAccess($event, $pilot)) {
                return $event;
            }
        }

        // Nie podawaj zarchiwizowanej imprezy — strony trip dają 403 (viewPilotDetails).
        return null;
    }

    /**
     * @param  class-string<Page>  $pageClass
     */
    private function resolvePageGroup(string $pageClass, string $panelId): string
    {
        if ($panelId === 'pilot') {
            return 'pilot';
        }

        $group = $pageClass::getNavigationGroup();

        return $this->navigationGroupToSlug($group);
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function resolveResourceGroup(string $resourceClass): string
    {
        if (str_contains($resourceClass, '\\Pilot\\')) {
            return 'pilot';
        }

        return $this->navigationGroupToSlug($resourceClass::getNavigationGroup());
    }

    private function navigationGroupToSlug(?string $group): string
    {
        return match ($group) {
            FilamentNavigation::GROUP_EVENTS, FilamentNavigation::GROUP_OPERATIONS, FilamentNavigation::GROUP_TEMPLATES, FilamentNavigation::GROUP_TASKS => 'events',
            FilamentNavigation::GROUP_EVENT_TEMPLATES => 'templates',
            FilamentNavigation::GROUP_FINANCE => 'finance',
            FilamentNavigation::GROUP_EXECUTIVE => 'executive',
            FilamentNavigation::GROUP_CONTACTS => 'contacts',
            FilamentNavigation::GROUP_DICTIONARIES, FilamentNavigation::GROUP_CONFIG, FilamentNavigation::GROUP_SETTINGS, FilamentNavigation::GROUP_TOOLS => 'dictionaries',
            FilamentNavigation::GROUP_SYSTEM => 'system',
            PilotNavigation::GROUP_TRIPS => 'pilot',
            default => 'other',
        };
    }

    /**
     * @return array{panel: string, group: string, label: string, path: string, auth: string}
     */
    private function makeEntry(string $panel, string $group, string $label, string $fullUrlOrPath, string $auth): array
    {
        $path = str_starts_with($fullUrlOrPath, '/')
            ? $fullUrlOrPath
            : (parse_url($fullUrlOrPath, PHP_URL_PATH) ?: $fullUrlOrPath);

        return [
            'panel' => $panel,
            'group' => $group,
            'label' => $label,
            'path' => $path,
            'auth' => $auth,
        ];
    }

    /**
     * @param  list<array{panel: string, group: string, label: string, path: string, auth: string}>  $urls
     * @return list<array{panel: string, group: string, label: string, path: string, auth: string}>
     */
    private function deduplicateUrls(array $urls): array
    {
        $seen = [];
        $unique = [];

        foreach ($urls as $entry) {
            $key = $entry['path'].'|'.$entry['auth'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $entry;
        }

        usort($unique, fn (array $a, array $b): int => [$a['panel'], $a['group'], $a['path']] <=> [$b['panel'], $b['group'], $b['path']]);

        return $unique;
    }

    private function pilotTripPageNeedsEvent(string $pageClass): bool
    {
        return in_array($pageClass, [
            \App\Filament\Pilot\Pages\PilotAdvancePage::class,
            \App\Filament\Pilot\Pages\PilotChecklistPage::class,
            \App\Filament\Pilot\Pages\PilotHotelPlanPage::class,
            \App\Filament\Pilot\Pages\PilotProgramPage::class,
            \App\Filament\Pilot\Pages\PilotSettlementPage::class,
        ], true);
    }

    private function skip(string $label, string $reason): void
    {
        $this->skipped[] = [
            'label' => $label,
            'reason' => $reason,
        ];
    }
}

/**
 * Avoid coupling to seeder class in production autoload edge cases.
 */
final class PilotDemoSeederEmail
{
    public const DEFAULT = 'pilot@test.local';
}
