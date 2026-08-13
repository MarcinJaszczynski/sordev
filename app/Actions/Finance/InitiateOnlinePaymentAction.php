<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreementPaymentSchedule;
use App\Models\OnlinePaymentSession;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InitiateOnlinePaymentAction
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
    ) {}

    /**
     * @return array{session: OnlinePaymentSession, checkout_url: string}
     */
    public function __invoke(Model $payable, ?string $payerEmail = null, ?string $description = null): array
    {
        if (! $payable instanceof ContractPaymentSchedule && ! $payable instanceof EventAgreementPaymentSchedule) {
            throw new InvalidArgumentException('Nieobsługiwany przedmiot płatności online.');
        }

        $amount = (float) ($payable->amount ?? 0);
        $paid = (float) ($payable->paid_amount ?? 0);
        $remaining = max(0, round($amount - $paid, 2));

        if ($remaining <= 0) {
            throw new InvalidArgumentException('Rata jest już opłacona.');
        }

        $event = $payable instanceof ContractPaymentSchedule
            ? $payable->contract?->event
            : $payable->eventAgreement?->event;

        $gateway = $this->gateways->driver();

        return DB::transaction(function () use ($payable, $remaining, $event, $gateway, $payerEmail, $description): array {
            $session = OnlinePaymentSession::query()->create([
                'driver' => $gateway->driver(),
                'status' => OnlinePaymentSession::STATUS_PENDING,
                'payable_type' => $payable::class,
                'payable_id' => $payable->getKey(),
                'event_id' => $event?->getKey(),
                'amount' => $remaining,
                'currency' => (string) config('payments.currency', 'PLN'),
                'description' => $description ?: ($payable->label ?? $payable->title ?? 'Rata'),
                'payer_email' => $payerEmail,
                'expires_at' => now()->addHours(max(1, (int) config('payments.session_ttl_hours', 24))),
            ]);

            $checkout = $gateway->createCheckout($session);

            $session->forceFill([
                'checkout_url' => $checkout['checkout_url'],
                'external_id' => $checkout['external_id'] ?? null,
                'meta' => $checkout['meta'] ?? null,
            ])->save();

            return [
                'session' => $session->fresh() ?? $session,
                'checkout_url' => (string) $checkout['checkout_url'],
            ];
        });
    }
}
