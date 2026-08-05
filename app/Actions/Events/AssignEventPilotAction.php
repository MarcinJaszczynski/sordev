<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Data\AssignEventPilotData;
use App\Models\Event;
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

            $payload = [
                'assigned_to' => $data->assignedTo,
            ];

            if (Schema::hasColumn('events', 'shared_with_pilot') && $data->sharedWithPilot !== null) {
                $payload['shared_with_pilot'] = $data->sharedWithPilot;
            }

            $event->fill($payload);

            if (
                Schema::hasColumn('events', 'shared_with_pilot')
                && $previousPilot
                && (int) $previousPilot !== (int) ($data->assignedTo ?? 0)
            ) {
                $event->shared_with_pilot = false;
            }

            $event->save();

            return $event->fresh() ?? $event;
        });
    }
}
