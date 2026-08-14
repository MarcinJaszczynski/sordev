<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Jednorazowy preload kosztów rozliczenia dla punktów programu (lista / RM).
 */
class ProgramPointSettlementCostCache
{
    /** @var array<int, EventSettlementCost|null> */
    private array $baseCostsByPointId = [];

    /** @var array<int, EloquentCollection<int, EventSettlementCost>> */
    private array $paymentRowsByPointId = [];

    /** @var array<int, array{files_count: int, has_uploaded_file: bool, hint: string, status_label: string, first_file_url: string|null}> */
    private array $documentMetaByPointId = [];

    private bool $warmed = false;

    /**
     * @param  Collection<int, EventProgramPoint>|EloquentCollection<int, EventProgramPoint>  $points
     */
    public function warm(Collection|EloquentCollection $points, Event $event): void
    {
        if ($this->warmed || $points->isEmpty()) {
            return;
        }

        $this->warmed = true;

        $settlement = $event->relationLoaded('activeSettlement')
            ? $event->activeSettlement
            : $event->activeSettlement()->first();

        if (! $settlement) {
            return;
        }

        $pointIds = $points->pluck('id')->filter()->unique()->values();

        $aggregator = app(ProgramPointSetFinanceAggregator::class);
        $childIds = $aggregator->childIdsForParents($points);
        if ($childIds !== []) {
            $pointIds = $pointIds->merge($childIds)->unique()->values();
        }

        if ($pointIds->isEmpty()) {
            return;
        }

        $costs = $settlement->costs()
            ->whereIn('source_id', $pointIds)
            ->whereIn('source_type', ['program_point', 'program_point_payment'])
            ->with(['plannedCurrency', 'actualCurrency'])
            ->get();

        $documents = $settlement->relationLoaded('documents')
            ? $settlement->documents
            : $settlement->documents()->get();

        foreach ($pointIds as $pointId) {
            $rows = $costs->where('source_id', $pointId);
            $base = $rows->first(fn (EventSettlementCost $cost): bool => $cost->source_type === 'program_point');
            $payments = $rows
                ->filter(fn (EventSettlementCost $cost): bool => $cost->source_type === 'program_point_payment'
                    && $cost->payment_status !== 'cancelled')
                ->values();

            $this->baseCostsByPointId[(int) $pointId] = $base;
            $this->paymentRowsByPointId[(int) $pointId] = $payments;
            $this->documentMetaByPointId[(int) $pointId] = $this->buildDocumentMeta($base, $payments, $documents);
        }
    }

    public function baseCost(int $pointId): ?EventSettlementCost
    {
        return $this->baseCostsByPointId[$pointId] ?? null;
    }

    /**
     * @return EloquentCollection<int, EventSettlementCost>|Collection<int, EventSettlementCost>
     */
    public function paymentRows(int $pointId): Collection|EloquentCollection
    {
        return $this->paymentRowsByPointId[$pointId] ?? collect();
    }

    /**
     * @return array{files_count: int, has_uploaded_file: bool, hint: string, status_label: string, first_file_url: string|null}
     */
    public function documentMeta(int $pointId): array
    {
        return $this->documentMetaByPointId[$pointId] ?? [
            'files_count' => 0,
            'has_uploaded_file' => false,
            'hint' => 'Brak pliku',
            'status_label' => 'Brak wgranego pliku faktury / dowodu',
            'first_file_url' => null,
        ];
    }

    /**
     * @param  Collection<int, EventSettlementDocument>|EloquentCollection<int, EventSettlementDocument>  $documents
     * @param  Collection<int, EventSettlementCost>|EloquentCollection<int, EventSettlementCost>  $payments
     * @return array{files_count: int, has_uploaded_file: bool, hint: string, status_label: string, first_file_url: string|null}
     */
    private function buildDocumentMeta(
        ?EventSettlementCost $base,
        Collection|EloquentCollection $payments,
        Collection|EloquentCollection $documents,
    ): array {
        $ids = collect($base ? [$base->id] : [])
            ->merge($payments->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $linkedDocs = $documents->filter(function ($doc) use ($ids): bool {
            $linked = collect($doc->linked_cost_ids ?? [])->map(fn ($id) => (int) $id)->all();

            return count(array_intersect($ids, $linked)) > 0;
        });

        $filePaths = $linkedDocs
            ->flatMap(fn ($doc): array => collect($doc->files ?? [])
                ->filter(fn ($path) => is_string($path) && $path !== '')
                ->values()
                ->all())
            ->values();

        $filesCount = $filePaths->count();
        $firstFileUrl = $filesCount > 0
            ? Storage::disk('public')->url((string) $filePaths->first())
            : null;
        $numbers = $payments
            ->map(fn (EventSettlementCost $p): ?string => $p->document_number ?: $p->invoice_number)
            ->filter(fn (?string $n): bool => filled($n))
            ->unique()
            ->values();

        if ($filesCount > 0) {
            $first = basename((string) $filePaths->first());
            $type = EventSettlementDocument::$documentTypes[$linkedDocs->first()?->document_type ?? '']
                ?? ((string) ($linkedDocs->first()?->document_type ?: 'Plik'));
            $number = (string) ($linkedDocs->first()?->document_number ?: ($numbers->first() ?? ''));
            $hint = $number !== ''
                ? $type.': nr '.$number
                : ($filesCount === 1
                    ? $type
                    : $type.' ('.$filesCount.' pl.)');

            return [
                'files_count' => $filesCount,
                'has_uploaded_file' => true,
                'hint' => $hint,
                'status_label' => 'Faktura / dokument wgrany: '.$hint
                    .($number === '' && $filesCount > 0 ? ' · '.$first : ''),
                'first_file_url' => $firstFileUrl,
            ];
        }

        if ($numbers->isNotEmpty()) {
            $joined = $numbers->take(2)->implode(', ');

            return [
                'files_count' => 0,
                'has_uploaded_file' => false,
                'hint' => 'Nr '.$joined.' (bez pliku)',
                'status_label' => 'Brak wgranego pliku — jest numer: '.$joined,
                'first_file_url' => null,
            ];
        }

        return [
            'files_count' => 0,
            'has_uploaded_file' => false,
            'hint' => 'Brak pliku',
            'status_label' => 'Brak wgranego pliku faktury / dowodu',
            'first_file_url' => null,
        ];
    }
}
