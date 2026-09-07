<?php

namespace App\Enums;

enum VehicleType: string
{
    case Bus = 'bus';
    case Microbus = 'microbus';
    case Limo = 'limo';
    case Van = 'van';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bus => 'Autokar',
            self::Microbus => 'Mikrobus',
            self::Limo => 'Limuzyna',
            self::Van => 'Van',
            self::Other => 'Inny',
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
