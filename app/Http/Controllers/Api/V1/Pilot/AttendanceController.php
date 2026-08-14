<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pilot;

use App\Actions\Events\MarkAttendanceAction;
use App\Data\MarkAttendanceData;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AttendanceController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorizePilotView($event);

        $validated = $request->validate([
            'day' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $day = (int) ($validated['day'] ?? 1);
        $maxDay = max(1, (int) ($event->duration_days ?? 1));
        $day = min($day, $maxDay);

        $participants = [];
        $statuses = [];

        if (Schema::hasTable('event_participants')) {
            $rows = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('status', EventParticipant::STATUS_ACTIVE)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name']);

            foreach ($rows as $participant) {
                $participants[] = [
                    'id' => $participant->id,
                    'first_name' => $participant->first_name,
                    'last_name' => $participant->last_name,
                ];
                $statuses[(string) $participant->id] = EventAttendance::STATUS_UNKNOWN;
            }
        }

        if (Schema::hasTable('event_attendances')) {
            EventAttendance::query()
                ->where('event_id', $event->id)
                ->where('day', $day)
                ->get()
                ->each(function (EventAttendance $row) use (&$statuses): void {
                    $statuses[(string) $row->event_participant_id] = $row->status;
                });
        }

        return $this->success([
            'event_id' => $event->id,
            'day' => $day,
            'max_day' => $maxDay,
            'participants' => $participants,
            'statuses' => $statuses,
            'can_edit' => app(\App\Services\PilotAccessService::class)->hasFullAccess($event, $request->user()),
        ]);
    }

    public function update(Request $request, Event $event, MarkAttendanceAction $action): JsonResponse
    {
        $this->authorizePilotView($event);
        $this->assertPilotCanMutate($event);

        $validated = $request->validate([
            'day' => ['required', 'integer', 'min:1', 'max:60'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', 'string', Rule::in([
                EventAttendance::STATUS_PRESENT,
                EventAttendance::STATUS_ABSENT,
                EventAttendance::STATUS_UNKNOWN,
            ])],
        ]);

        $statuses = [];
        foreach ($validated['statuses'] as $participantId => $status) {
            $statuses[(int) $participantId] = $status;
        }

        $action(new MarkAttendanceData(
            event: $event,
            day: (int) $validated['day'],
            statuses: $statuses,
            markedBy: $request->user()?->id,
        ));

        return $this->success(
            $this->show($request->merge(['day' => $validated['day']]), $event)->getData(true)['data'],
            'Zapisano obecność.'
        );
    }
}
