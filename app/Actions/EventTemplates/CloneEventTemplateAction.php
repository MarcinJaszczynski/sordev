<?php

declare(strict_types=1);

namespace App\Actions\EventTemplates;

use App\Models\EventTemplate;
use App\Services\UnifiedPriceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Klonuje szablon imprezy wraz z relacjami (bez wyłączania FK / PRAGMA).
 * Attach pivotów jest bezpieczny na MySQL i SQLite: nowy template_id + istniejące point_id.
 */
final class CloneEventTemplateAction
{
    /**
     * @param  array<int, array<string, mixed>>|null  $hotelDaysFromForm  opcjonalny stan z formularza edycji
     */
    public function __invoke(EventTemplate $original, ?array $hotelDaysFromForm = null): EventTemplate
    {
        $original->load([
            'tags',
            'programPoints',
            'dayInsurances.insurance',
            'hotelDays',
            'startingPlaceAvailabilities',
            'taxes',
            'pricesPerPerson',
            'transportTypes',
            'eventTypes',
            'eventPriceDescription',
            'programPointChildren',
        ]);

        return DB::transaction(function () use ($original, $hotelDaysFromForm): EventTemplate {
            $clone = EventTemplate::query()->create([
                'name' => $original->name.' (Kopia)',
                'subtitle' => $original->subtitle,
                'slug' => $original->slug.'-kopia-'.uniqid(),
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
            ]);

            $clone->tags()->sync($original->tags->pluck('id')->all());
            $clone->transportTypes()->sync($original->transportTypes->pluck('id')->all());
            $clone->eventTypes()->sync($original->eventTypes->pluck('id')->all());

            if ($original->eventPriceDescription->isNotEmpty()) {
                $clone->eventPriceDescription()->sync($original->eventPriceDescription->pluck('id')->all());
            }

            $this->cloneProgramPoints($original, $clone);
            $this->clonePricesPerPerson($original, $clone);
            $this->cloneDayInsurances($original, $clone);
            $this->cloneHotelDays($original, $clone, $hotelDaysFromForm);
            $this->cloneStartingPlaceAvailabilities($original, $clone);
            $clone->taxes()->sync($original->taxes->pluck('id')->all());
            $this->cloneProgramPointChildren($original, $clone);

            try {
                (new UnifiedPriceCalculator)->recalculateForTemplate($clone);
            } catch (\Throwable $e) {
                Log::error('Błąd przeliczania cen po klonowaniu szablonu: '.$e->getMessage(), [
                    'template_id' => $clone->id,
                ]);
            }

            return $clone->fresh() ?? $clone;
        });
    }

    private function cloneProgramPoints(EventTemplate $original, EventTemplate $clone): void
    {
        $rows = [];
        $now = now();

        foreach ($original->programPoints as $point) {
            $rows[$point->id] = [
                'day' => $point->pivot->day,
                'order' => $point->pivot->order,
                'notes' => $point->pivot->notes,
                'include_in_program' => (bool) $point->pivot->include_in_program,
                'include_in_calculation' => (bool) $point->pivot->include_in_calculation,
                'active' => (bool) $point->pivot->active,
                'show_title_style' => $point->pivot->show_title_style,
                'show_description' => $point->pivot->show_description,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            $clone->programPoints()->attach($rows);
        }
    }

    private function cloneProgramPointChildren(EventTemplate $original, EventTemplate $clone): void
    {
        $rows = [];
        $now = now();

        foreach ($original->programPointChildren as $childRelation) {
            $rows[$childRelation->id] = [
                'include_in_program' => $childRelation->pivot->include_in_program,
                'include_in_calculation' => $childRelation->pivot->include_in_calculation,
                'active' => $childRelation->pivot->active,
                'show_title_style' => $childRelation->pivot->show_title_style ?? null,
                'show_description' => $childRelation->pivot->show_description ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            $clone->programPointChildren()->attach($rows);
        }
    }

    private function clonePricesPerPerson(EventTemplate $original, EventTemplate $clone): void
    {
        $clone->pricesPerPerson()->delete();

        foreach ($original->pricesPerPerson as $price) {
            $clone->pricesPerPerson()->create([
                'event_template_qty_id' => $price->event_template_qty_id,
                'currency_id' => $price->currency_id,
                'start_place_id' => $price->start_place_id,
                'price_per_person' => $price->price_per_person,
            ]);
        }
    }

    private function cloneDayInsurances(EventTemplate $original, EventTemplate $clone): void
    {
        foreach ($original->dayInsurances as $dayInsurance) {
            $clone->dayInsurances()->create([
                'day' => $dayInsurance->day,
                'insurance_id' => $dayInsurance->insurance_id,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $hotelDaysFromForm
     */
    private function cloneHotelDays(EventTemplate $original, EventTemplate $clone, ?array $hotelDaysFromForm): void
    {
        $nights = max(0, (int) $clone->duration_days - 1);

        if (is_array($hotelDaysFromForm) && $hotelDaysFromForm !== []) {
            foreach ($hotelDaysFromForm as $dayIndex => $hotelDay) {
                if ($dayIndex >= $nights) {
                    continue;
                }

                $clone->hotelDays()->create([
                    'day' => $hotelDay['day'] ?? ($dayIndex + 1),
                    'hotel_room_ids_qty' => $hotelDay['hotel_room_ids_qty'] ?? [],
                    'hotel_room_ids_gratis' => $hotelDay['hotel_room_ids_gratis'] ?? [],
                    'hotel_room_ids_staff' => $hotelDay['hotel_room_ids_staff'] ?? [],
                    'hotel_room_ids_driver' => $hotelDay['hotel_room_ids_driver'] ?? [],
                ]);
            }

            for ($i = count($hotelDaysFromForm) + 1; $i <= $nights; $i++) {
                $clone->hotelDays()->create([
                    'day' => $i,
                    'hotel_room_ids_qty' => [],
                    'hotel_room_ids_gratis' => [],
                    'hotel_room_ids_staff' => [],
                    'hotel_room_ids_driver' => [],
                ]);
            }

            return;
        }

        $originalHotelDays = $original->hotelDays->keyBy('day');

        for ($i = 1; $i <= $nights; $i++) {
            $originalDay = $originalHotelDays->get($i);
            $clone->hotelDays()->create([
                'day' => $i,
                'hotel_room_ids_qty' => $originalDay->hotel_room_ids_qty ?? [],
                'hotel_room_ids_gratis' => $originalDay->hotel_room_ids_gratis ?? [],
                'hotel_room_ids_staff' => $originalDay->hotel_room_ids_staff ?? [],
                'hotel_room_ids_driver' => $originalDay->hotel_room_ids_driver ?? [],
            ]);
        }
    }

    private function cloneStartingPlaceAvailabilities(EventTemplate $original, EventTemplate $clone): void
    {
        foreach ($original->startingPlaceAvailabilities as $availability) {
            $clone->startingPlaceAvailabilities()->create([
                'start_place_id' => $availability->start_place_id,
                'end_place_id' => $availability->end_place_id,
                'available' => $availability->available,
                'note' => $availability->note,
            ]);
        }
    }
}
