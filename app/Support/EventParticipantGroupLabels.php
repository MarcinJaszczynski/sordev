<?php

namespace App\Support;

final class EventParticipantGroupLabels
{
    public const GRATIS = 'Opiekunowie/Dodatkowe';

    public const GRATIS_GENITIVE = 'opiekunów/dodatkowych';

    /** @return array<string, string> */
    public static function hotelRoleLabels(): array
    {
        return [
            'qty' => 'Uczestnicy',
            'gratis' => self::GRATIS,
            'staff' => 'Obsługa',
            'driver' => 'Kierowca',
        ];
    }
}
