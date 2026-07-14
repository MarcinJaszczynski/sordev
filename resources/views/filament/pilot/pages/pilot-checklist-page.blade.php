<x-filament-panels::page>
    @if(filled($archiveMessage) && $readOnly)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $archiveMessage }}
        </div>
    @endif

    @livewire('pilot-event-checklist', ['eventId' => $event->id, 'forcePilotView' => true], key('pilot-checklist-'.$event->id))
</x-filament-panels::page>
