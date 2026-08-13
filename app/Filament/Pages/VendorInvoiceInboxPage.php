<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesVendorInvoices;
use App\Filament\Concerns\InteractsWithVendorInvoiceReview;
use App\Models\VendorInvoice;
use App\Support\FilamentNavigation;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class VendorInvoiceInboxPage extends Page implements HasTable
{
    use AuthorizesVendorInvoices;
    use InteractsWithTable;
    use InteractsWithVendorInvoiceReview;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static string $view = 'filament.pages.vendor-invoice-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Stos do opracowania';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin']) || static::canViewInvoices() || static::canManageAssignment());
    }

    public function getTitle(): string
    {
        return 'Stos do opracowania';
    }

    public function getNavigationTabs(): array
    {
        return \App\Support\FinanceModuleNavigation::tabs('inbox');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(VendorInvoice::query()->where('matching_status', 'unmatched')->with(['lines', 'contractor', 'event']))
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Numer')
                    ->searchable()
                    ->description(fn (VendorInvoice $record) => $record->ksef_number),
                Tables\Columns\TextColumn::make('seller_name')
                    ->label('Wystawca')
                    ->limit(35)
                    ->description(fn (VendorInvoice $record) => $record->seller_nip ? 'NIP '.$record->seller_nip : null),
                Tables\Columns\TextColumn::make('gross_amount')->label('Brutto')->money('PLN'),
                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Poz.')
                    ->counts('lines')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sale_date')->label('Data sprzedaży')->date('d.m.Y'),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notatki')
                    ->limit(40)
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->actions($this->vendorInvoiceReviewActions());
    }
}
