<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EventAuditLogPage;
use App\Filament\Resources\EventResource\Pages\ManageEventContracts;
use App\Filament\Resources\EventResource\Pages\ManageEventDocuments;
use App\Support\WorkflowModuleNavigation;
use Illuminate\Support\Facades\Schema;

trait HasEventDocumentsSubNavigation
{
    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public static function documentsSubNavigationActiveTab(): string
    {
        return match (static::class) {
            ManageEventContracts::class => 'contracts',
            ManageEventDocuments::class => 'files',
            EventAuditLogPage::class => 'audit',
            default => 'contracts',
        };
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, url: string, icon: string, badge: ?string, active?: bool}>
     */
    public static function documentsSubNavigationTabs(int|string $recordId): array
    {
        $tabs = [];

        if (Schema::hasTable('contracts') || Schema::hasTable('event_agreements')) {
            $tabs[] = [
                'key' => 'contracts',
                'label' => 'Umowy',
                'description' => 'Umowy i harmonogramy płatności',
                'icon' => 'heroicon-o-document-check',
                'url' => EventResource::getUrl('contracts', ['record' => $recordId]),
                'badge' => null,
            ];
        }

        if (Schema::hasTable('event_documents')) {
            $tabs[] = [
                'key' => 'files',
                'label' => 'Pliki',
                'description' => 'Dokumenty i paczki PDF',
                'icon' => 'heroicon-o-folder',
                'url' => EventResource::getUrl('documents', ['record' => $recordId]),
                'badge' => null,
            ];
        }

        if (Schema::hasTable('event_histories')) {
            $tabs[] = [
                'key' => 'audit',
                'label' => 'Historia',
                'description' => 'Dziennik zmian imprezy',
                'icon' => 'heroicon-o-clock',
                'url' => EventResource::getUrl('audit', ['record' => $recordId]),
                'badge' => null,
            ];
        }

        if ($tabs === []) {
            return [];
        }

        return WorkflowModuleNavigation::markActive($tabs, static::documentsSubNavigationActiveTab());
    }

    /**
     * @return array<int, string>
     */
    public static function documentsRouteNames(): array
    {
        $routes = [];

        if (Schema::hasTable('contracts') || Schema::hasTable('event_agreements')) {
            $routes[] = ManageEventContracts::getRouteName();
        }

        if (Schema::hasTable('event_documents')) {
            $routes[] = ManageEventDocuments::getRouteName();
        }

        if (Schema::hasTable('event_histories')) {
            $routes[] = EventAuditLogPage::getRouteName();
        }

        return $routes;
    }

    /**
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        $recordId = $this->getRecord()->getKey();
        $section = match (static::documentsSubNavigationActiveTab()) {
            'contracts' => 'Umowy',
            'files' => 'Pliki',
            'audit' => 'Historia',
            default => 'Dokumenty',
        };

        $moduleUrl = EventResource::getUrl('contracts', ['record' => $recordId]);
        if (! Schema::hasTable('contracts') && ! Schema::hasTable('event_agreements')) {
            $moduleUrl = Schema::hasTable('event_documents')
                ? EventResource::getUrl('documents', ['record' => $recordId])
                : EventResource::getUrl('audit', ['record' => $recordId]);
        }

        return $this->eventRecordBreadcrumbs(
            moduleLabel: 'Dokumenty',
            moduleUrl: $moduleUrl,
            sectionLabel: $section,
        );
    }
}
