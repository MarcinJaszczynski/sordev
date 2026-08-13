<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TemplateProgramPointCopier
{
    /**
     * Kopiuje pełne drzewo punktów programu z szablonu na imprezę (w tym zagnieżdżone sety).
     */
    public function copyToEvent(Event $event): void
    {
        $template = $event->eventTemplate;

        if (! $template) {
            return;
        }

        EventProgramPoint::runWithoutSideEffects(function () use ($event): void {
            $context = $this->buildCopyContext($event);
            $created = 0;

            foreach ($this->rootPivotRows($context) as $pivotRow) {
                if ($this->copyPivotSlot($event, $pivotRow, $context) !== null) {
                    $created++;
                }
            }

            if ($created > 0) {
                app(EventProgramPointOrderService::class)->repairEvent($event->fresh());
            }
        });
    }

    /**
     * Uzupełnia brakujące sloty programu wg pivotu szablonu (bez usuwania istniejących punktów).
     */
    public function fillMissingFromTemplate(Event $event): int
    {
        $template = $event->eventTemplate;

        if (! $template) {
            return 0;
        }

        return EventProgramPoint::runWithoutSideEffects(function () use ($event): int {
            $context = $this->buildCopyContext($event);
            $created = 0;

            foreach ($this->groupedRootPivotRows($context) as $rows) {
                /** @var object $firstRow */
                $firstRow = $rows->first();
                $day = (int) $firstRow->day;
                $templatePointId = (int) $firstRow->event_template_program_point_id;

                $existing = EventProgramPoint::query()
                    ->where('event_id', $event->id)
                    ->where('day', $day)
                    ->where('event_template_program_point_id', $templatePointId)
                    ->whereNull('parent_id')
                    ->orderBy('order')
                    ->orderBy('id')
                    ->get();

                foreach ($rows->values() as $index => $pivotRow) {
                    if ($existing->has($index)) {
                        continue;
                    }

                    if ($this->copyPivotSlot($event, $pivotRow, $context) !== null) {
                        $created++;
                    }
                }
            }

            if ($created > 0) {
                app(EventProgramPointOrderService::class)->repairEvent($event->fresh());
            }

            return $created;
        });
    }

    /**
     * @return array{
     *     template_id: int,
     *     participant_count: int,
     *     child_template_ids: Collection<int, int>,
     *     child_pivots: Collection<int, object>,
     *     parent_child_links: Collection<int, Collection<int, object>>,
     *     template_points: Collection<int, EventTemplateProgramPoint>
     * }
     */
    protected function buildCopyContext(Event $event): array
    {
        $templateId = (int) $event->event_template_id;
        $pivotRows = $this->loadPivotRows($templateId);
        $templatePointIds = $pivotRows->pluck('event_template_program_point_id')->map(fn ($id) => (int) $id)->unique();

        // Rekurencyjnie zbierz całe drzewo parent→child (w tym wnuki), nie tylko bezpośrednie dzieci rootów.
        $parentChildLinks = $this->loadParentChildTree($templatePointIds);

        $childIdsFromLinks = $parentChildLinks
            ->flatten(1)
            ->pluck('child_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        $allTemplatePointIds = $templatePointIds->merge($childIdsFromLinks)->unique();

        $templatePoints = EventTemplateProgramPoint::query()
            ->with(['currency'])
            ->whereIn('id', $allTemplatePointIds)
            ->get()
            ->keyBy('id');

        $childTemplateIds = $childIdsFromLinks;

        return [
            'template_id' => $templateId,
            'participant_count' => max(1, (int) ($event->participant_count ?? 1)),
            'pivot_rows' => $pivotRows,
            'child_template_ids' => $childTemplateIds,
            'child_pivots' => DB::table('event_template_program_point_child_pivot')
                ->where('event_template_id', $templateId)
                ->get()
                ->keyBy('program_point_child_id'),
            'parent_child_links' => $parentChildLinks,
            'template_points' => $templatePoints,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<int, object>
     */
    protected function rootPivotRows(array $context): Collection
    {
        return $context['pivot_rows']
            ->reject(fn (object $row): bool => $context['child_template_ids']->contains((int) $row->event_template_program_point_id))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<string, Collection<int, object>>
     */
    protected function groupedRootPivotRows(array $context): Collection
    {
        return $this->rootPivotRows($context)
            ->groupBy(fn (object $row): string => (int) $row->day.'|'.(int) $row->event_template_program_point_id);
    }

    protected function loadPivotRows(int $templateId): Collection
    {
        return DB::table('event_template_event_template_program_point')
            ->where('event_template_id', $templateId)
            ->orderBy('day')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Ładuje powiązania parent→child dla całego drzewa (BFS), nie tylko jednego poziomu.
     *
     * @param  Collection<int, int>  $rootTemplatePointIds
     * @return Collection<int, Collection<int, object>>
     */
    protected function loadParentChildTree(Collection $rootTemplatePointIds): Collection
    {
        $allLinks = collect();
        $frontier = $rootTemplatePointIds->values()->all();
        $seenParents = [];

        while ($frontier !== []) {
            $batch = array_values(array_filter(
                $frontier,
                fn (int $id): bool => ! isset($seenParents[$id])
            ));

            if ($batch === []) {
                break;
            }

            foreach ($batch as $id) {
                $seenParents[$id] = true;
            }

            $rows = DB::table('event_template_program_point_parent')
                ->whereIn('parent_id', $batch)
                ->orderBy('order')
                ->get();

            $allLinks = $allLinks->concat($rows);
            $frontier = $rows
                ->pluck('child_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return $allLinks->groupBy('parent_id');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function copyPivotSlot(Event $event, object $pivotRow, array $context): ?EventProgramPoint
    {
        $templatePointId = (int) $pivotRow->event_template_program_point_id;
        $templatePoint = $context['template_points']->get($templatePointId);

        if (! $templatePoint) {
            return null;
        }

        $pointForCopy = clone $templatePoint;
        $pointForCopy->setRelation('pivot', $this->pivotRowToRelation($pivotRow));

        return $this->copyPointRecursive(
            $event,
            $pointForCopy,
            null,
            (int) ($pivotRow->day ?? 1),
            (int) ($pivotRow->order ?? 1),
            $context['participant_count'],
            $context['parent_child_links'],
            $context['child_pivots'],
            $context['template_points'],
        );
    }

    protected function pivotRowToRelation(object $row): object
    {
        return (object) [
            'day' => $row->day ?? 1,
            'order' => $row->order ?? 1,
            'notes' => $row->notes ?? null,
            'start_time' => $row->start_time ?? null,
            'end_time' => $row->end_time ?? null,
            'include_in_program' => (bool) ($row->include_in_program ?? true),
            'include_in_calculation' => (bool) ($row->include_in_calculation ?? true),
            'active' => (bool) ($row->active ?? true),
            'show_title_style' => (bool) ($row->show_title_style ?? true),
            'show_description' => (bool) ($row->show_description ?? true),
        ];
    }

    /**
     * @param  Collection<int, \Illuminate\Support\Collection<int, object>>  $parentChildLinks
     * @param  Collection<int, object>  $childPivots
     * @param  Collection<int, EventTemplateProgramPoint>  $templatePoints
     */
    protected function copyPointRecursive(
        Event $event,
        EventTemplateProgramPoint $templatePoint,
        ?int $eventParentId,
        int $day,
        int $order,
        int $participantCount,
        Collection $parentChildLinks,
        Collection $childPivots,
        Collection $templatePoints,
    ): EventProgramPoint {
        $childPivot = $childPivots->get($templatePoint->id);

        $unitPrice = (float) ($templatePoint->unit_price ?? 0);
        $groupSize = max(1, (int) ($templatePoint->group_size ?? 1));
        $quantity = max(1, (int) ceil($participantCount / $groupSize));

        $rootPivot = $eventParentId === null && $templatePoint->relationLoaded('pivot')
            ? $templatePoint->pivot
            : null;

        $eventPoint = new EventProgramPoint([
            'event_id' => $event->id,
            'event_template_program_point_id' => $templatePoint->id,
            'parent_id' => $eventParentId,
            'name' => $templatePoint->name,
            'description' => $templatePoint->description,
            'office_notes' => $templatePoint->office_notes,
            'pilot_notes' => $templatePoint->pilot_notes,
            'day' => $day,
            'order' => $order,
            'start_time' => $eventParentId ? null : ($rootPivot->start_time ?? null),
            'end_time' => $eventParentId ? null : ($rootPivot->end_time ?? null),
            'duration_hours' => $templatePoint->duration_hours,
            'duration_minutes' => $templatePoint->duration_minutes,
            'featured_image' => $templatePoint->featured_image,
            'gallery_images' => is_array($templatePoint->gallery_images)
                ? json_encode(array_values($templatePoint->gallery_images), JSON_UNESCAPED_SLASHES)
                : $templatePoint->gallery_images,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total_price' => round($unitPrice * $quantity, 2),
            'notes' => $eventParentId
                ? null
                : ($rootPivot->notes ?? null),
            'include_in_program' => $childPivot?->include_in_program ?? $rootPivot?->include_in_program ?? true,
            'include_in_calculation' => $childPivot?->include_in_calculation ?? $rootPivot?->include_in_calculation ?? true,
            'active' => $childPivot?->active ?? $rootPivot?->active ?? true,
            'show_title_style' => $childPivot?->show_title_style ?? $rootPivot?->show_title_style ?? true,
            'show_description' => $childPivot?->show_description ?? $rootPivot?->show_description ?? true,
            'group_size' => $templatePoint->group_size,
            'currency_id' => $templatePoint->currency_id,
            'convert_to_pln' => (bool) ($templatePoint->convert_to_pln ?? false),
            'is_hotel' => Event::templatePointLooksLikeHotel($templatePoint),
        ]);
        $eventPoint->setRelation('event', $event);
        $eventPoint->save();

        $childLinks = $parentChildLinks->get($templatePoint->id, collect());

        foreach ($childLinks as $link) {
            $childTemplate = $templatePoints->get((int) $link->child_id);

            if (! $childTemplate) {
                continue;
            }

            $this->copyPointRecursive(
                $event,
                $childTemplate,
                $eventPoint->id,
                $day,
                (int) ($link->order ?? 1),
                $participantCount,
                $parentChildLinks,
                $childPivots,
                $templatePoints,
            );
        }

        return $eventPoint;
    }
}
