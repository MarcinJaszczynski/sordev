@php
    $tabs = $this::financeSubNavigationTabs($record->getKey());
@endphp

@include('filament.components.workflow-module-nav', [
    'tabs' => $tabs,
    'ariaLabel' => 'Nawigacja finansów imprezy',
])
