@php
    /** @var \App\Models\Event $event */
@endphp

<div class="space-y-3">
    @livewire(\App\Livewire\PilotCashDesk::class, [
        'event' => $event,
        'context' => 'admin',
        'compact' => true,
        'showOfficePayoutBlock' => false,
        'focus' => 'exchange',
    ], key('pilot-exchange-modal-'.$event->id))
</div>
