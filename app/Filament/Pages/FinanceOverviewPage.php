<?php

namespace App\Filament\Pages;

use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\VendorInvoiceResource;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\VendorInvoice;
use App\Support\FilamentNavigation;
use App\Support\FinanceModuleNavigation;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

class FinanceOverviewPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.finance-overview';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Pulpit finansowy';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin', 'ksiegowosc']) || $user->can('view_any_event::settlement'));
    }

    public function getTitle(): string
    {
        return 'Pulpit finansowy';
    }

    /** @return array<string, mixed> */
    public function getOverviewStats(): array
    {
        $openSettlements = EventSettlement::query()
            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
            ->count();

        $unpaidPilotFunds = Schema::hasColumn('events', 'pilot_funds_paid')
            ? Event::query()
                ->whereNotNull('assigned_to')
                ->where('pilot_funds_paid', false)
                ->where(function ($query): void {
                    if (Schema::hasColumn('events', 'pilot_advance_planned_amount')) {
                        $query->whereNotNull('pilot_advance_planned_amount')
                            ->where('pilot_advance_planned_amount', '>', 0);
                    } else {
                        $query->whereRaw('1 = 1');
                    }
                })
                ->whereIn('status', [
                    Event::STATUS_CONFIRMED,
                    Event::STATUS_TO_SETTLE,
                    Event::STATUS_SETTLED,
                ])
                ->count()
            : 0;

        $plannedPilotAdvances = Schema::hasColumn('events', 'pilot_advance_planned_amount')
            ? (float) Event::query()
                ->whereNotNull('assigned_to')
                ->where('pilot_funds_paid', false)
                ->whereNotNull('pilot_advance_planned_amount')
                ->sum('pilot_advance_planned_amount')
            : 0.0;

        $invoiceStats = Schema::hasTable('vendor_invoices')
            ? [
                'unmatched' => VendorInvoice::query()->where('matching_status', 'unmatched')->count(),
                'due' => VendorInvoice::query()
                    ->where('approval_status', 'approved')
                    ->where('payment_status', 'due')
                    ->count(),
                'overdue' => VendorInvoice::query()
                    ->where('approval_status', 'approved')
                    ->where('payment_status', 'due')
                    ->whereDate('due_date', '<', now())
                    ->count(),
            ]
            : ['unmatched' => 0, 'due' => 0, 'overdue' => 0];

        return [
            'open_settlements' => $openSettlements,
            'unpaid_pilot_funds' => $unpaidPilotFunds,
            'planned_pilot_advances' => $plannedPilotAdvances,
            ...$invoiceStats,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getWorkflowSteps(): array
    {
        return [
            [
                'step' => 1,
                'title' => 'Import wpłat klientów',
                'body' => 'Wgraj CSV z Banku Millennium. System dopasuje przelewy do umów i wpłat uczestników.',
                'url' => BankPaymentImportPage::getUrl(),
            ],
            [
                'step' => 2,
                'title' => 'Import faktur KSeF',
                'body' => 'Wgraj CSV/XML (i opcjonalnie zbiorczy PDF). System utworzy rekordy i dopasuje PDF po numerze KSeF.',
                'url' => VendorInvoiceImportPage::getUrl(),
            ],
            [
                'step' => 3,
                'title' => 'Opracuj stos niedopasowanych',
                'body' => 'Przypisz faktury bez imprezy do właściwej wycieczki i punktu programu.',
                'url' => VendorInvoiceInboxPage::getUrl(),
            ],
            [
                'step' => 4,
                'title' => 'Akceptuj i rozlicz',
                'body' => 'W rejestrze faktur ustaw akceptację, status płatności i opcjonalnie dodaj pozycję do rozliczenia imprezy.',
                'url' => VendorInvoiceResource::getUrl('index'),
            ],
            [
                'step' => 5,
                'title' => 'Rozliczenie i wypłata pilota',
                'body' => 'W rozliczeniu imprezy zweryfikuj koszty i gotówkę pilota. Na liście imprez oznacz wypłatę środków pilotowi.',
                'url' => EventSettlementResource::getUrl('index'),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getNavigationTabs(): array
    {
        return FinanceModuleNavigation::tabs('overview');
    }
}
