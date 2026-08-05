<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesVendorInvoices;
use App\Filament\Concerns\AuthorizesWithShield;
use App\Filament\Resources\VendorInvoiceResource\Pages;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\VendorInvoice;
use App\Services\Invoices\ContractorResolver;
use App\Services\Invoices\VendorInvoiceProgramPointSync;
use App\Services\Invoices\VendorInvoiceSettlementSync;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class VendorInvoiceResource extends Resource
{
    use AuthorizesVendorInvoices;
    use AuthorizesWithShield;

    protected static ?string $model = VendorInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Rejestr faktur';

    protected static ?string $modelLabel = 'faktura kosztowa';

    protected static ?string $pluralModelLabel = 'Faktury kosztowe';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'ksef_number', 'seller_name', 'seller_nip', 'buyer_name'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        /** @var VendorInvoice $record */
        $details = [];

        if ($record->seller_name) {
            $details['Wystawca'] = (string) $record->seller_name;
        }

        if ($record->gross_amount !== null) {
            $details['Brutto'] = \App\Support\MoneyFormatter::format((float) $record->gross_amount, $record->currency ?: 'PLN');
        }

        return $details;
    }

    public static function canViewAny(): bool
    {
        return parent::canViewAny() || static::canViewInvoices();
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record) || static::canViewInvoices();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identyfikacja')->columns(3)->schema([
                Forms\Components\TextInput::make('invoice_number')->label('Numer faktury'),
                Forms\Components\TextInput::make('ksef_number')->label('Numer KSeF')->disabled(),
                Forms\Components\TextInput::make('seller_name')->label('Wystawca'),
                Forms\Components\TextInput::make('seller_nip')->label('NIP wystawcy'),
                Forms\Components\TextInput::make('payment_method')->label('Metoda płatności'),
                Forms\Components\Placeholder::make('pdf_link')
                    ->label('Skan PDF')
                    ->content(fn (?VendorInvoice $record) => $record?->pdf_url
                        ? new \Illuminate\Support\HtmlString('<a class="text-primary-600 underline" href="'.$record->pdf_url.'" target="_blank">Otwórz PDF</a>')
                        : 'Brak pliku PDF'),
            ]),
            Forms\Components\Section::make('Kwoty i daty')->columns(3)->schema([
                Forms\Components\TextInput::make('net_amount')->label('Netto')->numeric()->prefix('PLN'),
                Forms\Components\TextInput::make('vat_amount')->label('VAT')->numeric()->prefix('PLN'),
                Forms\Components\TextInput::make('gross_amount')->label('Brutto')->numeric()->prefix('PLN'),
                Forms\Components\DatePicker::make('issue_date')->label('Data wystawienia'),
                Forms\Components\DatePicker::make('sale_date')->label('Data sprzedaży'),
                Forms\Components\DatePicker::make('due_date')->label('Termin płatności'),
                Forms\Components\DatePicker::make('received_date')->label('Data wpływu'),
            ]),
            Forms\Components\Section::make('Płatność')->columns(3)->schema([
                Forms\Components\Select::make('payment_status')
                    ->label('Status płatności')
                    ->options(VendorInvoice::$paymentStatuses)
                    ->required()
                    ->live(),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Data płatności')
                    ->visible(fn (Forms\Get $get) => in_array($get('payment_status'), ['paid', 'partial'], true)),
                Forms\Components\TextInput::make('paid_amount')
                    ->label('Kwota opłacona')
                    ->numeric()
                    ->prefix('PLN')
                    ->visible(fn (Forms\Get $get) => in_array($get('payment_status'), ['paid', 'partial'], true)),
            ]),
            Forms\Components\Section::make('Przypisanie')->columns(2)->schema([
                Forms\Components\Select::make('contractor_id')
                    ->label('Kontrahent')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Contractor::query()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->limit(20)
                        ->pluck('name', 'id'))
                    ->getOptionLabelUsing(fn ($value) => Contractor::find($value)?->name),
                Forms\Components\Select::make('event_id')
                    ->label('Impreza')
                    ->searchable()
                    ->live()
                    ->getSearchResultsUsing(fn (string $search) => Event::query()
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Event $e) => [$e->id => "{$e->code} — {$e->name}"]))
                    ->getOptionLabelUsing(fn ($value) => optional(Event::find($value), fn (Event $e) => "{$e->code} — {$e->name}")),
                Forms\Components\Select::make('event_program_point_id')
                    ->label('Punkt programu')
                    ->options(fn (Forms\Get $get) => EventProgramPoint::query()
                        ->where('event_id', $get('event_id'))
                        ->with('contractor')
                        ->get()
                        ->mapWithKeys(fn (EventProgramPoint $p) => [
                            $p->id => ($p->contractor?->name ?? $p->name ?? 'Punkt').' (dzień '.$p->day.')',
                        ])),
                Forms\Components\Select::make('event_settlement_cost_id')
                    ->label('Koszt rozliczenia')
                    ->searchable()
                    ->options(fn (Forms\Get $get) => EventSettlementCost::query()
                        ->whereHas('settlement', fn ($q) => $q->where('event_id', $get('event_id')))
                        ->with('contractor')
                        ->orderBy('order')
                        ->get()
                        ->mapWithKeys(fn (EventSettlementCost $cost) => [
                            $cost->id => trim(($cost->contractor?->name ?? $cost->name ?? 'Koszt').' — '.number_format((float) $cost->actual_amount_pln, 2, ',', ' ').' PLN'),
                        ]))
                    ->visible(fn (Forms\Get $get) => (bool) $get('event_id')),
                Forms\Components\Select::make('matching_status')
                    ->label('Status dopasowania')
                    ->options(VendorInvoice::$matchingStatuses),
            ]),
            Forms\Components\Section::make('Akceptacja i uwagi')->schema([
                Forms\Components\Select::make('approval_status')
                    ->label('Status akceptacji')
                    ->options(VendorInvoice::$approvalStatuses)
                    ->required(),
                Forms\Components\Checkbox::make('sync_to_settlement')
                    ->label('Dodaj do rozliczenia imprezy przy akceptacji')
                    ->visible(fn (Forms\Get $get) => (bool) $get('event_id')),
                Forms\Components\Textarea::make('notes')->label('Notatki z importu')->rows(2),
                Forms\Components\Textarea::make('internal_notes')->label('Uwagi wewnętrzne')->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->label('Numer')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('ksef_number')->label('KSeF')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('seller_name')->label('Wystawca')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('seller_nip')->label('NIP')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('gross_amount')->label('Brutto')->money('PLN')->sortable(),
                Tables\Columns\TextColumn::make('due_date')->label('Termin')->date('d.m.Y')->sortable(),
                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Płatność')
                    ->formatStateUsing(fn ($state) => VendorInvoice::$paymentStatuses[$state] ?? $state)
                    ->colors(['warning' => 'due', 'success' => 'paid', 'danger' => 'cancelled']),
                Tables\Columns\BadgeColumn::make('approval_status')
                    ->label('Akceptacja')
                    ->formatStateUsing(fn ($state) => VendorInvoice::$approvalStatuses[$state] ?? $state)
                    ->colors(['gray' => 'pending', 'success' => 'approved', 'danger' => 'rejected']),
                Tables\Columns\TextColumn::make('event.code')->label('Impreza')->placeholder('—'),
                Tables\Columns\IconColumn::make('pdf_path')
                    ->label('PDF')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-document'),
                Tables\Columns\BadgeColumn::make('matching_status')
                    ->label('Dopasowanie')
                    ->formatStateUsing(fn ($state) => VendorInvoice::$matchingStatuses[$state] ?? $state)
                    ->colors([
                        'success' => 'auto_matched',
                        'info' => 'manual',
                        'warning' => 'needs_review',
                        'gray' => 'unmatched',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Płatność')
                    ->options(VendorInvoice::$paymentStatuses),
                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Akceptacja')
                    ->options(VendorInvoice::$approvalStatuses),
                Tables\Filters\SelectFilter::make('matching_status')
                    ->label('Dopasowanie')
                    ->options(VendorInvoice::$matchingStatuses),
                Tables\Filters\Filter::make('overdue')
                    ->label('Przeterminowane')
                    ->query(fn (Builder $q) => $q->where('payment_status', 'due')->whereDate('due_date', '<', now())),
                Tables\Filters\TernaryFilter::make('has_pdf')
                    ->label('Ma PDF')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('pdf_path'),
                        false: fn (Builder $q) => $q->whereNull('pdf_path'),
                    ),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document')
                    ->url(fn (VendorInvoice $record) => $record->pdf_url)
                    ->openUrlInNewTab()
                    ->visible(fn (VendorInvoice $record) => (bool) $record->pdf_path),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Akceptuj')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (VendorInvoice $record) => $record->approval_status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (VendorInvoice $record) {
                        $record->update([
                            'approval_status' => 'approved',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                        if ($record->sync_to_settlement) {
                            app(VendorInvoiceSettlementSync::class)->sync($record->fresh());
                        }
                        if ($record->event_program_point_id) {
                            app(VendorInvoiceProgramPointSync::class)->sync($record->fresh());
                        }
                        Notification::make()->title('Faktura zaakceptowana')->success()->send();
                    }),
                Tables\Actions\Action::make('markPaid')
                    ->label('Opłacona')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (VendorInvoice $record) => $record->payment_status === 'due')
                    ->action(fn (VendorInvoice $record) => $record->update([
                        'payment_status' => 'paid',
                        'paid_amount' => $record->gross_amount,
                        'payment_date' => now(),
                    ])),
                Tables\Actions\Action::make('createContractor')
                    ->label('Utwórz kontrahenta')
                    ->icon('heroicon-o-building-office')
                    ->visible(fn (VendorInvoice $record) => ! $record->contractor_id && $record->seller_nip)
                    ->action(function (VendorInvoice $record) {
                        app(ContractorResolver::class)->createFromInvoice($record);
                        Notification::make()->title('Utworzono kontrahenta')->success()->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorInvoices::route('/'),
            'edit' => Pages\EditVendorInvoice::route('/{record}/edit'),
        ];
    }
}
