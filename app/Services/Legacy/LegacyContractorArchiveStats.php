<?php

namespace App\Services\Legacy;

use App\Models\Contractor;
use App\Models\LegacyEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyContractorArchiveStats
{
    /**
     * @return array{
     *     total: int,
     *     completed: int,
     *     cancelled: int,
     *     other: int,
     *     as_purchaser: int,
     *     as_executor: int,
     *     participants_completed: int,
     *     participants_all: int,
     *     first_start: ?Carbon,
     *     last_start: ?Carbon,
     *     years_label: string,
     * }
     */
    public function forContractor(Contractor $contractor): array
    {
        if (! Schema::hasTable('legacy_events')) {
            return $this->empty();
        }

        $base = $this->eventsQuery($contractor);
        $total = (clone $base)->count();
        if ($total === 0) {
            return $this->empty();
        }

        $completed = (clone $base)->where('legacy_events.legacy_status', 'Zakończona')->count();
        $cancelled = (clone $base)->where('legacy_events.legacy_status', 'Anulowana')->count();
        $other = max(0, $total - $completed - $cancelled);

        $participantsCompleted = (int) (clone $base)
            ->where('legacy_events.legacy_status', 'Zakończona')
            ->sum('legacy_events.participant_count');

        $participantsAll = (int) (clone $base)->sum('legacy_events.participant_count');

        $first = (clone $base)->whereNotNull('legacy_events.start_datetime')->orderBy('legacy_events.start_datetime')->value('legacy_events.start_datetime');
        $last = (clone $base)->whereNotNull('legacy_events.start_datetime')->orderByDesc('legacy_events.start_datetime')->value('legacy_events.start_datetime');

        $firstStart = $first ? Carbon::parse($first) : null;
        $lastStart = $last ? Carbon::parse($last) : null;

        $yearsLabel = '—';
        if ($firstStart && $lastStart) {
            $y1 = $firstStart->format('Y');
            $y2 = $lastStart->format('Y');
            $yearsLabel = $y1 === $y2 ? $y1 : $y1.'–'.$y2;
        }

        $asPurchaser = 0;
        $asExecutor = 0;
        if (Schema::hasTable('contractor_legacy_event')) {
            $asPurchaser = (int) DB::table('contractor_legacy_event')
                ->where('contractor_id', $contractor->id)
                ->where('role', 'purchaser')
                ->count();
            $asExecutor = (int) DB::table('contractor_legacy_event')
                ->where('contractor_id', $contractor->id)
                ->whereIn('role', ['executor', 'payment'])
                ->count();
        } else {
            $asPurchaser = $total;
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'other' => $other,
            'as_purchaser' => $asPurchaser,
            'as_executor' => $asExecutor,
            'participants_completed' => $participantsCompleted,
            'participants_all' => $participantsAll,
            'first_start' => $firstStart,
            'last_start' => $lastStart,
            'years_label' => $yearsLabel,
        ];
    }

    private function eventsQuery(Contractor $contractor)
    {
        if (Schema::hasTable('contractor_legacy_event')) {
            return LegacyEvent::query()
                ->select('legacy_events.*')
                ->join('contractor_legacy_event', 'contractor_legacy_event.legacy_event_id', '=', 'legacy_events.id')
                ->where('contractor_legacy_event.contractor_id', $contractor->id);
        }

        return LegacyEvent::query()
            ->select('legacy_events.*')
            ->where('legacy_events.contractor_id', $contractor->id);
    }

    /**
     * @return array{
     *     total: int,
     *     completed: int,
     *     cancelled: int,
     *     other: int,
     *     as_purchaser: int,
     *     as_executor: int,
     *     participants_completed: int,
     *     participants_all: int,
     *     first_start: ?Carbon,
     *     last_start: ?Carbon,
     *     years_label: string,
     * }
     */
    private function empty(): array
    {
        return [
            'total' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'other' => 0,
            'as_purchaser' => 0,
            'as_executor' => 0,
            'participants_completed' => 0,
            'participants_all' => 0,
            'first_start' => null,
            'last_start' => null,
            'years_label' => '—',
        ];
    }
}
