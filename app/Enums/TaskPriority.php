<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Domyślny',
            self::Urgent => 'Pilne',
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

    public static function normalize(?string $value): string
    {
        return match ($value) {
            'urgent', 'high' => self::Urgent->value,
            default => self::Normal->value,
        };
    }

    public static function sortWeight(?string $value): int
    {
        return self::normalize($value) === self::Urgent->value ? 2 : 1;
    }
}
