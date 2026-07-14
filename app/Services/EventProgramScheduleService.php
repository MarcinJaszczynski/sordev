<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class EventProgramScheduleService
{
    public function __construct(
        protected ProgramPointSetTimePropagator $setTimePropagator,
        protected EventProgramPointOrderService $orderService,
    ) {}

    public function bootstrapFromTemplate(Event $event, ?string $dayStart = null, bool $onlyUnlocked = false): int
    {
        $event = $event->fresh() ?? $event;
        $updated = 0;

        $maxDay = max(
            (int) ($event->duration_days ?? 1),
            (int) (EventProgramPoint::query()->where('event_id', $event->id)->max('day') ?? 1),
        );

        for ($day = 1; $day <= $maxDay; $day++) {
            $start = $dayStart ?? $event->programDayStartTimeLabel($day);
            $updated += $this->layoutDay($event, $day, $start, $onlyUnlocked);
        }

        $rootsWithChildren = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->whereNull('parent_id')
            ->whereHas('children')
            ->get();

        foreach ($rootsWithChildren as $root) {
            $updated += $this->setTimePropagator->propagateFromParent($root->fresh());
        }

        $updated += $this->orderService->repairOrderByStartTimes($event->fresh());

        return $updated;
    }

    public function relayoutDay(
        Event $event,
        int $day,
        bool $onlyUnlocked = true,
        ?string $dayStart = null,
        bool $includeTimedNonProgramPoints = false,
    ): int {
        $event = $event->fresh() ?? $event;
        $start = $dayStart ?? $event->programDayStartTimeLabel($day);
        $updated = $this->layoutDay($event, $day, $start, $onlyUnlocked, $includeTimedNonProgramPoints);

        $rootsWithChildren = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('day', $day)
            ->whereNull('parent_id')
            ->whereHas('children')
            ->get();

        foreach ($rootsWithChildren as $root) {
            $updated += $this->setTimePropagator->propagateFromParent($root->fresh());
        }

        $updated += $this->orderService->repairOrderByStartTimes($event->fresh(), $day);

        return $updated;
    }

    public function applyManualTimeChange(EventProgramPoint $point, string $startTime, string $endTime): void
    {
        if ((bool) ($point->hide_times ?? false)) {
            return;
        }

        $startTime = $this->normalizeTime($startTime);
        $endTime = $this->normalizeTime($endTime);

        if ($startTime === null || $endTime === null) {
            return;
        }

        if ($this->timeToMinutes($endTime) <= $this->timeToMinutes($startTime)) {
            throw new InvalidArgumentException('Godzina końca musi być późniejsza niż godzina startu.');
        }

        $payload = [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];

        if ($this->supportsManualLockColumn()) {
            $payload['times_manually_locked'] = true;
        }

        $point->update($payload);
        $point = $point->fresh();

        if ($point->parent_id === null) {
            $this->cascadeFollowingUnlocked($point);
        }

        if ($point->children()->exists()) {
            $this->setTimePropagator->propagateFromParent($point->fresh());
        }

        $this->orderService->repairOrderByStartTimes($point->event);
    }

    public function cascadeFollowingUnlocked(EventProgramPoint $anchor): int
    {
        if ($anchor->parent_id !== null || (bool) ($anchor->hide_times ?? false)) {
            return 0;
        }

        $anchor = $anchor->fresh();

        if (! $anchor?->end_time) {
            return 0;
        }

        $cursor = $this->timeToMinutes((string) $anchor->end_time);
        $updated = 0;

        $followingRoots = $this->rootPointsForDay($anchor->event_id, (int) $anchor->day)
            ->filter(fn (EventProgramPoint $point): bool => (int) $point->order > (int) $anchor->order);

        foreach ($followingRoots as $point) {
            if ((bool) ($point->hide_times ?? false)) {
                continue;
            }

            if ($this->isManuallyLocked($point) && filled($point->start_time) && filled($point->end_time)) {
                $cursor = max($cursor, $this->timeToMinutes((string) $point->end_time));

                continue;
            }

            $duration = $this->durationMinutes($point);
            $start = $cursor;
            $end = min($start + $duration, 23 * 60 + 59);

            if ($end <= $start) {
                $end = min($start + 15, 23 * 60 + 59);
            }

            $payload = [
                'start_time' => $this->minutesToTime($start),
                'end_time' => $this->minutesToTime($end),
            ];

            if ($this->supportsManualLockColumn()) {
                $payload['times_manually_locked'] = false;
            }

            $point->update($payload);
            $cursor = $end;
            $updated++;

            if ($point->children()->exists()) {
                $updated += $this->setTimePropagator->propagateFromParent($point->fresh());
            }
        }

        return $updated;
    }

    protected function layoutDay(
        Event $event,
        int $day,
        string $dayStart,
        bool $onlyUnlocked,
        bool $includeTimedNonProgramPoints = false,
    ): int {
        $cursor = $this->timeToMinutes($dayStart);
        $updated = 0;

        foreach ($this->rootPointsForDay($event->id, $day, $includeTimedNonProgramPoints) as $point) {
            if ((bool) ($point->hide_times ?? false)) {
                if (filled($point->end_time)) {
                    $cursor = max($cursor, $this->timeToMinutes((string) $point->end_time));
                }

                continue;
            }

            if ($onlyUnlocked && $this->isManuallyLocked($point) && filled($point->start_time) && filled($point->end_time)) {
                $cursor = max($cursor, $this->timeToMinutes((string) $point->end_time));

                continue;
            }

            if ($onlyUnlocked && $this->isManuallyLocked($point)) {
                continue;
            }

            if ($onlyUnlocked) {
                [$start, $end] = $this->resolveChainedWindow($point, $cursor);
            } else {
                [$start, $end] = $this->resolveBootstrapWindow($point, $cursor);
            }

            $payload = [
                'start_time' => $this->minutesToTime($start),
                'end_time' => $this->minutesToTime($end),
            ];

            if ($this->supportsManualLockColumn()) {
                $payload['times_manually_locked'] = false;
            }

            $point->update($payload);
            $cursor = $end;
            $updated++;
        }

        return $updated;
    }

    /**
     * @return Collection<int, EventProgramPoint>
     */
    protected function rootPointsForDay(int $eventId, int $day, bool $includeTimedNonProgramPoints = false): Collection
    {
        return EventProgramPoint::query()
            ->where('event_id', $eventId)
            ->where('day', $day)
            ->whereNull('parent_id')
            ->when(
                $includeTimedNonProgramPoints,
                fn ($query) => $query->where(function ($query): void {
                    $query
                        ->where('include_in_program', true)
                        ->orWhere(function ($query): void {
                            $query
                                ->whereNotNull('start_time')
                                ->whereNotNull('end_time');
                        });
                }),
                fn ($query) => $query->where('include_in_program', true),
            )
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function resolveBootstrapWindow(EventProgramPoint $point, int $cursor): array
    {
        $duration = $this->durationMinutes($point);
        $startMinutes = filled($point->start_time) ? $this->timeToMinutes((string) $point->start_time) : null;
        $endMinutes = filled($point->end_time) ? $this->timeToMinutes((string) $point->end_time) : null;

        if ($startMinutes !== null && $endMinutes !== null && $endMinutes > $startMinutes) {
            return [$startMinutes, $endMinutes];
        }

        if ($startMinutes !== null) {
            return [$startMinutes, $startMinutes + $duration];
        }

        return $this->resolveChainedWindow($point, $cursor);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function resolveChainedWindow(EventProgramPoint $point, int $cursor): array
    {
        $duration = $this->durationMinutes($point);
        $start = $cursor;
        $end = min($start + $duration, 23 * 60 + 59);

        if ($end <= $start) {
            $end = min($start + 15, 23 * 60 + 59);
        }

        return [$start, $end];
    }

    protected function durationMinutes(EventProgramPoint $point): int
    {
        $fromDuration = ((int) ($point->duration_hours ?? 0) * 60) + (int) ($point->duration_minutes ?? 0);

        if ($fromDuration > 0) {
            return $fromDuration;
        }

        if (filled($point->start_time) && filled($point->end_time)) {
            $diff = $this->timeToMinutes((string) $point->end_time) - $this->timeToMinutes((string) $point->start_time);

            if ($diff > 0) {
                return $diff;
            }
        }

        return 60;
    }

    protected function isManuallyLocked(EventProgramPoint $point): bool
    {
        if (! $this->supportsManualLockColumn()) {
            return false;
        }

        return (bool) ($point->times_manually_locked ?? false);
    }

    protected function supportsManualLockColumn(): bool
    {
        return Schema::hasColumn('event_program_points', 'times_manually_locked');
    }

    protected function normalizeTime(?string $time): ?string
    {
        if (! filled($time)) {
            return null;
        }

        $time = trim((string) $time);

        try {
            return Carbon::createFromFormat(strlen($time) > 5 ? 'H:i:s' : 'H:i', strlen($time) > 5 ? substr($time, 0, 8) : $time)
                ->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function timeToMinutes(string $time): int
    {
        $time = trim($time);

        if ($time === '') {
            return 0;
        }

        try {
            $normalized = strlen($time) > 5 ? substr($time, 0, 8) : $time;
            $format = strlen($time) > 5 ? 'H:i:s' : 'H:i';
            $parsed = Carbon::createFromFormat($format, $normalized);

            return $parsed->hour * 60 + $parsed->minute;
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function minutesToTime(int $minutes): string
    {
        $minutes = max(0, min($minutes, 23 * 60 + 59));

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}
