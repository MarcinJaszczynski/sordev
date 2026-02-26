<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Resources\EventTemplateResource;
use App\Models\EventTemplate;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Support\Collection;

class ListEventTemplates extends ListRecords
{
    protected static string $resource = EventTemplateResource::class;

    public function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\EventTemplateResource\Widgets\PriceRecalcProgressWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    protected static string $defaultPaginationPageOption = '25';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Action::make('removeDuplicates')
                ->label('Usuń duplikaty cen')
                ->icon('heroicon-o-scissors')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    // Globalna deduplikacja
                    $duplicateGroups = \App\Models\EventTemplatePricePerPerson::select('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
                        ->selectRaw('COUNT(*) as count')
                        ->groupBy('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
                        ->having('count', '>', 1)
                        ->get();
                    $removed = 0;
                    foreach ($duplicateGroups as $group) {
                        $records = \App\Models\EventTemplatePricePerPerson::where([
                            'event_template_id' => $group->event_template_id,
                            'event_template_qty_id' => $group->event_template_qty_id,
                            'currency_id' => $group->currency_id,
                            'start_place_id' => $group->start_place_id,
                        ])->orderByDesc('id')->get();
                        for ($i = 1; $i < $records->count(); $i++) { $records[$i]->delete(); $removed++; }
                    }
                    \Filament\Notifications\Notification::make()
                        ->title('Deduplikacja zakończona')
                        ->body('Usunięto rekordów: ' . $removed)
                        ->success()
                        ->send();
                }),
            Actions\Action::make('recalculateAllPrices')
                ->label('Przelicz ceny dla wszystkich')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    // Uruchom job w tle, aby nie blokować UI (po odpowiedzi)
                    $userId = (int) (Auth::id() ?? 0);
                    // zainicjuj stan postępu, żeby widget od razu się pojawił
                    \App\Services\PriceRecalcProgress::start($userId, \App\Models\EventTemplate::count());
                    \App\Jobs\RecalculateAllEventTemplatePricesJob::dispatch($userId)->afterResponse();

