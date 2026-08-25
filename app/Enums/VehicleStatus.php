<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Active = 'active';
    case InService = 'in_service';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktywny',
            self::InService => 'W serwisie',
            self::Retired => 'Wycofany',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::InService => 'warning',
            self::Retired => 'gray',
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
