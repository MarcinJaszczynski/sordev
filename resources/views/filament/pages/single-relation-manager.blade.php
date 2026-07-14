<x-filament-panels::page>
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
