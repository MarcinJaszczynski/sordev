@php
    $tabs = \App\Support\FinanceModuleNavigation::tabs($activeTab ?? null);
    $ariaLabel = 'Nawigacja modułu finansowego';
@endphp

@include('filament.components.workflow-module-nav', ['tabs' => $tabs, 'ariaLabel' => $ariaLabel])
