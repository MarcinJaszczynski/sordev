<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\PilotAccessService;

class EventSettlementPolicy
{
    public function view(User $user, EventSettlement $settlement): bool
    {
        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        $event = $settlement->event;
        if (! $event) {
            return false;
        }

        if (app(PilotAccessService::class)->shouldUseOfficeEventAccess($user)) {
            return $user->can('update', $event);
        }

        if (! $user->hasRole('pilot')) {
            return $user->can('update', $event);
        }

        return (int) $event->assigned_to === (int) $user->id;
    }

    public function update(User $user, EventSettlement $settlement): bool
    {
        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        $event = $settlement->event;
        if ($event && $user->can('manageFinance', $event)) {
            return true;
        }

        return $this->view($user, $settlement) && $settlement->isEditableByPilot();
    }

    public function recordParticipantPayment(User $user, EventSettlement $settlement): bool
    {
        $event = $settlement->event;

        return $event !== null && $user->can('manageFinance', $event);
    }

    public function recordCostPayment(User $user, EventSettlement $settlement): bool
    {
        return $this->update($user, $settlement);
    }

    public function updateCostPlan(User $user, EventSettlement $settlement): bool
    {
        $event = $settlement->event;

        return $event !== null && $user->can('manageFinance', $event);
    }

    public function attachCostDocument(User $user, EventSettlement $settlement): bool
    {
        return $this->updateCostPlan($user, $settlement);
    }

    public function recordCostPaymentForCost(User $user, EventSettlementCost $cost): bool
    {
        $settlement = $cost->settlement;

        return $settlement !== null && $this->recordCostPayment($user, $settlement);
    }

    public function recordParticipantPaymentForPayment(User $user, EventSettlementParticipantPayment $payment): bool
    {
        $settlement = $payment->settlement;

        return $settlement !== null && $this->recordParticipantPayment($user, $settlement);
    }
}
