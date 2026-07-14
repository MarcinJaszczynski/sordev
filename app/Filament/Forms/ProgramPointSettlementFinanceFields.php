<?php

namespace App\Filament\Forms;

use App\Models\Currency;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

final class ProgramPointSettlementFinanceFields
{
    /**
     * @return list<Forms\Components\Component>
     */
    public static function planFields(): array
    {
        return [
            Forms\Components\TextInput::make('settlement_planned_amount')
                ->label('Kwota planowana')
                ->numeric()
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalculatePlannedPln($set, $get)),

            CurrencyConversionFields::currencySelect('settlement_planned_currency_id')
                ->label('Waluta planu')
                ->live()
                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                    if ($state) {
                        $set('settlement_planned_rate', Currency::find($state)?->exchange_rate ?? 1);
                    }

                    self::recalculatePlannedPln($set, $get);
                }),

            CurrencyConversionFields::convertToggle('settlement_planned_convert_to_pln', 'settlement_planned_currency_id')
                ->label('Przelicz plan na PLN')
                ->live()
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalculatePlannedPln($set, $get)),

            Forms\Components\TextInput::make('settlement_planned_rate')
                ->label('Kurs planu')
                ->numeric()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalculatePlannedPln($set, $get)),

            Forms\Components\TextInput::make('settlement_planned_amount_pln')
                ->label(fn (Get $get): string => self::isForeignCurrency($get('settlement_planned_currency_id')) && ! (bool) ($get('settlement_planned_convert_to_pln') ?? true)
                    ? 'Suma w PLN'
                    : 'Plan = PLN')
                ->numeric()
                ->readOnly()
                ->suffix('PLN')
                ->placeholder(fn (Get $get): ?string => self::isForeignCurrency($get('settlement_planned_currency_id')) && ! (bool) ($get('settlement_planned_convert_to_pln') ?? true)
                    ? 'Bez przeliczenia'
                    : null),

            Forms\Components\Select::make('settlement_paid_by')
                ->label('Płaci')
                ->options(EventSettlementCost::$paidByOptions)
                ->required(),

            self::payableUntilField('settlement_payment_due_date'),

            Forms\Components\Select::make('settlement_payment_method')
                ->label('Sposób płatności')
                ->options(EventSettlementCost::$paymentMethods)
                ->nullable(),

            Forms\Components\Textarea::make('settlement_notes')
                ->label('Uwagi')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return list<Forms\Components\Component>
     */
    public static function advanceFields(): array
    {
        return [
            Forms\Components\Select::make('settlement_advance_paid_by')
                ->label('Płaci')
                ->options(EventSettlementCost::$paidByOptions)
                ->required(),

            Forms\Components\Select::make('settlement_advance_type')
                ->label('Typ płatności')
                ->options(EventSettlementCost::$advanceTypes)
                ->required(),

            self::payableUntilField('settlement_advance_due_date'),

            Forms\Components\TextInput::make('settlement_advance_amount')
                ->label('Kwota zaliczki')
                ->numeric()
                ->suffix(fn (Get $get): string => self::currencySuffix($get('settlement_planned_currency_id')))
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalculateAdvancePaidPln($set, $get))
                ->nullable(),

            Forms\Components\TextInput::make('settlement_advance_paid_amount')
                ->label('Ile zapłaciliśmy')
                ->numeric()
                ->suffix(fn (Get $get): string => self::currencySuffix($get('settlement_advance_paid_currency_id') ?: $get('settlement_planned_currency_id')))
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalculateAdvancePaidPln($set, $get))
                ->nullable(),

            Forms\Components\Select::make('settlement_advance_paid_currency_id')
                ->label('Waluta wpłaty zaliczki')
                ->options(fn (): array => Currency::filamentSelectOptions())
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                    if ($state) {
                        $set('settlement_advance_paid_rate', Currency::find($state)?->exchange_rate ?? 1);
                    }

                    self::recalculateAdvancePaidPln($set, $get);
                })
                ->nullable(),

            Forms\Components\TextInput::make('settlement_advance_paid_rate')
                ->label('Kurs wpłaty zaliczki')
                ->numeric()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalculateAdvancePaidPln($set, $get))
                ->nullable(),

            Forms\Components\TextInput::make('settlement_advance_paid_amount_pln')
                ->label('Wpłata zaliczki = PLN')
                ->numeric()
                ->suffix('PLN')
                ->readOnly()
                ->nullable(),

            self::paymentDateField('settlement_advance_paid_at'),

            Forms\Components\Select::make('settlement_advance_payment_method')
                ->label('Sposób płatności')
                ->options(EventSettlementCost::$paymentMethods)
                ->nullable(),

            Forms\Components\Textarea::make('settlement_advance_notes')
                ->label('Uwagi')
                ->rows(2)
                ->columnSpan(2),
        ];
    }

    /**
     * @return list<Forms\Components\Component>
     */
    public static function advanceEntryFields(): array
    {
        return [
            Forms\Components\Hidden::make('id'),

            self::payableUntilField('due_date'),

            Forms\Components\TextInput::make('advance_amount')
                ->label('Kwota zaliczki')
                ->numeric()
                ->suffix(fn (Get $get): string => self::currencySuffix($get('currency_id')))
                ->live(onBlur: true)
                ->nullable(),

            Forms\Components\Select::make('currency_id')
                ->label('Waluta')
                ->options(fn (): array => Currency::filamentSelectOptions())
                ->searchable()
                ->nullable(),

            Forms\Components\DateTimePicker::make('paid_at')
                ->label('Zapłacone dnia')
                ->nullable(),

            Forms\Components\Textarea::make('notes')
                ->label('Uwagi')
                ->rows(2)
                ->columnSpanFull()
                ->nullable(),

            ...self::documentFields(prefix: ''),
        ];
    }

    /**
     * @return list<Forms\Components\Component>
     */
    public static function paymentEntryFields(): array
    {
        return [
            Forms\Components\Select::make('paid_by')
                ->label('Płaci')
                ->options(EventSettlementCost::$paidByOptions)
                ->required(),

            self::payableUntilField('due_date'),

            Forms\Components\TextInput::make('actual_amount')
                ->label('Wpłacono')
                ->numeric()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalculateActualPln($set, $get))
                ->nullable(),

            Forms\Components\Select::make('actual_currency_id')
                ->label('Waluta')
                ->options(fn (): array => Currency::filamentSelectOptions())
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                    if ($state) {
                        $set('actual_rate', Currency::find($state)?->exchange_rate ?? 1);
                    }

                    self::recalculateActualPln($set, $get);
                })
                ->nullable(),

            Forms\Components\TextInput::make('actual_rate')
                ->label('Kurs')
                ->numeric()
                ->step(0.0001)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalculateActualPln($set, $get)),

            Forms\Components\TextInput::make('actual_amount_pln')
                ->label('= PLN')
                ->numeric()
                ->suffix('PLN')
                ->readOnly(),

            Forms\Components\Select::make('payment_method')
                ->label('Forma')
                ->options(EventSettlementCost::$paymentMethods)
                ->nullable(),

            ...self::documentFields(prefix: ''),

            self::paymentDateField('paid_at'),

            Forms\Components\Textarea::make('notes')
                ->label('Uwagi')
                ->rows(2)
                ->columnSpanFull()
                ->nullable(),
        ];
    }

    /**
     * @return list<Forms\Components\Component>
     */
    public static function advanceDocumentSection(): array
    {
        return [
            Forms\Components\Section::make('Dokument zaliczki')
                ->columns(2)
                ->collapsed()
                ->schema(self::documentFields(prefix: 'advance_')),
        ];
    }

    /**
     * @return list<Forms\Components\Component>
     */
    public static function documentFields(string $prefix = ''): array
    {
        $documentTypeField = $prefix.'document_type';
        $documentNumberField = $prefix.'document_number';
        $documentFilesField = $prefix.'document_files';
        $documentIdField = $prefix.'document_id';

        return [
            Forms\Components\Hidden::make($documentIdField),

            Forms\Components\Select::make($documentTypeField)
                ->label('Typ dokumentu')
                ->options(EventSettlementDocument::$documentTypes)
                ->nullable(),

            Forms\Components\TextInput::make($documentNumberField)
                ->label('Numer dokumentu')
                ->maxLength(255)
                ->nullable(),

            Forms\Components\FileUpload::make($documentFilesField)
                ->label('Załącznik (FV / WZ / inny)')
                ->multiple()
                ->directory('event-settlement-documents')
                ->disk('public')
                ->visibility('public')
                ->nullable()
                ->columnSpanFull(),
        ];
    }

    public static function payableUntilField(string $name): Forms\Components\DateTimePicker
    {
        return Forms\Components\DateTimePicker::make($name)
            ->label('Płatne do')
            ->nullable();
    }

    public static function paymentDateField(string $name): Forms\Components\DateTimePicker
    {
        return Forms\Components\DateTimePicker::make($name)
            ->label('Termin płatności')
            ->nullable();
    }

    public static function recalculatePlannedPln(Set $set, Get $get): void
    {
        $amount = (float) ($get('settlement_planned_amount') ?: 0);
        $rate = (float) ($get('settlement_planned_rate') ?: 1);
        $pln = self::plannedPlnAmount($amount, $rate, $get);
        $set('settlement_planned_amount_pln', $pln);
    }

    public static function recalculateAdvancePaidPln(Set $set, Get $get): void
    {
        $amount = (float) ($get('settlement_advance_paid_amount') ?: 0);

        if ($amount <= 0) {
            $set('settlement_advance_paid_amount_pln', null);

            return;
        }

        $currencyId = $get('settlement_advance_paid_currency_id') ?: $get('settlement_planned_currency_id');
        $rate = (float) ($get('settlement_advance_paid_rate') ?: 1);

        if (self::isForeignCurrency($currencyId)) {
            $set('settlement_advance_paid_amount_pln', round($amount * $rate, 2));
        } else {
            $set('settlement_advance_paid_amount_pln', round($amount, 2));
        }
    }

    public static function recalculateActualPln(Set $set, Get $get): void
    {
        $amount = $get('actual_amount');

        if ($amount === null || $amount === '') {
            $set('actual_amount_pln', null);

            return;
        }

        $currencyId = $get('actual_currency_id');
        $rate = (float) ($get('actual_rate') ?: 1);
        $numericAmount = (float) $amount;

        if (self::isForeignCurrency($currencyId)) {
            $set('actual_amount_pln', round($numericAmount * $rate, 2));
        } else {
            $set('actual_amount_pln', round($numericAmount, 2));
        }
    }

    public static function plannedPlnAmount(float $amount, float $rate, Get $get): ?float
    {
        if (! self::isForeignCurrency($get('settlement_planned_currency_id'))) {
            return round($amount, 2);
        }

        if (! (bool) ($get('settlement_planned_convert_to_pln') ?? true)) {
            return null;
        }

        return round($amount * $rate, 2);
    }

    public static function isForeignCurrency(mixed $currencyId): bool
    {
        return CurrencyConversionFields::isForeignCurrency($currencyId);
    }

    public static function currencySuffix(mixed $currencyId): string
    {
        if (! $currencyId) {
            return 'PLN';
        }

        $currency = Currency::find($currencyId);

        return $currency?->symbol ?: ($currency?->code ?? 'PLN');
    }
}
