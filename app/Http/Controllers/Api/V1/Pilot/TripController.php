<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pilot;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\ProgramPointResource;
use App\Http\Resources\Api\V1\TripSummaryResource;
use App\Models\Event;
use App\Services\EventProgramPointOrderService;
use App\Services\PilotAccessService;
use App\Support\ClientPortalMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TripController extends BaseApiController
{
    public function index(Request $request, PilotAccessService $access): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $access->visibleTripsQuery($request->user())
            ->with(['startPlace:id,name', 'eventTemplate']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $trips = $query->latest('start_date')->paginate((int) ($validated['per_page'] ?? 20));

        return $this->success([
            'items' => TripSummaryResource::collection($trips->getCollection())->resolve(),
            'meta' => [
                'current_page' => $trips->currentPage(),
                'per_page' => $trips->perPage(),
                'total' => $trips->total(),
                'last_page' => $trips->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Event $event, PilotAccessService $access): JsonResponse
    {
        Gate::authorize('view', $event);

        $event->load(['startPlace:id,name', 'eventTemplate']);
        $details = $request->user()?->can('viewPilotDetails', $event) ?? false;

        $payload = TripSummaryResource::make($event)->resolve();
        $payload['details_available'] = $details;
        $payload['archive_message'] = $details ? null : $access->archiveMessage($event);

        if ($details) {
            $payload['client_name'] = $event->client_name;
            $payload['client_phone'] = $event->client_phone;
            $payload['client_email'] = $event->client_email;
            $payload['participant_count'] = $event->participant_count;
            $payload['cover_url'] = ClientPortalMedia::coverUrl($event);
        }

        return $this->success($payload);
    }

    public function program(Request $request, Event $event, EventProgramPointOrderService $orderService): JsonResponse
    {
        Gate::authorize('viewPilotDetails', $event);

        $points = $orderService->pilotProgramPoints($event);

        return $this->success([
            'event_id' => $event->id,
            'points' => $points
                ->map(fn ($point) => (new ProgramPointResource($point, 'pilot'))->resolve())
                ->values()
                ->all(),
        ]);
    }
}
