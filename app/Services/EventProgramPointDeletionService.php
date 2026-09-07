<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use Illuminate\Support\Facades\DB;

class EventProgramPointDeletionService
{
    private const UNDO_SESSION_PREFIX = 'program_point_deletion_undo.';

    private const UNDO_TTL_SECONDS = 3600;

    /**
     * Soft-delete punktu; dla rodzica setu — także wszystkich dzieci.
     *
     * @return array{
     *     point_ids: list<int>,
     *     was_set: bool,
     *     child_count: int,
     *     label: string,
     *     title: string,
     *     body: string
     * }
     */
    public function softDelete(EventProgramPoint $point): array
    {
        $point = EventProgramPoint::query()
            ->with(['templatePoint', 'parent.templatePoint', 'children'])
            ->find($point->id);

        if (! $point) {
            return [
                'point_ids' => [],
                'was_set' => false,
                'child_count' => 0,
                'label' => '',
                'title' => 'Punkt już usunięty',
                'body' => 'Nie było czego usuwać.',
            ];
        }

        $wasSet = $point->parent_id === null && $point->isSetParent();
        $label = $this->resolveLabel($point);
        $ids = [(int) $point->id];
        $childCount = 0;
        $hadParent = $point->parent_id !== null;

        DB::transaction(function () use ($point, &$ids, &$childCount): void {
            if ($point->parent_id === null) {
                $children = EventProgramPoint::query()
                    ->where('event_id', $point->event_id)
                    ->where('parent_id', $point->id)
                    ->orderBy('order')
                    ->orderBy('id')
                    ->get();

                foreach ($children as $child) {
                    $child->delete();
                    $ids[] = (int) $child->id;
                    $childCount++;
                }
            }

            $point->delete();
        });

        $result = [
            'point_ids' => array_values(array_unique($ids)),
            'was_set' => $wasSet,
            'child_count' => $childCount,
            'label' => $label,
            'title' => $wasSet
                ? 'Usunięto set „'.$label.'”'
                : 'Usunięto punkt „'.$label.'”',
            'body' => $wasSet
                ? 'Usunięto set wraz z '.$childCount.' '.($childCount === 1 ? 'podpunktem' : 'podpunktami').'. Możesz to cofnąć.'
                : ($hadParent
                    ? 'Usunięto podpunkt setu. Możesz to cofnąć.'
                    : 'Usunięto punkt programu. Możesz to cofnąć.'),
        ];

        $this->rememberUndo((int) $point->event_id, $result);

        return $result;
    }

    /**
     * Przywraca punkt; dla rodzica setu — także soft-deleted dzieci.
     */
    public function restore(EventProgramPoint $point): int
    {
        $restored = 0;

        DB::transaction(function () use ($point, &$restored): void {
            $point = EventProgramPoint::withTrashed()->find($point->id);

            if (! $point) {
                return;
            }

            if ($point->trashed()) {
                $point->restore();
                $restored++;
            }

            if ($point->parent_id !== null) {
                return;
            }

            $children = EventProgramPoint::onlyTrashed()
                ->where('event_id', $point->event_id)
                ->where('parent_id', $point->id)
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            foreach ($children as $child) {
                $child->restore();
                $restored++;
            }
        });

        return $restored;
    }

    /**
     * @param  list<int>  $pointIds
     */
    public function restoreByIds(int $eventId, array $pointIds): int
    {
        $pointIds = array_values(array_unique(array_filter(
            array_map('intval', $pointIds),
            fn (int $id): bool => $id > 0
        )));

        if ($pointIds === []) {
            return 0;
        }

        $restored = 0;

        DB::transaction(function () use ($eventId, $pointIds, &$restored): void {
            $points = EventProgramPoint::onlyTrashed()
                ->where('event_id', $eventId)
                ->whereIn('id', $pointIds)
                ->get()
                ->sortBy(fn (EventProgramPoint $point): int => $point->parent_id === null ? 0 : 1);

            foreach ($points as $point) {
                $point->restore();
                $restored++;
            }
        });

        $this->forgetUndo($eventId);

        return $restored;
    }

    public function forceDelete(EventProgramPoint $point): void
    {
        DB::transaction(function () use ($point): void {
            if ($point->parent_id === null) {
                EventProgramPoint::withTrashed()
                    ->where('event_id', $point->event_id)
                    ->where('parent_id', $point->id)
                    ->orderBy('id')
                    ->each(fn (EventProgramPoint $child) => $child->forceDelete());
            }

            $fresh = EventProgramPoint::withTrashed()->find($point->id);
            $fresh?->forceDelete();
        });

        $this->forgetUndo((int) $point->event_id);
    }

    /**
     * Soft-delete dzieci wskazujących na nieistniejącego lub soft-deleted rodzica.
     */
    public function cleanupOrphans(Event $event): int
    {
        $aliveIds = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $orphans = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->whereNotNull('parent_id')
            ->when(
                $aliveIds !== [],
                fn ($query) => $query->whereNotIn('parent_id', $aliveIds),
                fn ($query) => $query
            )
            ->get();

        $deleted = 0;

        foreach ($orphans as $orphan) {
            $orphan->delete();
            $deleted++;
        }

        return $deleted;
    }

