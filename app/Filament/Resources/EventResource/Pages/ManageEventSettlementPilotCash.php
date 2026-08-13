<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

/** @deprecated Bookmark → EventFinancePilotCash */
class ManageEventSettlementPilotCash extends RedirectEventToFinance
{
    public static function getResourcePageName(): string
    {
        return 'settlement-pilot-cash';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
        $this->redirect(\App\Filament\Resources\EventResource::getUrl('finance-pilot-cash', [
            'record' => $this->getRecord(),
        ]));
    }
}
