<?php

namespace App\Console\Commands;

use App\Models\EventSettlement;
use App\Models\Reservation;
use Illuminate\Console\Command;

class ReservationsSyncAudit extends Command
{
    protected $signature = 'reservations:sync-audit
        {--fix : Automatycznie napraw wykryte niespójności}
        {--event= : Ogranicz do konkretnego event_id}
        {--limit=0 : Limit rezerwacji do analizy (0 = bez limitu)}';

    protected $description = 'Sprawdza i opcjonalnie naprawia spójność powiązań rezerwacji z punktami programu i kosztami rozliczeń';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $eventId = $this->option('event');
        $limit = max(0, (int) $this->option('limit'));

        $query = Reservation::query()
            ->with(['programPoint.event', 'settlementCost.settlement'])
            ->orderBy('id');

        if ($eventId !== null && $eventId !== '') {
            $query->where('event_id', (int) $eventId);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('Brak rezerwacji do analizy.');

            return self::SUCCESS;
        }

        $issues = 0;
        $fixed = 0;
        $reportRows = [];

        foreach ($rows as $reservation) {
            $detected = $this->detectIssues($reservation);
            if (empty($detected)) {
                continue;
            }

            $issues++;
            $reportRows[] = [
                'id' => $reservation->id,
                'event_id' => $reservation->event_id,
                'program_point_id' => $reservation->program_point_id,
                'settlement_cost_id' => $reservation->settlement_cost_id,
                'issues' => implode(', ', $detected),
            ];

            if (! $fix) {
                continue;
            }

            if ($this->fixReservation($reservation)) {
                $fixed++;
            }
        }

        if (! empty($reportRows)) {
            $this->table(
                ['ID', 'Event', 'Program Point', 'Settlement Cost', 'Problemy'],
                array_slice($reportRows, 0, 100)
            );

            if (count($reportRows) > 100) {
                $this->warn('Pokazano pierwsze 100 rekordów z problemami.');
            }
        }

        $this->newLine();
        $this->info('Przeanalizowane: '.$rows->count());
        $this->info('Wykryte niespójności: '.$issues);

        if ($fix) {
            $this->info('Naprawione rekordy: '.$fixed);
        } else {
            $this->line('Uruchom z --fix, aby naprawić wykryte rekordy.');
        }

        return self::SUCCESS;
    }

    private function detectIssues(Reservation $reservation): array
    {
        $issues = [];

        $programPoint = $reservation->programPoint;
        $settlementCost = $reservation->settlementCost;

        if ($settlementCost && $settlementCost->source_type === 'program_point' && $settlementCost->source_id && ! $reservation->program_point_id) {
            $issues[] = 'missing_program_point_from_settlement_cost';
        }

        if ($programPoint && $reservation->event_id && (int) $reservation->event_id !== (int) $programPoint->event_id) {
            $issues[] = 'event_mismatch_vs_program_point';
        }

        if ($settlementCost && $settlementCost->settlement && $reservation->event_id && (int) $reservation->event_id !== (int) $settlementCost->settlement->event_id) {
            $issues[] = 'event_mismatch_vs_settlement';
        }

        if (
            $reservation->program_point_id
            && $settlementCost
            && $settlementCost->source_type === 'program_point'
            && $settlementCost->source_id
            && (int) $settlementCost->source_id !== (int) $reservation->program_point_id
        ) {
            $issues[] = 'program_point_mismatch_vs_settlement_cost';
        }

        if ($reservation->program_point_id && ! $reservation->settlement_cost_id) {
            $issues[] = 'missing_settlement_cost_for_program_point';
        }

        return $issues;
    }

    private function fixReservation(Reservation $reservation): bool
    {
        $reservation->loadMissing(['programPoint.event', 'settlementCost.settlement']);

        $updates = [];

        if (
            ! $reservation->program_point_id
            && $reservation->settlementCost
            && $reservation->settlementCost->source_type === 'program_point'
            && $reservation->settlementCost->source_id
        ) {
            $updates['program_point_id'] = (int) $reservation->settlementCost->source_id;
        }

        if ($reservation->programPoint?->event) {
            $event = $reservation->programPoint->event;
            $settlement = EventSettlement::findOrCreateActiveForEvent($event);
            $cost = $settlement->upsertCostFromProgramPoint(
                $reservation->programPoint->fresh(['templatePoint', 'currency', 'reservations'])
            );

            $updates['event_id'] = $event->id;
            $updates['settlement_cost_id'] = $cost->id;
        } elseif ($reservation->settlementCost?->settlement) {
            $updates['event_id'] = $reservation->settlementCost->settlement->event_id;
        }

        if (empty($updates)) {
            return false;
        }

        $reservation->forceFill($updates)->saveQuietly();

        return true;
    }
}
