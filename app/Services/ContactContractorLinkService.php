<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Contractor;

final class ContactContractorLinkService
{
    public function link(?int $contactId, ?int $contractorId): void
    {
        if (! $contactId || ! $contractorId || ! Contractor::hasContactPivotTable()) {
            return;
        }

        Contact::find($contactId)?->contractors()->syncWithoutDetaching([$contractorId]);
    }

    /**
     * @param  array<int, array{contact_id?: int|null, contractor_id?: int|null}>  $parties
     */
    public function linkParties(array $parties): void
    {
        foreach ($parties as $party) {
            $contactId = filled($party['contact_id'] ?? null) ? (int) $party['contact_id'] : null;
            $contractorId = filled($party['contractor_id'] ?? null) ? (int) $party['contractor_id'] : null;

            $this->link($contactId, $contractorId);
        }
    }
}
