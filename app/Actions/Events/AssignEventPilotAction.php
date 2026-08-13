<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Data\AssignEventPilotData;
use App\Models\Contractor;
use App\Models\Event;
use App\Services\PilotContractorAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Szybkie przypisanie pilota (modal / slideOver) — bez pełnej strony Pilot.
 */
final class AssignEventPilotAction
{
    public function __invoke(AssignEventPilotData $data): Event
    {
        return DB::transaction(function () use ($data): Event {
            $event = $data->event->fresh() ?? $data->event;
            $previousPilot = $event->assigned_to;
            $previousContractor = Schema::hasColumn('events', 'pilot_contractor_id')
                ? $event->pilot_contractor_id
                : null;

            $assignmentService = app(PilotContractorAssignmentService::class);
            $assignedTo = $data->assignedTo;

            if (Schema::hasColumn('events', 'pilot_contractor_id') && $data->pilotContractorId !== null) {
                $contractor = $data->pilotContractorId > 0
                    ? Contractor::query()->find($data->pilotContractorId)
                    : null;
                $assignedTo = $assignmentService->resolvePortalUserId($contractor);
            }

            $payload = [
                'assigned_to' => $assignedTo,
            ];

            if (Schema::hasColumn('events', 'pilot_contractor_id') && $data->pilotContractorId !== null) {
                $payload['pilot_contractor_id'] = $data->pilotContractorId > 0
                    ? $data->pilotContractorId
                    : null;
            }

            if (Schema::hasColumn('events', 'shared_with_pilot') && $data->sharedWithPilot !== null) {
                $payload['shared_with_pilot'] = $data->sharedWithPilot;
            }

            $event->fill($payload);

            if (
                Schema::hasColumn('events', 'shared_with_pilot')
                && (
                    ($previousPilot && (int) $previousPilot !== (int) ($assignedTo ?? 0))
                    || (
                        Schema::hasColumn('events', 'pilot_contractor_id')
                        && $data->pilotContractorId !== null
                        && (int) ($previousContractor ?? 0) !== (int) ($data->pilotContractorId ?? 0)
                    )
                )
            ) {
                $event->shared_with_pilot = false;
            }

            $event->save();

            return $event->fresh() ?? $event;
        });
    }
}
