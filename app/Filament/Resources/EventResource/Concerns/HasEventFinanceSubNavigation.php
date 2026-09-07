<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EventCalculation;
use App\Filament\Resources\EventResource\Pages\EventFinance;
use App\Filament\Resources\EventResource\Pages\EventFinanceParticipantPayments;
use App\Filament\Resources\EventResource\Pages\EventFinancePilotCash;
use App\Filament\Resources\EventResource\Pages\EventFinanceSettlementDocuments;
use App\Filament\Resources\EventResource\Pages\EventFinanceSnapshots;
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
            EventFinanceSnapshots::class => 'snapshots',
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
                'description' => null,
                'icon' => 'heroicon-o-banknotes',
                'url' => EventResource::getUrl('finance', ['record' => $recordId]),
                'badge' => $badges['finance'] ?? null,
            ],
            [
                'key' => 'participant-payments',
                'label' => 'Wpłaty / Faktury',
                'description' => null,
                'icon' => 'heroicon-o-credit-card',
                'url' => EventResource::getUrl('finance-participant-payments', ['record' => $recordId]),
                'badge' => $badges['participant-payments'] ?? null,
            ],
            [
                'key' => 'calculation',
                'label' => 'Kalkulacja',
                'description' => null,
                'icon' => 'heroicon-o-calculator',
                'url' => EventResource::getUrl('calculation', ['record' => $recordId]),
                'badge' => null,
            ],
        ];

        if (Schema::hasTable('event_snapshots')) {
            $tabs[] = [
                'key' => 'snapshots',
                'label' => 'Migawki',
                'description' => null,
                'icon' => 'heroicon-o-camera',
                'url' => EventResource::getUrl('finance-snapshots', ['record' => $recordId]),
                'badge' => null,
            ];
        }

        $tabs = array_merge($tabs, [
            [
                'key' => 'pilot-cash',
                'label' => 'Gotówka pilota',
                'description' => null,
                'icon' => 'heroicon-o-banknotes',
                'url' => EventResource::getUrl('finance-pilot-cash', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'settlement-documents',
                'label' => 'Dok. rozliczenia',
                'description' => null,
                'icon' => 'heroicon-o-paper-clip',
                'url' => EventResource::getUrl('finance-settlement-documents', ['record' => $recordId]),
                'badge' => null,
            ],
        ]);

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
        $routes = [
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

        if (Schema::hasTable('event_snapshots')) {
            $routes[] = EventFinanceSnapshots::getRouteName();
        }

        return $routes;
    }

    /**
     * Breadcrumb kończy się na module — aktywna sekcja jest w module-nav.
     *
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        $recordId = $this->getRecord()->getKey();

        return $this->eventRecordBreadcrumbs(
            moduleLabel: 'Finanse',
            moduleUrl: EventResource::getUrl('finance', ['record' => $recordId]),
            sectionLabel: null,
        );
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        // Pusty string ukrywa cały header Filament (w tym headerActions). HtmlString jest truthy.
        return new \Illuminate\Support\HtmlString('');
    }

    public function getSubheading(): ?string
    {
        return null;
    }
}
