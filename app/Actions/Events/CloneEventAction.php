<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventHotelRoomLine;
use App\Models\EventPricePerPerson;
use App\Models\EventProgramPoint;
use App\Models\EventQty;
use App\Models\EventSettlement;
use App\Models\EventSnapshot;
use App\Models\EventStartingPlaceAvailability;
use App\Services\EventHotelPlanService;
use App\Services\EventOrderingPartyService;
use App\Services\EventPriceCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Klonuje imprezę: ten sam szkielet (program, hotel, daty, zamawiający), nowy kod.
 * Bez uczestników, faktur, rozliczeń, umów, zadań i historii transakcyjnej.
 */
final class CloneEventAction
{
    /**
     * Atrybuty Event niekopiowane (tożsamość, finanse, flaga pilota, dokumenty ubezpieczeń).
     *
     * @var list<string>
     */
    private const EVENT_EXCEPT = [
        'code',
        'total_cost',
        'status',
        'created_by',
        'insurance_policy_number',
        'insurance_document_path',
        'insurance_insured_list_path',
        'insurance_amount',
        'insurance_payment_status',
        'insurance_status',
        'insurance_paid_at',
        'pilot_funds_paid',
        'pilot_funds_paid_at',
        'pilot_funds_paid_by',
        'pilot_advance_planned_amount',
        'pilot_advance_planned_at',
        'pilot_advance_planned_by',
        'pilot_advance_paid_amount',
        'pilot_advance_paid_currency_id',
        'pilot_advance_paid_comment',
        'shared_with_pilot',
        'shared_with_pilot_at',
        'shared_with_pilot_by',
        'pilot_trip_email_sent_at',
        'check_in_status',
        'check_in_notes',
        'driver_pickup_info_sent_at',
        'driver_pickup_info_sent_by',
    ];

    /**
     * Na punkcie programu nie kopiujemy kwot rozliczeniowych ani rezerwacji.
     * planned_price zostaje — klon ma być szkieletem oferty 1:1.
     *
     * @var list<string>
     */
    private const PROGRAM_POINT_EXCEPT = [
        'paid_price',
        'reservation_id',
    ];

