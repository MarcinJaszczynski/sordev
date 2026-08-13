<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\EventPaymentInstallmentTemplate;
use App\Services\EventPaymentInstallmentTemplateService;
use App\Services\EventPriceSummaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Podpowiedzi cen i rat do generatora umów (PLN + waluty obce).
 */
final class ContractGenerationPriceHints
{
    /**
     * @return array{
     *     ready: bool,
     *     pln_per_person: float,
     *     group_total_pln: float,
     *     price_label: string,
     *     foreign: list<array{currency: string, price_per_person: float, label: string}>,
     *     hint_html: string,
     *     schedule_hint_html: string,
     *     has_event_installment_template: bool,
     *     default_schedules_per_person: list<array<string, mixed>>
     * }
     */
    public static function forEvent(Event $event): array
    {
        $paying = max(1, (int) ($event->participant_count ?? 1));
        $summary = app(EventPriceSummaryService::class)->forEvent($event, includeNearest: false);

        $pln = 0.0;
        $foreign = [];
        $label = '—';
        $ready = (bool) ($summary['ready'] ?? false);

        if ($ready) {
            $pln = round((float) ($summary['price_per_person_rounded'] ?? $summary['price_per_person'] ?? 0), 2);
            $foreign = is_array($summary['foreign_prices'] ?? null) ? $summary['foreign_prices'] : [];
            $label = (string) ($summary['price_per_person_label'] ?? '—');
        }

        if ($pln <= 0) {
            $pln = round((float) $event->resolvedPricePerPerson($paying), 2);
            if ($pln > 0) {
                $label = MoneyFormatter::format($pln, 'PLN');
                $ready = true;
            }
        }

        $groupTotal = round($pln * $paying, 2);
        $foreignLines = '';
        foreach ($foreign as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = strtoupper((string) ($row['currency'] ?? ''));
            $amount = round((float) ($row['price_per_person'] ?? 0), 2);
            if ($code === '' || $amount <= 0) {
                continue;
            }
            $foreignLines .= sprintf(
                '<li><strong>%s</strong> — zwykle płatne w autokarze / u pilota (nie blokuje „terminowości” wpłaty PLN).</li>',
                e(MoneyFormatter::format($amount, $code))
            );
        }

        $hint = '<div class="space-y-2 text-sm">'
            .'<p><strong>Za osobę:</strong> '.e($label).'</p>'
            .'<p><strong>Suma grupy ('.$paying.' os.):</strong> '.e(MoneyFormatter::format($groupTotal, 'PLN'))
            .($foreign !== [] ? ' + waluty × liczba osób' : '')
            .'</p>';
        if ($foreignLines !== '') {
            $hint .= '<p class="text-gray-600 dark:text-gray-300">Waluty obce:</p><ul class="list-disc pl-5">'.$foreignLines.'</ul>';
        }
        $hint .= '<p class="text-xs text-gray-500">Przy umowie indywidualnej / gdy płacą rodzice — w polu kwoty podajesz <strong>PLN za jedną osobę</strong>, nie sumę grupy.</p>'
            .'</div>';

        $hasTemplate = Schema::hasTable('event_payment_installment_templates')
            && $event->paymentInstallmentTemplates()->exists();

        return [
            'ready' => $ready,
            'pln_per_person' => $pln,
            'group_total_pln' => $groupTotal,
            'price_label' => $label,
            'foreign' => $foreign,
            'hint_html' => $hint,
            'schedule_hint_html' => self::schedulePreviewHtml($event, max(0.01, $pln)),
            'has_event_installment_template' => $hasTemplate,
            'default_schedules_per_person' => self::defaultSchedulesPerPerson(
                $event,
                $pln,
                $foreign,
                includeForeign: false,
            ),
        ];
    }

