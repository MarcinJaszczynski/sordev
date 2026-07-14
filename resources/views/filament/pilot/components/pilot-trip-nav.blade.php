@php
    $tabs = \App\Support\PilotTripModuleNavigation::tabs($event, $activeTab ?? null);
@endphp

@include('filament.components.workflow-module-nav', ['tabs' => $tabs, 'ariaLabel' => 'Nawigacja wycieczki'])
