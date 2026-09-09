<x-filament-panels::page>
    @php
        /** @var array $status */
        /** @var array $usage */
        /** @var array $sourceStats */
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Status klucza i limitów</x-slot>
            <x-slot name="description">
                Klucz z panelu ma pierwszeństwo przed <code>OPENROUTESERVICE_API_KEY</code> w .env.
                Limity chronią przed wyczerpaniem darmowej puli ORS.
            </x-slot>

            <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Klucz API</dt>
                    <dd class="font-medium">
                        @if($status['has_api_key'])
                            <span class="text-success-600 dark:text-success-400">{{ $status['api_key_masked'] }}</span>
                        @else
                            <span class="text-danger-600 dark:text-danger-400">Brak</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Źródło klucza</dt>
                    <dd class="font-medium">
                        @switch($status['api_key_source'])
                            @case('panel')
                                Panel (zaszyfrowany w bazie)
                                @break
                            @case('env')
                                .env (fallback)
                                @break
                            @default
                                Brak
                        @endswitch
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Zużycie dziś</dt>
                    <dd class="font-medium">
                        {{ $usage['daily_used'] }} / {{ $usage['daily_limit'] }}
                        @if($usage['daily_remaining'] <= 0)
                            <span class="text-danger-600">— limit wyczerpany</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">W tej minucie</dt>
                    <dd class="font-medium">{{ $usage['minute_used'] }} / {{ $usage['requests_per_minute'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Odstęp min.</dt>
                    <dd class="font-medium">{{ $status['min_interval_ms'] }} ms</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Źródła odległości w bazie</x-slot>
            <x-slot name="description">
                Widać od razu, ile par jest z formuły (Haversine), a ile z prawdziwej trasy ORS.
            </x-slot>

            <div class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                @foreach($sourceStats as $row)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-gray-500 dark:text-gray-400">{{ $row['label'] }}</div>
                        <div class="text-lg font-semibold">{{ $row['count'] }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Ustawienia</x-slot>
            <form wire:submit="save" class="space-y-4">
                {{ $this->form }}

                <div class="flex flex-wrap gap-3">
                    <x-filament::button type="submit" icon="heroicon-o-check">
                        Zapisz
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Przeliczanie (kolejka)</x-slot>
            <x-slot name="description">
                Duże przeliczenia idą w tle i respektują limity. Nie odpalaj synchronicznie tysięcy par z UI.
            </x-slot>

            <div class="flex flex-wrap gap-3">
                <x-filament::button
                    color="warning"
                    icon="heroicon-o-map"
                    wire:click="dispatchRecalculateEstimates"
                    wire:confirm="Nadpisać wszystkie szacunki Haversine trasami drogowymi ORS? Job pójdzie w kolejkę."
                >
                    Kolejka: szacunki → ORS
                </x-filament::button>
                <x-filament::button
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    wire:click="dispatchRecalculateMissing"
                    wire:confirm="Uzupełnić brakujące odległości przez ORS?"
                >
                    Kolejka: tylko brakujące
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    color="gray"
                    href="{{ \App\Filament\Resources\PlaceDistanceResource::getUrl() }}"
                    icon="heroicon-o-list-bullet"
                >
                    Lista odległości
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Uwagi</x-slot>
            <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                <li>Kolumna <strong>Źródło</strong> na liście odległości: „Formuła (szacunek)” vs „OpenRouteService (trasa)” vs „Ręcznie”.</li>
                <li>Przy HTTP 429 job robi dłuższy backoff i wznawia — nie spamuj API.</li>
                <li>CLI: <code>php artisan places:recalculate-distances --estimates-only</code></li>
                @if($envFallback && $status['api_key_source'] === 'panel')
                    <li>W .env też jest klucz — panel ma pierwszeństwo.</li>
                @endif
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
