<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Filament\Resources\EventResource\Concerns\ResolvesEventSettlement;
use App\Filament\Resources\EventSettlementResource\RelationManagers\ParticipantPaymentsRelationManager;
use App\Models\Event;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Page;

/**
 * Kanoniczny UI wpłat uczestników — zakładka w hubie Finanse.
 */
class EventFinanceParticipantPayments extends Page
{
    use HasEventFinanceSubNavigation;
    use HasRelationManagers;
    use InteractsWithEventRecord;
    use ResolvesEventSettlement;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-participant-payments';

    protected static ?string $navigationLabel = 'Wpłaty';

    protected static ?string $title = 'Wpłaty';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

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
        return [
            ParticipantPaymentsRelationManager::class,
        ];
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
