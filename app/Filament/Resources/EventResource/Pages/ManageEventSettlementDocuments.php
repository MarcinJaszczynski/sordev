<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

/** @deprecated Bookmark → EventFinanceSettlementDocuments */
class ManageEventSettlementDocuments extends RedirectEventToFinance
{
    public static function getResourcePageName(): string
    {
        return 'settlement-documents';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
        $this->redirect(\App\Filament\Resources\EventResource::getUrl('finance-settlement-documents', [
            'record' => $this->getRecord(),
        ]));
    }
}
