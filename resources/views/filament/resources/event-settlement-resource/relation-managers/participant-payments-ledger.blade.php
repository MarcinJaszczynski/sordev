<div class="fi-resource-relation-manager flex flex-col gap-y-6">
    @php
        $focusPaymentId = $this->focusPaymentId
            ?? (request()->integer('payment') ?: null);
        $focusPaymentId = $focusPaymentId && (int) $focusPaymentId > 0 ? (int) $focusPaymentId : null;
    @endphp
    @livewire('participant-payments-ledger', [
        'settlementId' => $this->getOwnerRecord()->getKey(),
        'eventId' => $this->getOwnerRecord()->event_id,
        'focusPaymentId' => $focusPaymentId,
    ], key('participant-payments-ledger-'.$this->getOwnerRecord()->getKey().'-'.($focusPaymentId ?: 'all')))
</div>
