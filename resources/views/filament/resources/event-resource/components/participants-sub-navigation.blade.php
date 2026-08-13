@php
    $tabs = $this::participantsSubNavigationTabs($record->getKey());
@endphp

@if ($tabs !== [])
    @include('filament.components.workflow-module-nav', [
        'tabs' => $tabs,
        'ariaLabel' => 'Nawigacja uczestników imprezy',
    ])
@endif
