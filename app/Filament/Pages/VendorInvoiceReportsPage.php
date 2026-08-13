<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesVendorInvoices;
use App\Models\VendorInvoice;
use App\Support\FilamentNavigation;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorInvoiceReportsPage extends Page implements HasTable
{
    use AuthorizesVendorInvoices;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.vendor-invoice-reports';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Raporty płatności';

    protected static ?int $navigationSort = 7;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public string $activeTab = 'due';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin']) || static::canViewInvoices());
    }

    public function getTitle(): string
    {
        return 'Raporty płatności faktur';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    public function getNavigationTabs(): array
    {
        return \App\Support\FinanceModuleNavigation::tabs('reports');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getReportQuery())
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->label('Numer')->searchable(),
                Tables\Columns\TextColumn::make('seller_name')->label('Wystawca')->limit(30),
                Tables\Columns\TextColumn::make('gross_amount')->label('Brutto')->money('PLN')->summarize(Tables\Columns\Summarizers\Sum::make()->money('PLN')),
                Tables\Columns\TextColumn::make('due_date')->label('Termin')->date('d.m.Y'),
                Tables\Columns\TextColumn::make('payment_date')->label('Data płatności')->date('d.m.Y')->toggleable(),
                Tables\Columns\TextColumn::make('event.code')->label('Impreza'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Eksport CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => $this->exportCsv()),
            ]);
    }

    protected function getReportQuery(): Builder
    {
        return match ($this->activeTab) {
            'overdue' => VendorInvoice::query()
                ->where('approval_status', 'approved')
                ->where('payment_status', 'due')
                ->whereDate('due_date', '<', now()),
            'approved_unpaid' => VendorInvoice::query()
                ->where('approval_status', 'approved')
                ->where('payment_status', 'due'),
            'paid_period' => VendorInvoice::query()
                ->where('payment_status', 'paid')
                ->whereDate('payment_date', '>=', now()->startOfMonth()),
            default => VendorInvoice::query()
                ->where('approval_status', 'approved')
                ->where('payment_status', 'due')
                ->orderBy('due_date'),
        };
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->getReportQuery()->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Numer', 'KSeF', 'Wystawca', 'Brutto', 'Termin', 'Impreza', 'Status płatności']);
            foreach ($rows as $invoice) {
                fputcsv($out, [
                    $invoice->invoice_number,
                    $invoice->ksef_number,
                    $invoice->seller_name,
                    $invoice->gross_amount,
                    $invoice->due_date?->format('Y-m-d'),
                    $invoice->event?->code,
                    VendorInvoice::$paymentStatuses[$invoice->payment_status] ?? $invoice->payment_status,
                ]);
            }
            fclose($out);
        }, 'faktury-raport-'.$this->activeTab.'.csv');
    }
}
