@php
    $tabs = \App\Support\ExecutiveModuleNavigation::tabs($activeTab ?? null);
    $ariaLabel = 'Nawigacja panelu zarządczego';
@endphp

@include('filament.components.workflow-module-nav', ['tabs' => $tabs, 'ariaLabel' => $ariaLabel])
