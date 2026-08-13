<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresUfgContractsTable;
use App\Filament\Resources\ContractResource\Pages;
use App\Jobs\Tfg\SubmitTfgFeedJob;
use App\Models\Contract;
use App\Models\TfgDictionaryItem;
use App\Services\Tfg\Exceptions\TfgValidationException;
use App\Services\Tfg\TfgCsvExportService;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractResource extends Resource
{
    use RequiresUfgContractsTable;

    protected static ?string $model = Contract::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Umowy UFG';

    protected static ?string $modelLabel = 'Umowa';

    protected static ?string $pluralModelLabel = 'Umowy';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podstawowe')->schema([
                Forms\Components\Select::make('event_id')
                    ->label('Impreza')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('contract_number')->label('Numer umowy'),
                Forms\Components\TextInput::make('reservation_number')->label('Numer rezerwacji'),
                Forms\Components\DatePicker::make('contract_date')->label('Data umowy')->required(),
                Forms\Components\Select::make('subject_code')
                    ->label('Przedmiot umowy')
                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_SUBJECT))
                    ->required()
                    ->searchable(),
                Forms\Components\Select::make('payment_method_code')
                    ->label('Sposób wpłat (UFG)')
                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_PAYMENT_METHOD))
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('total_price')->label('Łączna cena')->numeric()->required(),
                Forms\Components\Select::make('currency')
                    ->label('Waluta')
                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_CURRENCY))
                    ->default('PLN')
                    ->searchable(),
                Forms\Components\Select::make('contract_type')
                    ->label('Typ umowy')
                    ->options(Contract::$types)
                    ->default(Contract::TYPE_GROUP),
                Forms\Components\Select::make('status')->label('Status')->options(Contract::$statuses),
                Forms\Components\Select::make('payment_status')->label('Status płatności')->options(Contract::$paymentStatuses),
            ])->columns(['default' => 1, 'md' => 2]),

            Forms\Components\Section::make('Warianty realizacji (max 50)')->schema([
                Forms\Components\Repeater::make('variants')
                    ->relationship()
                    ->label('')
                    ->maxItems(50)
                    ->schema([
                        Forms\Components\TextInput::make('travelers_count')->label('Liczba podróżnych')->numeric()->minValue(1)->required(),
                        Forms\Components\DatePicker::make('starts_at')->label('Termin od'),
                        Forms\Components\DatePicker::make('ends_at')->label('Termin do'),
                        Forms\Components\Repeater::make('locations')
                            ->relationship()
                            ->label('Lokalizacje (max 5)')
                            ->maxItems(5)
                            ->schema([
                                Forms\Components\Select::make('scope_type')
                                    ->label('Zakres')
                                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_SCOPE)),
                                Forms\Components\Select::make('country_code')
                                    ->label('Kraj')
                                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_COUNTRY))
                                    ->searchable(),
                                Forms\Components\TextInput::make('locality')->label('Miejscowość'),
                            ])->columns(['default' => 1, 'md' => 2, 'xl' => 3])->columnSpanFull(),
                        Forms\Components\Repeater::make('transports')
                            ->relationship()
                            ->label('Transport (max 3)')
                            ->maxItems(3)
                            ->schema([
                                Forms\Components\Select::make('transport_code')
                                    ->label('Rodzaj transportu')
                                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_TRANSPORT))
                                    ->required()
                                    ->live(),
                                Forms\Components\TagsInput::make('icao_codes')
                                    ->label('Kody ICAO (lot)')
                                    ->placeholder('Wpisz kod ICAO')
                                    ->visible(fn (Get $get) => TfgDictionaryItem::requiresIcao((string) $get('transport_code')))
                                    ->required(fn (Get $get) => TfgDictionaryItem::requiresIcao((string) $get('transport_code'))),
                            ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
                    ])->collapsible(),
            ]),

            Forms\Components\Section::make('Wpłaty i zwroty')->schema([
                Forms\Components\Repeater::make('payments')
                    ->relationship()
                    ->label('Wpłaty (max 286)')
                    ->maxItems(286)
                    ->schema([
                        Forms\Components\TextInput::make('amount')->label('Kwota')->numeric()->required(),
                        Forms\Components\DatePicker::make('paid_at')->label('Data wpłaty'),
                        Forms\Components\Select::make('payment_method_code')
                            ->label('Forma')
                            ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_PAYMENT_METHOD)),
                        Forms\Components\TextInput::make('description')->label('Opis'),
                    ])->columns(['default' => 1, 'md' => 2, 'xl' => 4]),
                Forms\Components\Repeater::make('refunds')
                    ->relationship()
                    ->label('Zwroty (max 286)')
                    ->maxItems(286)
                    ->schema([
                        Forms\Components\TextInput::make('amount')->label('Kwota')->numeric()->required(),
                        Forms\Components\DatePicker::make('refunded_at')->label('Data zwrotu'),
                        Forms\Components\TextInput::make('description')->label('Opis'),
                    ])->columns(['default' => 1, 'md' => 2, 'xl' => 3]),
            ]),

            Forms\Components\Section::make('Status TFG')->schema([
                Forms\Components\Placeholder::make('tfg_status_display')
                    ->label('Status TFG')
                    ->content(fn (?Contract $record) => $record?->tfg_status ?: '—'),
                Forms\Components\Placeholder::make('pending_operation_display')
                    ->label('Oczekująca operacja')
                    ->content(fn (?Contract $record) => $record?->pending_operation ?: '—'),
                Forms\Components\Placeholder::make('tfg_synced_at_display')
                    ->label('Ostatnia synchronizacja')
                    ->content(fn (?Contract $record) => optional($record?->tfg_synced_at)?->format('d.m.Y H:i') ?: '—'),
                Forms\Components\Placeholder::make('tfg_deadline_display')
                    ->label('Termin korekty (+14 dni)')
                    ->content(fn (?Contract $record) => optional($record?->tfg_update_deadline_at)?->format('d.m.Y H:i') ?: '—'),
            ])->columns(['default' => 1, 'md' => 2])->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('operational_number')
                    ->label('Numer operacyjny')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract_number')->label('Numer TFG')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('event.name')->label('Impreza')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('contract_date')->label('Data')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('total_price')->label('Kwota')->money('PLN'),
                Tables\Columns\TextColumn::make('tfg_status')->label('TFG')->badge(),
                Tables\Columns\TextColumn::make('pending_operation')->label('Operacja')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->label('Status')->formatStateUsing(fn ($state) => Contract::$statuses[$state] ?? $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Impreza')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('tfg_status')->label('Status TFG')->options([
                    'Zawarta' => 'Zawarta',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('export_csv')
                    ->label('Pobierz CSV (TFG)')
                    ->icon('heroicon-o-table-cells')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('operation')
                            ->label('Typ operacji')
                            ->options([
                                Contract::OP_NOWEDANE => 'Nowe dane',
                                Contract::OP_KOREKTA => 'Korekta',
                                Contract::OP_ROZWIAZANIE => 'Rozwiązanie',
                                Contract::OP_USUNIECIE => 'Usunięcie',
                            ])
                            ->default(Contract::OP_NOWEDANE)
                            ->required(),
                    ])
                    ->action(function (Contract $record, array $data) {
                        return static::downloadCsvFor(collect([$record]), $data['operation']);
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('submit_tfg')
                        ->label('Wyślij przez API (eksperymentalne)')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('gray')
                        ->visible(fn (Contract $record) => $record->canSubmitNewData() && blank($record->pending_operation))
                        ->requiresConfirmation()
                        ->modalDescription('Kanał API jest eksperymentalny. Zalecany sposób przekazania danych to eksport CSV.')
                        ->action(function (Contract $record) {
                            $record->queueTfgOperation(Contract::OP_NOWEDANE);
                            SubmitTfgFeedJob::dispatch([$record->id]);
                        }),
                    Tables\Actions\Action::make('correct_tfg')
                        ->label('Koryguj (API)')
                        ->icon('heroicon-o-pencil-square')
                        ->color('gray')
                        ->visible(fn (Contract $record) => $record->canCorrect())
                        ->form([
                            Forms\Components\Select::make('correction_reason')
                                ->label('Powód korekty')
                                ->options(config('tfg.correction_reasons'))
                                ->live()
                                ->required(),
                            Forms\Components\DatePicker::make('tfg_change_date')
                                ->label('Data zmiany umowy')
                                ->visible(fn (Get $get) => $get('correction_reason') === 'ZMIANA')
                                ->required(fn (Get $get) => $get('correction_reason') === 'ZMIANA'),
                        ])
                        ->action(function (Contract $record, array $data) {
                            $record->queueTfgOperation(Contract::OP_KOREKTA, $data['correction_reason'], [
                                'tfg_change_date' => $data['tfg_change_date'] ?? null,
                            ]);
                            SubmitTfgFeedJob::dispatch([$record->id]);
                        }),
                    Tables\Actions\Action::make('terminate_tfg')
                        ->label('Rozwiąż (API)')
                        ->color('warning')
                        ->visible(fn (Contract $record) => $record->canTerminateOrDelete())
                        ->form([
                            Forms\Components\DatePicker::make('tfg_termination_date')
                                ->label('Data rozwiązania umowy')
                                ->default(now())
                                ->required(),
                        ])
                        ->action(function (Contract $record, array $data) {
                            $record->queueTfgOperation(Contract::OP_ROZWIAZANIE, null, [
                                'tfg_termination_date' => $data['tfg_termination_date'] ?? null,
                            ]);
                            SubmitTfgFeedJob::dispatch([$record->id]);
                        }),
                    Tables\Actions\Action::make('delete_tfg')
                        ->label('Usuń w TFG (API)')
                        ->color('danger')
                        ->visible(fn (Contract $record) => $record->canTerminateOrDelete())
                        ->requiresConfirmation()
                        ->action(function (Contract $record) {
                            $record->queueTfgOperation(Contract::OP_USUNIECIE);
                            SubmitTfgFeedJob::dispatch([$record->id]);
                        }),
                ])->label('Kanał API')->icon('heroicon-o-cloud-arrow-up')->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_export_csv')
                    ->label('Eksport CSV do TFG')
                    ->icon('heroicon-o-table-cells')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('operation')
                            ->label('Typ operacji')
                            ->options([
                                Contract::OP_NOWEDANE => 'Nowe dane',
                                Contract::OP_KOREKTA => 'Korekta',
                                Contract::OP_ROZWIAZANIE => 'Rozwiązanie',
                                Contract::OP_USUNIECIE => 'Usunięcie',
                            ])
                            ->default(Contract::OP_NOWEDANE)
                            ->required(),
                    ])
                    ->action(function ($records, array $data) {
                        return static::downloadCsvFor(collect($records), $data['operation']);
                    }),
                Tables\Actions\BulkAction::make('bulk_submit_tfg')
                    ->label('Wyślij przez API (eksperymentalne)')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $ids = [];
                        foreach ($records as $record) {
                            if ($record->canSubmitNewData() && blank($record->pending_operation)) {
                                $record->queueTfgOperation(Contract::OP_NOWEDANE);
                                $ids[] = $record->id;
                            }
                        }
                        if ($ids !== []) {
                            SubmitTfgFeedJob::dispatch($ids);
                        }
                    }),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['event', 'lastFeedLog']);
    }

    protected static function downloadCsvFor(Collection $records, string $operation): ?StreamedResponse
    {
        $service = app(TfgCsvExportService::class);

        try {
            $result = $service->generate($records->values(), $operation);
        } catch (TfgValidationException $exception) {
            $errors = $exception->errors;
            unset($errors['_file']);

            Notification::make()
                ->title('Eksport CSV zablokowany')
                ->body('Błędy walidacji w '.count($errors).' umowach. Sprawdź dane na stronie "Eksport TFG (CSV)".')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('Wygenerowano plik CSV')
            ->body($result['filename'].' • umów: '.$result['contracts_count'].' • wierszy: '.$result['rows_count'])
            ->success()
            ->send();

        $content = $result['content'];

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $result['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
