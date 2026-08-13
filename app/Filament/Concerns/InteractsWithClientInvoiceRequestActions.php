<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Forms\ClientInvoiceRequestFormFields;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Support\ClientInvoiceRequestAdminHelper;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

trait InteractsWithClientInvoiceRequestActions
{
    public function createInvoiceRequestAction(): Action
    {
        return Action::make('createInvoiceRequest')
            ->label('Wniosek o fakturę')
            ->icon('heroicon-o-receipt-percent')
            ->color('gray')
            ->visible(fn (): bool => Schema::hasTable('client_invoice_requests'))
            ->modalHeading('Wniosek o fakturę')
            ->modalDescription('Wypełnij dane nabywcy — wniosek trafi do skrzynki „Wnioski o fakturę” w module Finanse.')
            ->modalIcon('heroicon-o-receipt-percent')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Zapisz wniosek')
            ->fillForm(function (array $arguments): array {
                $participantId = (int) ($arguments['participantId'] ?? 0);
                if ($participantId > 0) {
                    $participant = EventParticipant::query()
                        ->where('event_id', $this->invoiceRequestEventId())
                        ->findOrFail($participantId);

                    return ClientInvoiceRequestAdminHelper::prefillFromParticipant($participant);
                }

                $paymentId = (int) ($arguments['paymentId'] ?? 0);
                if ($paymentId > 0) {
                    $payment = EventSettlementParticipantPayment::query()
                        ->whereKey($paymentId)
                        ->whereHas('settlement', fn ($query) => $query->where('event_id', $this->invoiceRequestEventId()))
                        ->firstOrFail();

                    return ClientInvoiceRequestAdminHelper::prefillFromPayment(
                        $payment,
                        $this->invoiceRequestEventId(),
                    );
                }

                return [
                    'event_id' => $this->invoiceRequestEventId(),
                ];
            })
            ->form(fn (): array => ClientInvoiceRequestFormFields::adminModalSchema(
                lockedEventId: $this->invoiceRequestEventId(),
                event: Event::query()->find($this->invoiceRequestEventId()),
            ))
            ->action(function (array $data, array $arguments): void {
                [$linkedUser, $contract] = $this->resolveInvoiceRequestContext($arguments);

                ClientInvoiceRequestAdminHelper::createFromAdminForm($data, $linkedUser, $contract);

                Notification::make()
                    ->title('Utworzono wniosek o fakturę')
                    ->body('Wniosek jest widoczny w skrzynce „Wnioski o fakturę”.')
                    ->success()
                    ->send();
            });
    }

    protected function invoiceRequestEventId(): int
    {
        if (property_exists($this, 'eventId') && filled($this->eventId)) {
            return (int) $this->eventId;
        }

        throw new \LogicException('Komponent musi udostępnić eventId dla wniosku o fakturę.');
    }

    /**
     * @return array{0: ?User, 1: ?Contract}
     */
    protected function resolveInvoiceRequestContext(array $arguments): array
    {
        $participantId = (int) ($arguments['participantId'] ?? 0);
        if ($participantId > 0) {
            $participant = EventParticipant::query()
                ->with('contract')
                ->where('event_id', $this->invoiceRequestEventId())
                ->findOrFail($participantId);

            return [
                self::findLinkedUser($participant->email),
                $participant->contract,
            ];
        }

        $paymentId = (int) ($arguments['paymentId'] ?? 0);
        if ($paymentId > 0) {
            $payment = EventSettlementParticipantPayment::query()
                ->with(['eventParticipant.contract', 'contracts'])
                ->whereKey($paymentId)
                ->whereHas('settlement', fn ($query) => $query->where('event_id', $this->invoiceRequestEventId()))
                ->firstOrFail();

            $participant = $payment->eventParticipant;
            $contract = $participant?->contract ?? $payment->contracts->first();
            $email = $participant?->email
                ?: $contract?->signer_email
                ?: $contract?->customer_email;

            return [
                self::findLinkedUser($email),
                $contract,
            ];
        }

        return [null, null];
    }

    protected static function findLinkedUser(?string $email): ?User
    {
        if (! filled($email)) {
            return null;
        }

        return User::query()->where('email', $email)->first();
    }
}
