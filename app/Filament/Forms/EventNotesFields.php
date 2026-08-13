<?php

namespace App\Filament\Forms;

use Filament\Forms;
use FilamentTiptapEditor\TiptapEditor;
use Illuminate\Support\Facades\Schema;

class EventNotesFields
{
    private static function editor(string $name): TiptapEditor
    {
        return TiptapEditor::make($name)
            ->profile('notes')
            ->maxContentWidth('full')
            ->columnSpanFull();
    }

    public static function generalNotes(): TiptapEditor
    {
        return self::editor('notes')
            ->label('Zapytanie klienta/Uwagi ogólne')
            ->extraInputAttributes(['class' => 'event-notes-editor'])
            ->placeholder('Treść zapytania klienta lub dodatkowe uwagi o imprezie.')
            ->helperText('Zapytanie od klienta i informacje ogólne — widoczne przy podstawowych danych imprezy.');
    }

    public static function officeNotes(): TiptapEditor
    {
        return self::editor('office_notes')
            ->label('Uwagi dla biura')
            ->visible(fn (): bool => Schema::hasColumn('events', 'office_notes'))
            ->helperText('Uwagi wewnętrzne dla pracowników biura.');
    }

    public static function pilotNotes(): TiptapEditor
    {
        return self::editor('pilot_notes')
            ->label('Uwagi dla pilota')
            ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_notes'))
            ->helperText('Informacje przekazywane pilotowi / opiekunowi (trafią do pakietu pilota).');
    }

    public static function driverNotes(): TiptapEditor
    {
        return self::editor('driver_notes')
            ->label('Uwagi dla kierowcy')
            ->visible(fn (): bool => Schema::hasColumn('events', 'driver_notes'))
            ->helperText('Informacje przekazywane kierowcy (trafią do pakietu kierowcy).');
    }

    public static function hotelNotes(): TiptapEditor
    {
        return self::editor('hotel_notes')
            ->label('Uwagi — który hotel wybrać')
            ->visible(fn (): bool => Schema::hasColumn('events', 'hotel_notes'))
            ->placeholder('Wpisz wskazówki dotyczące wyboru hotelu')
            ->helperText('Wskazówki przy wyborze hotelu. Widoczne w planie noclegów i we wszystkich oknach rezerwacji hotelowych.');
    }

    public static function dietInfo(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            Forms\Components\Placeholder::make('diet_from_participants')
                ->label('Diety z listy uczestników')
                ->content(function (?\App\Models\Event $record): \Illuminate\Support\HtmlString {
                    if (! $record) {
                        return new \Illuminate\Support\HtmlString('<span class="text-gray-500">—</span>');
                    }

                    $summary = app(\App\Services\EventDietSummaryService::class)->forEvent($record);
                    if ($summary['count'] === 0) {
                        return new \Illuminate\Support\HtmlString(
                            '<span class="text-gray-500">Brak wpisanych diet na kartach uczestników.</span>'
                        );
                    }

                    $items = collect($summary['lines'])
                        ->map(fn (string $line): string => '<li>'.e($line).'</li>')
                        ->implode('');

                    return new \Illuminate\Support\HtmlString(
                        '<ul class="list-disc pl-5 text-sm text-gray-800 dark:text-gray-200">'.$items.'</ul>'
                        .'<p class="mt-1 text-xs text-gray-500">Możesz skopiować / uzupełnić pole poniżej (decyzja biura dla pilota/hotelu).</p>'
                    );
                })
                ->columnSpanFull(),

            Forms\Components\Textarea::make('diet_info')
                ->label('Diety (decyzja biura)')
                ->placeholder('Wpisz dietę (decyzja biura)')
                ->helperText('Pole operacyjne dla pilota/hotelu. Uzupełnij ręcznie na podstawie listy powyżej lub własnej decyzji.')
                ->visible(fn (): bool => Schema::hasColumn('events', 'diet_info'))
                ->columnSpanFull()
                ->rows(3),
        ])->columnSpanFull();
    }
}
