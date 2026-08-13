<?php

namespace App\Filament\Concerns;

use App\Models\BankPaymentImportBatch;
use App\Models\BankPaymentImportLine;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlementParticipantPayment;
use App\Services\BankPayments\BankPaymentImportService;
use Filament\Notifications\Notification;

/**
 * Wspólna logika ręcznego przypisania linii importu bankowego (podgląd + skrzynka).
 */
trait InteractsWithBankPaymentAssignment
{
    public ?int $assigningLineId = null;

    public string $assignTargetType = 'participant_payment';

    public ?int $assignEventId = null;

    public ?int $assignParticipantPaymentId = null;

    public ?int $assignContractId = null;

    public bool $assignAndApply = false;

    public function openAssignModal(int $lineId): void
    {
        $line = $this->findAssignableLine($lineId);

        if (! $line) {
            Notification::make()->title('Nie znaleziono linii')->danger()->send();

            return;
        }

        $this->assigningLineId = $line->id;
        $this->assignAndApply = false;

        if ($line->match_status === 'matched_pilot_advance') {
            $this->assignTargetType = 'pilot_advance';
            $this->assignEventId = $line->event_id;
            $this->assignParticipantPaymentId = null;
            $this->assignContractId = null;
        } elseif ($line->contract_id) {
            $this->assignTargetType = 'contract';
            $this->assignContractId = $line->contract_id;
            $this->assignEventId = $line->event_id ?? $line->contract?->event_id;
            $this->assignParticipantPaymentId = null;
        } elseif ($line->participant_payment_id) {
            $this->assignTargetType = 'participant_payment';
            $this->assignParticipantPaymentId = $line->participant_payment_id;
            $this->assignEventId = $line->event_id
                ?? $line->participantPayment?->settlement?->event_id;
            $this->assignContractId = null;
        } else {
            $this->assignTargetType = 'participant_payment';
            $this->assignEventId = $this->defaultAssignEventId();
            $this->assignParticipantPaymentId = null;
            $this->assignContractId = null;
        }
    }

    public function closeAssignModal(): void
    {
        $this->assigningLineId = null;
        $this->assignTargetType = 'participant_payment';
        $this->assignEventId = null;
        $this->assignParticipantPaymentId = null;
        $this->assignContractId = null;
        $this->assignAndApply = false;
    }

    public function updatedAssignTargetType(): void
    {
        $this->assignParticipantPaymentId = null;
        $this->assignContractId = null;

        if ($this->assignTargetType !== 'pilot_advance' && ! $this->assignEventId) {
            $this->assignEventId = $this->defaultAssignEventId();
        }
    }

    public function updatedAssignEventId(): void
    {
        $this->assignParticipantPaymentId = null;
        $this->assignContractId = null;
    }

    public function saveAssignment(): void
    {
        $line = $this->findAssignableLine($this->assigningLineId);

        if (! $line) {
            Notification::make()->title('Nie znaleziono linii')->danger()->send();

            return;
        }

        try {
            $service = app(BankPaymentImportService::class);

            $service->assignLine(
                $line,
                $this->assignTargetType,
                $this->assignParticipantPaymentId,
                $this->assignContractId,
                $this->assignEventId,
            );

            if ($this->assignAndApply) {
                $line = $line->fresh();
                $batch = BankPaymentImportBatch::query()->findOrFail($line->batch_id);
                $line->update(['selected' => true]);
                $result = $service->applyLines($batch, [$line->id]);

                if (($result['applied'] ?? 0) < 1) {
                    Notification::make()
                        ->title('Przypisano, ale nie zaksięgowano')
                        ->body(implode(' ', $result['errors'] ?? []))
                        ->warning()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Przypisano i zaksięgowano')
                        ->success()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('Przypisanie zapisane')
                    ->success()
                    ->send();
            }

            $this->closeAssignModal();
            $this->afterBankPaymentAssignmentSaved();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Nie udało się przypisać')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearAssignment(int $lineId): void
    {
        $line = $this->findAssignableLine($lineId);

        if (! $line) {
            return;
        }

        try {
            app(BankPaymentImportService::class)->assignLine($line, 'clear');
            Notification::make()->title('Usunięto przypisanie')->success()->send();
            $this->closeAssignModal();
            $this->afterBankPaymentAssignmentSaved();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Nie udało się wyczyścić')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<int|string, string>
     */
    public function assignEventOptions(?string $search = null): array
    {
        $query = Event::query()->orderByDesc('id');

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        $defaultEventId = $this->defaultAssignEventId();
        if ($defaultEventId) {
            $query->whereKey($defaultEventId);
        }

        return $query
            ->limit(40)
            ->get()
            ->mapWithKeys(fn (Event $event) => [
                $event->id => trim(($event->code ? $event->code.' — ' : '').($event->name ?? '#'.$event->id)),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function assignParticipantPaymentOptions(): array
    {
        if (! $this->assignEventId) {
            return [];
        }

        return EventSettlementParticipantPayment::query()
            ->with('settlement')
            ->whereHas('settlement', fn ($q) => $q->where('event_id', $this->assignEventId))
            ->where('payment_status', '!=', 'cancelled')
            ->orderBy('participant_name')
            ->get()
            ->mapWithKeys(function (EventSettlementParticipantPayment $payment) {
                $due = number_format((float) $payment->due_amount_pln, 2, ',', ' ');
                $paid = number_format((float) $payment->paid_amount_pln, 2, ',', ' ');
                $ref = $payment->booking_reference ? ' · '.$payment->booking_reference : '';

                return [
                    $payment->id => $payment->participant_name.$ref.' ('.$paid.'/'.$due.' PLN)',
                ];
            })
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function assignContractOptions(): array
    {
        if (! $this->assignEventId) {
            return [];
        }

        return Contract::query()
            ->where('event_id', $this->assignEventId)
            ->whereNotIn('status', ['cancelled', 'template'])
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->mapWithKeys(function (Contract $contract) {
                $number = $contract->contract_number ?: $contract->reservation_number ?: '#'.$contract->id;
                $party = $contract->participant_name ?: $contract->title ?: '';

                return [
                    $contract->id => trim($number.($party ? ' — '.$party : '')),
                ];
            })
            ->all();
    }

    protected function defaultAssignEventId(): ?int
    {
        return null;
    }

    protected function findAssignableLine(?int $lineId): ?BankPaymentImportLine
    {
        if (! $lineId) {
            return null;
        }

        $line = BankPaymentImportLine::query()
            ->with(['contract', 'participantPayment.settlement', 'event'])
            ->whereKey($lineId)
            ->where('applied', false)
            ->first();

        return $line && $this->canManageBankPaymentLine($line) ? $line : null;
    }

    protected function canManageBankPaymentLine(BankPaymentImportLine $line): bool
    {
        return true;
    }

    protected function afterBankPaymentAssignmentSaved(): void
    {
        //
    }
}
