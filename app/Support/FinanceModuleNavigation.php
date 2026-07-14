<?php

namespace App\Support;

use App\Filament\Pages\BankPaymentImportPage;
use App\Filament\Pages\ClientInvoiceRequestsInboxPage;
use App\Filament\Pages\EventAccountingFolderPage;
use App\Filament\Pages\FinanceOverviewPage;
use App\Filament\Pages\FinancialReportPage;
use App\Filament\Pages\ParticipantPaymentsPage;
use App\Filament\Pages\PendingPaymentsInboxPage;
use App\Filament\Pages\VendorInvoiceImportPage;
use App\Filament\Pages\VendorInvoiceInboxPage;
use App\Filament\Pages\VendorInvoiceReportsPage;
use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\VendorInvoiceResource;

/**
 * Wspólna nawigacja podstron modułu finansowego (faktury KSeF, rozliczenia).
 */
final class FinanceModuleNavigation
{
    /** @return array<int, array{key: string, label: string, description: string, url: string, icon: string, badge: ?string, active?: bool}> */
    public static function tabs(?string $active = null): array
    {

        $tabs = [

            [

                'key' => 'overview',

                'label' => 'Pulpit finansowy',

                'description' => 'Podsumowanie i skróty',

                'url' => FinanceOverviewPage::getUrl(),

                'icon' => 'heroicon-o-home-modern',

                'badge' => null,

            ],

            [

                'key' => 'settlements',

                'label' => 'Rozliczenia',

                'description' => 'Koszty imprez i gotówka pilota',

                'url' => EventSettlementResource::getUrl('index'),

                'icon' => 'heroicon-o-calculator',

                'badge' => null,

            ],

            [

                'key' => 'invoices',

                'label' => 'Rejestr faktur',

                'description' => 'Wszystkie faktury kosztowe',

                'url' => VendorInvoiceResource::getUrl('index'),

                'icon' => 'heroicon-o-document-text',

                'badge' => null,

            ],

            [

                'key' => 'participant-payments',

                'label' => 'Wpłaty uczestników',

                'description' => 'Płatności indywidualne w umowach grupowych',

                'url' => ParticipantPaymentsPage::getUrl(),

                'icon' => 'heroicon-o-users',

                'badge' => null,

            ],

            [

                'key' => 'financial-report',

                'label' => 'Raport finansowy',

                'description' => 'Wpływy, koszty i saldo',

                'url' => FinancialReportPage::getUrl(),

                'icon' => 'heroicon-o-table-cells',

                'badge' => null,

            ],

            [

                'key' => 'accounting-folder',

                'label' => 'Teczka księgowa',

                'description' => 'Paczka ZIP dla księgowej',

                'url' => EventAccountingFolderPage::getUrl(),

                'icon' => 'heroicon-o-folder-arrow-down',

                'badge' => null,

            ],

            [

                'key' => 'bank-import',

                'label' => 'Import wpłat',

                'description' => 'Wyciąg Millennium CSV',

                'url' => BankPaymentImportPage::getUrl(),

                'icon' => 'heroicon-o-building-library',

                'badge' => null,

            ],

            [

                'key' => 'import',

                'label' => 'Import KSeF',

                'description' => 'CSV, XML i zbiorczy PDF',

                'url' => VendorInvoiceImportPage::getUrl(),

                'icon' => 'heroicon-o-arrow-up-tray',

                'badge' => null,

            ],

            [

                'key' => 'client-invoice-requests',

                'label' => 'Wnioski o fakturę',

                'description' => 'Wnioski klientów z portalu',

                'url' => ClientInvoiceRequestsInboxPage::getUrl(),

                'icon' => 'heroicon-o-document-check',

                'badge' => null,

            ],

            [

                'key' => 'pending-payments',

                'label' => 'Sterta płatności',

                'description' => 'Terminy i zaległe płatności',

                'url' => PendingPaymentsInboxPage::getUrl(),

                'icon' => 'heroicon-o-banknotes',

                'badge' => null,

            ],

            [

                'key' => 'inbox',

                'label' => 'Stos do opracowania',

                'description' => 'Niedopasowane faktury',

                'url' => VendorInvoiceInboxPage::getUrl(),

                'icon' => 'heroicon-o-inbox-stack',

                'badge' => null,

            ],

            [

                'key' => 'reports',

                'label' => 'Raporty płatności',

                'description' => 'Terminy i eksport CSV',

                'url' => VendorInvoiceReportsPage::getUrl(),

                'icon' => 'heroicon-o-chart-bar',

                'badge' => null,

            ],

        ];

        return WorkflowModuleNavigation::markActive($tabs, $active);

    }
}
