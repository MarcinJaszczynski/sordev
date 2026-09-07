<x-filament-panels::page>
    @include('filament.resources.event-resource.components.finance-sub-navigation', ['record' => $record])

    <div class="mb-4 rounded-xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
        <p class="font-medium text-gray-900 dark:text-gray-100">Migawki stanu imprezy</p>
        <p class="mt-1">
            Zapisują program i koszty na dany moment (np. przed zmianami od klienta).
            Możesz porównać migawkę z bieżącym stanem. Przywracanie stanu jest na razie wyłączone.
        </p>
    </div>

    @php
        $relationManagers = $this->getRelationManagers();
        $managerLivewireProperties = [
            'ownerRecord' => $record,
            'pageClass' => static::class,
        ];
    @endphp

    @if ($relationManagers === [])
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Tabela migawek jest niedostępna.
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

                <div wire:key="event-finance-snapshots-{{ $managerKey }}" class="flex flex-col gap-y-4">
                    @livewire(
                        $normalizedManagerClass,
                        [...$managerLivewireProperties, ...$managerProperties],
                        key($normalizedManagerClass.'-'.$managerKey.'-'.$record->getKey()),
                    )
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
