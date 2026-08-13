<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Support\MoneyFormatter;
use Illuminate\Support\Facades\Schema;

/**
 * Porównanie ceny klienta: obowiązująca (umowa/aneks/szablon indywidualny → ręczna) vs kalkulacja programu, per waluta.
 *
 * Priorytet: aneks → umowa grupowa → umowa indywidualna (wysłana) → szablon indywidualny z generatora → ręczna.
 * Szablon „goły” (status=template bez is_individual_template) nie wygrywa z wygenerowaną umową.
 */
final class EventClientPriceComparisonService
{
    public function __construct(
        private EventPriceSummaryService $priceSummary,
        private EventManualPricePerPersonService $manualPricePerPerson,
    ) {}

    /**
     * @return array{
     *     label: string,
     *     has_contract: bool,
     *     source: 'contract'|'manual'|'none',
     *     currencies: list<array{
     *         currency: string,
     *         effective: ?float,
     *         calculation: ?float,
     *         pair_label: string
     *     }>
     * }
     */
    public function forEvent(Event $event): array
    {
        $calculation = $this->calculationPrices($event);
        $contract = $this->resolveEffectiveContract($event);
        $effective = [];
        $source = 'none';

        if ($contract !== null) {
            $effective = $this->contractPricesPerPerson($contract);
            if ($effective !== []) {
                $source = 'contract';
            }
        }

        if ($source === 'none') {
            $manual = $this->manualPrices($event);
            if ($manual !== []) {
                $effective = $manual;
                $source = 'manual';
            }
        }

        $currencies = $this->mergeCurrencyPairs($effective, $calculation);

        return [
            'label' => $this->composeLabel($currencies),
            'has_contract' => $source === 'contract',
            'source' => $source,
            'currencies' => $currencies,
        ];
    }

    /**
     * @deprecated Użyj resolveEffectiveContract()
     */
    public function resolveEffectiveGroupContract(Event $event): ?Contract
    {
        return $this->resolveEffectiveContract($event);
    }

    /**
     * Najnowszy aneks z jawną ceną, inaczej umowa grupowa/indywidualna, inaczej szablon indywidualny z generatora.
     */
    public function resolveEffectiveContract(Event $event): ?Contract
    {
        if (! Schema::hasTable('contracts')) {
            return null;
        }

        $contracts = Contract::query()
            ->where('event_id', $event->id)
            ->whereIn('contract_type', [Contract::TYPE_GROUP, Contract::TYPE_INDIVIDUAL])
            ->with('paymentSchedules')
            ->orderByDesc('id')
            ->get();

        if ($contracts->isEmpty()) {
            return null;
        }

        $nonTemplate = $contracts->filter(
            fn (Contract $contract): bool => (string) ($contract->status ?? '') !== 'template'
        );
        $withPrice = $nonTemplate->filter(fn (Contract $contract): bool => $this->hasExplicitClientPrice($contract));

        $annexWithPrice = $withPrice->first(fn (Contract $contract): bool => $contract->isAnnex());
        if ($annexWithPrice) {
            return $annexWithPrice;
        }

        $groupWithPrice = $withPrice->first(fn (Contract $contract): bool => $contract->isGroup());
        if ($groupWithPrice) {
            return $groupWithPrice;
        }

        $individualWithPrice = $withPrice->first(fn (Contract $contract): bool => $contract->isIndividual());
        if ($individualWithPrice) {
            return $individualWithPrice;
        }

        // Po wygenerowaniu umowy indywidualnej kanoniczna cena siedzi na szablonie (status=template).
        $individualTemplateWithPrice = $contracts
            ->filter(fn (Contract $contract): bool => $this->isPricedIndividualTemplate($contract))
            ->first();
        if ($individualTemplateWithPrice) {
            return $individualTemplateWithPrice;
        }

        return $nonTemplate->first(fn (Contract $contract): bool => $contract->isAnnex())
            ?? $nonTemplate->first()
            ?? $contracts->first(fn (Contract $contract): bool => $this->isPricedIndividualTemplate($contract));
    }

    private function isPricedIndividualTemplate(Contract $contract): bool
    {
        if (! $contract->isIndividual() || (string) ($contract->status ?? '') !== 'template') {
            return false;
        }

        if (! (bool) data_get($contract->meta, 'is_individual_template', false)) {
            return false;
        }

        return $this->hasExplicitClientPrice($contract);
    }

    private function hasExplicitClientPrice(Contract $contract): bool
    {
        if ((float) ($contract->unit_price ?? 0) > 0.009) {
            return true;
        }

        if ((float) data_get($contract->meta, 'unit_price_pln', 0) > 0.009) {
            return true;
        }

        $participants = max(0, (int) ($contract->participant_count ?? 0));
        $due = (float) ($contract->total_price ?? 0);
        if ($participants > 0 && $due > 0.009) {
            return true;
        }

        if ($contract->paymentSchedules
            ->contains(fn ($schedule): bool => $this->isForeignPerPersonSchedule($schedule))) {
            return true;
        }

        $foreignMeta = data_get($contract->meta, 'foreign_prices_per_person', []);

        return is_array($foreignMeta) && collect($foreignMeta)->contains(function ($row): bool {
            return is_array($row) && round((float) ($row['price_per_person'] ?? 0), 2) > 0.009;
        });
    }

