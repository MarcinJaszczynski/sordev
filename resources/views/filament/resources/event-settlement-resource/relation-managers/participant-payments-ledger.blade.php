<div class="fi-resource-relation-manager flex flex-col gap-y-6">
    @livewire('participant-payments-ledger', [
        'settlementId' => $this->getOwnerRecord()->getKey(),
        'eventId' => $this->getOwnerRecord()->event_id,
    ], key('participant-payments-ledger-'.$this->getOwnerRecord()->getKey()))
</div>
