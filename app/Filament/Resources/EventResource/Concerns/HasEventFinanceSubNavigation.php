<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EventCalculation;
use App\Filament\Resources\EventResource\Pages\EventFinance;
use App\Filament\Resources\EventResource\Pages\EventFinanceParticipantPayments;
use App\Filament\Resources\EventResource\Pages\EventFinancePilotCash;
use App\Filament\Resources\EventResource\Pages\EventFinanceSettlementDocuments;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementCosts;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementCurrencyExchanges;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementDocuments;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementPilotCash;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementSummary;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Services\SettlementPaymentHealthService;
use App\Support\WorkflowModuleNavigation;
use Illuminate\Support\Facades\Schema;

trait HasEventFinanceSubNavigation
{
    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public static function financeSubNavigationActiveTab(): string
    {
        return match (static::class) {
            EventFinance::class => 'finance',
            EventFinanceParticipantPayments::class => 'participant-payments',
            EventCalculation::class => 'calculation',
            EventFinancePilotCash::class,
            ManageEventSettlementPilotCash::class,
            ManageEventSettlementCurrencyExchanges::class => 'pilot-cash',
            EventFinanceSettlementDocuments::class,
            ManageEventSettlementDocuments::class => 'settlement-documents',
            ManageEventSettlementSummary::class,
            ManageEventSettlementCosts::class => 'finance',
            default => 'finance',
        };
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, url: string, icon: string, badge: ?string, active?: bool}>
     */
    public static function financeSubNavigationTabs(int|string $recordId): array
    {
        $badges = static::financeSubNavigationBadges($recordId);

        $tabs = [
            [
                'key' => 'finance',
                'label' => 'Koszty',
                'description' => 'Plan, płatności i dokumenty kosztowe',
                'icon' => 'heroicon-o-banknotes',
                'url' => EventResource::getUrl('finance', ['record' => $recordId]),
                'badge' => $badges['finance'] ?? null,
            ],
            [
                'key' => 'participant-payments',
                'label' => 'Wpłaty',
                'description' => 'Rejestr wpłat uczestników',
                'icon' => 'heroicon-o-credit-card',
                'url' => EventResource::getUrl('finance-participant-payments', ['record' => $recordId]),
                'badge' => $badges['participant-payments'] ?? null,
            ],
            [
                'key' => 'calculation',
                'label' => 'Kalkulacja',
                'description' => 'Cena z programu i marża',
                'icon' => 'heroicon-o-calculator',
                'url' => EventResource::getUrl('calculation', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'pilot-cash',
                'label' => 'Gotówka i waluty',
                'description' => 'Zaliczka pilota wycieczki i wymiany',
                'icon' => 'heroicon-o-banknotes',
                'url' => EventResource::getUrl('finance-pilot-cash', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'settlement-documents',
                'label' => 'Dok. rozliczenia',
                'description' => 'Załączniki do rozliczenia',
                'icon' => 'heroicon-o-paper-clip',
                'url' => EventResource::getUrl('finance-settlement-documents', ['record' => $recordId]),
                'badge' => null,
            ],
        ];

        return WorkflowModuleNavigation::markActive($tabs, static::financeSubNavigationActiveTab());
    }

    /**
     * @return array<string, string|null>
     */
    protected static function financeSubNavigationBadges(int|string $recordId): array
    {
        $financeBadge = null;
        $paymentsBadge = null;

        try {
            $event = Event::query()->find($recordId);
            if (! $event) {
                return [];
            }

            $settlement = EventSettlement::findActiveForEvent($event);

            if ($settlement && Schema::hasTable('event_settlement_costs')) {
                $health = app(SettlementPaymentHealthService::class);
                $counts = $health->countByStatus($health->evaluateSettlement($settlement));
                $overdue = (int) ($counts[SettlementPaymentHealthService::STATUS_OVERDUE] ?? 0);
                if ($overdue > 0) {
                    $financeBadge = (string) $overdue;
                }
            }

            if ($settlement && Schema::hasTable('event_settlement_participant_payments')) {
                $open = EventSettlementParticipantPayment::query()
                    ->where('settlement_id', $settlement->getKey())
                    ->whereIn('payment_status', ['pending', 'partial'])
                    ->count();
                if ($open > 0) {
                    $paymentsBadge = (string) $open;
                }
            }
        } catch (\Throwable) {
            // Badge jest pomocniczy — nie blokuj nawigacji przy braku schematu.
        }

        return [
            'finance' => $financeBadge,
            'participant-payments' => $paymentsBadge,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function financeRouteNames(): array
    {
        return [
            EventFinance::getRouteName(),
            EventFinanceParticipantPayments::getRouteName(),
            EventCalculation::getRouteName(),
            EventFinancePilotCash::getRouteName(),
            EventFinanceSettlementDocuments::getRouteName(),
            ManageEventSettlementSummary::getRouteName(),
            ManageEventSettlementCosts::getRouteName(),
            ManageEventSettlementPilotCash::getRouteName(),
            ManageEventSettlementCurrencyExchanges::getRouteName(),
            ManageEventSettlementDocuments::getRouteName(),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        $recordId = $this->getRecord()->getKey();
        $section = match (static::financeSubNavigationActiveTab()) {
            'finance' => 'Koszty',
            'participant-payments' => 'Wpłaty',
            'calculation' => 'Kalkulacja',
            'pilot-cash' => 'Gotówka i waluty',
            'settlement-documents' => 'Dok. rozliczenia',
            default => 'Finanse',
        };

        return $this->eventRecordBreadcrumbs(
            moduleLabel: 'Finanse',
            moduleUrl: EventResource::getUrl('finance', ['record' => $recordId]),
            sectionLabel: $section,
        );
    }
}
