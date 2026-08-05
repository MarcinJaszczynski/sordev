<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Data\MarkAttendanceData;
use App\Models\EventAttendance;
use App\Models\EventParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class MarkAttendanceAction
{
    public function __invoke(MarkAttendanceData $data): void
    {
        if (! Schema::hasTable('event_attendances')) {
            throw new InvalidArgumentException('Tabela obecności nie jest dostępna. Uruchom migracje.');
        }

        $day = max(1, $data->day);

        DB::transaction(function () use ($data, $day): void {
            $validIds = EventParticipant::query()
                ->where('event_id', $data->event->id)
                ->pluck('id')
                ->all();

            foreach ($data->statuses as $participantId => $status) {
                $participantId = (int) $participantId;
                if (! in_array($participantId, $validIds, true)) {
                    continue;
                }

                $status = in_array($status, [
                    EventAttendance::STATUS_PRESENT,
                    EventAttendance::STATUS_ABSENT,
                    EventAttendance::STATUS_UNKNOWN,
                ], true) ? $status : EventAttendance::STATUS_UNKNOWN;

                EventAttendance::query()->updateOrCreate(
                    [
                        'event_id' => $data->event->id,
                        'event_participant_id' => $participantId,
                        'day' => $day,
                    ],
                    [
                        'status' => $status,
                        'marked_by' => $data->markedBy,
                    ],
                );
            }
        });
    }
}
