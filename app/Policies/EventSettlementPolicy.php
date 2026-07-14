<?php

namespace App\Policies;

use App\Models\EventSettlement;
use App\Models\User;

class EventSettlementPolicy
{
    public function view(User $user, EventSettlement $settlement): bool
    {
        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        if (! $user->hasRole('pilot')) {
            return false;
        }

        $event = $settlement->event;

        return $event && (int) $event->assigned_to === (int) $user->id;
    }

    public function update(User $user, EventSettlement $settlement): bool
    {
        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        return $this->view($user, $settlement) && $settlement->isEditableByPilot();
    }
}