    public function __invoke(Event $original): Event
    {
        // Świeży model bez withCount / withSum z tabeli Filament (agreements_count itd.),
        // inaczej replicate() próbuje wstawić je jako kolumny events.*.
        $original = Event::query()
            ->with([
                'programPoints' => fn ($q) => $q->orderBy('day')->orderBy('order'),
                'hotelStays.allRoomLines',
                'qtyVariants',
                'dayInsurances',
                'startingPlaceAvailabilities',
                'paymentInstallmentTemplates',
                'orderingContractors',
            ])
            ->findOrFail($original->id);

        return DB::transaction(function () use ($original): Event {
            $clone = $this->cloneEventRecord($original);
            $this->cloneOrderingParties($original, $clone);
            $pointIdMap = $this->cloneProgramPoints($original, $clone);
            $this->cloneQtyVariants($original, $clone);
            $this->cloneHotelStays($original, $clone, $pointIdMap);
            $this->cloneDayInsurances($original, $clone);
            $this->cloneStartingPlaceAvailabilities($original, $clone);
            $this->clonePaymentInstallmentTemplates($original, $clone);

            try {
                EventSettlement::findOrCreateActiveForEvent($clone);
                $clone->refreshActiveSettlementCosts();
            } catch (\Throwable $e) {
                Log::warning('CloneEventAction: settlement bootstrap failed', [
                    'event_id' => $clone->id,
                    'source_event_id' => $original->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                EventPricePerPerson::query()->where('event_id', $clone->id)->delete();
                (new EventPriceCalculator)->calculateForEvent($clone->fresh([
                    'bus',
                    'markup',
                    'eventTemplate.taxes',
                    'eventTemplate.markup',
                    'qtyVariants',
                    'programPoints.currency',
                    'dayInsurances.insurance',
                    'hotelStays.roomLines.currency',
                ]));
            } catch (\Throwable $e) {
                Log::warning('CloneEventAction: EventPriceCalculator failed', [
                    'event_id' => $clone->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $clone->calculateTotalCost();
            } catch (\Throwable $e) {
                Log::warning('CloneEventAction: calculateTotalCost failed', [
                    'event_id' => $clone->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                EventSnapshot::createSnapshot(
                    $clone,
                    'original',
                    'Pierwotny stan imprezy',
                    'Automatycznie utworzony snapshot po sklonowaniu imprezy #'.$original->id
                        .($original->code ? ' ('.$original->code.')' : ''),
                );
            } catch (\Throwable $e) {
                Log::warning('CloneEventAction: snapshot failed', [
                    'event_id' => $clone->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $clone->fresh() ?? $clone;
        });
    }

    private function cloneEventRecord(Event $original): Event
    {
        $clone = $original->replicate(self::EVENT_EXCEPT);
        $clone->name = rtrim((string) $original->name).' (Kopia)';
        $clone->code = null;
        $clone->status = Event::STATUS_INQUIRY;
        $clone->total_cost = 0;
        $clone->created_by = Auth::id() ?: $original->created_by;

        if (Schema::hasColumn('events', 'shared_with_pilot')) {
            $clone->shared_with_pilot = false;
            $clone->shared_with_pilot_at = null;
            $clone->shared_with_pilot_by = null;
        }

        if (Schema::hasColumn('events', 'pilot_funds_paid')) {
            $clone->pilot_funds_paid = false;
            $clone->pilot_funds_paid_at = null;
            $clone->pilot_funds_paid_by = null;
        }

        if (Schema::hasColumn('events', 'pilot_advance_paid_amount')) {
            $clone->pilot_advance_paid_amount = null;
            $clone->pilot_advance_paid_currency_id = null;
            $clone->pilot_advance_paid_comment = null;
        }

        if (Schema::hasColumn('events', 'pilot_advance_planned_amount')) {
            $clone->pilot_advance_planned_amount = null;
            $clone->pilot_advance_planned_at = null;
            $clone->pilot_advance_planned_by = null;
        }

        if (Schema::hasColumn('events', 'pilot_trip_email_sent_at')) {
            $clone->pilot_trip_email_sent_at = null;
        }

        if (Schema::hasColumn('events', 'driver_pickup_info_sent_at')) {
            $clone->driver_pickup_info_sent_at = null;
            $clone->driver_pickup_info_sent_by = null;
        }

        if (Schema::hasColumn('events', 'check_in_status')) {
            $clone->check_in_status = 'pending';
            $clone->check_in_notes = null;
        }

        $clone->save();

        return $clone;
    }

    private function cloneOrderingParties(Event $original, Event $clone): void
    {
        if (! Schema::hasTable('event_contractor')) {
            return;
        }

        $parties = app(EventOrderingPartyService::class)->partiesToFormState($original);
        if ($parties === []) {
            return;
        }

        $clone->syncOrderingParties($parties);
    }

    /**
     * @return array<int, int> old program point id → new id
     */
    private function cloneProgramPoints(Event $original, Event $clone): array
    {
        /** @var array<int, int> $map */
        $map = [];

        $remaining = $original->programPoints
            ->sortBy([
                ['day', 'asc'],
                ['order', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->keyBy('id');

        EventProgramPoint::runWithoutSideEffects(function () use ($remaining, $clone, &$map): void {
            $guard = 0;
            while ($remaining->isNotEmpty() && $guard < 10_000) {
                $guard++;
                $progress = false;

                foreach ($remaining as $id => $source) {
                    $parentId = $source->parent_id !== null ? (int) $source->parent_id : null;
                    if ($parentId !== null && ! isset($map[$parentId])) {
                        continue;
                    }

                    $point = $source->replicate(self::PROGRAM_POINT_EXCEPT);
                    $point->event_id = $clone->id;
                    $point->parent_id = $parentId !== null ? $map[$parentId] : null;
                    $point->paid_price = null;
                    $point->reservation_id = null;
                    $point->save();

                    $map[(int) $source->id] = (int) $point->id;
                    $remaining->forget($id);
                    $progress = true;
                }

                if (! $progress) {
                    Log::warning('CloneEventAction: unresolved program point parents', [
                        'clone_event_id' => $clone->id,
                        'remaining_ids' => $remaining->keys()->all(),
                    ]);
                    break;
                }
            }
        });

        return $map;
    }

    private function cloneQtyVariants(Event $original, Event $clone): void
    {
        if (! Schema::hasTable('event_qties')) {
            return;
        }

        foreach ($original->qtyVariants as $qty) {
            EventQty::query()->create([
                'event_id' => $clone->id,
                'qty' => $qty->qty,
                'gratis' => $qty->gratis,
                'staff' => $qty->staff,
                'driver' => $qty->driver,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $pointIdMap
     */
    private function cloneHotelStays(Event $original, Event $clone, array $pointIdMap): void
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return;
        }

        $hotelPlan = app(EventHotelPlanService::class);

        foreach ($original->hotelStays as $stay) {
            $newStay = $stay->replicate(['reservation_id']);
            $newStay->event_id = $clone->id;
            $newStay->reservation_id = null;

            $oldPointId = $stay->event_program_point_id ? (int) $stay->event_program_point_id : null;
            $newStay->event_program_point_id = ($oldPointId !== null && isset($pointIdMap[$oldPointId]))
                ? $pointIdMap[$oldPointId]
                : null;

            $newStay->save();

            if (! Schema::hasTable('event_hotel_room_lines')) {
                continue;
            }

            $lines = Schema::hasColumn('event_hotel_room_lines', 'price_layer')
                ? $stay->allRoomLines->sortBy([['price_layer', 'asc'], ['order', 'asc']])
                : $stay->allRoomLines->sortBy('order');

            foreach ($lines as $line) {
                /** @var EventHotelRoomLine $line */
                $newLine = $line->replicate();
                $newLine->event_hotel_stay_id = $newStay->id;
                $newLine->save();

                // Struktura jednostek pokoi bez obsady (uczestników nie klonujemy).
                try {
                    $hotelPlan->syncRoomUnits($newLine);
                } catch (\Throwable $e) {
                    // ignore when units table unavailable
                }
            }
        }
    }

    private function cloneDayInsurances(Event $original, Event $clone): void
    {
        if (! Schema::hasTable('event_day_insurance')) {
            return;
        }

        foreach ($original->dayInsurances as $dayInsurance) {
            $attributes = [
                'event_id' => $clone->id,
                'day' => $dayInsurance->day,
                'insurance_id' => $dayInsurance->insurance_id,
            ];

            if (Schema::hasColumn('event_day_insurance', 'is_done')) {
                $attributes['is_done'] = false;
            }

            // Nie kopiujemy event_insurance_policy_id — polisy są transakcyjne.
            EventDayInsurance::query()->create($attributes);
        }
    }

    private function cloneStartingPlaceAvailabilities(Event $original, Event $clone): void
    {
        if (! Schema::hasTable('event_starting_place_availability')) {
            return;
        }

        foreach ($original->startingPlaceAvailabilities as $availability) {
            EventStartingPlaceAvailability::query()->create([
                'event_id' => $clone->id,
                'start_place_id' => $availability->start_place_id,
                'end_place_id' => $availability->end_place_id,
                'available' => $availability->available,
                'note' => $availability->note,
            ]);
        }
    }

    private function clonePaymentInstallmentTemplates(Event $original, Event $clone): void
    {
        if (! Schema::hasTable('event_payment_installment_templates')) {
            return;
        }

        foreach ($original->paymentInstallmentTemplates as $template) {
            $row = $template->replicate(['event_id']);
            $row->event_id = $clone->id;
            $row->save();
        }
    }
}
