<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\PilotAdvanceLine;
use App\Models\PilotCashPreparation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PilotAdvanceService
{
    /**
     * @param  array<int, array{amount: float|int|string, currency_id: int}>  $lines
     */
    public function syncPlannedLines(Event $event, array $lines): Event
    {
        if (! Schema::hasTable('pilot_advance_lines')) {
            return $this->planAdvance($event, $this->sumLines($lines) ?: null);
        }

        $normalized = $this->normalizeLines($lines);

        $this->replaceLines($event, PilotAdvanceLine::PHASE_PLANNED, $normalized);
        $this->syncLegacyPlannedFields($event->fresh(), $normalized);

        return $event->fresh(['pilotAdvanceLines.currency']);
    }

    public function planAdvance(Event $event, ?float $amount, ?int $currencyId = null): Event
    {
        if (! Schema::hasColumn('events', 'pilot_advance_planned_amount')) {
            return $event;
        }

        if (Schema::hasTable('pilot_advance_lines') && $amount !== null && $amount > 0) {
            $resolvedCurrencyId = $currencyId ?? $this->defaultPlnCurrencyId();

            if ($resolvedCurrencyId) {
                return $this->syncPlannedLines($event, [[
                    'amount' => $amount,
                    'currency_id' => $resolvedCurrencyId,
                ]]);
            }
        }

        $payload = [
            'pilot_advance_planned_amount' => $amount !== null && $amount > 0 ? round($amount, 2) : null,
        ];

        if ($payload['pilot_advance_planned_amount'] !== null) {
            if (! $event->pilot_advance_planned_at) {
                $payload['pilot_advance_planned_at'] = now();
                $payload['pilot_advance_planned_by'] = Auth::id();
            }
        } else {
            $payload['pilot_advance_planned_at'] = null;
            $payload['pilot_advance_planned_by'] = null;
        }

        $event->update($payload);

        return $event->fresh();
    }

    /**
     * @param  array<int, array{amount: float|int|string, currency_id: int}>|null  $paidLines
     */
    public function approvePayment(
        Event $event,
        ?float $amountOverride = null,
        ?int $currencyId = null,
        ?string $comment = null,
        ?array $paidLines = null,
    ): Event {
        if (! Schema::hasColumn('events', 'pilot_funds_paid')) {
            return $event;
        }

        if (! $event->assigned_to) {
            throw new \InvalidArgumentException('Przypisz pilota do imprezy przed wypłatą zaliczki.');
        }

        $lines = $paidLines ?? $this->resolvePaidLinesForApproval($event, $amountOverride, $currencyId);

        if ($lines === []) {
            throw new \InvalidArgumentException('Ustal planowaną zaliczkę przed zatwierdzeniem wypłaty.');
        }

        if (Schema::hasTable('pilot_advance_lines')) {
            $this->replaceLines($event, PilotAdvanceLine::PHASE_PAID, $lines);
        }

        $primary = $lines[0];
        $primaryAmount = round((float) $primary['amount'], 2);

        $payload = [
            'pilot_advance_planned_amount' => $primaryAmount,
            'pilot_funds_paid' => true,
            'pilot_advance_paid_amount' => $primaryAmount,
            'pilot_advance_paid_currency_id' => (int) $primary['currency_id'],
            'pilot_advance_paid_comment' => $comment,
        ];

        if (! $event->pilot_funds_paid) {
            $payload['pilot_funds_paid_at'] = now();
            $payload['pilot_funds_paid_by'] = Auth::id();
        }

        if (! $event->pilot_advance_planned_at) {
            $payload['pilot_advance_planned_at'] = now();
            $payload['pilot_advance_planned_by'] = Auth::id();
        }

        $event->update($payload);
        $this->syncPaidAdvanceToSettlementCash($event->fresh());

        return $event->fresh(['pilotAdvanceLines.currency', 'pilotAdvancePaidCurrency']);
    }

    public function syncPaidAdvanceToSettlementCash(Event $event): void
    {
        if (! $event->pilot_funds_paid) {
            return;
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $lines = $this->paidLines($event);

        if ($lines->isEmpty()) {
            $amount = (float) ($event->pilot_advance_paid_amount ?? $event->pilot_advance_planned_amount ?? 0);
            if ($amount <= 0) {
                return;
            }

            $lines = collect([[
                'amount' => $amount,
                'currency_id' => $event->pilot_advance_paid_currency_id ?? $this->defaultPlnCurrencyId(),
            ]]);
        }

        $usedCurrencyIds = [];

        foreach ($lines as $line) {
            $currencyId = (int) ($line['currency_id'] ?? 0);
            $amount = round((float) ($line['amount'] ?? 0), 2);

            if ($currencyId <= 0 || $amount <= 0) {
                continue;
            }

            $usedCurrencyIds[] = $currencyId;

            $cash = PilotCashPreparation::query()->firstOrCreate(
                [
                    'settlement_id' => $settlement->id,
                    'currency_id' => $currencyId,
                ],
                [
                    'status' => 'provided',
                ],
            );

            $cash->update([
                'provided_amount' => $amount,
                'status' => 'provided',
                'provided_at' => $cash->provided_at ?? ($event->pilot_funds_paid_at ?? now()),
            ]);
        }

        $settlement->recalculatePilotCash();
        app(PilotSettlementService::class)->syncPilotCashSpentFromCosts($settlement->fresh() ?? $settlement);
    }

    /**
     * Biuro wypłaca gotówkę pilotowi (kwota + waluta + data).
     * Można wołać wielokrotnie (kolejne waluty / korekta kwoty w danej walucie).
     *
     * @param  array{
     *     amount: float|int|string,
     *     currency_id: int,
     *     provided_at?: \DateTimeInterface|string|null,
     *     comment?: string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function recordOfficeCashPayout(Event $event, array $data): Event
    {
        if (! $event->assigned_to) {
            throw new \InvalidArgumentException('Przypisz pilota do imprezy przed wypłatą gotówki.');
        }

        $amount = round((float) str_replace([' ', ','], ['', '.'], (string) ($data['amount'] ?? 0)), 2);
        $currencyId = (int) ($data['currency_id'] ?? 0);

        if ($amount <= 0 || $currencyId <= 0) {
            throw new \InvalidArgumentException('Podaj kwotę i walutę wypłaty.');
        }

        $providedAt = $data['provided_at'] ?? now();
        if (is_string($providedAt)) {
            $providedAt = \Illuminate\Support\Carbon::parse($providedAt);
        }

        $comment = filled($data['comment'] ?? null)
            ? (string) $data['comment']
            : ($data['notes'] ?? null);

        $existing = $this->paidLines($event)
            ->map(fn (array $line) => [
                'amount' => (float) $line['amount'],
                'currency_id' => (int) $line['currency_id'],
            ])
            ->values()
            ->all();

        $merged = [];
        $replaced = false;
        foreach ($existing as $line) {
            if ((int) $line['currency_id'] === $currencyId) {
                $merged[] = ['amount' => $amount, 'currency_id' => $currencyId];
                $replaced = true;
            } else {
                $merged[] = $line;
            }
        }
        if (! $replaced) {
            $merged[] = ['amount' => $amount, 'currency_id' => $currencyId];
        }

        if (Schema::hasTable('pilot_advance_lines')) {
            $this->replaceLines($event, PilotAdvanceLine::PHASE_PAID, $merged);
        }

        $payload = [
            'pilot_funds_paid' => true,
            'pilot_advance_paid_amount' => $amount,
            'pilot_advance_paid_currency_id' => $currencyId,
            'pilot_advance_paid_comment' => $comment,
        ];

        if (! $event->pilot_funds_paid) {
            $payload['pilot_funds_paid_at'] = $providedAt;
            $payload['pilot_funds_paid_by'] = Auth::id();
        } else {
            // Korekta / dopłata w innej walucie — zachowaj pierwszą datę, zaktualizuj provided_at na cash.
            $payload['pilot_funds_paid_at'] = $event->pilot_funds_paid_at ?? $providedAt;
        }

        if (! $event->pilot_advance_planned_at) {
            $payload['pilot_advance_planned_at'] = $providedAt;
            $payload['pilot_advance_planned_by'] = Auth::id();
            $payload['pilot_advance_planned_amount'] = $amount;
        }

        $event->update($payload);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $cash = PilotCashPreparation::query()->firstOrCreate(
            [
                'settlement_id' => $settlement->id,
                'currency_id' => $currencyId,
            ],
            ['status' => 'provided'],
        );

        $cash->update([
            'provided_amount' => $amount,
            'status' => 'provided',
            'provided_at' => $providedAt,
            'notes' => $comment ?: $cash->notes,
        ]);

        $settlement->recalculatePilotCash();
        app(PilotSettlementService::class)->syncPilotCashSpentFromCosts($settlement->fresh() ?? $settlement);

        return $event->fresh(['pilotAdvanceLines.currency', 'pilotAdvancePaidCurrency']);
    }

    /**
     * Usuwa wypłatę gotówki w danej walucie (omyłka / korekta).
     */
    public function clearOfficeCashPayout(Event $event, int $currencyId): Event
    {
        if ($currencyId <= 0) {
            throw new \InvalidArgumentException('Podaj walutę wypłaty do usunięcia.');
        }

        $remaining = $this->paidLines($event)
            ->filter(fn (array $line): bool => (int) $line['currency_id'] !== $currencyId)
            ->map(fn (array $line) => [
                'amount' => (float) $line['amount'],
                'currency_id' => (int) $line['currency_id'],
            ])
            ->values()
            ->all();

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());

        // Nie pozwól skasować wypłaty, jeśli wymiany zostawiłyby ujemne saldo w tej walucie.
        $exchangeOut = (float) $settlement->currencyExchanges()->where('from_currency_id', $currencyId)->sum('from_amount');
        $exchangeIn = (float) $settlement->currencyExchanges()->where('to_currency_id', $currencyId)->sum('to_amount');
        if (round($exchangeIn - $exchangeOut, 2) < -0.009) {
            throw new \InvalidArgumentException(
                'Nie można usunąć wypłaty — najpierw usuń lub popraw wymiany walut w tej walucie (inaczej saldo będzie ujemne).'
            );
        }

        $cash = PilotCashPreparation::query()
            ->where('settlement_id', $settlement->id)
            ->where('currency_id', $currencyId)
            ->first();

        if ($cash) {
            $cash->update([
                'provided_amount' => null,
                'provided_at' => null,
                'status' => ((float) ($cash->calculated_amount ?? 0) > 0) ? 'calculated' : ($cash->status ?: 'calculated'),
            ]);
        }

        if (Schema::hasTable('pilot_advance_lines')) {
            $this->replaceLines($event, PilotAdvanceLine::PHASE_PAID, $remaining);
        }

        if ($remaining === []) {
            $event->update([
                'pilot_funds_paid' => false,
                'pilot_funds_paid_at' => null,
                'pilot_funds_paid_by' => null,
                'pilot_advance_paid_amount' => null,
                'pilot_advance_paid_currency_id' => null,
                'pilot_advance_paid_comment' => null,
            ]);
        } else {
            $primary = $remaining[0];
            $event->update([
                'pilot_funds_paid' => true,
                'pilot_advance_paid_amount' => $primary['amount'],
                'pilot_advance_paid_currency_id' => $primary['currency_id'],
            ]);
        }

        $settlement->recalculatePilotCash();
        app(PilotSettlementService::class)->syncPilotCashSpentFromCosts($settlement->fresh() ?? $settlement);

        return $event->fresh(['pilotAdvanceLines.currency', 'pilotAdvancePaidCurrency', 'pilotFundsPaidByUser']);
    }

    /**
     * @return Collection<int, PilotAdvanceLine>
     */
    public function plannedLines(Event $event): Collection
    {
        if (! Schema::hasTable('pilot_advance_lines')) {
            return collect();
        }

        return $event->pilotAdvanceLines()
            ->with('currency')
            ->where('phase', PilotAdvanceLine::PHASE_PLANNED)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, array{amount: float, currency_id: int, currency?: Currency|null}>
     */
    public function paidLines(Event $event): Collection
    {
        if (Schema::hasTable('pilot_advance_lines')) {
            $lines = $event->pilotAdvanceLines()
                ->with('currency')
                ->where('phase', PilotAdvanceLine::PHASE_PAID)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($lines->isNotEmpty()) {
                return $lines->map(fn (PilotAdvanceLine $line) => [
                    'amount' => (float) $line->amount,
                    'currency_id' => (int) $line->currency_id,
                    'currency' => $line->currency,
                ]);
            }
        }

        if (! $event->pilot_funds_paid) {
            return collect();
        }

        $amount = (float) ($event->pilot_advance_paid_amount ?? $event->pilot_advance_planned_amount ?? 0);

        if ($amount <= 0) {
            return collect();
        }

        return collect([[
            'amount' => $amount,
            'currency_id' => (int) ($event->pilot_advance_paid_currency_id ?? $this->defaultPlnCurrencyId()),
            'currency' => $event->pilotAdvancePaidCurrency,
        ]]);
    }

    /**
     * @return array<int, array{amount: string, currency_id: int}>
     */
    public function plannedLinesFormState(Event $event): array
    {
        $lines = $this->plannedLines($event);

        if ($lines->isNotEmpty()) {
            return $lines->map(fn (PilotAdvanceLine $line) => [
                'amount' => (string) $line->amount,
                'currency_id' => (int) $line->currency_id,
            ])->values()->all();
        }

        if (filled($event->pilot_advance_planned_amount)) {
            return [[
                'amount' => (string) $event->pilot_advance_planned_amount,
                'currency_id' => (int) ($event->pilot_advance_paid_currency_id ?? $this->defaultPlnCurrencyId()),
            ]];
        }

        return [];
    }

    /**
     * @param  array<int, array{amount: float|int|string, currency_id: int}>  $lines
     */
    protected function replaceLines(Event $event, string $phase, array $lines): void
    {
        if (! Schema::hasTable('pilot_advance_lines')) {
            return;
        }

        $event->pilotAdvanceLines()->where('phase', $phase)->delete();

        foreach ($lines as $index => $line) {
            $event->pilotAdvanceLines()->create([
                'currency_id' => (int) $line['currency_id'],
                'amount' => round((float) $line['amount'], 2),
                'phase' => $phase,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @param  array<int, array{amount: float|int|string, currency_id: int}>  $lines
     */
    protected function syncLegacyPlannedFields(Event $event, array $lines): void
    {
        if (! Schema::hasColumn('events', 'pilot_advance_planned_amount')) {
            return;
        }

        $primary = $lines[0] ?? null;
        $totalPln = $this->sumLines($lines);

        $payload = [
            'pilot_advance_planned_amount' => $totalPln > 0 ? $totalPln : null,
        ];

        if ($primary && $totalPln > 0) {
            if (! $event->pilot_advance_planned_at) {
                $payload['pilot_advance_planned_at'] = now();
                $payload['pilot_advance_planned_by'] = Auth::id();
            }
        } elseif ($totalPln <= 0) {
            $payload['pilot_advance_planned_at'] = null;
            $payload['pilot_advance_planned_by'] = null;
        }

        $event->update($payload);
    }

    /**
     * @return array<int, array{amount: float, currency_id: int}>
     */
    protected function resolvePaidLinesForApproval(Event $event, ?float $amountOverride, ?int $currencyId): array
    {
        if (Schema::hasTable('pilot_advance_lines')) {
            $planned = $this->plannedLines($event)
                ->map(fn (PilotAdvanceLine $line) => [
                    'amount' => (float) $line->amount,
                    'currency_id' => (int) $line->currency_id,
                ])
                ->values()
                ->all();

            if ($planned !== []) {
                return $planned;
            }
        }

        $amount = $amountOverride ?? (float) ($event->pilot_advance_paid_amount ?? $event->pilot_advance_planned_amount ?? 0);

        if ($amount <= 0) {
            return [];
        }

        return [[
            'amount' => $amount,
            'currency_id' => $currencyId ?? $event->pilot_advance_paid_currency_id ?? $this->defaultPlnCurrencyId(),
        ]];
    }

    /**
     * @param  array<int, array{amount: float|int|string, currency_id?: int|null}>  $lines
     * @return array<int, array{amount: float, currency_id: int}>
     */
    protected function normalizeLines(array $lines): array
    {
        $defaultCurrencyId = $this->defaultPlnCurrencyId();
        $normalized = [];

        foreach ($lines as $line) {
            $amount = round((float) str_replace(',', '.', (string) ($line['amount'] ?? 0)), 2);
            $currencyId = (int) ($line['currency_id'] ?? $defaultCurrencyId);

            if ($amount <= 0 || $currencyId <= 0) {
                continue;
            }

            $normalized[] = [
                'amount' => $amount,
                'currency_id' => $currencyId,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{amount: float|int|string}>  $lines
     */
    protected function sumLines(array $lines): float
    {
        return round(collect($lines)->sum(fn (array $line) => (float) ($line['amount'] ?? 0)), 2);
    }

    private function defaultPlnCurrencyId(): ?int
    {
        return Currency::query()
            ->where(function ($query): void {
                $query->where('code', 'PLN')
                    ->orWhere('symbol', 'PLN');
            })
            ->orderBy('id')
            ->value('id');
    }
}
