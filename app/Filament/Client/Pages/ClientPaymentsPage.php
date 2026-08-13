<?php

declare(strict_types=1);

namespace App\Filament\Client\Pages;

use App\Actions\Finance\GenerateInstallmentPaymentLinkAction;
use App\Data\GenerateInstallmentPaymentLinkData;
use App\Filament\Actions\HelpArticleAction;
use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Services\ClientAccessService;
use App\Services\ContractGroupPricingService;
use App\Services\ContractInstallmentCheckoutService;
use App\Services\ParticipantPaymentBalanceService;
use App\Support\PaymentCta;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ClientPaymentsPage extends Page
{
    use AuthorizesClientTrip;
    use \App\Filament\Concerns\ShowsParticipantPaymentBalance;
    use HasClientTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-payments-page';

    protected static ?string $slug = 'payments/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);

        $service = app(ClientAccessService::class);
        $user = Auth::user();
        abort_unless(
            $service->isParticipant($user, $event) || $service->isGuardian($user, $event),
            403
        );

        $this->event = $event;
    }

    public function payInstallment(int $scheduleId): void
    {
        $user = Auth::user();
        $access = app(ClientAccessService::class);
        $access->assertPortalMutationsAllowed();

        abort_unless($access->userOwnsContractSchedule($user, $this->event, $scheduleId), 403);

        $schedule = ContractPaymentSchedule::query()
            ->whereKey($scheduleId)
            ->whereHas('contract', fn ($q) => $q->where('event_id', $this->event->id))
            ->firstOrFail();

        $result = app(GenerateInstallmentPaymentLinkAction::class)(
            new GenerateInstallmentPaymentLinkData(schedule: $schedule)
        );

        $this->redirect($result['url']);
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'payments';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Płatności: '.$this->event->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpArticleAction::make('jak-zaplacic', 'portal'),
        ];
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    protected function getViewData(): array
    {
        $user = Auth::user();
        $access = app(ClientAccessService::class);
        $isParticipant = $access->isParticipant($user, $this->event);
        $isGuardian = $access->isGuardian($user, $this->event);

        $contract = null;
        if ($isParticipant) {
            $contract = $access->participantContract($user, $this->event);
        } elseif ($isGuardian) {
            $contract = $access->accessibleContract($user, $this->event, EventPortalAccess::ROLE_GUARDIAN);
        }

        $presentation = $contract
            ? app(ContractGroupPricingService::class)->presentationFor($contract)
            : null;

        $checkout = $contract instanceof Contract
            ? app(ContractInstallmentCheckoutService::class)->snapshot($contract)
            : null;

        $payment = $isParticipant
            ? $access->participantPayment($user, $this->event)
            : null;

        // Jedno źródło salda: ParticipantPaymentBalanceService (umowa → ledger).
        $balanceService = app(ParticipantPaymentBalanceService::class);
        $balance = null;
        if ($contract instanceof Contract) {
            $balance = $balanceService->forContract($contract);
            if ($payment) {
                // Soft-sync księgi przy wejściu na płatności (te same liczby co lista uczestników).
                $balanceService->balanceRow($payment->loadMissing('contracts.paymentSchedules'));
            }
        } elseif ($payment) {
            $balance = $balanceService->balanceRow($payment->loadMissing('contracts.paymentSchedules'));
        }

        $duePln = $balance['due_pln'] ?? null;
        $paidPln = $balance['paid_pln'] ?? null;
        $remainingPln = $balance['remaining_pln'] ?? null;
        $statusLabel = $balance['display_status_label'] ?? null;

        $chargeNow = null;
        $chargeLabel = null;
        $chargeScheduleId = null;
        if (is_array($checkout) && ! empty($checkout['next_pln']) && (float) ($checkout['next_pln']['remaining'] ?? 0) > 0.009) {
            $chargeNow = (float) $checkout['next_pln']['remaining'];
            $chargeLabel = $checkout['next_pln']['label'] ?? 'Bieżąca rata';
            $chargeScheduleId = (int) ($checkout['next_pln']['id'] ?? 0) ?: null;
        } elseif ($balance && (float) ($balance['next_due_amount'] ?? 0) > 0.009) {
            $chargeNow = (float) $balance['next_due_amount'];
            $chargeLabel = $balance['installment_label'] ?? 'Bieżąca rata';
            $chargeScheduleId = $balance['next_schedule_id'] ?? null;
        }

        $payerName = $contract?->signer_name
            ?: $contract?->customer_name
            ?: $payment?->participant_name
            ?: null;

        $dietBreakdown = $contract instanceof Contract
            ? app(\App\Services\ContractDietSurchargeService::class)->presentation($contract)
            : null;

        return [
            'event' => $this->event->loadMissing(['eventTemplate', 'startPlace']),
            'isParticipant' => $isParticipant,
            'isGuardian' => $isGuardian,
            'contract' => $contract,
            'presentation' => $presentation,
            'checkout' => $checkout,
            'payment' => $payment,
            'payerName' => $payerName,
            'bookingReference' => $payment?->booking_reference ?: $contract?->contract_number,
            'duePln' => $duePln,
            'paidPln' => $paidPln,
            'remainingPln' => $remainingPln,
            'statusLabel' => $statusLabel,
            'chargeNow' => $chargeNow,
            'chargeLabel' => $chargeLabel,
            'chargeScheduleId' => $chargeScheduleId,
            'payCta' => PaymentCta::label(),
            'archiveMessage' => $access->archiveMessage($this->event),
            'accessHint' => $access->accessUntilHint($this->event),
            'groupPaymentsUrl' => $isGuardian
                ? ClientGroupPaymentsPage::urlFor($this->event)
                : null,
            'dietBreakdown' => $dietBreakdown,
        ];
    }
}
