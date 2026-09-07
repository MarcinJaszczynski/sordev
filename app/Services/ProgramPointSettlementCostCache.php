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
 *
 * Punkty is_hotel korzystają z kosztu accommodation_hotel* (jak panel hotelu / Finanse),
 * nie z osobnego program_point — żeby wpłata w Finansach była widoczna w programie.
 */
class ProgramPointSettlementCostCache
{
    /** @var array<int, EventSettlementCost|null> */
    private array $baseCostsByPointId = [];

    /** @var array<int, EloquentCollection<int, EventSettlementCost>> */
    private array $paymentRowsByPointId = [];

    /** @var array<int, array{files_count: int, has_uploaded_file: bool, hint: string, status_label: string, badge_label: string|null, first_file_url: string|null}> */
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

        $pointsById = $points->keyBy(fn (EventProgramPoint $point): int => (int) $point->id);
        if ($childIds !== []) {
            $missingChildIds = collect($childIds)
                ->reject(fn (int $id): bool => $pointsById->has($id))
                ->values()
                ->all();
            if ($missingChildIds !== []) {
                EventProgramPoint::query()
                    ->whereIn('id', $missingChildIds)
                    ->get()
                    ->each(function (EventProgramPoint $point) use ($pointsById): void {
                        $pointsById->put((int) $point->id, $point);
                    });
            }
        }

        $settlement->loadMissing(['costs.plannedCurrency', 'costs.actualCurrency', 'documents']);
        $allCosts = $settlement->costs;
        $documents = $settlement->documents;
        $health = app(SettlementPaymentHealthService::class);
        $hotelSync = app(HotelStaySettlementSync::class);

        $event->loadMissing(['hotelStays']);

        $programPointCosts = $allCosts
            ->filter(fn (EventSettlementCost $cost): bool => in_array($cost->source_type, ['program_point', 'program_point_payment'], true)
                && $pointIds->contains((int) $cost->source_id));

        foreach ($pointIds as $pointId) {
            $pointId = (int) $pointId;
            $point = $pointsById->get($pointId);

            $hotelPlan = $point instanceof EventProgramPoint
                ? $hotelSync->findForProgramPoint($event, $point)
                : null;

            if ($hotelPlan instanceof EventSettlementCost) {
                $payments = $health->paymentRowsForPlanCost($hotelPlan, $allCosts)
                    ->filter(fn (EventSettlementCost $cost): bool => $cost->payment_status !== 'cancelled')
                    ->values();

                $this->baseCostsByPointId[$pointId] = $hotelPlan;
                $this->paymentRowsByPointId[$pointId] = $payments;
                $this->documentMetaByPointId[$pointId] = $this->buildDocumentMeta($hotelPlan, $payments, $documents);

                continue;
            }

            $rows = $programPointCosts->where('source_id', $pointId);
            $base = $rows->first(fn (EventSettlementCost $cost): bool => $cost->source_type === 'program_point');
            $payments = $rows
                ->filter(fn (EventSettlementCost $cost): bool => $cost->source_type === 'program_point_payment'
                    && $cost->payment_status !== 'cancelled')
                ->values();

            $this->baseCostsByPointId[$pointId] = $base;
            $this->paymentRowsByPointId[$pointId] = $payments;
            $this->documentMetaByPointId[$pointId] = $this->buildDocumentMeta($base, $payments, $documents);
        }
    }

    public function baseCost(int $pointId): ?EventSettlementCost
    {
        $base = $this->baseCostsByPointId[$pointId] ?? null;

        // Defensywa: nigdy nie zwracaj Collection / innych typów pod ?EventSettlementCost.
        return $base instanceof EventSettlementCost ? $base : null;
    }

    /**
     * @return EloquentCollection<int, EventSettlementCost>|Collection<int, EventSettlementCost>
     */
    public function paymentRows(int $pointId): Collection|EloquentCollection
    {
        return $this->paymentRowsByPointId[$pointId] ?? collect();
    }

    /**
     * @return array{files_count: int, has_uploaded_file: bool, hint: string, status_label: string, badge_label: string|null, first_file_url: string|null}
     */
    public function documentMeta(int $pointId): array
    {
        return $this->documentMetaByPointId[$pointId] ?? [
            'files_count' => 0,
            'has_uploaded_file' => false,
            'hint' => 'Brak pliku',
            'status_label' => 'Brak wgranego pliku faktury / dowodu',
            'badge_label' => null,
            'first_file_url' => null,
        ];
    }

    /**
     * @param  Collection<int, EventSettlementDocument>|EloquentCollection<int, EventSettlementDocument>  $documents
     * @param  Collection<int, EventSettlementCost>|EloquentCollection<int, EventSettlementCost>  $payments
     * @return array{files_count: int, has_uploaded_file: bool, hint: string, status_label: string, badge_label: string|null, first_file_url: string|null}
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

        $typeKey = (string) ($linkedDocs->first()?->document_type ?: '');
        $typeLabel = EventSettlementDocument::$documentTypes[$typeKey]
            ?? ($typeKey !== '' ? $typeKey : 'Plik');
        $badgeLabel = EventSettlementDocument::$documentTypeBadges[$typeKey]
            ?? ($typeKey !== '' ? $typeLabel : 'Plik');

        if ($filesCount > 0) {
            $first = basename((string) $filePaths->first());
            $number = (string) ($linkedDocs->first()?->document_number ?: ($numbers->first() ?? ''));
            $hint = $number !== ''
                ? $badgeLabel.': nr '.$number
                : ($filesCount === 1
                    ? $badgeLabel
                    : $badgeLabel.' ('.$filesCount.' pl.)');

            return [
                'files_count' => $filesCount,
                'has_uploaded_file' => true,
                'hint' => $hint,
                'status_label' => $typeLabel.' wgrany: '.$hint
                    .($number === '' && $filesCount > 0 ? ' · '.$first : ''),
                'badge_label' => $badgeLabel,
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
                'badge_label' => null,
                'first_file_url' => null,
            ];
        }

        return [
            'files_count' => 0,
            'has_uploaded_file' => false,
            'hint' => 'Brak pliku',
            'status_label' => 'Brak wgranego pliku faktury / dowodu',
            'badge_label' => null,
            'first_file_url' => null,
        ];
    }
}
