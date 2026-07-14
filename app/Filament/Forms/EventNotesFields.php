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
            ->placeholder('Np. preferowany hotel, lokalizacja, kontakt do rezerwacji.')
            ->helperText('Wskazówki przy wyborze hotelu. Widoczne w planie noclegów i we wszystkich oknach rezerwacji hotelowych.');
    }

    public static function dietInfo(): Forms\Components\Textarea
    {
        return Forms\Components\Textarea::make('diet_info')
            ->label('Diety')
            ->placeholder('1 x dieta bezglutenowa, 2 x dieta wegetariańska')
            ->helperText('Informacja operacyjna o specjalnych dietach uczestników; widoczna dla pilota.')
            ->visible(fn (): bool => Schema::hasColumn('events', 'diet_info'))
            ->columnSpanFull()
            ->rows(3);
    }

    public static function wwwExtraInfo(): TiptapEditor
    {
        return self::editor('www_extra_info')
            ->label('Informacje WWW (per impreza)')
            ->visible(fn (): bool => Schema::hasColumn('events', 'www_extra_info'))
            ->helperText('Treść wyświetlana na publicznej stronie oferty — nadpisuje domyślny blok informacji.');
    }
}
