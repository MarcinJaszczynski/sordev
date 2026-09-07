<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Filament\Resources\EventResource\Concerns\ResolvesEventSettlement;
use App\Models\Event;
use Filament\Resources\Pages\Page;

/**
 * Gotówka dla pilota — saldo, wymiany, wydatki (wspólne z panelem pilota).
 */
class EventFinancePilotCash extends Page
{
    use HasEventFinanceSubNavigation;
    use InteractsWithEventRecord;
    use ResolvesEventSettlement;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-pilot-cash';

    protected static ?string $navigationLabel = 'Gotówka pilota';

    protected static ?string $title = 'Gotówka pilota';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

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

    public static function getResourcePageName(): string
    {
        return 'finance-pilot-cash';
    }

    public static function getRouteName(?string $panel = null): string
    {
        return EventResource::getRouteBaseName(panel: $panel).'.'.static::getResourcePageName();
    }
}
