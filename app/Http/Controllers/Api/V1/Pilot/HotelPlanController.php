<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pilot;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\Event;
use App\Services\EventHotelPlanService;
use App\Services\PilotAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelPlanController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function show(Event $event, EventHotelPlanService $hotels): JsonResponse
    {
        $this->authorizePilotDetails($event);

        $hotels->ensureStaysForEvent($event);
        $plan = $hotels->buildHotelPlanForPdf($event);

        $user = request()->user();
        $canEditRooms = $user
            && ((int) $event->assigned_to === (int) $user->id || $user->hasRole(['admin', 'super_admin', 'biuro']));

        return $this->success([
            'event_id' => $event->id,
            'nights' => $plan->values(),
            'can_edit_room_numbers' => $canEditRooms
                && app(PilotAccessService::class)->hasFullAccess($event, $user),
        ]);
    }

    public function updateRoomNumbers(Request $request, Event $event, EventHotelPlanService $hotels): JsonResponse
    {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $user = $request->user();
        abort_unless(
            $user && ((int) $event->assigned_to === (int) $user->id || $user->hasRole(['admin', 'super_admin', 'biuro'])),
            403
        );

        $validated = $request->validate([
            'room_numbers' => ['required', 'array'],
            'room_numbers.*' => ['nullable', 'string', 'max:50'],
        ]);

        $hotels->updateUnitRoomNumbers($event, $validated['room_numbers']);

        return $this->success(
            $this->show($event, $hotels)->getData(true)['data'],
            'Zapisano numery pokoi.'
        );
    }
}
