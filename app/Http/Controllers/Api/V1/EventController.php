<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Services\EventCalculationPresenter;
use App\Services\EventPriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Event::query()->with(['assignedUser:id,name', 'startPlace:id,name']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $events = $query->latest('id')->paginate((int) ($validated['per_page'] ?? 20));

        return $this->success($events);
    }

    public function show(Event $event): JsonResponse
    {
        $event->load([
            'assignedUser:id,name',
            'startPlace:id,name',
            'programPoints' => fn ($query) => $query->orderBy('day')->orderBy('order'),
            'pricePerPerson',
        ]);

        return $this->success($event);
    }

    public function calculation(Event $event): JsonResponse
    {
        return $this->success(EventCalculationPresenter::for($event)->toApiArray());
    }

    public function recalculatePrice(Event $event, EventPriceCalculator $calculator): JsonResponse
    {
        $calculator->calculateForEvent($event);

        $event->refresh()->load('pricePerPerson');

        return $this->success([
            'event_id' => $event->id,
            'price_per_person' => $event->pricePerPerson,
        ], 'Ceny zostaly przeliczone.');
    }

    public function reorderProgramPoints(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'day' => ['required', 'integer', 'min:1'],
            'point_ids' => ['required', 'array', 'min:1'],
            'point_ids.*' => ['integer', 'distinct'],
        ]);

        $day = (int) $validated['day'];
        $pointIds = collect($validated['point_ids'])->map(fn ($id) => (int) $id)->values()->all();

        try {
            app(\App\Services\EventProgramPointOrderService::class)->reorderDay($event, $day, $pointIds);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }

        $updated = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('day', $day)
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return $this->success([
            'event_id' => $event->id,
            'day' => $day,
            'program_points' => $updated,
        ], 'Kolejnosc punktow zostala zapisana.');
    }
}
