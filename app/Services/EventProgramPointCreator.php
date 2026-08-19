<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use App\Support\ProgramPointSearchDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EventProgramPointCreator
{
    /** @var list<string> */
    private const OPTION_KEYS = [
        'name',
        'description',
        'start_time',
        'end_time',
        'hide_times',
        'order',
        'unit_price',
        'quantity',
        'total_price',
        'currency_id',
        'convert_to_pln',
        'planned_price',
        'paid_price',
        'group_size',
        'notes',
        'office_notes',
        'pilot_notes',
        'include_in_program',
        'include_in_calculation',
        'include_gratis_in_cost',
        'include_pilot_in_cost',
        'include_driver_in_cost',
        'active',
    ];

    public function addFromTemplate(
        Event $event,
        EventTemplateProgramPoint $template,
        int $day,
        ?int $parentId = null,
        bool $cloneTemplateChildren = true,
        array $options = [],
    ): EventProgramPoint {
        $template->loadMissing(['children']);

        $groupSize = (int) ($options['group_size'] ?? $template->group_size ?? 0);
        $requestedQuantity = max(1, (int) ($options['quantity'] ?? 1));
        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $quantity = $groupSize > 0 && $requestedQuantity <= 1
            ? max(1, (int) ceil($participantCount / $groupSize))
            : $requestedQuantity;
        $unitPrice = (float) ($options['unit_price'] ?? $template->unit_price ?? 0);

        $point = $event->programPoints()->create($this->mergeOptions([
            'event_template_program_point_id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'day' => $day,
            'order' => $this->resolveOrder($event, $day, $parentId, $options),
            'parent_id' => $parentId,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total_price' => round($unitPrice * $quantity, 2),
            'currency_id' => $template->currency_id,
            'convert_to_pln' => (bool) ($template->convert_to_pln ?? false),
            'group_size' => $template->group_size,
            'notes' => $template->notes,
            'office_notes' => $template->office_notes,
            'pilot_notes' => $template->pilot_notes,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'include_gratis_in_cost' => (bool) ($template->include_gratis_in_cost ?? false),
            'include_pilot_in_cost' => (bool) ($template->include_pilot_in_cost ?? false),
            'include_driver_in_cost' => (bool) ($template->include_driver_in_cost ?? false),
            'active' => true,
        ], $options));

        if ($cloneTemplateChildren && $parentId === null && $template->children->isNotEmpty()) {
            $this->cloneTemplateChildren($template, $event, $point, $day);
            app(ProgramPointSetTimePropagator::class)->propagateFromParent($point->fresh(['children']));
        }

        return $point;
    }

    public function addBlank(Event $event, string $name, int $day, ?int $parentId = null, array $options = []): EventProgramPoint
    {
        return $event->programPoints()->create($this->mergeOptions([
            'name' => $name,
            'day' => $day,
            'order' => $this->resolveOrder($event, $day, $parentId, $options),
            'parent_id' => $parentId,
            'unit_price' => 0,
            'quantity' => 1,
            'total_price' => 0,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ], $options));
    }

    public function attachToParent(EventProgramPoint $child, EventProgramPoint $parent): EventProgramPoint
    {
        if ((int) $child->event_id !== (int) $parent->event_id) {
            throw new \InvalidArgumentException('Punkt i rodzic muszą należeć do tej samej imprezy.');
        }

        if ($child->id === $parent->id) {
            throw new \InvalidArgumentException('Punkt nie może być podpunktem samego siebie.');
        }

        $child->update([
            'parent_id' => $parent->id,
            'day' => $parent->day,
            'order' => $this->nextOrder($parent->event, (int) $parent->day, $parent->id),
        ]);

        return $child->fresh();
    }

    public function detachFromParent(EventProgramPoint $child): EventProgramPoint
    {
        if ($child->parent_id === null) {
            throw new \InvalidArgumentException('Punkt nie jest podpunktem setu.');
        }

        $day = (int) ($child->day ?? 1);
        $event = $child->event;

        $child->update([
            'parent_id' => null,
            'order' => $this->nextOrder($event, $day, null),
        ]);

        return $child->fresh();
    }

    public function duplicate(Event $event, EventProgramPoint $source, bool $withChildren = true): EventProgramPoint
    {
        if ((int) $source->event_id !== (int) $event->id) {
            throw new \InvalidArgumentException('Punkt musi należeć do podanej imprezy.');
        }

        $source->loadMissing(['children']);

        $clone = $source->replicate([
            'paid_price',
            'calculated_price',
            'planned_price',
        ]);
        $clone->order = $this->nextOrder($event, (int) $source->day, $source->parent_id);

        if (filled($clone->name)) {
            $clone->name = $clone->name.' (kopia)';
        }

        $clone->save();

        if ($withChildren && $source->parent_id === null && $source->children->isNotEmpty()) {
            foreach ($source->children as $child) {
                $childClone = $child->replicate([
                    'paid_price',
                    'calculated_price',
                    'planned_price',
                ]);
                $childClone->parent_id = $clone->id;
                $childClone->day = $clone->day;
                $childClone->order = $child->order;
                $childClone->save();
            }
        }

        return $clone;
    }

    /** @return Collection<int, EventTemplateProgramPoint> */
    public function searchTemplatePoints(string $term, int $limit = 25, bool $allowEmpty = false): Collection
    {
        $term = trim($term);

        if ($term === '') {
            if (! $allowEmpty) {
                return collect();
            }
        } elseif (mb_strlen($term) < 2) {
            return collect();
        }

        $query = EventTemplateProgramPoint::query()
            ->with([
                'tags:id,name',
                'currency:id,symbol,code',
                'parents' => fn ($parents) => $parents->select('event_template_program_points.id', 'event_template_program_points.name'),
            ])
            ->withCount(['children', 'parents']);

        if ($term !== '') {
            $this->applyCatalogSearch($query, $term);
        }

        return $query
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Opcje selecta: szablony + punkty innych imprez, z etykietą set/punkt/miasto.
     *
     * @return array<string, string>
     */
    public function searchCatalogSelectOptions(string $term, ?int $excludeEventId = null, int $limit = 30): array
    {
        $options = [];

        foreach ($this->searchTemplatePoints($term, $limit) as $point) {
            $options['template_'.$point->id] = ProgramPointSearchDisplay::html($point);
        }

        if (mb_strlen(trim($term)) >= 2) {
            foreach ($this->searchEventPoints($term, $excludeEventId, min(10, $limit)) as $point) {
                $options['event_'.$point->id] = ProgramPointSearchDisplay::eventPointHtml($point);
            }
        }

        return $options;
    }

    public function catalogOptionSelectedLabel(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        if (str_starts_with($value, 'template_')) {
            $point = EventTemplateProgramPoint::query()
                ->with([
                    'tags:id,name',
                    'currency:id,symbol,code',
                    'parents' => fn ($parents) => $parents->select('event_template_program_points.id', 'event_template_program_points.name'),
                ])
                ->withCount(['children', 'parents'])
                ->find((int) str_replace('template_', '', $value));

            return $point ? ProgramPointSearchDisplay::selectedLabel($point) : null;
        }

        if (str_starts_with($value, 'event_')) {
            $point = EventProgramPoint::query()
                ->with(['event:id,name,code', 'contractor:id,name', 'contractorLocation:id,name,city', 'templatePoint:id,name'])
                ->withCount('children')
                ->find((int) str_replace('event_', '', $value));

            return $point ? ProgramPointSearchDisplay::eventPointSelectedLabel($point) : null;
        }

        return null;
    }

    /** @return Collection<int, EventProgramPoint> */
    public function searchEventPoints(string $term, ?int $excludeEventId = null, int $limit = 10): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.$term.'%';

        return EventProgramPoint::query()
            ->with([
                'event:id,name,code',
                'contractor:id,name',
                'contractorLocation:id,name,city',
                'templatePoint:id,name',
            ])
            ->withCount('children')
            ->whereNotNull('event_id')
            ->when($excludeEventId, fn (Builder $query) => $query->where('event_id', '!=', $excludeEventId))
            ->where(function (Builder $query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('event', fn (Builder $event) => $event->where('name', 'like', $like)->orWhere('code', 'like', $like))
                    ->orWhereHas('contractor', fn (Builder $contractor) => $contractor->where('name', 'like', $like))
                    ->orWhereHas('contractorLocation', fn (Builder $location) => $location->where('city', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    private function applyCatalogSearch(Builder $query, string $term): void
    {
        $fragments = collect(preg_split('/[,\s]+/u', $term) ?: [])
            ->map(fn ($fragment) => trim((string) $fragment))
            ->filter(fn (string $fragment): bool => mb_strlen($fragment) >= 2)
            ->values();

        if ($fragments->isEmpty()) {
            $fragments = collect([$term]);
        }

        foreach ($fragments as $fragment) {
            $like = '%'.$fragment.'%';
            $query->where(function (Builder $group) use ($like): void {
                $group->whereRaw('UPPER(name) LIKE UPPER(?)', [$like])
                    ->orWhereRaw('UPPER(description) LIKE UPPER(?)', [$like])
                    ->orWhereRaw('UPPER(office_notes) LIKE UPPER(?)', [$like])
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->whereRaw('UPPER(name) LIKE UPPER(?)', [$like]))
                    ->orWhereHas('parents', fn (Builder $parents) => $parents->whereRaw('UPPER(event_template_program_points.name) LIKE UPPER(?)', [$like]));
            });
        }
    }

    protected function resolveOrder(Event $event, int $day, ?int $parentId, array $options): int
    {
        if (isset($options['order']) && $options['order'] !== '') {
            return (int) $options['order'];
        }

        return $this->nextOrder($event, $day, $parentId);
    }

    protected function nextOrder(Event $event, int $day, ?int $parentId): int
    {
        $query = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('day', $day);

        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        return (int) ($query->max('order') ?? 0) + 1;
    }

    protected function cloneTemplateChildren(
        EventTemplateProgramPoint $templateParent,
        Event $event,
        EventProgramPoint $eventParent,
        int $day,
    ): void {
        foreach ($templateParent->children as $index => $childTemplate) {
            $childTemplate->loadMissing(['children']);

            $groupSize = (int) ($childTemplate->group_size ?? 0);
            $participantCount = max(1, (int) ($event->participant_count ?? 1));
            $quantity = $groupSize > 0
                ? max(1, (int) ceil($participantCount / $groupSize))
                : 1;
            $unitPrice = (float) ($childTemplate->unit_price ?? 0);

            $childPoint = $event->programPoints()->create([
                'event_template_program_point_id' => $childTemplate->id,
                'name' => $childTemplate->name,
                'description' => $childTemplate->description,
                'day' => $day,
                'order' => $childTemplate->pivot->order ?? ($index + 1),
                'parent_id' => $eventParent->id,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'total_price' => round($unitPrice * $quantity, 2),
                'currency_id' => $childTemplate->currency_id,
                'convert_to_pln' => (bool) ($childTemplate->convert_to_pln ?? false),
                'group_size' => $childTemplate->group_size,
                'notes' => $childTemplate->notes,
                'office_notes' => $childTemplate->office_notes,
                'pilot_notes' => $childTemplate->pilot_notes,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'include_gratis_in_cost' => (bool) ($childTemplate->include_gratis_in_cost ?? false),
                'include_pilot_in_cost' => (bool) ($childTemplate->include_pilot_in_cost ?? false),
                'include_driver_in_cost' => (bool) ($childTemplate->include_driver_in_cost ?? false),
                'active' => true,
            ]);

            if ($childTemplate->children->isNotEmpty()) {
                $this->cloneTemplateChildren($childTemplate, $event, $childPoint, $day);
            }
        }
    }

    /** @param  array<string, mixed>  $base */
    protected function mergeOptions(array $base, array $options): array
    {
        foreach (self::OPTION_KEYS as $key) {
            if (! array_key_exists($key, $options)) {
                continue;
            }

            $value = $options[$key];

            if ($value === null || $value === '') {
                continue;
            }

            $base[$key] = $value;
        }

        if (isset($base['unit_price'], $base['quantity']) && ! isset($options['total_price'])) {
            $base['total_price'] = round((float) $base['unit_price'] * (int) $base['quantity'], 2);
        }

        return $base;
    }
}
