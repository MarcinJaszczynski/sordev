<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventProgramPoint;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NormalizeProgramPointTimes extends Command
{
    protected $signature = 'planner:normalize-program-times
        {--event= : ID imprezy (jeśli puste, wszystkie)}
        {--hours= : Ręczne przesunięcie godzinowe (np. 1, 2, -1)}
        {--dry-run : Tylko podgląd zmian bez zapisu}';

    protected $description = 'Normalizuje godziny start/end punktow programu po blednej konwersji UTC z planera';

    public function handle(): int
    {
        $eventId = $this->option('event');
        $manualHours = $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');

        $query = EventProgramPoint::query()
            ->where(function ($q): void {
                $q->whereNotNull('start_time')
                    ->orWhereNotNull('end_time');
            })
            ->with('event:id,start_date');

        if (! empty($eventId)) {
            $query->where('event_id', (int) $eventId);
        }

        $points = $query->orderBy('event_id')->orderBy('day')->orderBy('id')->get();

        if ($points->isEmpty()) {
            $this->warn('Brak punktow do aktualizacji.');
            return self::SUCCESS;
        }

        $changed = 0;
        $skipped = 0;

        foreach ($points as $point) {
            $offsetMinutes = $this->resolveOffsetMinutes($point, $manualHours);
            if ($offsetMinutes === 0) {
                $skipped++;
                continue;
            }

            $newStart = $this->shiftClockTime($point->start_time, $offsetMinutes);
            $newEnd = $this->shiftClockTime($point->end_time, $offsetMinutes);

            $startChanged = $newStart !== $point->start_time;
            $endChanged = $newEnd !== $point->end_time;

            if (! $startChanged && ! $endChanged) {
                $skipped++;
                continue;
            }

            $changed++;

            $this->line(sprintf(
                'event=%d point=%d day=%d start: %s -> %s | end: %s -> %s',
                (int) $point->event_id,
                (int) $point->id,
                (int) ($point->day ?? 1),
                (string) ($point->start_time ?? 'null'),
                (string) ($newStart ?? 'null'),
                (string) ($point->end_time ?? 'null'),
                (string) ($newEnd ?? 'null')
            ));

            if ($dryRun) {
                continue;
            }

            $point->update([
                'start_time' => $newStart,
                'end_time' => $newEnd,
            ]);
        }

        $this->newLine();
        $this->info('Podsumowanie:');
        $this->line('  Zmienionych: ' . $changed);
        $this->line('  Pominietych: ' . $skipped);
        $this->line('  Tryb: ' . ($dryRun ? 'dry-run' : 'zapis'));

        return self::SUCCESS;
    }

    private function resolveOffsetMinutes(EventProgramPoint $point, mixed $manualHours): int
    {
        if ($manualHours !== null && $manualHours !== '') {
            return (int) round((float) $manualHours * 60);
        }

        $tz = config('app.timezone', 'UTC');
        $eventStartDate = $point->event?->start_date;

        $baseDate = $eventStartDate
            ? Carbon::parse($eventStartDate, $tz)->startOfDay()
            : now($tz)->startOfDay();

        $dayOffset = max(0, ((int) ($point->day ?? 1)) - 1);
        $pointDate = $baseDate->copy()->addDays($dayOffset);

        return (int) $pointDate->utcOffset();
    }

    private function shiftClockTime(?string $time, int $offsetMinutes): ?string
    {
        if (! $time) {
            return null;
        }

        $normalized = substr($time, 0, 8);
        if (strlen($normalized) === 5) {
            $normalized .= ':00';
        }

        return Carbon::parse($normalized, 'UTC')
            ->addMinutes($offsetMinutes)
            ->format('H:i:s');
    }
}
