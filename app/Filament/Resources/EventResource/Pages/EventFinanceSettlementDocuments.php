<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Filament\Resources\EventResource\Concerns\ResolvesEventSettlement;
use App\Filament\Resources\EventSettlementResource\RelationManagers\DocumentsRelationManager;
use App\Models\Event;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumenty rozliczenia — nested tab w hubie Finanse.
 */
class EventFinanceSettlementDocuments extends Page
{
    use HasEventFinanceSubNavigation;
    use HasRelationManagers;
    use InteractsWithEventRecord;
    use ResolvesEventSettlement;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-settlement-managers';

    protected static ?string $navigationLabel = 'Dok. rozliczenia';

    protected static ?string $title = 'Dokumenty rozliczenia';

    protected static ?string $navigationIcon = 'heroicon-o-paper-clip';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
        $this->resolveEventSettlement($this->getRecord());
    }

    protected function currentEvent(): Event
    {
        /** @var Event $event */
        $event = $this->record;

        return $event;
    }

    /**
     * @return array<class-string>
     */
    protected function getAllRelationManagers(): array
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            return [];
        }

        return [DocumentsRelationManager::class];
    }

    public function getRelationManagers(): array
    {
        $managers = [];

        foreach ($this->getAllRelationManagers() as $manager) {
            $managers[$manager] = $manager;
        }

        return $managers;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }

    public static function getResourcePageName(): string
    {
        return 'finance-settlement-documents';
    }

    public static function getRouteName(?string $panel = null): string
    {
        return EventResource::getRouteBaseName(panel: $panel).'.'.static::getResourcePageName();
    }
}
