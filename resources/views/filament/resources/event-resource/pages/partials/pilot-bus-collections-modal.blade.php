@php
    /** @var \App\Models\Event $event */
@endphp

<div class="space-y-3">
    @livewire(\App\Livewire\EventBusCollections::class, [
        'event' => $event,
        'readOnly' => false,
    ], key('pilot-bus-collections-modal-'.$event->id))
</div>