                    \Filament\Notifications\Notification::make()
                        ->title('Przeliczanie cen uruchomione')
                        ->body('Zadanie działa w tle. Po zakończeniu dostaniesz powiadomienie z podsumowaniem.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            TableAction::make('edit')
                ->url(fn($record) => static::getResource()::getUrl('edit', ['record' => $record->id])),
            TableAction::make('delete'),
            TableAction::make('clone')
                ->label('Klonuj')
                ->icon('heroicon-o-document-duplicate')
                ->action(function ($record) {
                    // Załaduj wszystkie relacje
                    $record->load([
                        'tags',
                        'programPoints',
                        'dayInsurances.insurance',
                        'hotelDays',
                        'startingPlaceAvailabilities',
                        'taxes',
                        'pricesPerPerson' // zamiast qtyVariants
                    ]);

                    $clone = EventTemplate::create([
                        'name' => $record->name . ' (copy)',
                        'subtitle' => $record->subtitle,
                        'slug' => $record->slug . '-copy-' . uniqid(),
                        'duration_days' => $record->duration_days,
                        'is_active' => $record->is_active,
                        'featured_image' => $record->featured_image,
                        'event_description' => $record->event_description,
                        'gallery' => $record->gallery,
                        'office_description' => $record->office_description,
                        'notes' => $record->notes,
                        'transfer_km' => $record->transfer_km,
                        'program_km' => $record->program_km,
                        'bus_id' => $record->bus_id,
                        'markup_id' => $record->markup_id,
                        'start_place_id' => $record->start_place_id,
                        'end_place_id' => $record->end_place_id,
                        'transport_notes' => $record->transport_notes,
                        'seo_title' => $record->seo_title,
                        'seo_description' => $record->seo_description,
                        'seo_keywords' => $record->seo_keywords,
                    ]);

                    // Klonuj tagi
                    $clone->tags()->sync($record->tags->pluck('id')->toArray());

                    // Klonuj punkty programu (pivot)
                    foreach ($record->programPoints as $point) {
                        $clone->programPoints()->attach($point->id, [
                            'day' => $point->pivot->day,
                            'order' => $point->pivot->order,
                            'notes' => $point->pivot->notes,
                            'include_in_program' => $point->pivot->include_in_program,
                            'include_in_calculation' => $point->pivot->include_in_calculation,
                            'active' => $point->pivot->active,
                        ]);
                    }

                    // Klonuj ceny za osobę (zamiast wariantów QTY)
                    // Najpierw usuń istniejące ceny, jeśli istnieją
                    $clone->pricesPerPerson()->delete();

                    foreach ($record->pricesPerPerson as $price) {
                        $clone->pricesPerPerson()->create([
                            'event_template_qty_id' => $price->event_template_qty_id,
                            'currency_id' => $price->currency_id,
                            'start_place_id' => $price->start_place_id,
                            'price_per_person' => $price->price_per_person,
                        ]);
                    }

                    // Klonuj ubezpieczenia dni
                    foreach ($record->dayInsurances as $dayInsurance) {
                        $clone->dayInsurances()->create([
                            'day' => $dayInsurance->day,
                            'insurance_id' => $dayInsurance->insurance_id,
                        ]);
                    }

                    // Klonuj dni hotelowe - jeśli nie ma w bazie, generuj na podstawie duration_days
                    if ($record->hotelDays->count() > 0) {
                        // Kopiuj z bazy
                        foreach ($record->hotelDays as $hotelDay) {
                            $clone->hotelDays()->create([
                                'day' => $hotelDay->day,
                                'hotel_room_ids_qty' => $hotelDay->hotel_room_ids_qty ?? [],
                                'hotel_room_ids_gratis' => $hotelDay->hotel_room_ids_gratis ?? [],
                                'hotel_room_ids_staff' => $hotelDay->hotel_room_ids_staff ?? [],
                                'hotel_room_ids_driver' => $hotelDay->hotel_room_ids_driver ?? [],
                            ]);
                        }
                    } else {
                        // Generuj na podstawie duration_days
                        $nights = max(0, $record->duration_days - 1);
                        for ($i = 1; $i <= $nights; $i++) {
                            $clone->hotelDays()->create([
                                'day' => $i,
                                'hotel_room_ids_qty' => [],
                                'hotel_room_ids_gratis' => [],
                                'hotel_room_ids_staff' => [],
                                'hotel_room_ids_driver' => [],
                            ]);
                        }
                    }

                    // Klonuj dostępność miejsc startowych
                    foreach ($record->startingPlaceAvailabilities as $availability) {
                        $clone->startingPlaceAvailabilities()->create([
                            'start_place_id' => $availability->start_place_id,
                            'end_place_id' => $availability->end_place_id,
                            'available' => $availability->available,
                            'note' => $availability->note,
                        ]);
                    }

                    // Klonuj podatki
                    $clone->taxes()->sync($record->taxes->pluck('id')->toArray());

                    // Dodaj powiadomienie o udanym klonowaniu
                    \Filament\Notifications\Notification::make()
                        ->title('Szablon został pomyślnie sklonowany!')
                        ->success()
                        ->send();

                    // Przekieruj do edycji nowego klona
                    return redirect(static::getResource()::getUrl('edit', ['record' => $clone->id]));
                }),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('recalculatePricesSelected')
                    ->label('Przelicz ceny (zaznaczone)')
                    ->icon('heroicon-o-calculator')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $ids = $records->pluck('id')->map(fn($id) => (int) $id)->values()->all();
                        if (count($ids) === 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Nie wybrano żadnych szablonów')
                                ->warning()
                                ->send();
                            return;
                        }

                        $userId = (int) (Auth::id() ?? 0);
                        // Zainicjuj progress dla użytkownika
                        \App\Services\PriceRecalcProgress::start($userId, count($ids));
                        if ($userId <= 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Brak użytkownika')
                                ->body('Nie można zlecić przeliczenia bez zalogowanego użytkownika.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $chunkSize = 250;
                        $chunks = array_chunk($ids, $chunkSize);
                        foreach ($chunks as $chunk) {
                            \App\Jobs\RecalculateSelectedEventTemplatePricesJob::dispatch($chunk, $userId)->afterResponse();
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Przeliczanie cen zlecone')
                            ->body('Szablony: ' . count($ids) . ', paczki: ' . count($chunks) . '. Zadania wykonają się w tle.')
                            ->success()
                            ->send();
                    }),
                BulkAction::make('forceRecalculatePricesSelected')
                    ->label('WYMUSZ przeliczenie (zaznaczone)')
                    ->icon('heroicon-o-refresh')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $ids = $records->pluck('id')->map(fn($id) => (int) $id)->values()->all();
                        if (count($ids) === 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Nie wybrano żadnych szablonów')
                                ->warning()
                                ->send();
                            return;
                        }

                        $userId = (int) (Auth::id() ?? 0);
                        if ($userId <= 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Brak użytkownika')
                                ->body('Nie można zlecić przeliczenia bez zalogowanego użytkownika.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $chunkSize = 100;
                        $chunks = array_chunk($ids, $chunkSize);
                        // Zainicjuj progress
                        \App\Services\PriceRecalcProgress::start($userId, count($ids));
                        foreach ($chunks as $chunk) {
                            \App\Jobs\RecalculateSelectedEventTemplatePricesJob::dispatch($chunk, $userId, true)->afterResponse();
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Wymuszone przeliczanie zlecone')
                            ->body('Szablony: ' . count($ids) . ', paczki: ' . count($chunks) . '. Zadania wykonają się w tle.')
                            ->success()
                            ->send();
                    }),
            ]),
        ];
    }
}
