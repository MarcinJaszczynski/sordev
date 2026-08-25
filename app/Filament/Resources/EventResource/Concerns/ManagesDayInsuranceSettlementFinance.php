<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Concerns;

use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Services\SettlementPaymentHealthService;
use App\Support\MoneyFormatter;
use Filament\Notifications\Notification;
use Filament\Tables;

/**
 * Kolumny finansowe + otwarcie wspólnego drawera kosztu dla ubezpieczeń dnia.
 */
trait ManagesDayInsuranceSettlementFinance
{
    protected function settlementOwnerEvent(): Event
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Event
            ? $owner
            : throw new \LogicException('Settlement finance requires an Event owner record.');
    }

    protected function ensureDayInsurancePlanCost(EventDayInsurance $dayInsurance): ?EventSettlementCost
    {
        $event = $this->settlementOwnerEvent();
        $event->refreshActiveSettlementCosts();

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        return $settlement->costs()
            ->where('source_type', 'insurance_day')
            ->where('source_id', $dayInsurance->id)
            ->first();
    }

    protected function resolveDayInsurancePlanCost(EventDayInsurance $dayInsurance): ?EventSettlementCost
    {
        $settlement = $this->settlementOwnerEvent()->activeSettlement
            ?? EventSettlement::findOrCreateActiveForEvent($this->settlementOwnerEvent());

        return $settlement->costs()
            ->where('source_type', 'insurance_day')
            ->where('source_id', $dayInsurance->id)
            ->first();
    }

    /**
     * @return array{plan: ?EventSettlementCost, paid_pln: float, docs: int, status: ?string}
     */
    protected function dayInsuranceFinanceSnapshot(EventDayInsurance $dayInsurance): array
    {
        $plan = $this->resolveDayInsurancePlanCost($dayInsurance);
        if (! $plan) {
            return [
                'plan' => null,
                'paid_pln' => 0.0,
                'docs' => 0,
                'status' => null,
            ];
        }

        $settlement = $plan->settlement;
        $allCosts = $settlement?->relationLoaded('costs')
            ? $settlement->costs
            : ($settlement?->costs()->get() ?? collect());

        $health = app(SettlementPaymentHealthService::class);
        $paidPln = $health->paidPlnForPlanCost($plan, $allCosts);

        $docs = EventSettlementDocument::query()
            ->where('settlement_id', $plan->settlement_id)
            ->whereJsonContains('linked_cost_ids', (int) $plan->id)
            ->count();

        return [
            'plan' => $plan,
            'paid_pln' => $paidPln,
            'docs' => $docs,
            'status' => $plan->payment_status,
        ];
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    protected function dayInsuranceFinanceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('finance_plan')
                ->label('Plan')
                ->state(function (EventDayInsurance $record): string {
                    $snap = $this->dayInsuranceFinanceSnapshot($record);
                    $plan = $snap['plan'];
                    if (! $plan) {
                        return '—';
                    }

                    $amount = $plan->planned_amount_pln ?? $plan->planned_amount;

                    return MoneyFormatter::format((float) ($amount ?? 0), 'PLN');
                })
                ->alignEnd(),

            Tables\Columns\TextColumn::make('finance_paid')
                ->label('Zapłacono')
                ->state(function (EventDayInsurance $record): string {
                    $snap = $this->dayInsuranceFinanceSnapshot($record);
                    if (! $snap['plan']) {
                        return '—';
                    }

                    return MoneyFormatter::format($snap['paid_pln'], 'PLN');
                })
                ->alignEnd(),

            Tables\Columns\TextColumn::make('finance_status')
                ->label('Płatność')
                ->badge()
                ->state(function (EventDayInsurance $record): string {
                    $snap = $this->dayInsuranceFinanceSnapshot($record);
                    $status = $snap['status'] ?? null;

                    return $status
                        ? (EventSettlementCost::$paymentStatuses[$status] ?? $status)
                        : 'Brak w rozliczeniu';
                })
                ->color(function (EventDayInsurance $record): string {
                    $status = $this->dayInsuranceFinanceSnapshot($record)['status'] ?? null;

                    return match ($status) {
                        'paid' => 'success',
                        'partially_paid', 'advance_paid' => 'warning',
                        'planned', 'advance_required' => 'gray',
                        default => 'gray',
                    };
                }),

            Tables\Columns\TextColumn::make('finance_docs')
                ->label('Dok.')
                ->state(function (EventDayInsurance $record): string {
                    $snap = $this->dayInsuranceFinanceSnapshot($record);
                    $count = (int) ($snap['docs'] ?? 0);
                    if ($count <= 0) {
                        return '—';
                    }

                    return $count === 1 ? '📎 Polisa' : '📎 '.$count.' pl.';
                })
                ->tooltip(function (EventDayInsurance $record): ?string {
                    $count = (int) ($this->dayInsuranceFinanceSnapshot($record)['docs'] ?? 0);

                    return $count > 0
                        ? 'Załącznik polisy wgrany ('.$count.')'
                        : 'Brak wgranego pliku polisy';
                })
                ->color(fn (EventDayInsurance $record): string => ((int) ($this->dayInsuranceFinanceSnapshot($record)['docs'] ?? 0)) > 0
                    ? 'success'
                    : 'gray')
                ->alignCenter(),
        ];
    }

    /**
     * @return array<int, Tables\Actions\Action>
     */
    protected function dayInsuranceFinanceTableActions(): array
    {
        return [
            Tables\Actions\Action::make('open_finance')
                ->label('Płatności')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->action(function (EventDayInsurance $record): void {
                    $plan = $this->ensureDayInsurancePlanCost($record);
                    if (! $plan) {
                        Notification::make()
                            ->title('Brak pozycji w rozliczeniu')
                            ->body('Ubezpieczenie nie wygenerowało kosztu (sprawdź produkt i cenę).')
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->openCost((int) $plan->id);
                }),
        ];
    }

    protected function dayInsuranceLabel(EventDayInsurance $record): string
    {
        $name = $record->insurance?->name ?? ('ID '.$record->insurance_id);

        return 'Dzień '.(int) $record->day.' — '.$name;
    }
}
