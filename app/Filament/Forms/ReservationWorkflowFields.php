<?php

namespace App\Filament\Forms;

use Filament\Forms;

final class ReservationWorkflowFields
{
    public static function confirmBy(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('confirm_by')
            ->label('Potwierdzić do')
            ->displayFormat('d.m.Y')
            ->format('Y-m-d')
            ->native(false)
            ->nullable();
    }

    public static function confirmedAt(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('confirmed_at')
            ->label('Potwierdzono')
            ->displayFormat('d.m.Y')
            ->format('Y-m-d')
            ->native(false)
            ->nullable();
    }

    public static function depositDueAt(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('deposit_due_at')
            ->label('Zaliczka do')
            ->displayFormat('d.m.Y')
            ->format('Y-m-d')
            ->native(false)
            ->nullable();
    }

    public static function depositPaidAt(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('deposit_paid_at')
            ->label('Zaliczka zapłacona')
            ->displayFormat('d.m.Y')
            ->format('Y-m-d')
            ->native(false)
            ->nullable();
    }

    /** @return array<int, Forms\Components\Component> */
    public static function workflowSection(): array
    {
        return [
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(\App\Models\Reservation::$statuses)
                ->default('pending')
                ->required()
                ->live(),

            ReservationWorkflowFields::confirmBy(),
            ReservationWorkflowFields::confirmedAt(),
            ReservationWorkflowFields::depositDueAt(),
            ReservationWorkflowFields::depositPaidAt(),
        ];
    }
}
