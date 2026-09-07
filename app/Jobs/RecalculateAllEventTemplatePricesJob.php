<?php

namespace App\Jobs;

use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Services\PriceRecalcProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Orkiestruje przeliczenie wszystkich szablonów — dzieli pracę na paczki,
 * żeby uniknąć limitu czasu pojedynczego joba w kolejce.
 */
class RecalculateAllEventTemplatePricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** Liczba szablonów na paczkę — bezpieczna przy ~5 s / szablon i limicie workera. */
    public const CHUNK_SIZE = 75;

    public int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function handle(): void
    {
        self::dispatchChunks($this->userId, sync: false);
    }

    /**
     * @param  bool  $sync  true = wykonaj paczki synchronicznie (CLI), false = kolejka
     */
    public static function dispatchChunks(int $userId, bool $sync = false): void
    {
        $ids = EventTemplate::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $total = count($ids);

        if ($userId) {
            PriceRecalcProgress::start($userId, $total);
        }

        self::removeDuplicatePricesStatic();

        if ($total === 0) {
            if ($userId) {
                PriceRecalcProgress::finish($userId);
            }

            return;
        }

        $chunks = array_chunk($ids, self::CHUNK_SIZE);
        $lastChunkIndex = count($chunks) - 1;

        foreach ($chunks as $index => $chunk) {
            $job = new RecalculateSelectedEventTemplatePricesJob(
                $chunk,
                $userId,
                false,
                $index === $lastChunkIndex,
                $index === $lastChunkIndex,
            );

            if ($sync) {
                dispatch_sync($job);
            } else {
                dispatch($job);
            }
        }
    }

    private static function removeDuplicatePricesStatic(): void
    {
        $duplicateGroups = EventTemplatePricePerPerson::select('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            $records = EventTemplatePricePerPerson::where([
                'event_template_id' => $group->event_template_id,
                'event_template_qty_id' => $group->event_template_qty_id,
                'currency_id' => $group->currency_id,
                'start_place_id' => $group->start_place_id,
            ])->orderByDesc('id')->get();

            for ($i = 1; $i < $records->count(); $i++) {
                $records[$i]->delete();
            }
        }
    }
}
