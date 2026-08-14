<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\ProgramPointResource;
use App\Http\Resources\Api\V1\TripSummaryResource;
use App\Models\Event;
use App\Services\ClientAccessService;
use App\Services\ClientTripReadinessService;
use App\Services\EventProgramPointOrderService;
use App\Support\ClientPortalMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TripController extends BaseApiController
{
    public function index(Request $request, ClientAccessService $access): JsonResponse
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

    public function show(Request $request, Event $event, ClientAccessService $access): JsonResponse
    {
        Gate::authorize('viewClientPortal', $event);

        $event->load(['startPlace:id,name', 'eventTemplate']);
        $details = $request->user()?->can('viewClientPortalDetails', $event) ?? false;

        $payload = TripSummaryResource::make($event)->resolve();
        $payload['details_available'] = $details;
        $payload['archive_message'] = $details ? null : $access->archiveMessage($event);

        if ($details) {
            $payload['client_name'] = $event->client_name;
            $payload['diet_info'] = $event->diet_info;
            $payload['cover_url'] = ClientPortalMedia::coverUrl($event);
            $payload['role'] = $access->accessFor($request->user(), $event)?->role;
            $payload['readiness'] = collect(app(ClientTripReadinessService::class)->items($request->user(), $event))
                ->map(fn (array $item) => [
                    'key' => $item['key'] ?? null,
                    'label' => $item['label'] ?? null,
                    'status' => $item['status'] ?? null,
                    'detail' => $item['detail'] ?? null,
                ])
                ->values()
                ->all();
        }

        return $this->success($payload);
    }

    public function program(Request $request, Event $event, EventProgramPointOrderService $orderService): JsonResponse
    {
        Gate::authorize('viewClientPortalDetails', $event);

        $points = $orderService->clientProgramPoints($event);

        return $this->success([
            'event_id' => $event->id,
            'points' => $points
                ->map(fn ($point) => (new ProgramPointResource($point, 'client'))->resolve())
                ->values()
                ->all(),
        ]);
    }
}
