<x-filament-panels::page>
    @php
        $relationManagers = $this->getRelationManagers();
        $managerLivewireProperties = [
            'ownerRecord' => $record,
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

            <div wire:key="settlement-costs-{{ $managerKey }}" class="flex flex-col gap-y-4">
                @livewire(
                    $normalizedManagerClass,
                    [...$managerLivewireProperties, ...$managerProperties],
                    key($normalizedManagerClass.'-'.$managerKey),
                )
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
