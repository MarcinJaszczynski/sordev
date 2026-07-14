<?php

namespace App\Livewire;

use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventSettlementParticipantPaymentEntry;
use App\Services\ParticipantPaymentBalanceService;
use App\Services\ParticipantPaymentLedgerService;
use App\Services\ParticipantPaymentReminderService;
use App\Support\MoneyFormatter;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Component;

class ParticipantPaymentsLedger extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public int $settlementId;

    public int $eventId;

    public function mount(int $settlementId, int $eventId): void
    {
        $this->settlementId = $settlementId;
        $this->eventId = $eventId;

        $settlement = EventSettlement::query()->findOrFail($settlementId);
        abort_unless((int) $settlement->event_id === $eventId, 404);
    }

    #[On('settlement-data-changed')]
    public function refreshLedger(): void {}

    public function render()
    {
        return view('livewire.participant-payments-ledger', [
            'rows' => $this->ledgerRows(),
        ]);
    }

    public function addParticipantAction(): Action
    {
        return Action::make('addParticipant')
            ->label('Dodaj uczestnika')
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->form([
                Forms\Components\TextInput::make('participant_name')
                    ->label('Imię i nazwisko')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('booking_reference')
                    ->label('Nr rezerwacji')
                    ->maxLength(255),
                Forms\Components\TextInput::make('due_amount_pln')
                    ->label('Należna kwota (PLN)')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->suffix('PLN'),
            ])
            ->action(function (array $data): void {
                $settlement = $this->settlement();

                EventSettlementParticipantPayment::query()->create([
                    'settlement_id' => $settlement->id,
                    'participant_name' => $data['participant_name'],
                    'booking_reference' => $data['booking_reference'] ?? null,
                    'due_amount_pln' => round((float) ($data['due_amount_pln'] ?? 0), 2),
                    'paid_amount_pln' => 0,
                    'payment_status' => 'pending',
                ]);

                $this->notifyChanged('Dodano uczestnika do rozliczenia.');
            });
    }

    public function addEntryAction(): Action
    {
        return Action::make('addEntry')
            ->icon('heroicon-o-plus')
            ->color('gray')
            ->iconButton()
            ->modalHeading('Dodaj wpłatę')
            ->form([
                Forms\Components\DateTimePicker::make('paid_at')
                    ->label('Data wpłaty')
                    ->required()
                    ->default(now()),
                Forms\Components\TextInput::make('amount_pln')
                    ->label('Kwota (PLN)')
                    ->numeric()
                    ->required()
                    ->suffix('PLN'),
                Forms\Components\TextInput::make('payer_name')
                    ->label('Płatnik')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('bank_transfer_description')
                    ->label('Opis przelewu')
                    ->maxLength(255),
                Forms\Components\Select::make('payment_kind')
                    ->label('Rodzaj wpłaty')
                    ->options(EventSettlementParticipantPaymentEntry::$paymentKinds)
                    ->default(EventSettlementParticipantPaymentEntry::KIND_REGULAR)
                    ->required(),
                Forms\Components\Select::make('payment_method')
                    ->label('Forma płatności')
                    ->options(EventSettlementParticipantPayment::$paymentMethods)
                    ->default('transfer'),
            ])
            ->action(function (array $data, array $arguments): void {
                $payment = $this->findPayment((int) ($arguments['paymentId'] ?? 0));

                app(ParticipantPaymentLedgerService::class)->addEntry(
                    payment: $payment,
                    amount: (float) $data['amount_pln'],
                    paidAt: $data['paid_at'] ?? now(),
                    source: EventSettlementParticipantPaymentEntry::SOURCE_MANUAL,
                    paymentMethod: $data['payment_method'] ?? 'transfer',
                    payerName: $data['payer_name'] ?? null,
                    bankTransferDescription: $data['bank_transfer_description'] ?? null,
                    paymentKind: $data['payment_kind'] ?? EventSettlementParticipantPaymentEntry::KIND_REGULAR,
                );

                $this->notifyChanged('Wpłata została zapisana.');
            });
    }

    public function removeEntryAction(): Action
    {
        return Action::make('removeEntry')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->iconButton()
            ->requiresConfirmation()
            ->modalHeading('Usuń wpłatę')
            ->modalDescription('Czy na pewno usunąć tę wpłatę z historii?')
            ->action(function (array $arguments): void {
                $entry = EventSettlementParticipantPaymentEntry::query()
                    ->whereKey((int) ($arguments['entryId'] ?? 0))
                    ->whereHas('participantPayment', fn ($query) => $query
                        ->where('settlement_id', $this->settlementId)
                        ->whereHas('settlement', fn ($settlementQuery) => $settlementQuery->where('event_id', $this->eventId)))
                    ->firstOrFail();

                app(ParticipantPaymentLedgerService::class)->removeEntry($entry);

                $this->notifyChanged('Wpłata została usunięta.');
            });
    }

    public function removeParticipantAction(): Action
    {
        return Action::make('removeParticipant')
            ->label('Usuń')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Usuń uczestnika z rozliczenia')
            ->modalDescription('Czy na pewno usunąć uczestnika i całą historię wpłat?')
            ->action(function (array $arguments): void {
                $payment = $this->findPayment((int) ($arguments['paymentId'] ?? 0));

                if ($payment->hasLinkedAgreementsBesides()) {
                    Notification::make()
                        ->title('Nie można usunąć')
                        ->body('Uczestnik ma powiązane umowy lub aneksy. Odłącz je przed usunięciem.')
                        ->danger()
                        ->send();

                    return;
                }

                $payment->delete();

                $this->notifyChanged('Uczestnik został usunięty z rozliczenia.');
            });
    }

    public function sendReminderAction(): Action
    {
        return Action::make('sendReminder')
            ->label('Wyślij przypomnienie')
            ->icon('heroicon-o-envelope')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Wyślij przypomnienie o dopłacie')
            ->modalDescription('E-mail zostanie wysłany na adres uczestnika z informacją o pozostałej kwocie.')
            ->action(function (array $arguments): void {
                $payment = $this->findPayment((int) ($arguments['paymentId'] ?? 0));
                $result = app(ParticipantPaymentReminderService::class)->sendReminder($payment);

                if (! ($result['sent'] ?? false)) {
                    Notification::make()
                        ->title('Nie wysłano przypomnienia')
                        ->body((string) ($result['message'] ?? 'Brak adresu e-mail uczestnika.'))
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Przypomnienie wysłane')
                    ->body((string) ($result['message'] ?? 'E-mail został zakolejkowany.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function ledgerRows(): Collection
    {
        if (! Schema::hasTable('event_settlement_participant_payments')) {
            return collect();
        }

        $balanceService = app(ParticipantPaymentBalanceService::class);

        return EventSettlementParticipantPayment::query()
            ->where('settlement_id', $this->settlementId)
            ->whereHas('settlement', fn ($query) => $query->where('event_id', $this->eventId))
            ->with([
                'entries' => fn ($query) => $query->orderBy('paid_at')->orderBy('id'),
                'eventParticipant' => fn ($query) => $query->where('event_id', $this->eventId),
                'contracts' => fn ($query) => $query->where('event_id', $this->eventId),
            ])
            ->orderBy('participant_name')
            ->get()
            ->map(function (EventSettlementParticipantPayment $payment) use ($balanceService): array {
                $balance = $balanceService->balanceRow($payment);
                $participant = $payment->eventParticipant;

                return [
                    'payment' => $payment,
                    'participant_name' => $payment->participant_name,
                    'booking_reference' => $payment->booking_reference,
                    'email' => $participant?->email,
                    'phone' => $participant?->phone,
                    'due_pln' => (float) ($balance['due_pln'] ?? 0),
                    'paid_pln' => (float) ($balance['paid_pln'] ?? 0),
                    'remaining_pln' => (float) ($balance['remaining_pln'] ?? 0),
                    'entries' => $payment->entries,
                ];
            });
    }

    protected function settlement(): EventSettlement
    {
        return EventSettlement::query()
            ->where('event_id', $this->eventId)
            ->whereKey($this->settlementId)
            ->firstOrFail();
    }

    protected function findPayment(int $paymentId): EventSettlementParticipantPayment
    {
        return EventSettlementParticipantPayment::query()
            ->where('settlement_id', $this->settlementId)
            ->whereHas('settlement', fn ($query) => $query->where('event_id', $this->eventId))
            ->whereKey($paymentId)
            ->firstOrFail();
    }

    protected function notifyChanged(string $message): void
    {
        $this->dispatch('settlement-data-changed');

        Notification::make()
            ->title($message)
            ->success()
            ->send();
    }
}
