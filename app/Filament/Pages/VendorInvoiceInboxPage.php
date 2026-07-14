<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesVendorInvoices;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\VendorInvoice;
use App\Services\Invoices\VendorInvoiceProgramPointSync;
use App\Support\FilamentNavigation;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class VendorInvoiceInboxPage extends Page implements HasTable
{
    use AuthorizesVendorInvoices;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static string $view = 'filament.pages.vendor-invoice-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Stos do opracowania';

    protected static ?int $navigationSort = 5;

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
            ->query(VendorInvoice::query()->where('matching_status', 'unmatched')->with(['lines', 'contractor']))
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->label('Numer')->searchable(),
                Tables\Columns\TextColumn::make('ksef_number')->label('KSeF')->toggleable(),
                Tables\Columns\TextColumn::make('seller_name')->label('Wystawca')->limit(35),
                Tables\Columns\TextColumn::make('gross_amount')->label('Brutto')->money('PLN'),
                Tables\Columns\TextColumn::make('sale_date')->label('Data sprzedaży')->date('d.m.Y'),
            ])
            ->actions([
                Tables\Actions\Action::make('assign')
                    ->label('Przypisz')
                    ->icon('heroicon-o-link')
                    ->form([
                        Select::make('event_id')
                            ->label('Impreza')
                            ->searchable()
                            ->required()
                            ->live()
                            ->getSearchResultsUsing(fn (string $search) => Event::query()
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (Event $e) => [$e->id => "{$e->code} — {$e->name}"]))
                            ->getOptionLabelUsing(fn ($value) => optional(Event::find($value), fn (Event $e) => "{$e->code} — {$e->name}")),
                        Select::make('event_program_point_id')
                            ->label('Punkt programu')
                            ->options(fn (callable $get) => $get('event_id')
                                ? EventProgramPoint::query()
                                    ->where('event_id', $get('event_id'))
                                    ->with('contractor')
                                    ->get()
                                    ->mapWithKeys(fn (EventProgramPoint $p) => [
                                        $p->id => ($p->contractor?->name ?? $p->name ?? 'Punkt').' (dzień '.$p->day.')',
                                    ])
                                : []),
                        Select::make('contractor_id')
                            ->label('Kontrahent')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Contractor::query()
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('nip', 'like', "%{$search}%")
                                ->limit(20)
                                ->pluck('name', 'id')),
                    ])
                    ->action(function (VendorInvoice $record, array $data) {
                        $record->update([
                            'event_id' => $data['event_id'],
                            'event_program_point_id' => $data['event_program_point_id'] ?? null,
                            'contractor_id' => $data['contractor_id'] ?? $record->contractor_id,
                            'matching_status' => 'manual',
                        ]);

                        if ($record->event_program_point_id) {
                            app(VendorInvoiceProgramPointSync::class)->sync($record->fresh());
                        }

                        Notification::make()->title('Faktura przypisana')->success()->send();
                    }),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document')
                    ->url(fn (VendorInvoice $record) => $record->pdf_url)
                    ->openUrlInNewTab()
                    ->visible(fn (VendorInvoice $record) => (bool) $record->pdf_path),
            ]);
    }
}
