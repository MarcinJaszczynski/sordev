<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Legacy: Uczestnicy → Zapłacono. Kanoniczny UI: Finanse → Wpłaty uczestników.
 */
class ManageEventSettlementPayments extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-redirect';

    protected static ?string $navigationLabel = 'Wpłaty uczestników';

    protected static ?string $title = 'Wpłaty uczestników';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->redirect(EventResource::getUrl('finance-participant-payments', ['record' => $this->record]));
    }

    public static function getResourcePageName(): string
    {
        return 'participant-payments';
    }

    public static function getRouteName(?string $panel = null): string
    {
        return EventResource::getRouteBaseName(panel: $panel).'.'.static::getResourcePageName();
    }
}
