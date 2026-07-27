<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventAgreementPaymentSchedule;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Support\CurrencyAmountDisplay;
use App\Support\Tasks\OfficeTaskRecipients;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class EventPaymentReminderSyncService
{
    public function syncSettlementCost(EventSettlementCost $cost): void
    {
        $cost->loadMissing(['settlement.event', 'plannedCurrency']);
        $event = $cost->settlement?->event;
        $kind = $this->resolveSettlementKind($cost) ?? 'payment';

        if (! $event || $cost->trashed() || ! $cost->exists) {
            $this->retireTasks($this->fingerprint('settlement_cost', $cost->id, $kind));

            return;
        }

        if ($kind === null || ! $this->shouldRemindForSettlementCost($cost, $kind)) {
            $this->retireTasks($this->fingerprint('settlement_cost', $cost->id, $kind ?? 'payment'));

            return;
        }

        $dueDate = $cost->advance_due_date;

        if (! $dueDate) {
            $this->retireTasks($this->fingerprint('settlement_cost', $cost->id, $kind));

            return;
        }

        $programPoint = $cost->programPoint();
        $amountLabel = $this->formatSettlementCostAmount($cost, $kind);

        $this->upsertTask(
            fingerprint: $this->fingerprint('settlement_cost', $cost->id, $kind),
            title: $this->titleForKind($kind, $cost->name ?: 'Koszt rozliczenia', $event),
            description: 'Kwota: '.$amountLabel.'. Sprawdź rezerwację i rozliczenie imprezy.',
            dueDate: $dueDate,
            event: $event,
            taskableType: $programPoint ? EventProgramPoint::class : Event::class,
            taskableId: $programPoint?->id ?? $event->id,
            url: $cost->settlement_id
                ? \App\Filament\Resources\EventSettlementResource::getUrl('edit', ['record' => $cost->settlement_id])
                : \App\Filament\Resources\EventResource::getUrl('reservations', ['record' => $event->id]),
        );
    }

    public function syncContractSchedule(ContractPaymentSchedule $schedule): void
    {
        $fingerprint = $this->fingerprint('contract_schedule', $schedule->id, 'schedule');

        if (! $schedule->exists) {
            $this->retireTasks($fingerprint);

            return;
        }

        $schedule->loadMissing(['contract.event', 'contract.paymentSchedules']);
        $event = $schedule->contract?->event;

        if (! $event || ! $schedule->due_date) {
            $this->retireTasks($fingerprint);

            return;
        }

        if (! $this->isContractScheduleOutstanding($schedule)) {
            $this->retireTasks($fingerprint);

            return;
        }

        $this->upsertTask(
            fingerprint: $fingerprint,
            title: 'Termin raty kontraktu: '.($schedule->label ?: 'Rata').' ('.$event->name.')',
            description: 'Kwota: '.\App\Support\MoneyFormatter::format((float) $schedule->amount, $schedule->contract?->currency ?: 'PLN').'.',
            dueDate: Carbon::parse($schedule->due_date),
            event: $event,
            taskableType: Event::class,
            taskableId: $event->id,
            url: $schedule->contract_id
                ? \App\Filament\Resources\ContractResource::getUrl('edit', ['record' => $schedule->contract_id])
                : \App\Filament\Resources\EventResource::getUrl('reservations', ['record' => $event->id]),
        );
    }

    public function syncAgreementSchedule(EventAgreementPaymentSchedule $schedule): void
    {
        $fingerprint = $this->fingerprint('agreement_schedule', $schedule->id, 'schedule');

        if (! $schedule->exists) {
            $this->retireTasks($fingerprint);

            return;
        }

        $schedule->loadMissing(['eventAgreement.event', 'eventAgreement.paymentSchedules']);
        $event = $schedule->eventAgreement?->event;

        if (! $event || ! $schedule->due_date) {
            $this->retireTasks($fingerprint);

            return;
        }

        if (! $this->isAgreementScheduleOutstanding($schedule)) {
            $this->retireTasks($fingerprint);

            return;
        }

        $this->upsertTask(
            fingerprint: $fingerprint,
            title: 'Termin raty umowy: '.($schedule->label ?: 'Rata').' ('.$event->name.')',
            description: 'Kwota: '.\App\Support\MoneyFormatter::format((float) $schedule->amount, 'PLN').'.',
            dueDate: Carbon::parse($schedule->due_date),
            event: $event,
            taskableType: Event::class,
            taskableId: $event->id,
            url: \App\Filament\Resources\EventResource::getUrl('reservations', ['record' => $event->id]),
        );
    }

    public function syncVendorInvoice(VendorInvoice $invoice): void
    {
        $fingerprint = $this->fingerprint('vendor_invoice', $invoice->id, 'invoice');

        if (! $invoice->exists) {
            $this->retireTasks($fingerprint);

            return;
        }

        $invoice->loadMissing('event');
        $event = $invoice->event;

        if (! $event || ! $invoice->due_date || ! in_array($invoice->payment_status, ['due', 'partial'], true)) {
            $this->retireTasks($fingerprint);

            return;
        }

        $gross = (float) ($invoice->gross_amount ?? 0);
        $paid = (float) ($invoice->paid_amount ?? 0);
        $remaining = max(0, $gross - $paid);
        $amount = $remaining > 0 ? $remaining : $gross;

        $this->upsertTask(
            fingerprint: $this->fingerprint('vendor_invoice', $invoice->id, 'invoice'),
            title: 'Termin faktury KSeF: '.($invoice->invoice_number ?: ('#'.$invoice->id)).' ('.$event->name.')',
            description: 'Kwota: '.\App\Support\MoneyFormatter::format($amount, $invoice->currency ?: 'PLN').'.',
            dueDate: Carbon::parse($invoice->due_date),
            event: $event,
            taskableType: Event::class,
            taskableId: $event->id,
            url: \App\Filament\Resources\VendorInvoiceResource::getUrl('edit', ['record' => $invoice->id]),
        );
    }

    private function upsertTask(
        string $fingerprint,
        string $title,
        string $description,
        Carbon $dueDate,
        Event $event,
        string $taskableType,
        int $taskableId,
        ?string $url,
    ): void {
        $statusId = Task::getDefaultStatusId();

        if (! $statusId) {
            return;
        }

        $body = trim($description."\n\n".$fingerprint.($url ? "\n\nLink: ".$url : ''));
        $recipients = $this->resolveRecipients($event);

        if ($recipients->isEmpty()) {
            return;
        }

        $maxOrder = (int) Task::query()->where('status_id', $statusId)->max('order');

        foreach ($recipients as $recipient) {
            $existing = Task::query()
                ->where('description', 'like', '%'.$fingerprint.'%')
                ->where('assignee_id', $recipient->id)
                ->whereHas('status', fn ($query) => $query->where('name', '!=', 'Zakończone'))
                ->first();

            if ($existing) {
                $existing->update([
                    'title' => $title,
                    'description' => $body,
                    'due_date' => $dueDate,
                    'taskable_type' => $taskableType,
                    'taskable_id' => $taskableId,
                    'source' => TaskSource::System->value,
                ]);

                NotificationService::clearCacheForUser((int) $recipient->id);

                continue;
            }

            Task::create([
                'title' => $title,
                'description' => $body,
                'due_date' => $dueDate,
                'status_id' => $statusId,
                'priority' => TaskPriority::Urgent->value,
                'source' => TaskSource::System->value,
                'author_id' => $recipient->id,
                'assignee_id' => $recipient->id,
                'taskable_type' => $taskableType,
                'taskable_id' => $taskableId,
                'order' => ++$maxOrder,
            ]);

            NotificationService::clearCacheForUser((int) $recipient->id);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipients(Event $event): Collection
    {
        $recipients = OfficeTaskRecipients::users();

        if ($recipients->isNotEmpty()) {
            return $recipients;
        }

        if ($event->assigned_to) {
            $assignee = User::query()->find($event->assigned_to);

            if ($assignee) {
                return collect([$assignee]);
            }
        }

        $fallbackId = Auth::id();

        if ($fallbackId) {
            $fallback = User::query()->find($fallbackId);

            if ($fallback) {
                return collect([$fallback]);
            }
        }

        return collect();
    }

    private function retireTasks(string $fingerprint): void
    {
        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');

        if (! $completedStatusId) {
            return;
        }

        Task::query()
            ->where('description', 'like', '%'.$fingerprint.'%')
            ->where('status_id', '!=', $completedStatusId)
            ->update(['status_id' => $completedStatusId]);
    }

    private function fingerprint(string $source, int $id, string $kind): string
    {
        return '[payment-reminder:'.$source.':'.$id.':'.$kind.']';
    }

    private function resolveSettlementKind(EventSettlementCost $cost): ?string
    {
        if ($cost->source_type === 'program_point') {
            return 'plan';
        }

        if ($cost->source_type === 'program_point_payment') {
            if (in_array($cost->advance_type, ['advance', 'deposit'], true) || (float) ($cost->advance_amount ?? 0) > 0) {
                return 'advance';
            }

            return 'payment';
        }

        if (in_array($cost->advance_type, ['advance', 'deposit'], true)) {
            return 'advance';
        }

        return 'payment';
    }

    private function shouldRemindForSettlementCost(EventSettlementCost $cost, string $kind): bool
    {
        if (in_array($cost->payment_status, ['paid', 'cancelled'], true)) {
            return false;
        }

        if ($kind === 'advance' && (filled($cost->paid_at) || in_array($cost->payment_status, ['advance_paid', 'paid'], true))) {
            return false;
        }

        if ($kind === 'payment' && (filled($cost->paid_at) || filled($cost->actual_amount))) {
            return false;
        }

        return filled($cost->advance_due_date);
    }

    private function formatSettlementCostAmount(EventSettlementCost $cost, string $kind): string
    {
        $amount = match ($kind) {
            'advance' => (float) ($cost->advance_amount ?? $cost->planned_amount ?? 0),
            default => (float) ($cost->planned_amount ?? $cost->advance_amount ?? 0),
        };

        return CurrencyAmountDisplay::format(
            $amount,
            $cost->plannedCurrency,
            (bool) ($cost->planned_convert_to_pln ?? true),
        );
    }

    private function titleForKind(string $kind, string $name, Event $event): string
    {
        $eventLabel = $event->name ?: ('Impreza #'.$event->id);

        return match ($kind) {
            'plan' => 'Termin płatności punktu programu: '.$name.' ('.$eventLabel.')',
            'advance' => 'Termin zapłaty zaliczki: '.$name.' ('.$eventLabel.')',
            'payment' => 'Termin płatności: '.$name.' ('.$eventLabel.')',
            default => 'Termin płatności: '.$name.' ('.$eventLabel.')',
        };
    }

    private function isContractScheduleOutstanding(ContractPaymentSchedule $schedule): bool
    {
        $contract = $schedule->contract;

        if (! $contract) {
            return false;
        }

        return $this->isParentScheduleOutstanding(
            $schedule,
            $contract,
            $contract->paymentSchedules ?? collect(),
        );
    }

    private function isAgreementScheduleOutstanding(EventAgreementPaymentSchedule $schedule): bool
    {
        $agreement = $schedule->eventAgreement;

        if (! $agreement) {
            return false;
        }

        return $this->isParentScheduleOutstanding(
            $schedule,
            $agreement,
            $agreement->paymentSchedules ?? collect(),
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ContractPaymentSchedule|EventAgreementPaymentSchedule>|\Illuminate\Support\Collection<int, ContractPaymentSchedule|EventAgreementPaymentSchedule>  $schedules
     */
    private function isParentScheduleOutstanding(
        ContractPaymentSchedule|EventAgreementPaymentSchedule $schedule,
        Contract|\App\Models\EventAgreement|null $parent,
        \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection $schedules,
    ): bool {
        if (! $parent) {
            return false;
        }

        if (in_array($parent->payment_status, ['paid', 'cancelled', 'failed'], true)) {
            return false;
        }

        if (in_array($parent->status, ['cancelled', 'template'], true)) {
            return false;
        }

        $amountPaid = (float) ($parent->amount_paid ?? 0);
        $cumulativeDue = 0.0;

        foreach ($schedules->sortBy('sort_order')->values() as $row) {
            $cumulativeDue += (float) $row->amount;

            if ((int) $row->id === (int) $schedule->id) {
                return $amountPaid + 0.009 < $cumulativeDue;
            }
        }

        return $amountPaid + 0.009 < (float) $schedule->amount;
    }
}
