<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Actions\Crm\CreateClientTripInquiryAction;
use App\Data\CreateClientTripInquiryData;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\ClientTripInquiry;
use App\Models\Event;
use App\Services\ClientAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class InquiryController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function index(Request $request, Event $event, ClientAccessService $access): JsonResponse
    {
        $this->authorizeClientDetails($event);
        $user = $request->user();
        abort_unless($access->isParticipant($user, $event) || $access->isGuardian($user, $event), 403);

        if (! Schema::hasTable('client_trip_inquiries')) {
            return $this->success(['event_id' => $event->id, 'items' => []]);
        }

        $items = ClientTripInquiry::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->when(
                Schema::hasColumn('client_trip_inquiries', 'source'),
                fn ($q) => $q->where('source', ClientTripInquiry::SOURCE_CLIENT)
            )
            ->latest('id')
            ->get()
            ->map(fn (ClientTripInquiry $row) => [
                'id' => $row->id,
                'subject' => $row->subject,
                'body' => $row->body,
                'status' => $row->status,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->values();

        return $this->success(['event_id' => $event->id, 'items' => $items]);
    }

    public function store(
        Request $request,
        Event $event,
        ClientAccessService $access,
        CreateClientTripInquiryAction $action,
    ): JsonResponse {
        $this->authorizeClientDetails($event);
        $this->assertClientCanMutate();
        $user = $request->user();
        abort_unless($access->isParticipant($user, $event) || $access->isGuardian($user, $event), 403);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $portalAccess = $access->accessFor($user, $event);
        $contract = $access->accessibleContract($user, $event);

        $inquiry = $action(new CreateClientTripInquiryData(
            event: $event,
            user: $user,
            subject: $validated['subject'],
            body: $validated['body'],
            contract: $contract,
            portalAccess: $portalAccess,
            source: ClientTripInquiry::SOURCE_CLIENT,
        ));

        return $this->success([
            'id' => $inquiry->id,
            'subject' => $inquiry->subject,
            'status' => $inquiry->status,
        ], 'Wysłano zapytanie.', 201);
    }
}