    /**
     * Podgląd rat z szablonu imprezy: % → kwota + termin z D±N względem startu.
     */
    public static function schedulePreviewHtml(Event $event, float $baseAmountPln, int $multiplier = 1): string
    {
        $multiplier = max(1, $multiplier);
        $base = max(0.01, $baseAmountPln);

        if (! Schema::hasTable('event_payment_installment_templates')) {
            return '<p class="text-sm text-gray-500">Brak tabeli szablonu rat — ustaw raty ręcznie albo jedną wpłatę.</p>';
        }

        $templates = $event->paymentInstallmentTemplates()->get();
        if ($templates->isEmpty()) {
            return '<p class="text-sm text-gray-500">Brak szablonu rat na imprezie. Możesz wstawić domyślne (PLN + waluta) albo uzupełnić ręcznie.</p>';
        }

        $startDate = $event->start_date ? Carbon::parse($event->start_date) : null;
        $lines = '';

        foreach ($templates as $template) {
            /** @var EventPaymentInstallmentTemplate $template */
            $offset = (int) $template->due_offset_days;
            $dueLabel = $startDate
                ? $startDate->copy()->addDays($offset)->format('d.m.Y')
                : 'brak daty startu imprezy';
            $offsetLabel = $offset === 0
                ? 'D+0 (dzień startu)'
                : ($offset < 0 ? 'D'.$offset : 'D+'.$offset);

            $name = filled($template->label) ? (string) $template->label : 'Transza';

            if ($template->share_type === EventPaymentInstallmentTemplate::SHARE_FOREIGN) {
                $fx = round((float) ($template->amount_foreign ?? 0) * $multiplier, 2);
                $code = strtoupper((string) ($template->currency_code ?: 'EUR'));
                $lines .= '<li><strong>'.e($name).'</strong>: '
                    .e(MoneyFormatter::format($fx, $code))
                    .' · termin <strong>'.e($dueLabel).'</strong> ('.e($offsetLabel).')'
                    .' · zwykle u pilota / w autokarze</li>';

                continue;
            }

            if ($template->share_type === EventPaymentInstallmentTemplate::SHARE_FIXED_PLN) {
                $amount = round((float) ($template->amount_pln ?? 0) * $multiplier, 2);
                $lines .= '<li><strong>'.e($name).'</strong>: '
                    .e(MoneyFormatter::format($amount, 'PLN'))
                    .' (kwota stała)'
                    .' · termin <strong>'.e($dueLabel).'</strong> ('.e($offsetLabel).')</li>';

                continue;
            }

            $percent = (float) ($template->percent ?? 0);
            $amount = round($base * ($percent / 100) * $multiplier, 2);
            $baseShown = round($base * $multiplier, 2);
            $lines .= '<li><strong>'.e($name).'</strong>: '
                .e(rtrim(rtrim(number_format($percent, 2, ',', ' '), '0'), ',')).'% z '
                .e(MoneyFormatter::format($baseShown, 'PLN'))
                .' = <strong>'.e(MoneyFormatter::format($amount, 'PLN')).'</strong>'
                .' · termin <strong>'.e($dueLabel).'</strong> ('.e($offsetLabel).')</li>';
        }

        $scope = $multiplier > 1
            ? 'podgląd × '.$multiplier.' os. (baza ceny/os. × osoby na umowę / grupę)'
            : 'podgląd za 1 osobę (baza = cena/os.)';

        return '<div class="space-y-2 text-sm">'
            .'<p class="text-xs text-gray-500">'.e($scope).'</p>'
            .'<ul class="list-disc space-y-1 pl-5">'.$lines.'</ul>'
            .'</div>';
    }

    /**
     * @param  list<array{currency?: string, price_per_person?: float, label?: string}>  $foreign
     * @return list<array<string, mixed>>
     */
    public static function defaultSchedulesPerPerson(
        Event $event,
        float $plnPerPerson,
        array $foreign = [],
        bool $includeForeign = false,
        string $foreignPaidBy = 'pilot',
    ): array {
        if (Schema::hasTable('event_payment_installment_templates')
            && $event->paymentInstallmentTemplates()->exists()
        ) {
            $rows = app(EventPaymentInstallmentTemplateService::class)
                ->materializeForBase($event, max(0.01, $plnPerPerson));

            if ($rows !== []) {
                $templates = $event->paymentInstallmentTemplates()->get()->values();

                $mapped = array_map(static function (array $row, int $index) use ($templates, $plnPerPerson, $foreignPaidBy): array {
                    $template = $templates->get($index);
                    $label = $row['label'] ?? null;
                    $notes = $row['notes'] ?? null;

                    if ($template instanceof EventPaymentInstallmentTemplate
                        && $template->share_type === EventPaymentInstallmentTemplate::SHARE_PERCENT
                        && $template->percent !== null
                    ) {
                        $percent = (float) $template->percent;
                        $percentLabel = rtrim(rtrim(number_format($percent, 2, ',', ' '), '0'), ',');
                        if (blank($label)) {
                            $label = 'Rata '.$percentLabel.'%';
                        } elseif (! str_contains((string) $label, '%')) {
                            $label = $label.' ('.$percentLabel.'%)';
                        }
                        $offset = (int) $template->due_offset_days;
                        $notes = trim(implode(' · ', array_filter([
                            is_string($notes) ? $notes : null,
                            $percentLabel.'% z '.MoneyFormatter::format(max(0.01, $plnPerPerson), 'PLN'),
                            $offset === 0 ? 'D+0' : ($offset < 0 ? 'D'.$offset : 'D+'.$offset),
                        ])));
                    }

                    $paidBy = $row['paid_by'] ?? null;
                    $isFx = round((float) ($row['amount'] ?? 0), 2) <= 0.009
                        && round((float) ($row['amount_foreign'] ?? 0), 2) > 0.009;
                    if ($isFx) {
                        $paidBy = $foreignPaidBy;
                    }

                    return [
                        'label' => $label,
                        'amount' => (float) ($row['amount'] ?? 0),
                        'amount_foreign' => $row['amount_foreign'] ?? null,
                        'currency_code' => $row['currency_code'] ?? null,
                        'paid_by' => $paidBy,
                        'due_date' => $row['due_date'] ?? null,
                        'notes' => $notes,
                    ];
                }, $rows, array_keys($rows));

                return self::applyForeignCurrencyToSchedules(
                    $mapped,
                    $includeForeign,
                    $foreignPaidBy,
                    $foreign,
                    1,
                    $event,
                );
            }
        }

        $schedules = [];
        if ($plnPerPerson > 0) {
            $schedules[] = [
                'label' => 'Wpłata PLN',
                'amount' => $plnPerPerson,
                'amount_foreign' => null,
                'currency_code' => null,
                'paid_by' => 'office',
                'due_date' => null,
                'notes' => null,
            ];
        }

        return self::applyForeignCurrencyToSchedules(
            $schedules,
            $includeForeign,
            $foreignPaidBy,
            $foreign,
            1,
            $event,
        );
    }

