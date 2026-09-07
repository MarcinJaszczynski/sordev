<?php

declare(strict_types=1);

namespace App\Enums;

enum ProfitRecognitionMode: string
{
    case Auto = 'auto';
    case Planned = 'planned';
    case Paid = 'paid';
    case Blend = 'blend';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Auto (wg fazy)',
            self::Planned => 'Zawsze plan',
            self::Paid => 'Zawsze zapłacone',
            self::Blend => 'Zawsze blend (paid + remaining)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode): array => [$mode->value => $mode->label()])
            ->all();
    }
}
