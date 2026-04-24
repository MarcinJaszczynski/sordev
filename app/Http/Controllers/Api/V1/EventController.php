<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Services\EventPriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $pointIds = collect($validated['point_ids'])->map(fn ($id) => (int) $id)->values();

        $existingIds = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('day', $day)
            ->whereIn('id', $pointIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($existingIds->count() !== $pointIds->count()) {
            return $this->error('Lista punktow zawiera rekordy spoza wskazanego dnia lub eventu.');
        }

        DB::transaction(function () use ($pointIds, $day, $event): void {
            foreach ($pointIds as $order => $pointId) {
                EventProgramPoint::query()
                    ->where('id', $pointId)
                    ->where('event_id', $event->id)
                    ->update([
                        'day' => $day,
                        'order' => $order + 1,
                    ]);
            }
        });

        $updated = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('day', $day)
            ->orderBy('order')
            ->get();

        return $this->success([
            'event_id' => $event->id,
            'day' => $day,
            'program_points' => $updated,
        ], 'Kolejnosc punktow zostala zapisana.');
    }
}
