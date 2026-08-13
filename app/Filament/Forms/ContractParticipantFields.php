<?php

namespace App\Filament\Forms;

use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Schema;

class ContractParticipantFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(bool $collapsed = false): array
    {
        $section = Forms\Components\Section::make('Uczestnik')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Forms\Components\Select::make('gender')
                    ->label('Płeć')
                    ->options([
                        'female' => 'Kobieta',
                        'male' => 'Mężczyzna',
                        'other' => 'Inna',
                    ])
                    ->visible(fn (): bool => Schema::hasColumn('contracts', 'gender')),

                Forms\Components\Select::make('identification_type')
                    ->label('Dokument tożsamości')
                    ->options([
                        'id_card' => 'Dowód osobisty',
                        'passport' => 'Paszport',
                        'other' => 'Inny',
                    ])
                    ->visible(fn (): bool => Schema::hasColumn('contracts', 'identification_type')),

                Forms\Components\Toggle::make('requires_diet')
                    ->label('Dieta specjalna')
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        if ($state && Schema::hasColumn('contracts', 'diet_daily_pln')) {
                            $set('diet_daily_pln', 20);
                        }
                    })
                    ->visible(fn (): bool => Schema::hasColumn('contracts', 'requires_diet')),

                Forms\Components\TextInput::make('diet_type')
                    ->label('Rodzaj diety')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => Schema::hasColumn('contracts', 'diet_type') && (bool) $get('requires_diet')),

                Forms\Components\TextInput::make('diet_daily_pln')
                    ->label('Dopłata diety (PLN/dzień)')
                    ->numeric()
                    ->default(20)
                    ->suffix('PLN')
                    ->visible(fn (Get $get): bool => Schema::hasColumn('contracts', 'diet_daily_pln') && (bool) $get('requires_diet')),
            ]);

        if ($collapsed) {
            $section->collapsed();
        }

        return [$section];
    }
}
