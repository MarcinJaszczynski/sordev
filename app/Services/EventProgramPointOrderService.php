<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventProgramPointOrderService
{
    /**
     * Płaska lista do wyświetlenia: dzień → rodzice (order) → dzieci (order) pod rodzicem.
     *
     * @return EloquentCollection<int, EventProgramPoint>
     */
    public function sortedForDisplay(Event $event, ?EloquentCollection $points = null): EloquentCollection
    {
        $points ??= $this->loadPoints($event);

        $parents = $this->sortPointsForSchedule(
            $points->whereNull('parent_id'),
            compareDay: true,
        );

        $parentIds = $parents->pluck('id');

        $childrenByParent = $points
            ->whereNotNull('parent_id')
            ->filter(fn (EventProgramPoint $child) => $parentIds->contains($child->parent_id))
            ->groupBy('parent_id')
            ->map(fn (Collection $group) => $this->sortPointsForSchedule($group));

        $flat = new EloquentCollection;

        foreach ($parents as $parent) {
            $flat->push($parent);

            foreach ($childrenByParent->get($parent->id, collect()) as $child) {
                $flat->push($child);
            }
        }

        $orphanChildren = $this->sortPointsForSchedule(
            $points
                ->whereNotNull('parent_id')
                ->filter(fn (EventProgramPoint $child) => ! $parentIds->contains($child->parent_id)),
            compareDay: true,
        );

        foreach ($orphanChildren as $child) {
            $flat->push($child);
        }

        return $flat;
    }

    /**
     * Aktywne punkty uwzględnione w programie; podpunkty tylko gdy rodzic też jest widoczny.
     *
     * @return EloquentCollection<int, EventProgramPoint>
     */
    public function visibleProgramPoints(Event $event, bool $requireActive = true): EloquentCollection
    {
        $points = $this->loadPoints($event);

        $visible = $points->filter(function (EventProgramPoint $point) use ($requireActive) {
            if (! (bool) $point->include_in_program) {
                return false;
            }

            if ($requireActive && ! (bool) $point->active) {
                return false;
            }

            return true;
        });

        $visibleParentIds = $visible
            ->whereNull('parent_id')
            ->pluck('id');

        $filtered = $visible->filter(function (EventProgramPoint $point) use ($visibleParentIds) {
            if ($point->parent_id === null) {
                return true;
            }

            return $visibleParentIds->contains($point->parent_id);
        });

        return $this->sortedForDisplay($event, new EloquentCollection($filtered->values()->all()));
    }

    /**
     * Program widoczny w portalu pilota — bez dnia fakultatywnego (day > duration_days).
     *
     * @return EloquentCollection<int, EventProgramPoint>
     */
    public function pilotProgramPoints(Event $event, bool $requireActive = true): EloquentCollection
    {
        $durationDays = max(1, (int) ($event->duration_days ?? $event->eventTemplate?->duration_days ?? 1));

        return new EloquentCollection(
            $this->visibleProgramPoints($event, $requireActive)
                ->filter(fn (EventProgramPoint $point) => (int) $point->day <= $durationDays)
                ->values()
                ->all()
        );
    }

    /**
     * @return EloquentCollection<int, EventProgramPoint>
     */
    public function loadPoints(Event $event): EloquentCollection
    {
        return EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->with(['templatePoint', 'contractor', 'reservations.contractor', 'children', 'parent'])
            ->withCount('children')
            ->get();
    }

    /**
     * @return array<int, EventProgramPoint>
     */
    public function detectSetMembers(EventProgramPoint $parent): array
    {
        $parent->loadMissing('children');

        return array_merge(
            [$parent],
            $parent->children->sortBy([['order', 'asc'], ['id', 'asc']])->all()
        );
    }

    /**
     * Zapis kolejności z DOM / API — rodzice per dzień + dzieci per rodzic.
     *
     * @param  array<int|string>  $orderedIds
     */
    public function applyDomReorder(Event $event, array $orderedIds): void
    {
        $orderedIds = collect($orderedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($orderedIds === []) {
            return;
        }

        DB::transaction(function () use ($event, $orderedIds): void {
            $points = EventProgramPoint::query()
                ->where('event_id', $event->id)
                ->whereIn('id', $orderedIds)
                ->get()
                ->keyBy('id');

            $parentsByDay = [];
            $childrenByParent = [];

            foreach ($orderedIds as $id) {
                $point = $points->get($id);

                if (! $point) {
                    continue;
                }

                if ($point->parent_id === null) {
                    $parentsByDay[(int) $point->day][] = (int) $point->id;
                } else {
                    $childrenByParent[(int) $point->parent_id][] = (int) $point->id;
                }
            }

            foreach ($parentsByDay as $day => $parentIds) {
                $this->reorderParentsInDay($event, (int) $day, $parentIds);
            }

            foreach ($childrenByParent as $parentId => $childIds) {
                $this->reorderChildrenOfParent((int) $parentId, $childIds);
            }

            $this->syncChildrenDaysWithParents($event);
        });
    }

    /**
     * API: zmiana kolejności w jednym dniu (lista ID w nowej kolejności).
     *
     * @param  array<int|string>  $pointIds
     */
    public function reorderDay(Event $event, int $day, array $pointIds): void
    {
        $pointIds = collect($pointIds)->map(fn ($id) => (int) $id)->values();

        $existing = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('day', $day)
            ->whereIn('id', $pointIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($existing->count() !== $pointIds->count()) {
            throw new \InvalidArgumentException('Lista punktów zawiera rekordy spoza wskazanego dnia lub imprezy.');
        }

        $this->applyDomReorder($event, $pointIds->all());
    }

    /**
     * Przesuwa blok rodzica (rodzic + dzieci) na nową pozycję w dniu.
     */
    public function moveParentBlock(Event $event, int $parentId, int $newOrder, int $day): void
    {
        $parent = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->whereNull('parent_id')
            ->findOrFail($parentId);

        DB::transaction(function () use ($event, $parent, $newOrder, $day): void {
            $parentIds = EventProgramPoint::query()
                ->where('event_id', $event->id)
                ->where('day', $day)
                ->whereNull('parent_id')
                ->orderBy('order')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $parentIds = array_values(array_filter($parentIds, fn (int $id) => $id !== (int) $parent->id));
            $insertAt = max(0, min(count($parentIds), $newOrder - 1));
            array_splice($parentIds, $insertAt, 0, [(int) $parent->id]);

            $this->reorderParentsInDay($event, $day, $parentIds);

            if ((int) $parent->day !== $day) {
                $parent->update(['day' => $day]);
                EventProgramPoint::query()
                    ->where('parent_id', $parent->id)
                    ->update(['day' => $day]);
            }
        });
    }

    public function repairEvent(Event $event): int
    {
        $updated = 0;

        DB::transaction(function () use ($event, &$updated): void {
            $maxDay = max(
                (int) ($event->duration_days ?? 1),
                (int) (EventProgramPoint::query()->where('event_id', $event->id)->max('day') ?? 1)
            );

            for ($day = 1; $day <= $maxDay; $day++) {
                $parentIds = EventProgramPoint::query()
                    ->where('event_id', $event->id)
                    ->where('day', $day)
                    ->whereNull('parent_id')
                    ->orderBy('order')
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $updated += $this->reorderParentsInDay($event, $day, $parentIds, $this->sequenceStartForDay($event, $day));

                foreach ($parentIds as $parentId) {
                    $childIds = EventProgramPoint::query()
                        ->where('parent_id', $parentId)
                        ->orderBy('order')
                        ->orderBy('id')
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $childStart = $this->sequenceStartForChildren($parentId);
                    $updated += $this->reorderChildrenOfParent($parentId, $childIds, $childStart);
                }
            }

            $this->syncChildrenDaysWithParents($event);
        });

        return $updated;
    }

    /**
     * Ustawia order wg start_time (jak planer), potem order, potem id — osobno rodzice w dniu i dzieci w secie.
     */
    public function repairOrderByStartTimes(Event $event, ?int $onlyDay = null): int
    {
        $updated = 0;

        DB::transaction(function () use ($event, $onlyDay, &$updated): void {
            $maxDay = max(
                (int) ($event->duration_days ?? 1),
                (int) (EventProgramPoint::query()->where('event_id', $event->id)->max('day') ?? 1)
            );

            $firstDay = $onlyDay ?? 1;
            $lastDay = $onlyDay ?? $maxDay;

            for ($day = $firstDay; $day <= $lastDay; $day++) {
                $parents = EventProgramPoint::query()
                    ->where('event_id', $event->id)
                    ->where('day', $day)
                    ->whereNull('parent_id')
                    ->get();

                $parentIds = $this->sortPointsForSchedule($parents)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $updated += $this->reorderParentsInDay(
                    $event,
                    $day,
                    $parentIds,
                    $this->sequenceStartForDay($event, $day)
                );

                foreach ($parentIds as $parentId) {
                    $children = EventProgramPoint::query()
                        ->where('parent_id', $parentId)
                        ->get();

                    $childIds = $this->sortPointsForSchedule($children)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $updated += $this->reorderChildrenOfParent(
                        $parentId,
                        $childIds,
                        $this->sequenceStartForChildren($parentId)
                    );
                }
            }

            $this->syncChildrenDaysWithParents($event);
        });

        return $updated;
    }

    /**
     * @param  Collection<int, EventProgramPoint>|EloquentCollection<int, EventProgramPoint>  $points
     * @return EloquentCollection<int, EventProgramPoint>
     */
    protected function sortPointsForSchedule(Collection|EloquentCollection $points, bool $compareDay = false): EloquentCollection
    {
        return new EloquentCollection(
            $points
                ->sort(fn (EventProgramPoint $a, EventProgramPoint $b) => $this->comparePointsForSchedule($a, $b, $compareDay))
                ->values()
                ->all()
        );
    }

    protected function comparePointsForSchedule(EventProgramPoint $a, EventProgramPoint $b, bool $compareDay = false): int
    {
        if ($compareDay) {
            $dayCmp = (int) $a->day <=> (int) $b->day;
            if ($dayCmp !== 0) {
                return $dayCmp;
            }
        }

        $timeA = $this->normalizeStartTime($a->start_time);
        $timeB = $this->normalizeStartTime($b->start_time);

        if ($timeA !== null && $timeB !== null) {
            $timeCmp = strcmp($timeA, $timeB);
            if ($timeCmp !== 0) {
                return $timeCmp;
            }
        } elseif ($timeA !== null) {
            return -1;
        } elseif ($timeB !== null) {
            return 1;
        }

        $orderCmp = (int) $a->order <=> (int) $b->order;
        if ($orderCmp !== 0) {
            return $orderCmp;
        }

        return (int) $a->id <=> (int) $b->id;
    }

    protected function normalizeStartTime(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return substr((string) $time, 0, 8);
    }

    /**
     * Przywraca day/order rodziców z pivotu szablonu (dopasowanie po dniu + template_id, także duplikaty).
     */
    public function syncOrderFromTemplate(Event $event): int
    {
        if (! $event->event_template_id) {
            return 0;
        }

        $templateChildIds = DB::table('event_template_program_point_parent')
            ->pluck('child_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $pivotRows = DB::table('event_template_event_template_program_point')
            ->where('event_template_id', $event->event_template_id)
            ->orderBy('day')
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => (int) $row->day.'|'.(int) $row->event_template_program_point_id);

        $updated = 0;

        DB::transaction(function () use ($event, $templateChildIds, $pivotRows, &$updated): void {
            foreach ($pivotRows as $key => $rows) {
                [$day, $templatePointId] = array_map('intval', explode('|', (string) $key, 2));

                if ($templateChildIds->has($templatePointId)) {
                    continue;
                }

                $templateOrders = $rows->pluck('order')->map(fn ($order) => (int) $order)->values();

                $candidates = EventProgramPoint::query()
                    ->where('event_id', $event->id)
                    ->where('day', $day)
                    ->where('event_template_program_point_id', $templatePointId)
                    ->whereNull('parent_id')
                    ->orderBy('order')
                    ->orderBy('id')
                    ->get();

                foreach ($candidates as $index => $point) {
                    $expectedOrder = (int) ($templateOrders[$index] ?? ($index + 1));

                    if ((int) $point->order !== $expectedOrder) {
                        $point->update(['order' => $expectedOrder]);
                        $updated++;
                    }
                }
            }

            $this->repairTemplateSetLinks($event, renumber: false);
            $this->syncChildrenDaysWithParents($event);
        });

        return $updated;
    }

    /**
     * Ustawia parent_id na podstawie relacji szablonu (dla istniejących imprez).
     * Dopasowanie per dzień — ten sam punkt szablonu może występować wielokrotnie.
     */
    public function repairTemplateSetLinks(Event $event, bool $renumber = true): int
    {
        if (! $event->event_template_id) {
            return 0;
        }

        $links = DB::table('event_template_program_point_parent')
            ->join('event_template_event_template_program_point as pivot', function ($join) use ($event) {
                $join->on('pivot.event_template_program_point_id', '=', 'event_template_program_point_parent.parent_id')
                    ->where('pivot.event_template_id', '=', $event->event_template_id);
            })
            ->select([
                'pivot.day',
                'pivot.order as parent_pivot_order',
                'event_template_program_point_parent.parent_id as template_parent_id',
                'event_template_program_point_parent.child_id as template_child_id',
                'event_template_program_point_parent.order as template_child_order',
            ])
            ->orderBy('pivot.day')
            ->orderBy('pivot.order')
            ->get();

        if ($links->isEmpty()) {
            return 0;
        }

        $updated = 0;

        DB::transaction(function () use ($event, $links, $renumber, &$updated): void {
            foreach ($links as $link) {
                $day = (int) $link->day;
                $parent = $this->findEventPointForTemplateSlot(
                    $event,
                    $day,
                    (int) $link->template_parent_id,
                    (int) $link->parent_pivot_order,
                    parentsOnly: true,
                );

                $child = $this->findEventPointForTemplateChild(
                    $event,
                    $day,
                    (int) $link->template_parent_id,
                    (int) $link->template_child_id,
                    (int) $link->template_child_order,
                );

                if (! $parent || ! $child || (int) $child->id === (int) $parent->id) {
                    continue;
                }

                $changes = [];

                if ((int) $child->parent_id !== (int) $parent->id) {
                    $changes['parent_id'] = $parent->id;
                }

                if ((int) $child->day !== (int) $parent->day) {
                    $changes['day'] = $parent->day;
                }

                $expectedOrder = (int) $link->template_child_order;
                if ((int) $child->order !== $expectedOrder) {
                    $changes['order'] = $expectedOrder;
                }

                if ($changes !== []) {
                    $child->update($changes);
                    $updated++;
                }
            }

            if ($renumber && $updated > 0) {
                $this->repairEvent($event);
            }
        });

        return $updated;
    }

    protected function findEventPointForTemplateSlot(
        Event $event,
        int $day,
        int $templatePointId,
        int $pivotOrder,
        bool $parentsOnly = false,
    ): ?EventProgramPoint {
        $pivotOrders = DB::table('event_template_event_template_program_point')
            ->where('event_template_id', $event->event_template_id)
            ->where('day', $day)
            ->where('event_template_program_point_id', $templatePointId)
            ->orderBy('order')
            ->orderBy('id')
            ->pluck('order')
            ->map(fn ($order) => (int) $order)
            ->values();

        $index = $pivotOrders->search($pivotOrder);

        if ($index === false) {
            return null;
        }

        $query = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('day', $day)
            ->where('event_template_program_point_id', $templatePointId)
            ->orderBy('order')
            ->orderBy('id');

        if ($parentsOnly) {
            $query->whereNull('parent_id');
        }

        return $query->get()->get($index);
    }

    protected function findEventPointForTemplateChild(
        Event $event,
        int $day,
        int $parentTemplateId,
        int $childTemplateId,
        int $childOrder,
    ): ?EventProgramPoint {
        $siblingLinks = DB::table('event_template_program_point_parent')
            ->where('parent_id', $parentTemplateId)
            ->orderBy('order')
            ->orderBy('child_id')
            ->get();

        $siblingIndex = $siblingLinks
            ->values()
            ->search(fn ($row) => (int) $row->child_id === $childTemplateId && (int) $row->order === $childOrder);

        if ($siblingIndex === false) {
            $siblingIndex = $siblingLinks
                ->values()
                ->search(fn ($row) => (int) $row->child_id === $childTemplateId);
        }

        if ($siblingIndex === false) {
            return null;
        }

        $candidates = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('day', $day)
            ->where('event_template_program_point_id', $childTemplateId)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return $candidates->get($siblingIndex) ?? $candidates->first();
    }

    protected function sequenceStartForDay(Event $event, int $day): int
    {
        $min = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('day', $day)
            ->whereNull('parent_id')
            ->min('order');

        return $min === null || (int) $min > 0 ? 1 : 0;
    }

    protected function sequenceStartForChildren(int $parentId): int
    {
        $min = EventProgramPoint::query()
            ->where('parent_id', $parentId)
            ->min('order');

        return $min === null || (int) $min > 0 ? 1 : 0;
    }

    /**
     * @param  array<int>  $parentIds
     */
    protected function reorderParentsInDay(Event $event, int $day, array $parentIds, int $startAt = 1): int
    {
        $updated = 0;

        foreach (array_values($parentIds) as $index => $parentId) {
            $expectedOrder = $startAt + $index;

            $affected = EventProgramPoint::query()
                ->where('event_id', $event->id)
                ->where('id', $parentId)
                ->whereNull('parent_id')
                ->where('day', $day)
                ->where('order', '!=', $expectedOrder)
                ->update(['order' => $expectedOrder]);

            $updated += $affected;
        }

        return $updated;
    }

    /**
     * Kolejność bloków (rodzic + dzieci) w dniu — np. po SortableJS w widoku drzewa.
     *
     * @param  array<int|string>  $orderedParentIds
     */
    public function reorderDayBlockOrder(Event $event, int $day, array $orderedParentIds): int
    {
        $orderedParentIds = collect($orderedParentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();

        $points = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('day', $day)
            ->get();

        $flat = [];

        foreach ($orderedParentIds as $parentId) {
            if (! $points->contains('id', $parentId)) {
                continue;
            }

            $flat[] = $parentId;

            foreach ($points->where('parent_id', $parentId)->sortBy('order') as $child) {
                $flat[] = (int) $child->id;
            }
        }

        foreach ($points->whereNull('parent_id') as $parent) {
            if (! $orderedParentIds->contains((int) $parent->id) && ! in_array((int) $parent->id, $flat, true)) {
                $flat[] = (int) $parent->id;
                foreach ($points->where('parent_id', $parent->id)->sortBy('order') as $child) {
                    $flat[] = (int) $child->id;
                }
            }
        }

        $this->applyDomReorder($event, $flat);

        return count($flat);
    }

    /**
     * @param  array<int|string>  $childIds
     */
    public function reorderSetChildren(Event $event, int $parentId, array $childIds): int
    {
        $childIds = collect($childIds)->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->values()->all();

        $parent = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->whereNull('parent_id')
            ->findOrFail($parentId);

        $startAt = $this->sequenceStartForChildren($parentId);

        return $this->reorderChildrenOfParent($parentId, $childIds, $startAt);
    }

    /**
     * Rozszerza kolejność z Filament reorder — rodzic ciągnie za sobą dzieci setu.
     *
     * @param  array<int|string>  $order
     * @return array<int, int>
     */
    public function expandReorderOrderWithSets(Event $event, array $order): array
    {
        $points = $this->loadPoints($event)->keyBy('id');
        $expanded = [];
        $seen = [];

        foreach ($order as $rawId) {
            $id = (int) $rawId;
            $point = $points->get($id);

            if (! $point || isset($seen[$id])) {
                continue;
            }

            if ($point->parent_id === null) {
                $expanded[] = $id;
                $seen[$id] = true;

                foreach ($points->where('parent_id', $id)->sortBy('order') as $child) {
                    $childId = (int) $child->id;
                    if (! isset($seen[$childId])) {
                        $expanded[] = $childId;
                        $seen[$childId] = true;
                    }
                }
            } elseif (! isset($seen[$id])) {
                $expanded[] = $id;
                $seen[$id] = true;
            }
        }

        foreach ($order as $rawId) {
            $id = (int) $rawId;
            if (! isset($seen[$id]) && $points->has($id)) {
                $expanded[] = $id;
            }
        }

        return $expanded;
    }

    /**
     * @param  array<int>  $childIds
     */
    protected function reorderChildrenOfParent(int $parentId, array $childIds, int $startAt = 1): int
    {
        $updated = 0;

        foreach (array_values($childIds) as $index => $childId) {
            $expectedOrder = $startAt + $index;

            $affected = EventProgramPoint::query()
                ->where('id', $childId)
                ->where('parent_id', $parentId)
                ->where('order', '!=', $expectedOrder)
                ->update(['order' => $expectedOrder]);

            $updated += $affected;
        }

        return $updated;
    }

    protected function syncChildrenDaysWithParents(Event $event): void
    {
        EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->whereNotNull('parent_id')
            ->with('parent:id,day')
            ->each(function (EventProgramPoint $child): void {
                $parentDay = $child->parent?->day;

                if ($parentDay !== null && (int) $child->day !== (int) $parentDay) {
                    $child->update(['day' => $parentDay]);
                }
            });
    }

    public function movePoint(EventProgramPoint $point, string $direction): void
    {
        $siblings = EventProgramPoint::query()
            ->where('event_id', $point->event_id)
            ->where('day', $point->day)
            ->where('parent_id', $point->parent_id)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(fn (EventProgramPoint $row) => $row->id === $point->id);
        if ($index === false) {
            return;
        }

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapIndex < 0 || $swapIndex >= $siblings->count()) {
            return;
        }

        $other = $siblings[$swapIndex];
        $currentOrder = (int) $point->order;
        $otherOrder = (int) $other->order;

        $point->update(['order' => $otherOrder]);
        $other->update(['order' => $currentOrder]);
    }
}
