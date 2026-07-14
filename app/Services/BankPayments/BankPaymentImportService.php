<?php

namespace App\Services\BankPayments;

use App\Models\BankPaymentImportBatch;
use App\Models\BankPaymentImportLine;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventSettlementParticipantPaymentEntry;
use App\Services\ContractPaymentSyncService;
use App\Services\ParticipantPaymentLedgerService;
use App\Services\PilotAdvanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BankPaymentImportService
{
    public function __construct(
        private readonly MillenniumCsvParser $parser = new MillenniumCsvParser,
        private readonly BankPaymentMatcher $matcher = new BankPaymentMatcher,
        private readonly ?ContractPaymentSyncService $contractPaymentSync = null,
    ) {}

    protected function contractPaymentSync(): ContractPaymentSyncService
    {
        return $this->contractPaymentSync ?? app(ContractPaymentSyncService::class);
    }

    /**
     * @return array{
     *     batch_id: int,
     *     total: int,
     *     matched: int,
     *     unmatched: int,
     *     duplicate: int
     * }
     */
    public function createPreviewFromCsv(string $content, ?string $filename = null, ?int $userId = null): array
    {
        if (! Schema::hasTable('bank_payment_import_batches')) {
            throw new \RuntimeException('Brak tabel importu wpłat — uruchom migracje.');
        }

        $transactions = $this->parser->parse($content);

        $batch = BankPaymentImportBatch::query()->create([
            'imported_by' => $userId,
            'bank' => 'millennium',
            'source_filename' => $filename,
            'lines_total' => count($transactions),
            'status' => 'preview',
        ]);

        $matched = 0;
        $unmatched = 0;
        $duplicate = 0;

        foreach ($transactions as $transaction) {
            $fingerprint = $this->fingerprint($transaction);
            $existing = BankPaymentImportLine::query()
                ->where('fingerprint', $fingerprint)
                ->where('applied', true)
                ->exists();

            if ($existing) {
                $duplicate++;

                continue;
            }

            $match = $this->matcher->match($transaction);

            if ($match['match_status'] === 'unmatched') {
                $unmatched++;
            } else {
                $matched++;
            }

            BankPaymentImportLine::query()->create([
                'batch_id' => $batch->id,
                'operation_date' => $transaction['operation_date'],
                'title' => $transaction['title'],
                'counterparty' => $transaction['counterparty'],
                'account_number' => $transaction['account_number'],
                'amount_pln' => $transaction['amount_pln'],
                'fingerprint' => $fingerprint,
                'match_status' => $match['match_status'],
                'match_reason' => $match['match_reason'],
                'contract_id' => $match['contract_id'],
                'participant_payment_id' => $match['participant_payment_id'],
                'event_id' => $match['event_id'],
                'selected' => $match['match_status'] !== 'unmatched',
            ]);
        }

        $batch->update([
            'lines_total' => $batch->lines()->count(),
            'meta' => [
                'duplicate_skipped' => $duplicate,
            ],
        ]);

        return [
            'batch_id' => $batch->id,
            'total' => $batch->lines()->count(),
            'matched' => $matched,
            'unmatched' => $unmatched,
            'duplicate' => $duplicate,
        ];
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return array{applied: int, skipped: int, errors: array<int, string>}
     */
    public function applyLines(BankPaymentImportBatch $batch, array $lineIds): array
    {
        $result = [
            'applied' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $lines = $batch->lines()
            ->whereIn('id', $lineIds)
            ->where('applied', false)
            ->get();

        DB::transaction(function () use ($lines, &$result): void {
            foreach ($lines as $line) {
                try {
                    if (! $line->selected) {
                        $result['skipped']++;

                        continue;
                    }

                    if ($line->match_status === 'unmatched') {
                        $result['skipped']++;
                        $result['errors'][] = 'Linia #'.$line->id.': brak dopasowania.';

                        continue;
                    }

                    $this->applyLine($line);
                    $line->forceFill([
                        'applied' => true,
                        'applied_at' => now(),
                        'apply_notes' => 'Zaksięgowano z importu Millennium',
                    ])->save();

                    $result['applied']++;
                } catch (\Throwable $exception) {
                    $result['skipped']++;
                    $result['errors'][] = 'Linia #'.$line->id.': '.$exception->getMessage();
                }
            }
        });

        $batch->update([
            'lines_applied' => $batch->lines()->where('applied', true)->count(),
            'lines_skipped' => $batch->lines()->where('applied', false)->count(),
            'status' => 'completed',
        ]);

        return $result;
    }

    private function applyLine(BankPaymentImportLine $line): void
    {
        $paidAt = $line->operation_date
            ? Carbon::parse($line->operation_date)->startOfDay()
            : now();

        if ($line->match_status === 'matched_pilot_advance') {
            if (! $line->event_id) {
                throw new \RuntimeException('Brak przypisanej imprezy dla zaliczki pilota.');
            }

            if (! Schema::hasColumn('events', 'pilot_funds_paid')) {
                throw new \RuntimeException('Pole zaliczki pilota nie jest dostępne w bazie.');
            }

            $event = Event::query()->findOrFail($line->event_id);
            $payload = [
                'pilot_funds_paid' => true,
                'pilot_funds_paid_at' => $paidAt,
                'pilot_funds_paid_by' => Auth::id(),
            ];

            if (Schema::hasColumn('events', 'pilot_advance_planned_amount')
                && blank($event->pilot_advance_planned_amount)
                && $line->amount_pln > 0) {
                $payload['pilot_advance_planned_amount'] = round((float) $line->amount_pln, 2);
                $payload['pilot_advance_planned_at'] = $paidAt;
                $payload['pilot_advance_planned_by'] = Auth::id();
            }

            $event->forceFill($payload)->save();
            app(PilotAdvanceService::class)->syncPaidAdvanceToSettlementCash($event->fresh());

            return;
        }

        if ($line->contract_id) {
            $contract = Contract::query()->findOrFail($line->contract_id);
            $newPaid = round((float) $contract->amount_paid + (float) $line->amount_pln, 2);

            $contract->forceFill([
                'amount_paid' => $newPaid,
                'paid_at' => $paidAt,
                'client_payment_method' => $contract->client_payment_method ?: 'transfer',
                'payment_status' => $newPaid >= (float) $contract->total_price ? 'paid' : 'pending',
            ])->save();

            $this->contractPaymentSync()->sync($contract->fresh());

            if ($contract->participant_payment_id) {
                $payment = EventSettlementParticipantPayment::query()->find($contract->participant_payment_id);
                if ($payment) {
                    $this->recordParticipantBankImportEntry($payment, $line, $paidAt);
                }
            }

            return;
        }

        if ($line->participant_payment_id) {
            $payment = EventSettlementParticipantPayment::query()->findOrFail($line->participant_payment_id);

            $this->recordParticipantBankImportEntry($payment, $line, $paidAt);

            return;
        }

        throw new \RuntimeException('Brak powiązanej umowy ani wpłaty uczestnika.');
    }

    private function recordParticipantBankImportEntry(
        EventSettlementParticipantPayment $payment,
        BankPaymentImportLine $line,
        Carbon $paidAt,
    ): void {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            return;
        }

        $alreadyRecorded = EventSettlementParticipantPaymentEntry::query()
            ->where('bank_payment_import_line_id', $line->id)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        app(ParticipantPaymentLedgerService::class)->addEntry(
            payment: $payment,
            amount: (float) $line->amount_pln,
            paidAt: $paidAt,
            source: EventSettlementParticipantPaymentEntry::SOURCE_BANK_IMPORT,
            paymentMethod: $payment->payment_method ?: 'transfer',
            bankPaymentImportLineId: $line->id,
            notes: 'Import Millennium: '.$line->title,
            payerName: filled($line->counterparty) ? (string) $line->counterparty : null,
            bankTransferDescription: filled($line->title) ? (string) $line->title : null,
        );
    }

    /**
     * @param  array{
     *     operation_date: ?string,
     *     title: string,
     *     counterparty: ?string,
     *     account_number: ?string,
     *     amount_pln: float
     * }  $transaction
     */
    private function fingerprint(array $transaction): string
    {
        return hash('sha256', implode('|', [
            $transaction['operation_date'] ?? '',
            number_format((float) $transaction['amount_pln'], 2, '.', ''),
            mb_strtolower(trim($transaction['title'] ?? '')),
            mb_strtolower(trim($transaction['counterparty'] ?? '')),
        ]));
    }
}
