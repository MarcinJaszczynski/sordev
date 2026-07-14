<?php

namespace App\Filament\Forms;

use App\Filament\Forms\PhoneInput;
use Filament\Forms;

final class ContractorLocationFields
{
    /** @return array<int, Forms\Components\Component> */
    public static function schema(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Nazwa oddziału / obiektu')
                ->required()
                ->maxLength(255)
                ->columnSpanFull()
                ->helperText('Np. „Hotel Górski — Zakopane”, „Restauracja Centrum”.'),

            Forms\Components\TextInput::make('street')
                ->label('Ulica')
                ->columnSpan(2),
            Forms\Components\TextInput::make('house_number')
                ->label('Nr domu')
                ->columnSpan(1),
            Forms\Components\TextInput::make('postal_code')
                ->label('Kod pocztowy')
                ->columnSpan(1),
            Forms\Components\TextInput::make('city')
                ->label('Miejscowość')
                ->columnSpan(2),
            Forms\Components\TextInput::make('region')
                ->label('Region / województwo')
                ->columnSpan(1),
            Forms\Components\TextInput::make('country')
                ->label('Kraj')
                ->default('Polska')
                ->columnSpan(1),

            Forms\Components\Fieldset::make('Osoba na miejscu')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('contact_first_name')
                        ->label('Imię')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('contact_last_name')
                        ->label('Nazwisko')
                        ->maxLength(255),
                    PhoneInput::make('phone')
                        ->label('Telefon'),
                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->maxLength(255),
                ]),

            Forms\Components\Toggle::make('is_primary')
                ->label('Domyślne miejsce')
                ->helperText('Używane przy auto-wyborze w formularzach imprezy.')
                ->inline(false),

            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'active' => 'Aktywne',
                    'inactive' => 'Nieaktywne',
                ])
                ->default('active')
                ->required(),

            Forms\Components\Textarea::make('office_notes')
                ->label('Uwagi dla biura')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }
}
