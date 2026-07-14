<x-filament-panels::page>
    @include('filament.resources.event-resource.components.participants-sub-navigation', ['record' => $record])

    @livewire('event-bank-payment-import-panel', ['eventId' => $record->id], key('bank-import-'.$record->id))

    @php
        $relationManagers = $this->getRelationManagers();
        $managerLivewireProperties = [
            'ownerRecord' => $settlement,
            'pageClass' => static::class,
        ];
    @endphp

    <div class="flex flex-col gap-y-8">
        @foreach ($relationManagers as $managerKey => $manager)
            @php
                $normalizedManagerClass = $manager instanceof \Filament\Resources\RelationManagers\RelationManagerConfiguration
                    ? $manager->relationManager
                    : $manager;
                $managerProperties = $manager instanceof \Filament\Resources\RelationManagers\RelationManagerConfiguration
                    ? [...$normalizedManagerClass::getDefaultProperties(), ...$manager->getProperties()]
                    : $normalizedManagerClass::getDefaultProperties();
            @endphp

            <div wire:key="event-participants-payments-{{ $managerKey }}" class="flex flex-col gap-y-4">
                @livewire(
                    $normalizedManagerClass,
                    [...$managerLivewireProperties, ...$managerProperties],
                    key($normalizedManagerClass.'-'.$managerKey.'-'.$settlement->getKey()),
                )
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
