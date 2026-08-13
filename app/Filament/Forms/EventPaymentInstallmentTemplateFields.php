<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Models\EventPaymentInstallmentTemplate;
use Filament\Forms;
use Filament\Forms\Get;

class EventPaymentInstallmentTemplateFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Forms\Components\Repeater::make('installment_templates')
                ->label('Szablon harmonogramu wpłat')
                ->helperText('Terminy względem startu imprezy (np. −30 = 30 dni przed). Procent liczony od ceny/os. (umowa → ręczna → kalkulacja). Waluta zwykle → pilot w dniu startu.')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('Opis')
                        ->placeholder('Wpisz opis raty')
                        ->maxLength(255),

                    Forms\Components\Select::make('share_type')
                        ->label('Typ')
                        ->options(EventPaymentInstallmentTemplate::$shareTypes)
                        ->default(EventPaymentInstallmentTemplate::SHARE_PERCENT)
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('percent')
                        ->label('% ceny')
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue(100)
                        ->suffix('%')
                        ->visible(fn (Get $get): bool => $get('share_type') === EventPaymentInstallmentTemplate::SHARE_PERCENT)
                        ->required(fn (Get $get): bool => $get('share_type') === EventPaymentInstallmentTemplate::SHARE_PERCENT),

                    Forms\Components\TextInput::make('amount_pln')
                        ->label('Kwota PLN')
                        ->numeric()
                        ->minValue(0.01)
                        ->suffix('PLN')
                        ->visible(fn (Get $get): bool => $get('share_type') === EventPaymentInstallmentTemplate::SHARE_FIXED_PLN)
                        ->required(fn (Get $get): bool => $get('share_type') === EventPaymentInstallmentTemplate::SHARE_FIXED_PLN),

                    Forms\Components\TextInput::make('amount_foreign')
                        ->label('Kwota waluty')
                        ->numeric()
                        ->minValue(0.01)
                        ->visible(fn (Get $get): bool => $get('share_type') === EventPaymentInstallmentTemplate::SHARE_FOREIGN)
                        ->required(fn (Get $get): bool => $get('share_type') === EventPaymentInstallmentTemplate::SHARE_FOREIGN),

                    Forms\Components\TextInput::make('currency_code')
                        ->label('Waluta')
                        ->maxLength(8)
                        ->placeholder('Wpisz kod waluty')
                        ->visible(fn (Get $get): bool => $get('share_type') === EventPaymentInstallmentTemplate::SHARE_FOREIGN)
                        ->required(fn (Get $get): bool => $get('share_type') === EventPaymentInstallmentTemplate::SHARE_FOREIGN),

                    Forms\Components\TextInput::make('due_offset_days')
                        ->label('Dni do startu (D±N)')
                        ->numeric()
                        ->integer()
                        ->default(fn (Get $get): int => $get('share_type') === EventPaymentInstallmentTemplate::SHARE_FOREIGN ? 0 : -30)
                        ->helperText('0 = dzień startu, −14 = 14 dni przed'),

                    Forms\Components\Select::make('paid_by')
                        ->label('Płatnik / odbiorca')
                        ->options(EventPaymentInstallmentTemplate::$paidByOptions)
                        ->default(fn (Get $get): string => $get('share_type') === EventPaymentInstallmentTemplate::SHARE_FOREIGN
                            ? EventPaymentInstallmentTemplate::PAID_BY_PILOT
                            : EventPaymentInstallmentTemplate::PAID_BY_OFFICE)
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
                    : (EventPaymentInstallmentTemplate::$shareTypes[$state['share_type'] ?? ''] ?? 'Transza'))
                ->columnSpanFull(),
        ];
    }
}
