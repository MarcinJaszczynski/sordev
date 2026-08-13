<x-filament-widgets::widget>
    <x-filament::section heading="Kontynuuj pracę" icon="heroicon-o-arrow-path">
        @php($events = $this->getRecentEvents())
        @if (empty($events))
            <p class="text-sm text-gray-500">Brak ostatnio edytowanych imprez w toku.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($events as $event)
                    <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <a href="{{ $event['url'] }}" class="font-semibold text-primary-600 hover:underline">{{ $event['name'] }}</a>
                            <div class="text-xs text-gray-500">{{ $event['updated'] }}</div>
                        </div>
                        <x-filament::button tag="a" href="{{ $event['url'] }}" size="sm" color="gray" title="Przejdź do karty imprezy">
                            Otwórz imprezę
                        </x-filament::button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
