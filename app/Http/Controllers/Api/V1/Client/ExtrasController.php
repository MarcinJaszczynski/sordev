<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventPortalAccess;
use App\Services\ClientAccessService;
use App\Services\ContractExtrasSurchargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ExtrasController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function show(Request $request, Event $event, ClientAccessService $access): JsonResponse
    {
        $this->authorizeClientDetails($event);
        $user = $request->user();
        abort_unless($access->isParticipant($user, $event) || $access->isGuardian($user, $event), 403);

        $contract = $this->resolveContract($access, $user, $event);
        $catalog = $contract
            ? app(ContractExtrasSurchargeService::class)->presentation($contract)
            : [];

        $participants = $this->scopedParticipants($access, $user, $event)->map(function (EventParticipant $p) {
            $extras = is_array($p->selected_extras ?? null) ? $p->selected_extras : [];

            return [
                'id' => $p->id,
                'first_name' => $p->first_name,
                'last_name' => $p->last_name,
                'diet' => $p->diet,
                'selections' => array_merge(['diet' => (string) ($p->diet ?? '')], $extras),
            ];
        })->values();

        return $this->success([
            'event_id' => $event->id,
            'catalog' => $catalog,
            'participants' => $participants,
        ]);
    }

    public function update(Request $request, Event $event, ClientAccessService $access): JsonResponse
    {
        $this->authorizeClientDetails($event);
        $this->assertClientCanMutate();
        $user = $request->user();
        abort_unless($access->isParticipant($user, $event) || $access->isGuardian($user, $event), 403);

        $validated = $request->validate([
            'selections' => ['required', 'array'],
            'selections.*' => ['array'],
            'selections.*.*' => ['nullable', 'string', 'max:255'],
        ]);

        $scopedIds = $this->scopedParticipants($access, $user, $event)->pluck('id')->all();
        $extrasService = app(ContractExtrasSurchargeService::class);

        foreach ($validated['selections'] as $participantId => $selection) {
            $participantId = (int) $participantId;
            abort_unless(in_array($participantId, $scopedIds, true), 403);

            $participant = EventParticipant::query()->findOrFail($participantId);
            $diet = array_key_exists('diet', $selection)
                ? (trim((string) $selection['diet']) ?: null)
                : $participant->diet;

            $extras = $selection;
            unset($extras['diet']);

            $payload = ['diet' => $diet];
            if (Schema::hasColumn('event_participants', 'selected_extras')) {
                $payload['selected_extras'] = $extras;
            }

            $participant->forceFill($payload)->save();
            $extrasService->applyForParticipant($participant->fresh() ?? $participant);
        }

        return $this->success(
            $this->show($request, $event, $access)->getData(true)['data'],
            'Zapisano świadczenia.'
        );
    }

    private function resolveContract(ClientAccessService $access, $user, Event $event)
    {
        return $access->accessibleContract($user, $event)
            ?? $access->accessibleContract($user, $event, EventPortalAccess::ROLE_GUARDIAN)
            ?? $access->accessibleContract($user, $event, EventPortalAccess::ROLE_PARTICIPANT);
    }

    /**
     * @return \Illuminate\Support\Collection<int, EventParticipant>
     */
    private function scopedParticipants(ClientAccessService $access, $user, Event $event)
    {
        if ($access->isGuardian($user, $event)) {
            return $access->guardianParticipantsQuery($user, $event)->get();
        }

        $participantId = $access->accessFor($user, $event, EventPortalAccess::ROLE_PARTICIPANT)?->event_participant_id;
        if (! $participantId) {
            return collect();
        }

        return EventParticipant::query()->whereKey($participantId)->get();
    }
}
