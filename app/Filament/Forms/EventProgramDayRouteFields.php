<?php

namespace App\Filament\Forms;

use App\Models\Event;
use Filament\Forms;
use Illuminate\Support\Facades\Schema;

final class EventProgramDayRouteFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function fieldsForEvent(?Event $record): array
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            return [];
        }

        $days = max(1, (int) ($record?->duration_days ?? 1));
        $fields = [];

        for ($day = 1; $day <= $days; $day++) {
            $date = $record?->dateForProgramDay($day)?->format('d.m.Y');
            $fields[] = Forms\Components\TextInput::make("program_day_routes.{$day}")
                ->label($date ? "Dzień {$day} ({$date})" : "Dzień {$day}")
                ->placeholder('Wpisz trasę przejazdu')
                ->maxLength(500)
                ->columnSpanFull();
        }

        return $fields;
    }

    public static function section(string $heading = 'Trasy przejazdu'): Forms\Components\Section
    {
        return Forms\Components\Section::make($heading)
            ->description('Trasa autokaru na każdy dzień imprezy. Te same dane edytujesz też w zakładce Program.')
            ->schema(fn (?Event $record): array => self::fieldsForEvent($record))
            ->columns(1)
            ->collapsible();
    }
}
