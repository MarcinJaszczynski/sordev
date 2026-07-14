@php
    $record = $getRecord();
@endphp

@if ($record)
    @include('filament.components.sticky-notes-stack', [
        'notableType' => \App\Models\Contractor::class,
        'notableId' => $record->getKey(),
        'title' => 'Karteczki kontrahenta',
        'compact' => true,
    ])
@endif
