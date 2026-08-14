<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Actions\Events\UpsertEventParticipantAction;
use App\Data\UpsertEventParticipantData;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventPortalAccess;
use App\Services\ClientAccessService;
use App\Services\ContractDietSurchargeService;
use App\Services\ParentParticipantAccessService;
use App\Services\ParticipantPaymentBalanceService;
use App\Support\EventParticipantConsents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ParticipantsController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function index(Request $request, Event $event, ClientAccessService $access): JsonResponse
    {
        $this->authorizeClientDetails($event);
        abort_unless($access->isGuardian($request->user(), $event), 403);

        if (! Schema::hasTable('event_participants')) {
            return $this->success(['event_id' => $event->id, 'items' => [], 'consent_labels' => EventParticipantConsents::labels()]);
        }

        $balance = app(ParticipantPaymentBalanceService::class);
        $items = $access->guardianParticipantsQuery($request->user(), $event)
            ->get()
            ->map(function (EventParticipant $p) use ($balance) {
                $status = $balance->forParticipant($p);

                return [
                    'id' => $p->id,
                    'first_name' => $p->first_name,
                    'last_name' => $p->last_name,
                    'diet' => $p->diet,
                    'consents' => EventParticipantConsents::checklist($p->consents ?? null),
                    'balance' => $status,
                ];
            })
            ->values();

        return $this->success([
            'event_id' => $event->id,
            'items' => $items,
            'consent_labels' => EventParticipantConsents::labels(),
        ]);
    }

    public function store(Request $request, Event $event, ClientAccessService $access, UpsertEventParticipantAction $action): JsonResponse
    {
        return $this->upsert($request, $event, $access, $action, null);
    }

    public function update(
        Request $request,
        Event $event,
        EventParticipant $participant,
        ClientAccessService $access,
        UpsertEventParticipantAction $action,
    ): JsonResponse {
        return $this->upsert($request, $event, $access, $action, $participant);
    }

    public function destroy(Request $request, Event $event, EventParticipant $participant, ClientAccessService $access): JsonResponse
    {
        $this->authorizeClientDetails($event);
        $this->assertClientCanMutate();
        abort_unless($access->isGuardian($request->user(), $event), 403);

        $scoped = $access->guardianParticipantsQuery($request->user(), $event)
            ->whereKey($participant->id)
            ->firstOrFail();

        $scoped->delete();

        return $this->success(null, 'Usunięto uczestnika.');
    }

    public function parentLink(
        Request $request,
        Event $event,
        EventParticipant $participant,
        ClientAccessService $access,
        ParentParticipantAccessService $parents,
    ): JsonResponse {
        $this->authorizeClientDetails($event);
        abort_unless($access->isGuardian($request->user(), $event), 403);

        if (! Schema::hasColumn('event_participants', 'parent_access_token')) {
            return $this->error('Brak migracji tokenów rodzica.', [], 422);
        }

        $scoped = $access->guardianParticipantsQuery($request->user(), $event)
            ->whereKey($participant->id)
            ->firstOrFail();

        return $this->success([
            'url' => $parents->urlFor($scoped),
            'participant_id' => $scoped->id,
        ]);
    }

    private function upsert(
        Request $request,
        Event $event,
        ClientAccessService $access,
        UpsertEventParticipantAction $action,
        ?EventParticipant $participant,
    ): JsonResponse {
        $this->authorizeClientDetails($event);
        $this->assertClientCanMutate();
        abort_unless($access->isGuardian($request->user(), $event), 403);

        if ($participant) {
            $participant = $access->guardianParticipantsQuery($request->user(), $event)
                ->whereKey($participant->id)
                ->firstOrFail();
        }

        $validated = $request->validate([
            'first_name' => ['required_without:last_name', 'nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'diet' => ['nullable', 'string', 'max:255'],
            'consents' => ['nullable', 'array'],
            'consents.*' => ['boolean'],
        ]);

        $flags = [];
        foreach (EventParticipantConsents::allKeys() as $key) {
            $flags[$key] = (bool) ($validated['consents'][$key] ?? false);
        }

        $saved = $action(new UpsertEventParticipantData(
            event: $event,
            participant: $participant,
            firstName: trim((string) ($validated['first_name'] ?? '')) ?: null,
            lastName: trim((string) ($validated['last_name'] ?? '')) ?: null,
            diet: trim((string) ($validated['diet'] ?? '')) ?: null,
            consentFlags: $flags,
            ensurePayment: false,
            source: EventParticipant::SOURCE_MANUAL,
        ));

        $guardianContractId = $access
            ->accessFor($request->user(), $event, EventPortalAccess::ROLE_GUARDIAN)
            ?->contract_id;

        if (! $participant && $guardianContractId && Schema::hasColumn('event_participants', 'contract_id') && blank($saved->contract_id)) {
            $saved->forceFill(['contract_id' => $guardianContractId])->save();
            app(ContractDietSurchargeService::class)->applyForParticipant($saved->fresh() ?? $saved);
        }

        return $this->success([
            'id' => $saved->id,
            'first_name' => $saved->first_name,
            'last_name' => $saved->last_name,
            'diet' => $saved->diet,
        ], $participant ? 'Zaktualizowano uczestnika.' : 'Dodano uczestnika.', $participant ? 200 : 201);
    }
}