    /**
     * @return array<string, float>
     */
    private function contractPricesPerPerson(Contract $contract): array
    {
        $prices = [];

        $pln = $this->explicitContractPlnPerPerson($contract);
        if ($pln !== null) {
            $prices['PLN'] = $pln;
        }

        $foreignTotals = [];
        foreach ($contract->paymentSchedules as $schedule) {
            if (! $this->isForeignPerPersonSchedule($schedule)) {
                continue;
            }

            $code = $this->scheduleForeignCurrencyCode($schedule);
            if ($code === null) {
                continue;
            }

            $foreignTotals[$code] = ($foreignTotals[$code] ?? 0.0) + (float) $schedule->amount_foreign;
        }

        foreach ($foreignTotals as $code => $amount) {
            $prices[$code] = round((float) $amount, 2);
        }

        // Fallback: meta z generatora (gdy FX nie trafiło do rat).
        $foreignMeta = data_get($contract->meta, 'foreign_prices_per_person', []);
        if (is_array($foreignMeta)) {
            foreach ($foreignMeta as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['currency'] ?? '')));
                $amount = round((float) ($row['price_per_person'] ?? 0), 2);
                if ($code === '' || $code === 'PLN' || $amount <= 0.009) {
                    continue;
                }
                if (! isset($prices[$code])) {
                    $prices[$code] = $amount;
                }
            }
        }

        return $prices;
    }

    private function isForeignPerPersonSchedule(mixed $schedule): bool
    {
        $foreign = (float) ($schedule->amount_foreign ?? 0);
        if ($foreign <= 0.009) {
            return false;
        }

        $plnAmount = (float) ($schedule->amount ?? 0);
        // Rata czysto walutowa (SHARE_FOREIGN / „waluta u pilota”) — kwota za osobę.
        if ($plnAmount > 0.009) {
            return false;
        }

        return $this->scheduleForeignCurrencyCode($schedule) !== null;
    }

    private function scheduleForeignCurrencyCode(mixed $schedule): ?string
    {
        $code = strtoupper(trim((string) ($schedule->currency_code ?? '')));
        if ($code === '' || $code === 'PLN') {
            return null;
        }

        return $code;
    }

    private function explicitContractPlnPerPerson(Contract $contract): ?float
    {
        $unit = (float) ($contract->unit_price ?? 0);
        if ($unit > 0.009) {
            return round($unit, 2);
        }

        $metaUnit = (float) data_get($contract->meta, 'unit_price_pln', 0);
        if ($metaUnit > 0.009) {
            return round($metaUnit, 2);
        }

        $participants = max(1, (int) ($contract->participant_count ?? 1));
        $due = (float) ($contract->total_price ?? 0);
        if ($due > 0.009) {
            return round($due / $participants, 2);
        }

        return null;
    }

    /**
     * @return array<string, float>
     */
    private function calculationPrices(Event $event): array
    {
        try {
            $summary = $this->priceSummary->forEvent($event, includeNearest: false);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        if (! ($summary['ready'] ?? false)) {
            return [];
        }

        $prices = [];
        $pln = round((float) ($summary['price_per_person_rounded'] ?? $summary['price_per_person'] ?? 0), 2);
        if ($pln > 0.009) {
            $prices['PLN'] = $pln;
        }

        foreach ($summary['foreign_prices'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = strtoupper((string) ($row['currency'] ?? ''));
            $amount = round((float) ($row['price_per_person'] ?? 0), 2);
            if ($code === '' || $code === 'PLN' || $amount <= 0.009) {
                continue;
            }
            $prices[$code] = $amount;
        }

        return $prices;
    }

    /**
     * @return array<string, float>
     */
    private function manualPrices(Event $event): array
    {
        $prices = [];
        foreach ($this->manualPricePerPerson->manualLinesForEvent($event) as $line) {
            $code = $this->normalizeCurrencyCode((string) ($line['currency_code'] ?? 'PLN'));
            $amount = round((float) ($line['amount'] ?? 0), 2);
            if ($code === '' || $amount <= 0.009) {
                continue;
            }
            $prices[$code] = $amount;
        }

        return $prices;
    }

    private function normalizeCurrencyCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $compact = str_replace([' ', '.'], '', $code);

        if (in_array($compact, ['PLN', 'ZL', 'ZŁ', 'ZLOTY', 'ZŁOTY'], true)) {
            return 'PLN';
        }

        return $code;
    }

    /**
     * @param  array<string, float>  $effective
     * @param  array<string, float>  $calculation
     * @return list<array{currency: string, effective: ?float, calculation: ?float, pair_label: string}>
     */
    private function mergeCurrencyPairs(array $effective, array $calculation): array
    {
        $codes = array_values(array_unique(array_merge(array_keys($effective), array_keys($calculation))));
        usort($codes, static function (string $a, string $b): int {
            if ($a === 'PLN') {
                return -1;
            }
            if ($b === 'PLN') {
                return 1;
            }

            return strcmp($a, $b);
        });

        $rows = [];
        foreach ($codes as $code) {
            $left = $effective[$code] ?? null;
            $right = $calculation[$code] ?? null;
            if ($left === null && $right === null) {
                continue;
            }

            $leftLabel = $left !== null ? MoneyFormatter::format($left, $code) : '—';
            $rightLabel = $right !== null ? MoneyFormatter::format($right, $code) : '—';

            $rows[] = [
                'currency' => $code,
                'effective' => $left,
                'calculation' => $right,
                'pair_label' => $leftLabel.' / '.$rightLabel,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{pair_label: string}>  $currencies
     */
    private function composeLabel(array $currencies): string
    {
        if ($currencies === []) {
            return '—';
        }

        return implode(' + ', array_column($currencies, 'pair_label'));
    }
}