    /**
     * @return array{heading: string, description: string, was_set: bool, child_count: int, label: string}
     */
    public function describeDeletion(EventProgramPoint $point): array
    {
        $point->loadMissing(['templatePoint', 'parent.templatePoint', 'children']);
        $label = $this->resolveLabel($point);
        $childCount = $point->parent_id === null
            ? (int) ($point->relationLoaded('children')
                ? $point->children->count()
                : $point->children()->count())
            : 0;
        $wasSet = $point->parent_id === null && $childCount > 0;

        if ($wasSet) {
            return [
                'heading' => 'Usunąć cały set „'.$label.'”?',
                'description' => 'Usuniesz set wraz z '.$childCount.' '
                    .($childCount === 1 ? 'podpunktem' : 'podpunktami')
                    .'. Zaliczki, wpłaty i rezerwacje zostają w rozliczeniu i na liście rezerwacji. Po usunięciu możesz to cofnąć przyciskiem „Cofnij”.',
                'was_set' => true,
                'child_count' => $childCount,
                'label' => $label,
            ];
        }

        if ($point->parent_id) {
            $parentLabel = $point->parent
                ? $this->resolveLabel($point->parent)
                : 'set';

            return [
                'heading' => 'Usunąć podpunkt „'.$label.'”?',
                'description' => 'Usuniesz tylko ten element z setu „'.$parentLabel.'”. Zaliczki, wpłaty i rezerwacje zostają. Po usunięciu możesz to cofnąć.',
                'was_set' => false,
                'child_count' => 0,
                'label' => $label,
            ];
        }

        return [
            'heading' => 'Usunąć punkt „'.$label.'”?',
            'description' => 'Usuniesz ten punkt programu. Zaliczki, wpłaty i rezerwacje zostają w rozliczeniu i na liście rezerwacji. Po usunięciu możesz to cofnąć.',
            'was_set' => false,
            'child_count' => 0,
            'label' => $label,
        ];
    }

    /**
     * @return array{point_ids: list<int>, was_set: bool, child_count: int, label: string, title: string, body: string}|null
     */
    public function pendingUndo(int $eventId): ?array
    {
        $payload = session()->get($this->undoSessionKey($eventId));

        if (! is_array($payload) || empty($payload['point_ids']) || empty($payload['remembered_at'])) {
            return null;
        }

        $rememberedAt = strtotime((string) $payload['remembered_at']);

        if ($rememberedAt === false || (time() - $rememberedAt) > self::UNDO_TTL_SECONDS) {
            $this->forgetUndo($eventId);

            return null;
        }

        $stillTrashed = EventProgramPoint::onlyTrashed()
            ->where('event_id', $eventId)
            ->whereIn('id', $payload['point_ids'])
            ->exists();

        if (! $stillTrashed) {
            $this->forgetUndo($eventId);

            return null;
        }

        return [
            'point_ids' => array_map('intval', $payload['point_ids']),
            'was_set' => (bool) ($payload['was_set'] ?? false),
            'child_count' => (int) ($payload['child_count'] ?? 0),
            'label' => (string) ($payload['label'] ?? 'punkt'),
            'title' => (string) ($payload['title'] ?? 'Usunięto punkt programu'),
            'body' => (string) ($payload['body'] ?? 'Możesz to cofnąć.'),
        ];
    }

    public function hasPendingUndo(int $eventId): bool
    {
        return $this->pendingUndo($eventId) !== null;
    }

    public function forgetUndo(int $eventId): void
    {
        session()->forget($this->undoSessionKey($eventId));
    }

    /**
     * @param  array{
     *     point_ids: list<int>,
     *     was_set: bool,
     *     child_count: int,
     *     label: string,
     *     title: string,
     *     body: string
     * }  $result
     */
    public function rememberUndoPayload(int $eventId, array $result): void
    {
        $this->rememberUndo($eventId, $result);
    }

    /**
     * @param  array{
     *     point_ids: list<int>,
     *     was_set: bool,
     *     child_count: int,
     *     label: string,
     *     title: string,
     *     body: string
     * }  $result
     */
    protected function rememberUndo(int $eventId, array $result): void
    {
        session()->put($this->undoSessionKey($eventId), [
            'point_ids' => $result['point_ids'],
            'was_set' => $result['was_set'],
            'child_count' => $result['child_count'],
            'label' => $result['label'],
            'title' => $result['title'],
            'body' => $result['body'],
            'remembered_at' => now()->toIso8601String(),
        ]);
    }

    protected function undoSessionKey(int $eventId): string
    {
        return self::UNDO_SESSION_PREFIX.$eventId;
    }

    protected function resolveLabel(EventProgramPoint $point): string
    {
        return (string) ($point->name
            ?? $point->templatePoint?->name
            ?? ('#'.$point->id));
    }
}
