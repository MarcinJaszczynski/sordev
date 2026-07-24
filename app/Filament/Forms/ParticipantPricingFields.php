<?php

namespace App\Filament\Forms;

use App\Models\Reservation;
use App\Support\EventParticipantGroupLabels;
use Filament\Forms;
use Filament\Forms\Get;

final class ParticipantPricingFields
{
    public static function participantScopeSelect(string $name = 'participant_scope'): Forms\Components\Radio
    {
        return Forms\Components\Radio::make($name)
            ->label('Liczba dotyczy')
            ->options(Reservation::$participantScopes)
            ->default('all')
            ->inline()
            ->live();
    }

    public static function participantCountInput(
        string $name = 'participant_count',
        string $scopeField = 'participant_scope',
    ): Forms\Components\TextInput {
        return Forms\Components\TextInput::make($name)
            ->label(fn (Get $get): string => $get($scopeField) === 'paying'
                ? 'Liczba płacących'
                : 'Liczba uczestników')
            ->numeric()
            ->minValue(1)
            ->default(1)
            ->required()
            ->live(onBlur: true)
            ->helperText(fn (Get $get): string => $get($scopeField) === 'paying'
                ? 'Tylko osoby, które płacą za tę pozycję (np. bez '.EventParticipantGroupLabels::GRATIS_GENITIVE.').'
                : 'Wszyscy uczestnicy objęci tą pozycją.');
    }

    public static function amountBasisSelect(string $name = 'amount_basis'): Forms\Components\Radio
    {
        return Forms\Components\Radio::make($name)
            ->label('Rodzaj ceny')
            ->options(Reservation::$amountBases)
            ->default('lump_sum')
            ->inline()
            ->live()
            ->helperText(fn (Get $get): string => $get($name) === 'per_person'
                ? 'Wpisz stawkę za 1 osobę — system pomnoży przez liczbę powyżej.'
                : 'Wpisz kwotę łączną za całą grupę (nie mnożymy przez liczbę osób).');
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function reservationPricingFields(): array
    {
        return [
            self::participantScopeSelect(),
            self::participantCountInput(),
            self::amountBasisSelect(),
        ];
    }

    public static function settlementPlannedScopeSelect(string $name = 'planned_participant_scope'): Forms\Components\Radio
    {
        return Forms\Components\Radio::make($name)
            ->label('Kwota za osobę dotyczy')
            ->options(Reservation::$participantScopes)
            ->default('all')
            ->inline()
            ->columnSpanFull()
            ->helperText('Czy stawka za osobę dotyczy wszystkich uczestników imprezy, czy tylko płacących.');
    }

    public static function settlementAmountBasisSelect(string $name = 'planned_amount_basis'): Forms\Components\Radio
    {
        return Forms\Components\Radio::make($name)
            ->label('Rodzaj ceny planowanej')
            ->options(Reservation::$amountBases)
            ->default('per_person')
            ->inline()
            ->columnSpanFull()
            ->live()
            ->helperText(fn (Get $get): string => $get($name) === 'per_person'
                ? 'Kwota za osobę × liczba uczestników/płacących.'
                : 'Kwota łączna za grupę — bez mnożenia przez liczbę osób.');
    }
}
