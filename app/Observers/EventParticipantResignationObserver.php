<?php

namespace App\Observers;

use App\Models\EventParticipantResignation;
use App\Services\EventParticipantCountChangeService;

class EventParticipantResignationObserver
{
    /** @var list<string> */
    private const EFFECTIVE_STATUSES = ['confirmed', 'settled'];

    public function created(EventParticipantResignation $resignation): void
    {
        if ($this->isEffective($resignation->status)) {
            app(EventParticipantCountChangeService::class)->applyResignationDecrement($resignation);
        }
    }

    public function updated(EventParticipantResignation $resignation): void
    {
        $oldStatus = (string) $resignation->getOriginal('status');
        $newStatus = (string) $resignation->status;

        if ($oldStatus === $newStatus) {
            return;
        }

        $wasEffective = $this->isEffective($oldStatus);
        $isEffective = $this->isEffective($newStatus);

        if (! $wasEffective && $isEffective) {
            app(EventParticipantCountChangeService::class)->applyResignationDecrement($resignation);

            return;
        }

        if ($wasEffective && $newStatus === 'cancelled') {
            app(EventParticipantCountChangeService::class)->revertResignationIncrement($resignation);
        }
    }

    public function deleted(EventParticipantResignation $resignation): void
    {
        $status = (string) ($resignation->getOriginal('status') ?? $resignation->status);

        if ($this->isEffective($status)) {
            app(EventParticipantCountChangeService::class)->revertResignationIncrement($resignation);
        }
    }

    private function isEffective(?string $status): bool
    {
        return in_array($status, self::EFFECTIVE_STATUSES, true);
    }
}
