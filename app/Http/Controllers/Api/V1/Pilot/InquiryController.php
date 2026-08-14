<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pilot;

use App\Actions\Crm\CreateClientTripInquiryAction;
use App\Data\CreateClientTripInquiryData;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\ClientTripInquiry;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class InquiryController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorizePilotDetails($event);

        if (! Schema::hasTable('client_trip_inquiries')) {
            return $this->success(['event_id' => $event->id, 'items' => []]);
        }

        $items = ClientTripInquiry::query()
            ->where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->when(
                Schema::hasColumn('client_trip_inquiries', 'source'),
                fn ($q) => $q->where('source', ClientTripInquiry::SOURCE_PILOT)
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

        return $this->success([
            'event_id' => $event->id,
            'items' => $items,
        ]);
    }

    public function store(Request $request, Event $event, CreateClientTripInquiryAction $action): JsonResponse
    {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $inquiry = $action(new CreateClientTripInquiryData(
            event: $event,
            user: $request->user(),
            subject: $validated['subject'],
            body: $validated['body'],
            source: ClientTripInquiry::SOURCE_PILOT,
        ));

        return $this->success([
            'id' => $inquiry->id,
            'subject' => $inquiry->subject,
            'status' => $inquiry->status,
        ], 'Wysłano zapytanie.', 201);
    }
}
