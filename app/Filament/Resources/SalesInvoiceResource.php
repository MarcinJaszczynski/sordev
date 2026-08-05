<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Finance\CreateVatMarginInvoiceDraftAction;
use App\Data\CreateVatMarginInvoiceDraftData;
use App\Filament\Resources\SalesInvoiceResource\Pages;
use App\Models\Event;
use App\Models\SalesInvoice;
use App\Support\FilamentNavigation;
use App\Support\MoneyFormatter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Faktury sprzedażowe';

    protected static ?string $modelLabel = 'faktura sprzedażowa';

    protected static ?string $pluralModelLabel = 'Faktury sprzedażowe';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'number';

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user && $user->hasRole(['admin', 'super_admin', 'biuro', 'ksiegowosc']);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();

        return $user && $user->hasRole(['admin', 'super_admin']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Schema::hasTable('sales_invoices') && static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Nabywca')->columns(2)->schema([
                Forms\Components\TextInput::make('buyer_name')->label('Nazwa')->disabled(),
                Forms\Components\TextInput::make('buyer_nip')->label('NIP')->disabled(),
                Forms\Components\Textarea::make('buyer_address')->label('Adres')->rows(2)->disabled()->columnSpanFull(),
            ]),
            Forms\Components\Section::make('Kwoty VAT-Marża')->columns(3)->schema([
                Forms\Components\TextInput::make('revenue_pln')->label('Przychód')->disabled(),
                Forms\Components\TextInput::make('cost_pln')->label('Koszt')->disabled(),
                Forms\Components\TextInput::make('margin_gross_pln')->label('Marża brutto')->disabled(),
                Forms\Components\TextInput::make('margin_net_pln')->label('Marża netto')->disabled(),
                Forms\Components\TextInput::make('vat_on_margin_pln')->label('VAT od marży')->disabled(),
                Forms\Components\TextInput::make('ksef_number')->label('Numer KSeF (przyszły)')->disabled(),
            ]),
            Forms\Components\Textarea::make('notes')->label('Notatki')->disabled()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['event', 'lines']))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Impreza')
                    ->searchable()
                    ->description(fn (SalesInvoice $record): ?string => $record->event?->code),
                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (?string $state): string => SalesInvoice::$types[$state] ?? (string) $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => SalesInvoice::$statuses[$state] ?? (string) $state)
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        SalesInvoice::STATUS_ISSUED_LOCAL => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('buyer_name')->label('Nabywca')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('margin_gross_pln')
                    ->label('Marża brutto')
                    ->formatStateUsing(fn ($state): string => MoneyFormatter::format((float) $state, 'PLN'))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('vat_on_margin_pln')
                    ->label('VAT marży')
                    ->formatStateUsing(fn ($state): string => MoneyFormatter::format((float) $state, 'PLN'))
                    ->alignEnd()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Utworzono')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(SalesInvoice::$statuses),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options(SalesInvoice::$types),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createDraft')
                    ->label('Utwórz szkic VAT-Marża')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => static::canCreate())
                    ->form([
                        Forms\Components\Select::make('event_id')
                            ->label('Impreza')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(fn (string $search): array => Event::query()
                                ->when($search !== '', fn (Builder $q) => $q
                                    ->where('code', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%"))
                                ->orderByDesc('id')
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn (Event $event): array => [
                                    $event->id => trim(($event->code ? $event->code.' — ' : '').($event->name ?? '')),
                                ])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => optional(Event::find($value), fn (Event $e): string => trim(($e->code ? $e->code.' — ' : '').($e->name ?? '')))),
                        Forms\Components\Select::make('type')
                            ->label('Typ')
                            ->options(SalesInvoice::$types)
                            ->default(SalesInvoice::TYPE_FINAL)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $event = Event::query()->findOrFail((int) $data['event_id']);
                        $invoice = app(CreateVatMarginInvoiceDraftAction::class)(new CreateVatMarginInvoiceDraftData(
                            event: $event,
                            type: (string) ($data['type'] ?? SalesInvoice::TYPE_FINAL),
                            createdBy: Auth::id(),
                        ));

                        Notification::make()
                            ->title('Utworzono szkic FV VAT-Marża #'.$invoice->id)
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('markIssuedLocal')
                    ->label('Wystaw lokalnie')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (SalesInvoice $record): bool => $record->status === SalesInvoice::STATUS_DRAFT && static::canViewAny())
                    ->requiresConfirmation()
                    ->action(function (SalesInvoice $record): void {
                        $record->update([
                            'status' => SalesInvoice::STATUS_ISSUED_LOCAL,
                            'number' => $record->number ?: ('LOK-'.now()->format('Ymd').'-'.$record->id),
                        ]);
                        Notification::make()->title('Faktura oznaczona jako wystawiona lokalnie')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesInvoices::route('/'),
            'view' => Pages\ViewSalesInvoice::route('/{record}'),
        ];
    }
}
