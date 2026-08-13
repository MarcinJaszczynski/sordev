<?php

namespace App\Support;

use App\Filament\Pages\BankPaymentImportPage;
use App\Filament\Pages\ClientInvoiceRequestsInboxPage;
use App\Filament\Pages\EventAccountingFolderPage;
use App\Filament\Pages\FinanceOverviewPage;
use App\Filament\Pages\FinancialReportPage;
use App\Filament\Pages\ParticipantPaymentsPage;
use App\Filament\Pages\PendingPaymentsInboxPage;
use App\Filament\Pages\UnmatchedBankPaymentsInboxPage;
use App\Filament\Pages\VendorInvoiceImportPage;
use App\Filament\Pages\VendorInvoiceInboxPage;
use App\Filament\Pages\VendorInvoiceReportsPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\VendorInvoiceResource;

/**
 * Wspólna nawigacja podstron modułu finansowego.
 *
 * Hierarchia: Pulpit → Skrzynki → Rejestry → Narzędzia (narzędzia tylko na pulpicie lub gdy aktywne).
 */
final class FinanceModuleNavigation
{
    /**
     * @return list<string>
     */
    public static function toolKeys(): array
    {
        return [
            'bank-import',
            'import',
            'accounting-folder',
            'financial-report',
            'reports',
        ];
    }

    /**
     * @return array<int, array{
     *   key: string,
     *   label: string,
     *   description: string,
     *   url: string,
     *   icon: string,
     *   badge: ?string,
     *   group: string,
     *   active?: bool
     * }>
     */
    public static function tabs(?string $active = null): array
    {
        $primary = [
            [
                'key' => 'overview',
                'label' => 'Pulpit finansowy',
                'description' => 'Podsumowanie i skróty',
                'url' => FinanceOverviewPage::getUrl(),
                'icon' => 'heroicon-o-home-modern',
                'badge' => null,
                'group' => 'Pulpit',
            ],
            [
                'key' => 'pending-payments',
                'label' => 'Skrzynka płatności',
                'description' => 'Terminy i zaległe płatności',
                'url' => PendingPaymentsInboxPage::getUrl(),
                'icon' => 'heroicon-o-banknotes',
                'badge' => null,
                'group' => 'Skrzynki',
            ],
            [
                'key' => 'inbox',
                'label' => 'Stos do opracowania',
                'description' => 'Niedopasowane faktury',
                'url' => VendorInvoiceInboxPage::getUrl(),
                'icon' => 'heroicon-o-inbox-stack',
                'badge' => null,
                'group' => 'Skrzynki',
            ],
            [
                'key' => 'bank-unmatched',
                'label' => 'Wpłaty do dopasowania',
                'description' => 'Niedopasowane przelewy Millennium',
                'url' => UnmatchedBankPaymentsInboxPage::getUrl(),
                'icon' => 'heroicon-o-arrows-right-left',
                'badge' => null,
                'group' => 'Skrzynki',
            ],
            [
                'key' => 'client-invoice-requests',
                'label' => 'Wnioski o fakturę',
                'description' => 'Wnioski klientów z portalu',
                'url' => ClientInvoiceRequestsInboxPage::getUrl(),
                'icon' => 'heroicon-o-document-check',
                'badge' => null,
                'group' => 'Skrzynki',
            ],
            [
                'key' => 'to-settle',
                'label' => 'Imprezy do rozliczenia',
                'description' => 'Lista imprez w statusie do rozliczenia',
                'url' => EventResource::getUrl('index', ['activeTab' => 'to_settle']),
                'icon' => 'heroicon-o-calculator',
                'badge' => null,
                'group' => 'Rejestry',
            ],
            [
                'key' => 'participant-payments',
                'label' => 'Rejestr wpłat',
                'description' => 'Cross-event → deep-link do imprezy',
                'url' => ParticipantPaymentsPage::getUrl(),
                'icon' => 'heroicon-o-users',
                'badge' => null,
                'group' => 'Rejestry',
            ],
            [
                'key' => 'invoices',
                'label' => 'Rejestr faktur',
                'description' => 'Wszystkie faktury kosztowe',
                'url' => VendorInvoiceResource::getUrl('index'),
                'icon' => 'heroicon-o-document-text',
                'badge' => null,
                'group' => 'Rejestry',
            ],
        ];

        $tools = [
            [
                'key' => 'bank-import',
                'label' => 'Import wpłat',
                'description' => 'Wyciąg Millennium CSV',
                'url' => BankPaymentImportPage::getUrl(),
                'icon' => 'heroicon-o-building-library',
                'badge' => null,
                'group' => 'Narzędzia',
            ],
            [
                'key' => 'import',
                'label' => 'Import KSeF',
                'description' => 'CSV, XML i zbiorczy PDF',
                'url' => VendorInvoiceImportPage::getUrl(),
                'icon' => 'heroicon-o-arrow-up-tray',
                'badge' => null,
                'group' => 'Narzędzia',
            ],
            [
                'key' => 'accounting-folder',
                'label' => 'Teczka księgowa',
                'description' => 'Paczka ZIP dla księgowej',
                'url' => EventAccountingFolderPage::getUrl(),
                'icon' => 'heroicon-o-folder-arrow-down',
                'badge' => null,
                'group' => 'Narzędzia',
            ],
            [
                'key' => 'financial-report',
                'label' => 'Raport finansowy',
                'description' => 'Wpływy, koszty i saldo',
                'url' => FinancialReportPage::getUrl(),
                'icon' => 'heroicon-o-table-cells',
                'badge' => null,
                'group' => 'Narzędzia',
            ],
            [
                'key' => 'reports',
                'label' => 'Raporty płatności',
                'description' => 'Terminy i eksport CSV',
                'url' => VendorInvoiceReportsPage::getUrl(),
                'icon' => 'heroicon-o-chart-bar',
                'badge' => null,
                'group' => 'Narzędzia',
            ],
        ];

        $showTools = $active === null
            || $active === 'overview'
            || in_array($active, self::toolKeys(), true);

        $tabs = $showTools ? array_merge($primary, $tools) : $primary;

        return WorkflowModuleNavigation::markActive($tabs, $active);
    }

    /**
     * Klucze tabów, które powinny zostać w sidebarze (reszta tylko w module nav).
     *
     * @return list<string>
     */
    public static function sidebarKeys(): array
    {
        return [
            'overview',
            'pending-payments',
            'inbox',
            'bank-unmatched',
            'participant-payments',
            'invoices',
        ];
    }
}
