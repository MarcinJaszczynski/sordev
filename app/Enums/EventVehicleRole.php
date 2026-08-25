<?php

namespace App\Enums;

enum EventVehicleRole: string
{
    case Main = 'main';
    case Shuttle = 'shuttle';
    case AirportTransfer = 'airport_transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Główny autokar',
            self::Shuttle => 'Wahadło',
            self::AirportTransfer => 'Transfer na lotnisko',
            self::Other => 'Inna rola',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
