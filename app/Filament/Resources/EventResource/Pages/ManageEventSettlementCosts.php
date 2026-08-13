<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

/** @deprecated Bookmark → EventFinance */
class ManageEventSettlementCosts extends RedirectEventToFinance
{
    public static function getResourcePageName(): string
    {
        return 'settlement-costs';
    }
}
