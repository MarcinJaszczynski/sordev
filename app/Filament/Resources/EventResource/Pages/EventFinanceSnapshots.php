<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Actions\CreateEventSnapshotAction;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Filament\Resources\EventResource\RelationManagers\SnapshotsRelationManager;
use App\Models\Event;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Schema;

/**
 * Migawki stanu imprezy — nested tab w hubie Finanse.
 */
class EventFinanceSnapshots extends Page
{
    use HasEventFinanceSubNavigation;
    use HasRelationManagers;
    use InteractsWithEventRecord;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-snapshots';

    protected static ?string $navigationLabel = 'Migawki';

    protected static ?string $title = 'Migawki';

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
        abort_unless(Schema::hasTable('event_snapshots'), 404);
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
        if (! Schema::hasTable('event_snapshots')) {
            return [];
        }

        return [SnapshotsRelationManager::class];
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

    protected function getHeaderActions(): array
    {
        return [
            CreateEventSnapshotAction::make(),
        ];
    }

    public static function getResourcePageName(): string
    {
        foreach (EventResource::getPages() as $pageName => $pageRegistration) {
            if ($pageRegistration->getPage() !== static::class) {
                continue;
            }

            return $pageName;
        }

        throw new \Exception('Page ['.static::class.'] is not registered to the resource ['.EventResource::class.'].');
    }

    public static function getRouteName(?string $panel = null): string
    {
        return EventResource::getRouteBaseName(panel: $panel).'.'.static::getResourcePageName();
    }
}
