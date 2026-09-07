<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Event;
use App\Models\PilotFeeLine;
use App\Support\CurrencyAmountDisplay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PilotFeeService
{
    /**
     * @param  array<int, array{amount: float|int|string, currency_id: int}>  $lines
     */
    public function syncDueLines(Event $event, array $lines): Event
    {
        if (! Schema::hasTable('pilot_fee_lines')) {
            return $event;
        }

        $this->replaceLines($event, PilotFeeLine::PHASE_DUE, $this->normalizeLines($lines));

        return $event->fresh(['pilotFeeLines.currency']);
    }

    /**
     * @param  array<int, array{amount: float|int|string, currency_id: int}>  $lines
     */
    public function syncPaidLines(Event $event, array $lines): Event
    {
        if (! Schema::hasTable('pilot_fee_lines')) {
            return $event;
        }

        $this->replaceLines($event, PilotFeeLine::PHASE_PAID, $this->normalizeLines($lines));

        return $event->fresh(['pilotFeeLines.currency']);
    }

    /**
     * Oznacza wynagrodzenie jako wypłacone — kopiuje linie należne → paid
     * (opcjonalnie z override kwot z formularza).
     *
     * @param  array<int, array{amount: float|int|string, currency_id: int}>|null  $paidLines
     */
    public function markPaid(Event $event, ?array $paidLines = null): Event
    {
        if (! Schema::hasTable('pilot_fee_lines')) {
            return $event;
        }

        $lines = $paidLines !== null
            ? $this->normalizeLines($paidLines)
            : $this->dueLines($event)
                ->map(fn (PilotFeeLine $line) => [
                    'amount' => (float) $line->amount,
                    'currency_id' => (int) $line->currency_id,
                ])
                ->values()
                ->all();

        if ($lines === []) {
            throw new \InvalidArgumentException('Ustal należne wynagrodzenie przed oznaczeniem wypłaty.');
        }

        return $this->syncPaidLines($event, $lines);
    }

    public function clearPaid(Event $event): Event
    {
        if (! Schema::hasTable('pilot_fee_lines')) {
            return $event;
        }

        $event->pilotFeeLines()->where('phase', PilotFeeLine::PHASE_PAID)->delete();

        return $event->fresh(['pilotFeeLines.currency']);
    }

    /**
     * @return Collection<int, PilotFeeLine>
     */
    public function dueLines(Event $event): Collection
    {
        if (! Schema::hasTable('pilot_fee_lines')) {
            return collect();
        }

        return $event->pilotFeeLines()
            ->with('currency')
            ->where('phase', PilotFeeLine::PHASE_DUE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, PilotFeeLine>
     */
    public function paidLines(Event $event): Collection
    {
        if (! Schema::hasTable('pilot_fee_lines')) {
            return collect();
        }

        return $event->pilotFeeLines()
            ->with('currency')
            ->where('phase', PilotFeeLine::PHASE_PAID)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, array{amount: string, currency_id: int}>
     */
    public function dueLinesFormState(Event $event): array
    {
        return $this->dueLines($event)
            ->map(fn (PilotFeeLine $line) => [
                'amount' => (string) $line->amount,
                'currency_id' => (int) $line->currency_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Pozostało do wypłaty: max(due − paid, 0) per waluta.
     *
     * @return Collection<int, array{amount: float, currency_id: int, currency: ?Currency}>
     */
    public function remainingLines(Event $event): Collection
    {
        $dueByCurrency = $this->dueLines($event)->keyBy('currency_id');
        $paidByCurrency = $this->paidLines($event)->keyBy('currency_id');

        $currencyIds = $dueByCurrency->keys()->merge($paidByCurrency->keys())->unique();

        return $currencyIds
            ->map(function (int|string $currencyId) use ($dueByCurrency, $paidByCurrency) {
                $id = (int) $currencyId;
                $due = (float) ($dueByCurrency->get($id)?->amount ?? 0);
                $paid = (float) ($paidByCurrency->get($id)?->amount ?? 0);
                $remaining = round(max(0, $due - $paid), 2);

                if ($remaining <= 0.009) {
                    return null;
                }

                $currency = $dueByCurrency->get($id)?->currency
                    ?? $paidByCurrency->get($id)?->currency;

                return [
                    'amount' => $remaining,
                    'currency_id' => $id,
                    'currency' => $currency,
                ];
            })
            ->filter()
            ->values();
    }

    public function formatDueLabel(Event $event): string
    {
        return $this->formatLinesLabel($this->dueLines($event));
    }

    public function formatPaidLabel(Event $event): string
    {
        return $this->formatLinesLabel($this->paidLines($event));
    }

    public function formatRemainingLabel(Event $event): string
    {
        $lines = $this->remainingLines($event);

        if ($lines->isEmpty()) {
            return $this->dueLines($event)->isEmpty() ? '—' : '0';
        }

        $parts = [];
        foreach ($lines as $line) {
            $parts[] = CurrencyAmountDisplay::formatIndicative(
                (float) $line['amount'],
                $line['currency'] ?? null,
            );
        }

        return implode(' + ', $parts);
    }

    public function hasOutstandingFee(Event $event): bool
    {
        return $this->remainingLines($event)->isNotEmpty();
    }

    public function isFullyPaid(Event $event): bool
    {
        if ($this->dueLines($event)->isEmpty()) {
            return false;
        }

        return ! $this->hasOutstandingFee($event);
    }

    /**
     * @param  Collection<int, PilotFeeLine>  $lines
     */
    protected function formatLinesLabel(Collection $lines): string
    {
        if ($lines->isEmpty()) {
            return '—';
        }

        $parts = [];
        foreach ($lines as $line) {
            $amount = (float) $line->amount;
            if ($amount <= 0.009) {
                continue;
            }

            $parts[] = CurrencyAmountDisplay::formatIndicative($amount, $line->currency);
        }

        return $parts !== [] ? implode(' + ', $parts) : '—';
    }

    /**
     * @param  array<int, array{amount: float|int|string, currency_id: int}>  $lines
     */
    protected function replaceLines(Event $event, string $phase, array $lines): void
    {
        $event->pilotFeeLines()->where('phase', $phase)->delete();

        foreach ($lines as $index => $line) {
            $event->pilotFeeLines()->create([
                'currency_id' => (int) $line['currency_id'],
                'amount' => round((float) $line['amount'], 2),
                'phase' => $phase,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @param  array<int, array{amount: float|int|string, currency_id?: int|null}>  $lines
     * @return array<int, array{amount: float, currency_id: int}>
     */
    protected function normalizeLines(array $lines): array
    {
        $defaultCurrencyId = $this->defaultPlnCurrencyId();
        $byCurrency = [];

        foreach ($lines as $line) {
            $amount = round((float) str_replace(',', '.', (string) ($line['amount'] ?? 0)), 2);
            $currencyId = (int) ($line['currency_id'] ?? $defaultCurrencyId);

            if ($amount <= 0 || $currencyId <= 0) {
                continue;
            }

            $byCurrency[$currencyId] = ($byCurrency[$currencyId] ?? 0) + $amount;
        }

        $normalized = [];
        foreach ($byCurrency as $currencyId => $amount) {
            $normalized[] = [
                'amount' => round((float) $amount, 2),
                'currency_id' => (int) $currencyId,
            ];
        }

        return $normalized;
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
