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
 *
 * Źródło prawdy tożsamości: kontrahent (`pilot_contractor_id`).
 * `assigned_to` to opcjonalne konto portalu — ustawiane tylko gdy da się je rozwiązać;
 * brak konta NIE kasuje istniejącego Usera (jak ManageEventPilot).
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
            $payload = [];
            $assignedTo = $previousPilot;

            if (Schema::hasColumn('events', 'pilot_contractor_id') && $data->pilotContractorId !== null) {
                $contractorId = $data->pilotContractorId > 0 ? $data->pilotContractorId : null;
                $payload['pilot_contractor_id'] = $contractorId;

                if ($contractorId !== null) {
                    $contractor = Contractor::query()->find($contractorId);
                    $resolved = $assignmentService->resolvePortalUserId($contractor);

                    // Konto portalu tylko gdy istnieje — inaczej zachowaj previous assigned_to.
                    if ($resolved !== null) {
                        $payload['assigned_to'] = $resolved;
                        $assignedTo = $resolved;
                    }
                } else {
                    $payload['assigned_to'] = null;
                    $assignedTo = null;
                }
            } else {
                $assignedTo = $data->assignedTo;
                $payload['assigned_to'] = $assignedTo;
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
