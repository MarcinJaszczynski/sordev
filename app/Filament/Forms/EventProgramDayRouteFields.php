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

        // Tylko dni wycieczki (core) — slot fakultatywny (core+1) jest pod szablon/stronę, nie pod kierowcę.
        $days = $record?->resolveCoreProgramDaysCount() ?? 1;
        $fields = [];

        for ($day = 1; $day <= $days; $day++) {
            $date = $record?->dateForProgramDay($day)?->format('d.m.Y');
            $label = $date ? "Dzień {$day} ({$date})" : "Dzień {$day}";

            $daySection = Forms\Components\Section::make($label)
                ->collapsible()
                ->compact()
                ->schema([
                    Forms\Components\TextInput::make("program_day_routes.{$day}")
                        ->hiddenLabel()
                        ->placeholder('Wpisz trasę przejazdu')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->extraAttributes(['class' => 'transport-route-row']);

            if ($day > 1) {
                $daySection->collapsed();
            }

            $fields[] = $daySection;
        }

        return $fields;
    }

    public static function section(string $heading = 'Trasy przejazdu'): Forms\Components\Section
    {
        return Forms\Components\Section::make($heading)
            ->description('Ramowa trasa autokaru na każdy dzień wycieczki (bez opcji fakultatywnych). Widoczna w portalu pilota, w Programie i w pakiecie kierowcy.')
            ->schema(function (?Event $record, $livewire = null): array {
                $event = $record
                    ?? (is_object($livewire) && method_exists($livewire, 'getRecord')
                        ? $livewire->getRecord()
                        : null);

                return self::fieldsForEvent($event instanceof Event ? $event : null);
            })
            ->columns(1)
            ->collapsible();
    }
}
