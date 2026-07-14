<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EventCalculation;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementCosts;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementCurrencyExchanges;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementDocuments;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementPilotCash;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementSummary;

trait HasEventFinanceSubNavigation
{
    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public static function financeSubNavigationActiveTab(): string
    {
        return match (static::class) {
            EventCalculation::class => 'calculation',
            ManageEventSettlementSummary::class => 'settlement-summary',
            ManageEventSettlementCosts::class => 'settlement-costs',
            ManageEventSettlementPilotCash::class => 'settlement-pilot-cash',
            ManageEventSettlementCurrencyExchanges::class => 'settlement-currency-exchanges',
            ManageEventSettlementDocuments::class => 'settlement-documents',
            default => 'calculation',
        };
    }

    /**
     * @return array<int, array{key: string, label: string, icon: string, url: string}>
     */
    public static function financeSubNavigationTabs(int|string $recordId): array
    {
        $tabs = [
            [
                'key' => 'calculation',
                'label' => 'Kalkulacja',
                'icon' => 'heroicon-o-calculator',
                'url' => EventResource::getUrl('calculation', ['record' => $recordId]),
            ],
            [
                'key' => 'settlement-summary',
                'label' => 'Podsumowanie',
                'icon' => 'heroicon-o-home',
                'url' => EventResource::getUrl('settlement-summary', ['record' => $recordId]),
            ],
            [
                'key' => 'settlement-costs',
                'label' => 'Koszty',
                'icon' => 'heroicon-o-banknotes',
                'url' => EventResource::getUrl('settlement-costs', ['record' => $recordId]),
            ],
            [
                'key' => 'settlement-pilot-cash',
                'label' => 'Gotówka pilota',
                'icon' => 'heroicon-o-wallet',
                'url' => EventResource::getUrl('settlement-pilot-cash', ['record' => $recordId]),
            ],
            [
                'key' => 'settlement-currency-exchanges',
                'label' => 'Wymiany walut',
                'icon' => 'heroicon-o-arrows-right-left',
                'url' => EventResource::getUrl('settlement-currency-exchanges', ['record' => $recordId]),
            ],
            [
                'key' => 'settlement-documents',
                'label' => 'Dokumenty',
                'icon' => 'heroicon-o-folder',
                'url' => EventResource::getUrl('settlement-documents', ['record' => $recordId]),
            ],
        ];

        return $tabs;
    }

    /**
     * @return array<int, string>
     */
    public static function financeRouteNames(): array
    {
        $routes = [
            EventCalculation::getRouteName(),
            ManageEventSettlementSummary::getRouteName(),
            ManageEventSettlementCosts::getRouteName(),
            ManageEventSettlementPilotCash::getRouteName(),
            ManageEventSettlementCurrencyExchanges::getRouteName(),
            ManageEventSettlementDocuments::getRouteName(),
        ];

        return $routes;
    }
}
