<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\Data\UpsertReservationData;
use App\Filament\Forms\ReservationFormFields;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use App\Services\ReservationTaskSyncService;
use Illuminate\Support\Facades\DB;

final class UpsertReservationAction
{
    public function __construct(
        private readonly ReservationTaskSyncService $taskSync,
    ) {}

    public function __invoke(UpsertReservationData $data): Reservation
    {
        return DB::transaction(function () use ($data): Reservation {
            $attributes = ReservationFormFields::normalizeSaveData($data->attributes);
            $programPoint = $data->programPoint;
            $settlementCost = $data->settlementCost;

            if (! $programPoint && ! empty($attributes['program_point_id'])) {
                $programPoint = EventProgramPoint::query()->find($attributes['program_point_id']);
            }

            if ($programPoint) {
                $attributes['program_point_id'] = $programPoint->id;
                $attributes['event_id'] ??= $programPoint->event_id;
                $attributes['contractor_id'] = $programPoint->contractor_id
                    ?? ($attributes['contractor_id'] ?? null);
            }

            if ($settlementCost) {
                $settlementCost->loadMissing('settlement');
                $attributes['settlement_cost_id'] = $settlementCost->id;
                $attributes['event_id'] ??= $settlementCost->settlement?->event_id;

                if (
                    blank($attributes['program_point_id'] ?? null)
                    && $settlementCost->source_type === 'program_point'
                    && $settlementCost->source_id
                ) {
                    $attributes['program_point_id'] = $settlementCost->source_id;
                }

                if (blank($attributes['contractor_id'] ?? null)) {
                    $attributes['contractor_id'] = $settlementCost->contractor_id;
                }
            }

            $attributes['created_by'] ??= $data->createdBy ?? auth()->id();

            if ($data->reservation) {
                $data->reservation->update($attributes);
                $reservation = $data->reservation->fresh();
            } else {
                $reservation = Reservation::query()->create($attributes);
            }

            ReservationFormFields::persistAttachments(
                $reservation,
                $data->attachmentData !== [] ? $data->attachmentData : $data->attributes,
            );

            $this->taskSync->sync($reservation->fresh([
                'event',
                'programPoint.templatePoint',
                'contractor',
            ]));

            return $reservation->fresh([
                'event',
                'programPoint',
                'contractor',
                'settlementCost',
            ]);
        });
    }
}
