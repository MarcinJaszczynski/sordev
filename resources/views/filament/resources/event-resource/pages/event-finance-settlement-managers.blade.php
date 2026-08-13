<x-filament-panels::page>
    @include('filament.resources.event-resource.components.finance-sub-navigation', ['record' => $record])

    @php
        $relationManagers = $this->getRelationManagers();
        $managerLivewireProperties = [
            'ownerRecord' => $settlement,
            'pageClass' => static::class,
        ];
    @endphp

    @if ($relationManagers === [])
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Brak danych do wyświetlenia w tej sekcji (tabela nie istnieje lub jest pusta).
        </div>
    @else
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

                <div wire:key="event-finance-settlement-{{ $managerKey }}" class="flex flex-col gap-y-4">
                    @livewire(
                        $normalizedManagerClass,
                        [...$managerLivewireProperties, ...$managerProperties],
                        key($normalizedManagerClass.'-'.$managerKey.'-'.$settlement->getKey()),
                    )
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
