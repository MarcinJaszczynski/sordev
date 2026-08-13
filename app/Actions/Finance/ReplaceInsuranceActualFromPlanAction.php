<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\RecordSettlementCostPaymentData;
use App\Models\EventSettlementCost;
use App\Services\SettlementPaymentHealthService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * „Zastąp rzeczywiste” dla ubezpieczeń:
 * - opcjonalnie dołącza pliki polisy + opis,
 * - księguje pełną płatność faktury (advance_type=full) na kwotę z dokumentów lub planu.
 *
 * Plan (ustalenia) pozostaje bez zmian — zmienia się warstwa zapłacona / dokumenty.
 */
final class ReplaceInsuranceActualFromPlanAction
{
    public function __construct(
        private readonly RecordSettlementCostPaymentAction $recordPayment,
        private readonly AttachSettlementCostDocumentAction $attachDocument,
        private readonly SettlementPaymentHealthService $health,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function __invoke(
        EventSettlementCost $planCost,
        ?int $paidByUserId = null,
        array $files = [],
        ?string $description = null,
        ?float $actualAmountPln = null,
    ): EventSettlementCost {
        if ($planCost->source_type !== 'insurance_day') {
            throw new InvalidArgumentException('Akcja dotyczy wyłącznie pozycji ubezpieczeń.');
        }

        return DB::transaction(function () use ($planCost, $paidByUserId, $files, $description, $actualAmountPln): EventSettlementCost {
            $settlement = $planCost->settlement()->firstOrFail();
            \Illuminate\Support\Facades\Gate::authorize('recordCostPayment', $settlement);

            if ($files !== []) {
                ($this->attachDocument)(
                    planCost: $planCost,
                    files: $files,
                    documentType: 'invoice',
                    documentNumber: $planCost->document_number ?? $planCost->invoice_number,
                    notes: $description ?? 'Polisa — zastąpienie rzeczywistym',
                );
            }

            $settlement->load('documents');
            $amount = $actualAmountPln;
            if ($amount === null || $amount <= 0) {
                $amount = $this->resolveAmountFromDocuments($planCost, $settlement)
                    ?? (float) ($planCost->planned_amount_pln ?? $planCost->planned_amount ?? 0);
            }

            $amount = round((float) $amount, 2);
            if ($amount <= SettlementPaymentHealthService::TOLERANCE) {
                throw new InvalidArgumentException('Brak kwoty do zastąpienia rzeczywistego (plan/dokument).');
            }

            $allCosts = $settlement->fresh(['costs'])->costs;
            $alreadyPaid = $this->health->paidPlnForPlanCost($planCost, $allCosts);
            $delta = round($amount - $alreadyPaid, 2);

            if ($delta > SettlementPaymentHealthService::TOLERANCE) {
                ($this->recordPayment)(new RecordSettlementCostPaymentData(
                    planCost: $planCost->fresh() ?? $planCost,
                    amountPln: $delta,
                    paymentMethod: 'transfer',
                    paidBy: (string) ($planCost->paid_by ?? 'office'),
                    advanceType: 'full',
                    paidAt: now(),
                    notes: $description ?? 'Zastąpienie rzeczywistą polisą / fakturą ubezpieczenia',
                    paidByUserId: $paidByUserId,
                ));
            } elseif ($delta < -SettlementPaymentHealthService::TOLERANCE) {
                // Już zapłacono więcej niż docelowa kwota polisy — tylko opis / dokumenty.
                $planCost->update([
                    'notes' => trim(implode("\n", array_filter([
                        $planCost->notes,
                        $description ?? 'Zastąpienie rzeczywistym — kwota polisy niższa niż dotychczasowe wpłaty (sprawdź nadpłatę).',
                    ]))),
                ]);
            } else {
                // Kwota już pokryta — upewnij się, że jest oznaczenie pełnej płatności przez sync.
                $fresh = $settlement->fresh(['costs']);
                if ($fresh) {
                    $this->health->syncPlanPaymentStatus($planCost->fresh() ?? $planCost, $fresh->costs);
                }
            }

            if (filled($description) && $delta > SettlementPaymentHealthService::TOLERANCE) {
                // opis trafił na wpłatę; plan notes opcjonalnie
            } elseif (filled($description)) {
                $planCost->update([
                    'notes' => trim(implode("\n", array_filter([$planCost->notes, $description]))),
                ]);
            }

            return $planCost->fresh() ?? $planCost;
        });
    }

    private function resolveAmountFromDocuments(EventSettlementCost $planCost, $settlement): ?float
    {
        $costId = (int) $planCost->id;
        $docs = $settlement->documents
            ?? $settlement->documents()->get();

        $totals = $docs
            ->filter(function ($doc) use ($costId): bool {
                $linked = collect($doc->linked_cost_ids ?? [])->map(fn ($id) => (int) $id)->all();

                return in_array($costId, $linked, true)
                    && filled($doc->total_amount)
                    && (float) $doc->total_amount > 0;
            })
            ->sum(fn ($doc) => (float) $doc->total_amount);

        return $totals > 0 ? round((float) $totals, 2) : null;
    }
}
