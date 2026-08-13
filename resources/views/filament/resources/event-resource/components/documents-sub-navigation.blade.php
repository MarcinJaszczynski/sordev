@php
    $tabs = $this::documentsSubNavigationTabs($record->getKey());
@endphp

@if ($tabs !== [])
    @include('filament.components.workflow-module-nav', [
        'tabs' => $tabs,
        'ariaLabel' => 'Nawigacja dokumentów imprezy',
    ])
@endif
