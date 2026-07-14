<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Resources\EventSettlementResource;
use App\Filament\Widgets\FinanceModuleNavWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEventSettlements extends ListRecords
{
    protected static string $resource = EventSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nowe rozliczenie'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FinanceModuleNavWidget::make(['activeTab' => 'settlements']),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Koszty imprez, gotówka pilota i dokumenty — otwórz rozliczenie, aby zatwierdzić pozycje.';
    }
}
