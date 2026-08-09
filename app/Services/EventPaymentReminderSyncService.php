<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventAgreement;
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
use Illuminate\Support\Str;

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
        $this->retireTasks($this->fingerprint('contract_schedule', $schedule->id, 'schedule'));

        $schedule->loadMissing(['contract.event']);
        $event = $schedule->contract?->event;

        if ($event) {
            $this->syncEventContractInstallmentReminders($event);
        }
    }

    public function syncAgreementSchedule(EventAgreementPaymentSchedule $schedule): void
    {
        $this->retireTasks($this->fingerprint('agreement_schedule', $schedule->id, 'schedule'));

        $schedule->loadMissing(['eventAgreement.event']);
        $event = $schedule->eventAgreement?->event;

        if ($event) {
            $this->syncEventAgreementInstallmentReminders($event);
        }
    }

    public function syncEventContractInstallmentReminders(Event $event): void
    {
        $contracts = Contract::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['cancelled', 'template'])
            ->with('paymentSchedules')
            ->get();

        foreach ($contracts as $contract) {
            foreach ($contract->paymentSchedules as $schedule) {
                $this->retireTasks($this->fingerprint('contract_schedule', $schedule->id, 'schedule'));
            }
        }

        $groups = $this->buildInstallmentGroups(
            parents: $contracts,
            schedulesRelation: 'paymentSchedules',
            outstandingChecker: fn (ContractPaymentSchedule $schedule): bool => $this->isContractScheduleOutstanding($schedule),
        );

        $this->syncInstallmentGroups(
            event: $event,
            source: 'event_contract_installment',
            titlePrefix: 'Termin raty kontraktu',
            groups: $groups,
        );
    }

    public function syncEventAgreementInstallmentReminders(Event $event): void
    {
        $agreements = EventAgreement::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['cancelled', 'template'])
            ->with('paymentSchedules')
            ->get();

        foreach ($agreements as $agreement) {
            foreach ($agreement->paymentSchedules as $schedule) {
                $this->retireTasks($this->fingerprint('agreement_schedule', $schedule->id, 'schedule'));
            }
        }

        $groups = $this->buildInstallmentGroups(
            parents: $agreements,
            schedulesRelation: 'paymentSchedules',
            outstandingChecker: fn (EventAgreementPaymentSchedule $schedule): bool => $this->isAgreementScheduleOutstanding($schedule),
        );

        $this->syncInstallmentGroups(
            event: $event,
            source: 'event_agreement_installment',
            titlePrefix: 'Termin raty umowy',
            groups: $groups,
        );
    }

    /**
     * Zamyka legacy taski 1:1 per schedule i odświeża zbiorcze przypomnienia.
     *
     * @return array{retired_legacy: int, events_synced: int}
     */
    public function retireLegacyScheduleRemindersAndResync(bool $resync = true): array
    {
        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');
        $retired = 0;

        if ($completedStatusId) {
            $retired = Task::query()
                ->where(function ($query): void {
                    $query->where('description', 'like', '%[payment-reminder:contract_schedule:%')
                        ->orWhere('description', 'like', '%[payment-reminder:agreement_schedule:%');
                })
                ->where('status_id', '!=', $completedStatusId)
                ->update(['status_id' => $completedStatusId]);
        }

        $eventsSynced = 0;

        if ($resync) {
            $eventIds = Contract::query()
                ->whereNotIn('status', ['cancelled', 'template'])
                ->whereHas('paymentSchedules')
                ->pluck('event_id')
                ->merge(
                    EventAgreement::query()
                        ->whereNotIn('status', ['cancelled', 'template'])
                        ->whereHas('paymentSchedules')
                        ->pluck('event_id')
                )
                ->filter()
                ->unique()
                ->values();

            foreach ($eventIds as $eventId) {
                $event = Event::query()->find($eventId);

                if (! $event) {
                    continue;
                }

                $this->syncEventContractInstallmentReminders($event);
                $this->syncEventAgreementInstallmentReminders($event);
                $eventsSynced++;
            }
        }

        return [
            'retired_legacy' => (int) $retired,
            'events_synced' => $eventsSynced,
        ];
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

    /**
     * @param  Collection<int, Contract|EventAgreement>  $parents
     * @param  callable(ContractPaymentSchedule|EventAgreementPaymentSchedule): bool  $outstandingChecker
     * @return array<string, array{label: string, total: int, paid: int, outstanding: int, due_date: ?Carbon}>
     */
    private function buildInstallmentGroups(
        Collection $parents,
        string $schedulesRelation,
        callable $outstandingChecker,
    ): array {
        $groups = [];

        foreach ($parents as $parent) {
            $schedules = $parent->{$schedulesRelation} ?? collect();

            foreach ($schedules as $schedule) {
                if (! $schedule->due_date) {
                    continue;
                }

                $key = $this->installmentLabelKey($schedule);
                $label = trim((string) ($schedule->label ?: '')) ?: ('Rata #'.((int) $schedule->sort_order + 1));

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'label' => $label,
                        'total' => 0,
                        'paid' => 0,
                        'outstanding' => 0,
                        'due_date' => null,
                    ];
                }

                $groups[$key]['total']++;

                if ($outstandingChecker($schedule)) {
                    $groups[$key]['outstanding']++;
                    $due = Carbon::parse($schedule->due_date);

                    if ($groups[$key]['due_date'] === null || $due->lt($groups[$key]['due_date'])) {
                        $groups[$key]['due_date'] = $due;
                    }
                } else {
                    $groups[$key]['paid']++;
                }
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, array{label: string, total: int, paid: int, outstanding: int, due_date: ?Carbon}>  $groups
     */
    private function syncInstallmentGroups(
        Event $event,
        string $source,
        string $titlePrefix,
        array $groups,
    ): void {
        $activeFingerprints = [];
        $eventLabel = $event->name ?: ('Impreza #'.$event->id);
        $url = $this->eventReservationsUrl($event);

        foreach ($groups as $labelKey => $group) {
            $fingerprint = $this->groupFingerprint($source, (int) $event->id, $labelKey);

            if ($group['outstanding'] <= 0 || ! $group['due_date']) {
                $this->retireTasks($fingerprint);

                continue;
            }

            $activeFingerprints[] = $fingerprint;
            $paid = (int) $group['paid'];
            $total = (int) $group['total'];

            $this->upsertTask(
                fingerprint: $fingerprint,
                title: $titlePrefix.': '.$group['label'].' ('.$eventLabel.')',
                description: 'Zapłaciło '.$paid.' z '.$total.'.',
                dueDate: $group['due_date'],
                event: $event,
                taskableType: Event::class,
                taskableId: (int) $event->id,
                url: $url,
            );
        }

        $this->retireStaleGroupTasks($source, (int) $event->id, $activeFingerprints);
    }

    /**
     * @param  list<string>  $activeFingerprints
     */
    private function retireStaleGroupTasks(string $source, int $eventId, array $activeFingerprints): void
    {
        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');

        if (! $completedStatusId) {
            return;
        }

        $prefix = '[payment-reminder:'.$source.':'.$eventId.':';

        $tasks = Task::query()
            ->where('description', 'like', '%'.$prefix.'%')
            ->where('status_id', '!=', $completedStatusId)
            ->get(['id', 'description', 'status_id']);

        foreach ($tasks as $task) {
            if (! preg_match('/\[payment-reminder:'.preg_quote($source, '/').':'.$eventId.':([^\]]+)\]/', (string) $task->description, $matches)) {
                continue;
            }

            $fingerprint = '[payment-reminder:'.$source.':'.$eventId.':'.$matches[1].']';

            if (! in_array($fingerprint, $activeFingerprints, true)) {
                $task->update(['status_id' => $completedStatusId]);
            }
        }
    }

    private function installmentLabelKey(ContractPaymentSchedule|EventAgreementPaymentSchedule $schedule): string
    {
        $label = trim((string) ($schedule->label ?: ''));

        if ($label !== '') {
            $slug = Str::slug(Str::lower($label));

            return $slug !== '' ? $slug : 'order-'.(int) $schedule->sort_order;
        }

        return 'order-'.(int) $schedule->sort_order;
    }

    private function groupFingerprint(string $source, int $eventId, string $labelKey): string
    {
        return '[payment-reminder:'.$source.':'.$eventId.':'.$labelKey.']';
    }

    private function eventReservationsUrl(Event $event): string
    {
        try {
            return \App\Filament\Resources\EventResource::getUrl('reservations', ['record' => $event->id]);
        } catch (\Throwable) {
            return url('/admin/events/'.$event->id.'/reservations');
        }
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
        Contract|EventAgreement|null $parent,
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
