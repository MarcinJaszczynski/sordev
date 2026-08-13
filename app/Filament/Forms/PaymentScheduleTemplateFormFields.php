<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Models\PaymentScheduleTemplateInstallment;
use Filament\Forms;
use Filament\Forms\Get;

final class PaymentScheduleTemplateFormFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Forms\Components\Section::make('Podstawowe')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa szablonu')
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktywny')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Nieaktywne wersje nie pojawiają się przy tworzeniu umów.'),

                    Forms\Components\CheckboxList::make('applies_to')
                        ->label('Dotyczy typów umów')
                        ->options(\App\Models\PaymentScheduleTemplate::$appliesToOptions)
                        ->columns(['default' => 1, 'md' => 3])
                        ->helperText('Puste = uniwersalny (wszystkie typy).')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('version')
                        ->label('Wersja')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->default(1),

                    Forms\Components\Textarea::make('version_notes')
                        ->label('Notatka wersji')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Transze harmonogramu')
                ->schema(static::installmentRepeater())
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function installmentRepeater(): array
    {
        return [
            Forms\Components\Repeater::make('installments')
                ->label('Transze')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('Opis')
                        ->placeholder('Np. Zaliczka, Dopłata, Waluta u pilota')
                        ->maxLength(255),

                    Forms\Components\Select::make('share_type')
                        ->label('Typ')
                        ->options(PaymentScheduleTemplateInstallment::$shareTypes)
                        ->default(PaymentScheduleTemplateInstallment::SHARE_PERCENT)
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('percent')
                        ->label('% ceny')
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue(100)
                        ->suffix('%')
                        ->visible(fn (Get $get): bool => $get('share_type') === PaymentScheduleTemplateInstallment::SHARE_PERCENT)
                        ->required(fn (Get $get): bool => $get('share_type') === PaymentScheduleTemplateInstallment::SHARE_PERCENT),

                    Forms\Components\TextInput::make('amount_pln')
                        ->label('Kwota PLN')
                        ->numeric()
                        ->minValue(0.01)
                        ->suffix('PLN')
                        ->visible(fn (Get $get): bool => $get('share_type') === PaymentScheduleTemplateInstallment::SHARE_FIXED_PLN)
                        ->required(fn (Get $get): bool => $get('share_type') === PaymentScheduleTemplateInstallment::SHARE_FIXED_PLN),

                    Forms\Components\TextInput::make('amount_foreign')
                        ->label('Kwota waluty')
                        ->numeric()
                        ->minValue(0.01)
                        ->visible(fn (Get $get): bool => $get('share_type') === PaymentScheduleTemplateInstallment::SHARE_FOREIGN)
                        ->required(fn (Get $get): bool => $get('share_type') === PaymentScheduleTemplateInstallment::SHARE_FOREIGN),

                    Forms\Components\TextInput::make('currency_code')
                        ->label('Waluta')
                        ->maxLength(8)
                        ->placeholder('EUR')
                        ->visible(fn (Get $get): bool => $get('share_type') === PaymentScheduleTemplateInstallment::SHARE_FOREIGN)
                        ->required(fn (Get $get): bool => $get('share_type') === PaymentScheduleTemplateInstallment::SHARE_FOREIGN),

                    Forms\Components\TextInput::make('due_offset_from_days')
                        ->label('Płatne od (D±N)')
                        ->numeric()
                        ->integer()
                        ->default(-30)
                        ->helperText('0 = dzień startu, −14 = 14 dni przed'),

                    Forms\Components\TextInput::make('due_offset_to_days')
                        ->label('Płatne do (D±N)')
                        ->numeric()
                        ->integer()
                        ->default(-30)
                        ->helperText('Deadline raty. Równy „od” = jeden termin.'),

                    Forms\Components\TextInput::make('due_offset_days')
                        ->label('Dni do startu (legacy)')
                        ->numeric()
                        ->integer()
                        ->default(-30)
                        ->visible(false)
                        ->dehydrated(true),

                    Forms\Components\Select::make('paid_by')
                        ->label('Odbiorca wpłaty')
                        ->options(PaymentScheduleTemplateInstallment::$paidByOptions)
                        ->default(PaymentScheduleTemplateInstallment::PAID_BY_OFFICE)
                        ->required(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Uwagi')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->defaultItems(0)
                ->addActionLabel('Dodaj transzę')
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null)
                    ? (string) $state['label']
                    : (PaymentScheduleTemplateInstallment::$shareTypes[$state['share_type'] ?? ''] ?? 'Transza'))
                ->columnSpanFull(),
        ];
    }
}
