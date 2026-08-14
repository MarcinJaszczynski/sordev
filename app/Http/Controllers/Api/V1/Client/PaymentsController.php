<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Actions\Finance\GenerateInstallmentPaymentLinkAction;
use App\Data\GenerateInstallmentPaymentLinkData;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Services\ClientAccessService;
use App\Services\ContractDietSurchargeService;
use App\Services\ContractGroupPricingService;
use App\Services\ContractInstallmentCheckoutService;
use App\Services\ParticipantPaymentBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentsController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function show(Request $request, Event $event, ClientAccessService $access): JsonResponse
    {
        $this->authorizeClientDetails($event);

        $user = $request->user();
        $isParticipant = $access->isParticipant($user, $event);
        $isGuardian = $access->isGuardian($user, $event);
        abort_unless($isParticipant || $isGuardian, 403);

        $contract = null;
        if ($isParticipant) {
            $contract = $access->participantContract($user, $event);
        } elseif ($isGuardian) {
            $contract = $access->accessibleContract($user, $event, EventPortalAccess::ROLE_GUARDIAN);
        }

        $presentation = $contract
            ? app(ContractGroupPricingService::class)->presentationFor($contract)
            : null;

        $checkout = $contract instanceof Contract
            ? app(ContractInstallmentCheckoutService::class)->snapshot($contract)
            : null;

        $payment = $isParticipant
            ? $access->participantPayment($user, $event)
            : null;

        $balanceService = app(ParticipantPaymentBalanceService::class);
        $balance = null;
        if ($contract instanceof Contract) {
            $balance = $balanceService->forContract($contract);
            if ($payment) {
                $balanceService->balanceRow($payment->loadMissing('contracts.paymentSchedules'));
            }
        } elseif ($payment) {
            $balance = $balanceService->balanceRow($payment->loadMissing('contracts.paymentSchedules'));
        }

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

        $diet = $contract
            ? app(ContractDietSurchargeService::class)->presentation($contract)
            : null;

        return $this->success([
            'event_id' => $event->id,
            'role' => $isGuardian ? EventPortalAccess::ROLE_GUARDIAN : EventPortalAccess::ROLE_PARTICIPANT,
            'contract_id' => $contract?->id,
            'booking_reference' => $contract?->booking_reference ?? $contract?->agreement_number,
            'payer_name' => $contract?->signer_name,
            'balance' => $balance,
            'checkout' => $checkout,
            'presentation' => $presentation,
            'diet_breakdown' => $diet,
            'charge_now' => $chargeNow,
            'charge_label' => $chargeLabel,
            'charge_schedule_id' => $chargeScheduleId,
        ]);
    }

    public function payLink(
        Request $request,
        Event $event,
        int $schedule,
        ClientAccessService $access,
        GenerateInstallmentPaymentLinkAction $action,
    ): JsonResponse {
        $this->authorizeClientDetails($event);
        $this->assertClientCanMutate();

        abort_unless($access->userOwnsContractSchedule($request->user(), $event, $schedule), 403);

        $model = ContractPaymentSchedule::query()
            ->whereKey($schedule)
            ->whereHas('contract', fn ($q) => $q->where('event_id', $event->id))
            ->firstOrFail();

        $result = $action(new GenerateInstallmentPaymentLinkData(schedule: $model));

        return $this->success($result, 'Wygenerowano link płatności.');
    }
}
