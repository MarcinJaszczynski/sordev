<?php

namespace App\Filament\Pages;

use App\Filament\Actions\HelpArticleAction;
use App\Filament\Concerns\InteractsWithBankPaymentAssignment;
use App\Models\BankPaymentImportLine;
use App\Services\BankPayments\BankPaymentImportService;
use App\Support\FilamentNavigation;
use App\Support\FinanceModuleNavigation;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class UnmatchedBankPaymentsInboxPage extends Page implements HasTable
{
    use InteractsWithBankPaymentAssignment;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string $view = 'filament.pages.unmatched-bank-payments-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Wpłaty do dopasowania';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin', 'ksiegowosc']) || $user->can('view_any_event::settlement'));
    }

    public static function getNavigationBadge(): ?string
    {
        if (! Schema::hasTable('bank_payment_import_lines')) {
            return null;
        }

        $count = BankPaymentImportService::unmatchedPendingQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function getTitle(): string
    {
        return 'Wpłaty do dopasowania';
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpArticleAction::make('import-bankowy'),
        ];
    }

    public function getNavigationTabs(): array
    {
        return FinanceModuleNavigation::tabs('bank-unmatched');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->inboxQuery())
            ->defaultSort('operation_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('operation_date')
                    ->label('Data')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->limit(50)
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('counterparty')
                    ->label('Nadawca')
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount_pln')
                    ->label('Kwota')
                    ->money('PLN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('batch.source_filename')
                    ->label('Wyciąg')
                    ->toggleable()
                    ->limit(24),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Import')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('assign')
                    ->label('Przypisz')
                    ->icon('heroicon-o-link')
                    ->action(fn (BankPaymentImportLine $record) => $this->openAssignModal($record->id)),
            ])
            ->emptyStateHeading('Brak niedopasowanych wpłat')
            ->emptyStateDescription('Po imporcie Millennium wpłaty bez automatycznego dopasowania pojawią się tutaj.');
    }

    protected function inboxQuery(): Builder
    {
        if (! Schema::hasTable('bank_payment_import_lines')) {
            return BankPaymentImportLine::query()->whereRaw('1 = 0');
        }

        return BankPaymentImportLine::query()
            ->unmatchedPending()
            ->with(['batch']);
    }

    protected function afterBankPaymentAssignmentSaved(): void
    {
        $this->resetTable();
    }
}
