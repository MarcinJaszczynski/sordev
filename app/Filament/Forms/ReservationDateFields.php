<?php

namespace App\Filament\Forms;

use Filament\Forms;

class ReservationDateFields
{
    public static function reservedAt(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('reserved_at')
            ->label('Data rezerwacji')
            ->displayFormat('d.m.Y')
            ->format('Y-m-d')
            ->native(false)
            ->default(now()->toDateString())
            ->required();
    }

    public static function expiresAt(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('expires_at')
            ->label('Wygasa')
            ->displayFormat('d.m.Y')
            ->format('Y-m-d')
            ->native(false)
            ->nullable()
            ->helperText('Pozostaw puste, aby rezerwacja była ważna bezterminowo');
    }
}
