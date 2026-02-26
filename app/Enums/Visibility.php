<?php

namespace App\Enums;

enum Visibility: string
{
    case PUBLIC = 'public';
    case INTERNAL = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Publiczny',
            self::INTERNAL => 'Wewnętrzny',
        };
    }
}