    /**
     * Usuwa lub dokłada wiersze FX zgodnie z decyzją przy generacji.
     *
     * @param  list<array<string, mixed>>  $schedules
     * @param  list<array{currency?: string, price_per_person?: float, label?: string}>  $foreignPerPerson
     * @return list<array<string, mixed>>
     */
    public static function applyForeignCurrencyToSchedules(
        array $schedules,
        bool $includeForeign,
        string $foreignPaidBy,
        array $foreignPerPerson,
        int $multiplier,
        ?Event $event = null,
    ): array {
        $foreignPaidBy = in_array($foreignPaidBy, ['office', 'pilot'], true) ? $foreignPaidBy : 'pilot';
        $multiplier = max(1, $multiplier);

        $plnRows = array_values(array_filter(
            $schedules,
            static fn (array $row): bool => ! (
                round((float) ($row['amount'] ?? 0), 2) <= 0.009
                && round((float) ($row['amount_foreign'] ?? 0), 2) > 0.009
            ),
        ));

        if (! $includeForeign) {
            return $plnRows;
        }

        $fxRows = [];
        foreach ($schedules as $row) {
            if (round((float) ($row['amount'] ?? 0), 2) <= 0.009
                && round((float) ($row['amount_foreign'] ?? 0), 2) > 0.009
            ) {
                $row['paid_by'] = $foreignPaidBy;
                $place = $foreignPaidBy === 'office' ? 'w biurze' : 'u pilota / w autokarze';
                if (blank($row['notes'] ?? null)) {
                    $row['notes'] = 'Płatne '.$place;
                }
                if (is_string($row['label'] ?? null) && str_contains((string) $row['label'], 'autokar')) {
                    $row['label'] = 'Waluta — płatne '.$place;
                }
                $fxRows[] = $row;
            }
        }

        if ($fxRows === []) {
            foreach ($foreignPerPerson as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper((string) ($row['currency'] ?? ''));
                $amount = round((float) ($row['price_per_person'] ?? 0) * $multiplier, 2);
                if ($code === '' || $code === 'PLN' || $amount <= 0) {
                    continue;
                }
                $place = $foreignPaidBy === 'office' ? 'w biurze' : 'u pilota / w autokarze';
                $fxRows[] = [
                    'label' => 'Waluta — płatne '.$place,
                    'amount' => 0,
                    'amount_foreign' => $amount,
                    'currency_code' => $code,
                    'paid_by' => $foreignPaidBy,
                    'due_date' => $event?->start_date?->toDateString(),
                    'notes' => 'Płatne '.$place,
                ];
            }
        }

        return array_values(array_merge($plnRows, $fxRows));
    }

    /**
     * Skaluje raty (PLN i FX) × liczba osób.
     *
     * @param  list<array<string, mixed>>  $schedules
     * @return list<array<string, mixed>>
     */
    public static function scaleSchedules(array $schedules, int $multiplier): array
    {
        $multiplier = max(1, $multiplier);
        if ($multiplier === 1) {
            return $schedules;
        }

        return array_map(static function (array $row) use ($multiplier): array {
            $row['amount'] = round(((float) ($row['amount'] ?? 0)) * $multiplier, 2);
            if (isset($row['amount_foreign']) && (float) $row['amount_foreign'] > 0) {
                $row['amount_foreign'] = round((float) $row['amount_foreign'] * $multiplier, 2);
            }

            return $row;
        }, $schedules);
    }
}
