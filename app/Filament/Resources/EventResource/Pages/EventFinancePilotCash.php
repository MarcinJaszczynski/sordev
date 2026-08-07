<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Filament\Resources\EventResource\Concerns\ResolvesEventSettlement;
use App\Filament\Resources\EventSettlementResource\RelationManagers\PilotCashRelationManager;
use App\Filament\Resources\EventSettlementResource\RelationManagers\PilotCurrencyExchangesRelationManager;
use App\Models\Event;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Schema;

/**
 * Gotówka pilota + wymiany walut — nested tab w hubie Finanse.
 */
class EventFinancePilotCash extends Page
{
    use HasEventFinanceSubNavigation;
    use HasRelationManagers;
    use InteractsWithEventRecord;
    use ResolvesEventSettlement;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-settlement-managers';

    protected static ?string $navigationLabel = 'Gotówka i waluty';

    protected static ?string $title = 'Gotówka i waluty';

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

    /**
     * @return array<class-string>
     */
    protected function getAllRelationManagers(): array
    {
        $managers = [];

        if (Schema::hasTable('pilot_cash_preparations')) {
            $managers[] = PilotCashRelationManager::class;
        }

        if (Schema::hasTable('pilot_currency_exchanges')) {
            $managers[] = PilotCurrencyExchangesRelationManager::class;
        }

        return $managers;
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
        return 'finance-pilot-cash';
    }

    public static function getRouteName(?string $panel = null): string
    {
        return EventResource::getRouteBaseName(panel: $panel).'.'.static::getResourcePageName();
    }
}
