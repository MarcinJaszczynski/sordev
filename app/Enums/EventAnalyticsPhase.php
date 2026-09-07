<?php

declare(strict_types=1);

namespace App\Enums;

enum EventAnalyticsPhase: string
{
    case Future = 'future';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Future => 'Przyszła',
            self::InProgress => 'W trakcie',
            self::Completed => 'Zakończona',
            self::Cancelled => 'Anulowana',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $phase): array => [$phase->value => $phase->label()])
            ->all();
    }
}
