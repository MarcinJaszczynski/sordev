<x-filament-panels::page>
    @include('filament.resources.event-resource.components.finance-sub-navigation', ['record' => $record])

    @php
        $relationManagers = $this->getRelationManagers();
    @endphp

    @if (count($relationManagers))
        <x-filament-panels::resources.relation-managers
            :active-manager="array_key_first($relationManagers)"
            :managers="$relationManagers"
            :owner-record="$record"
            :page-class="static::class"
        />
    @endif
</x-filament-panels::page>
