@php
    $tabs = $this::operationsSubNavigationTabs($record->getKey());
@endphp

@include('filament.components.workflow-module-nav', [
    'tabs' => $tabs,
    'ariaLabel' => 'Nawigacja operacji imprezy',
])
