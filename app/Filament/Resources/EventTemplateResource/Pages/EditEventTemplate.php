<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Resources\EventTemplateResource;
use App\Models\EventTemplate;
use App\Traits\CompressesImages;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\UnifiedPriceCalculator;
use App\Models\EventTemplatePricePerPerson;
use App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable;
use App\Filament\Resources\EventTemplateResource\Widgets\EventProgramKanbanWidget;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EditEventTemplate extends EditRecord
{
    use CompressesImages;

    protected static string $resource = EventTemplateResource::class;

    public array $hotel_days = [];

    protected static string $view = 'filament.resources.event-template-resource.pages.edit-event-template';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\Action::make('clone')
                ->label('Klonuj')
                ->icon('heroicon-o-document-duplicate')
                ->action(fn() => $this->cloneEventTemplate()),
            Actions\Action::make('edit-program')
                ->label('Edytuj program')
                ->icon('heroicon-o-bars-3')
                ->url(fn() => static::getResource()::getUrl('edit-program', ['record' => $this->record->id]))
                ->color('primary'),
            Actions\Action::make('transport')
                ->label('Transport i kalkulacja')
                ->icon('heroicon-o-truck')
                ->url(fn() => static::getResource()::getUrl('transport', ['record' => $this->record->id]))
                ->color('warning'),
        ];
    }

    protected function cloneEventTemplate()
    {
        $original = $this->record;

        // Załaduj wszystkie relacje
        $original->load([
            'tags',
            'programPoints',
            'dayInsurances.insurance',
            'hotelDays',
            'startingPlaceAvailabilities',
            'taxes',
            'pricesPerPerson',
            // 'qtyVariants', // usunięte - tabela nie ma event_template_id
            'transportTypes',
            'eventTypes',
            'eventPriceDescription',
            'programPointChildren'
        ]);

        $clone = EventTemplate::create([
            'name' => $original->name . ' (Kopia)',
            'subtitle' => $original->subtitle,
            'slug' => $original->slug . '-kopia-' . uniqid(),
            'duration_days' => $original->duration_days,
            'is_active' => $original->is_active,
            'featured_image' => $original->featured_image,
            'event_description' => $original->event_description,
            'gallery' => $original->gallery,
            'office_description' => $original->office_description,
            'notes' => $original->notes,
            'transfer_km' => $original->transfer_km,
            'program_km' => $original->program_km,
            'bus_id' => $original->bus_id,
            'markup_id' => $original->markup_id,
            'start_place_id' => $original->start_place_id,
            'end_place_id' => $original->end_place_id,
            'transport_notes' => $original->transport_notes,
            'seo_title' => $original->seo_title,
            'seo_description' => $original->seo_description,
            'seo_keywords' => $original->seo_keywords,
            // usunięte: transfer_km2, program_km2, seo_twitter_title, seo_twitter_description, seo_twitter_image, seo_schema
        ]);

        // Klonuj tagi
        $clone->tags()->sync($original->tags->pluck('id')->toArray());

        // Klonuj typy transportu
        $clone->transportTypes()->sync($original->transportTypes->pluck('id')->toArray());

        // Klonuj typy wydarzeń
        $clone->eventTypes()->sync($original->eventTypes->pluck('id')->toArray());

        // Klonuj opis ceny wydarzenia (pivot)
        if ($original->eventPriceDescription->isNotEmpty()) {
            $clone->eventPriceDescription()->sync($original->eventPriceDescription->pluck('id')->toArray());
        }

        // Wyłącz foreign keys przed klonowaniem punktów programu
        DB::unprepared('PRAGMA foreign_keys = OFF');

        // Klonuj punkty programu (pivot) - kopiuj wszystkie pola z pivotu
        foreach ($original->programPoints as $point) {
            $clone->programPoints()->attach($point->id, [
                'day' => $point->pivot->day,
                'order' => $point->pivot->order,
                'notes' => $point->pivot->notes,
                'include_in_program' => (bool)$point->pivot->include_in_program,
                'include_in_calculation' => (bool)$point->pivot->include_in_calculation,
                'active' => (bool)$point->pivot->active,
                'show_title_style' => $point->pivot->show_title_style,
                'show_description' => $point->pivot->show_description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Ponownie włącz foreign keys
        DB::unprepared('PRAGMA foreign_keys = ON');

        // Pomijamy klonowanie wariantów QTY – są globalne, nie zależą od szablonu
        // foreach ($original->qtyVariants as $qtyVariant) { ... }

        // Klonuj ceny za osobę
        // Najpierw usuń istniejące ceny, jeśli istnieją
        $clone->pricesPerPerson()->delete();

        foreach ($original->pricesPerPerson as $price) {
            $clone->pricesPerPerson()->create([
                'event_template_qty_id' => $price->event_template_qty_id,
                'currency_id' => $price->currency_id,
                'start_place_id' => $price->start_place_id,
                'price_per_person' => $price->price_per_person,
            ]);
        }

        // Klonuj ubezpieczenia dni
        foreach ($original->dayInsurances as $dayInsurance) {
            $clone->dayInsurances()->create([
                'day' => $dayInsurance->day,
                'insurance_id' => $dayInsurance->insurance_id,
            ]);
        }

        // Klonuj dni hotelowe - kopiuj strukturę pokojów z aktualnego stanu lub z bazy
        $nights = max(0, $clone->duration_days - 1);
        Log::info("Cloning hotel days: generating {$nights} nights for {$clone->duration_days} day trip");

        // Najpierw spróbuj użyć aktualnych danych z komponentu
        if (!empty($this->hotel_days)) {
            Log::info("Using hotel days from component state");
            foreach ($this->hotel_days as $dayIndex => $hotelDay) {
                if ($dayIndex < $nights) {
                    $clone->hotelDays()->create([
                        'day' => $hotelDay['day'],
                        'hotel_room_ids_qty' => $hotelDay['hotel_room_ids_qty'] ?? [],
                        'hotel_room_ids_gratis' => $hotelDay['hotel_room_ids_gratis'] ?? [],
                        'hotel_room_ids_staff' => $hotelDay['hotel_room_ids_staff'] ?? [],
                        'hotel_room_ids_driver' => $hotelDay['hotel_room_ids_driver'] ?? [],
                    ]);
                    Log::info("Copied hotel day {$hotelDay['day']} from component");
                }
            }

            // Jeśli brakuje dni, uzupełnij pustymi
            if (count($this->hotel_days) < $nights) {
                for ($i = count($this->hotel_days) + 1; $i <= $nights; $i++) {
                    $clone->hotelDays()->create([
                        'day' => $i,
                        'hotel_room_ids_qty' => [],
                        'hotel_room_ids_gratis' => [],
                        'hotel_room_ids_staff' => [],
                        'hotel_room_ids_driver' => [],
                    ]);
                    Log::info("Created empty hotel day {$i}");
                }
            }
        } else {
            // Jeśli nie ma danych w komponencie, spróbuj z bazy
            Log::info("Using hotel days from database");
            $originalHotelDays = $original->hotelDays()->orderBy('day')->get()->keyBy('day');

            for ($i = 1; $i <= $nights; $i++) {
                if ($originalHotelDays->has($i)) {
                    $originalDay = $originalHotelDays[$i];
                    $clone->hotelDays()->create([
                        'day' => $i,
                        'hotel_room_ids_qty' => $originalDay->hotel_room_ids_qty ?? [],
                        'hotel_room_ids_gratis' => $originalDay->hotel_room_ids_gratis ?? [],
                        'hotel_room_ids_staff' => $originalDay->hotel_room_ids_staff ?? [],
                        'hotel_room_ids_driver' => $originalDay->hotel_room_ids_driver ?? [],
                    ]);
                    Log::info("Copied hotel day {$i} from database");
                } else {
                    $clone->hotelDays()->create([
                        'day' => $i,
                        'hotel_room_ids_qty' => [],
                        'hotel_room_ids_gratis' => [],
                        'hotel_room_ids_staff' => [],
                        'hotel_room_ids_driver' => [],
                    ]);
                    Log::info("Created empty hotel day {$i}");
                }
            }
        }

        // Klonuj dostępność miejsc startowych
        foreach ($original->startingPlaceAvailabilities as $availability) {
            $clone->startingPlaceAvailabilities()->create([
                'start_place_id' => $availability->start_place_id,
                'end_place_id' => $availability->end_place_id,
                'available' => $availability->available,
                'note' => $availability->note,
            ]);
        }

        // Klonuj podatki
        $clone->taxes()->sync($original->taxes->pluck('id')->toArray());

        // Klonuj dzieci punktów programu (programPointChildren)

        foreach ($original->programPointChildren as $childRelation) {
            $clone->programPointChildren()->attach($childRelation->id, [
                'include_in_program' => $childRelation->pivot->include_in_program,
                'include_in_calculation' => $childRelation->pivot->include_in_calculation,
                'active' => $childRelation->pivot->active,
                'show_title_style' => $childRelation->pivot->show_title_style ?? null,
                'show_description' => $childRelation->pivot->show_description ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Automatyczne przeliczenie cen po klonowaniu
        try {
            (new UnifiedPriceCalculator())->recalculateForTemplate($clone);
        } catch (\Throwable $e) {
            Log::error('Błąd podczas automatycznego przeliczania cen po klonowaniu: ' . $e->getMessage());
        }

        // Dodaj powiadomienie o udanym klonowaniu
        \Filament\Notifications\Notification::make()
            ->title('Szablon został pomyślnie sklonowany!')
            ->success()
            ->send();

        // Przekieruj do edycji nowego klona
        return redirect(static::getResource()::getUrl('edit', ['record' => $clone->id]));
    }

    public function mount($record): void
    {
        parent::mount($record);

        Log::info("Mount called for record {$this->record->id}, hotelDays count: " . $this->record->hotelDays->count());

        // Najpierw spróbuj załadować z bazy
        if ($this->record->hotelDays->count() > 0) {
            Log::info("Loading hotel days from database for record {$this->record->id}");
            $this->loadHotelDaysFromDatabase();
        } else {
            // Jeśli nie ma w bazie, wygeneruj na podstawie duration_days
            Log::info("Generating hotel days from duration_days for record {$this->record->id}");
            $this->refreshHotelDays();
        }

        Log::info("Mount finished, hotel_days count: " . count($this->hotel_days));
    }

    private function loadHotelDaysFromDatabase(): void
    {
        // Ładuj dane z bazy, posortowane po dniu
        $this->hotel_days = $this->record->hotelDays()
            ->orderBy('day')
            ->get()
            ->map(function ($day) {
                return [
                    'day' => $day->day,
                    'hotel_room_ids_qty' => $day->hotel_room_ids_qty ?? [],
                    'hotel_room_ids_gratis' => $day->hotel_room_ids_gratis ?? [],
                    'hotel_room_ids_staff' => $day->hotel_room_ids_staff ?? [],
                    'hotel_room_ids_driver' => $day->hotel_room_ids_driver ?? [],
                ];
            })->toArray();
    }

    public function addDay()
    {
        $this->hotel_days[] = [
            'day' => count($this->hotel_days) + 1,
            'hotel_room_ids_qty' => [],
            'hotel_room_ids_gratis' => [],
            'hotel_room_ids_staff' => [],
            'hotel_room_ids_driver' => [],
        ];
    }

    public function addRoom($role, $dayIndex)
    {
        if (!isset($this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"])) {
            $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"] = [];
        }
        $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"][] = null;
    }

    public function copyToNextDay($dayIndex)
    {
        if (!isset($this->hotel_days[$dayIndex + 1])) {
            return;
        }
        foreach (['qty', 'gratis', 'staff', 'driver'] as $role) {
            $this->hotel_days[$dayIndex + 1]["hotel_room_ids_{$role}"] =
                $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"] ?? [];
        }
    }

    public function removeRoomFromDay($dayIndex, $role, $roomId)
    {
        if (!isset($this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"])) {
            return;
        }

        $rooms = $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"];
        $key = array_search($roomId, $rooms);

        if ($key !== false) {
            unset($rooms[$key]);
            $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"] = array_values($rooms);
        }
    }

    // Debug method
    public function debugHotelDays()
    {
        Log::info('Hotel days:', $this->hotel_days);
        dd($this->hotel_days);
    }

    public function forceRefreshHotelDays()
    {
        $this->refreshHotelDays();
        $this->dispatch('$refresh');
    }

    // Metoda do manualnego zapisania (przycisk w UI)
    public function saveHotelDays()
    {
        try {
            $this->saveHotelDaysToDatabase();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Noclegi zostały zapisane!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Błąd podczas zapisywania: ' . $e->getMessage()
            ]);
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $response = parent::mutateFormDataBeforeSave($data);
        // Przelicz ceny po zapisaniu zmian
        (new UnifiedPriceCalculator())->recalculateForTemplate($this->record);
        // Zapisz hotel_days do bazy (np. przez relację)
        // $data['hotel_days'] = $this->hotel_days;
        // Implementacja zależna od Twojego modelu
        return $response;
    }

    protected function afterSave(): void
    {
        // Debug: Sprawdź co jest w $this->data
        Log::info('EditEventTemplate afterSave - data:', $this->data);
        Log::info('EditEventTemplate afterSave - event_price_description_id:', [$this->data['event_price_description_id'] ?? 'NOT SET']);

        // Nie wykonuj dla nowo utworzonych rekordów podczas klonowania
        // oraz nie wykonuj jeśli to jest przekierowanie po klonowaniu
        if (!$this->record->wasRecentlyCreated && !request()->has('clone')) {
            $this->saveHotelDaysToDatabase();
        }

        // Zapisz powiązanie z event_price_description do pivot
        $priceDescriptionId = $this->data['event_price_description_id'] ?? null;
        Log::info('EditEventTemplate afterSave - priceDescriptionId value:', [$priceDescriptionId]);

        if ($priceDescriptionId) {
            Log::info('EditEventTemplate afterSave - syncing price description:', [$priceDescriptionId]);
            $this->record->eventPriceDescription()->sync([$priceDescriptionId]);
            Log::info('EditEventTemplate afterSave - sync completed');
        } else {
            Log::info('EditEventTemplate afterSave - clearing price descriptions');
            $this->record->eventPriceDescription()->sync([]);
        }

        // Automatycznie przelicz ceny po zapisaniu
        try {
            (new UnifiedPriceCalculator())->recalculateForTemplate($this->record);
        } catch (\Exception $e) {
            Log::error('Error recalculating prices after save: ' . $e->getMessage());
        }
    }

    public function updatedData($value, $key)
    {
        // Reaguj na zmianę duration_days
        if ($key === 'duration_days') {
            Log::info("Duration days changed to: {$value}");
            $this->refreshHotelDays();
        }
    }

    private function saveHotelDaysToDatabase()
    {
        try {
            Log::info("Saving hotel days to database", $this->hotel_days);

            // Optymalizowane zapisywanie - aktualizuj tylko zmienione
            $existingDays = $this->record->hotelDays()->get()->keyBy('day');

            foreach ($this->hotel_days as $dayData) {
                $day = $dayData['day'];

                $data = [
                    'hotel_room_ids_qty' => $dayData['hotel_room_ids_qty'] ?? [],
                    'hotel_room_ids_gratis' => $dayData['hotel_room_ids_gratis'] ?? [],
                    'hotel_room_ids_staff' => $dayData['hotel_room_ids_staff'] ?? [],
                    'hotel_room_ids_driver' => $dayData['hotel_room_ids_driver'] ?? [],
                ];

                if ($existingDays->has($day)) {
                    // Aktualizuj istniejący
                    $existingDays[$day]->update($data);
                    Log::info("Updated day {$day}");
                } else {
                    // Utwórz nowy
                    $this->record->hotelDays()->create(array_merge($data, ['day' => $day]));
                    Log::info("Created day {$day}");
                }
            }

            // Usuń dni, które już nie istnieją w $this->hotel_days
            $currentDays = collect($this->hotel_days)->pluck('day');
            $toDelete = $existingDays->whereNotIn('day', $currentDays);
            $toDelete->each(function ($day) {
                Log::info("Deleting day {$day->day}");
                $day->delete();
            });

            Log::info('Hotel days saved successfully');
        } catch (\Exception $e) {
            Log::error('Error saving hotel days: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updated($propertyName)
    {
        // Alternatywny hook dla reactywności - sprawdza czy zmieniono duration_days
        if (str_contains($propertyName, 'duration_days')) {
            Log::info("Property updated: {$propertyName}");
            $this->refreshHotelDays();
        }
    }

    // Hook dla formularza Filament
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        // Załaduj obecną wartość event_price_description_id z relacji pivot
        $eventPriceDescription = $this->record->eventPriceDescription()->first();
        if ($eventPriceDescription) {
            $data['event_price_description_id'] = $eventPriceDescription->id;
            Log::info('EditEventTemplate mutateFormDataBeforeFill - loaded event_price_description_id:', [$eventPriceDescription->id]);
        } else {
            $data['event_price_description_id'] = null;
            Log::info('EditEventTemplate mutateFormDataBeforeFill - no event_price_description found');
        }

        // Po załadowaniu danych z bazy nie odświeżaj automatycznie
        // $this->dispatch('$refresh');
        return $data;
    }

    public function refreshHotelDays()
    {
        // Sprawdź kilka źródeł duration_days
        $days = $this->data['duration_days'] ?? $this->record->duration_days ?? 1;
        $nights = max(0, $days - 1);

        // Debug
        Log::info("RefreshHotelDays: days={$days}, nights={$nights}");

        // Zachowaj istniejące dane dla dni, które już istnieją
        $existingData = $this->hotel_days;
        $this->hotel_days = [];

        for ($i = 1; $i <= $nights; $i++) {
            // Jeśli dzień już istniał, zachowaj jego dane
            if (isset($existingData[$i - 1])) {
                $this->hotel_days[] = $existingData[$i - 1];
            } else {
                // Utwórz nowy dzień z pustymi danymi
                $this->hotel_days[] = [
                    'day' => $i,
                    'hotel_room_ids_qty' => [],
                    'hotel_room_ids_gratis' => [],
                    'hotel_room_ids_staff' => [],
                    'hotel_room_ids_driver' => [],
                ];
            }
        }

        Log::info("RefreshHotelDays result: " . count($this->hotel_days) . " nights");
    }
}
