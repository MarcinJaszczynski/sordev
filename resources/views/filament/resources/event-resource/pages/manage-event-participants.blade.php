<x-filament-panels::page>
    @include('filament.resources.event-resource.components.participants-sub-navigation', ['record' => $record])

    @php
        $participantsCount = \Illuminate\Support\Facades\Schema::hasTable('event_participants')
            ? (int) $record->participants()->count()
            : 0;
    @endphp

    @if ($participantsCount === 0)
        <div class="mb-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center dark:border-gray-600 dark:bg-gray-800/50">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Brak uczestników na liście</p>
            <p class="mt-1 text-sm text-gray-500">Dodaj pierwszego uczestnika w edytorze poniżej — lista imienna jest bazą dla umów, diet i pokoi.</p>
        </div>
    @endif

    @livewire('event-participant-list-editor', ['eventId' => $this->record->id], key('event-participants-'.$this->record->id))
</x-filament-panels::page>
