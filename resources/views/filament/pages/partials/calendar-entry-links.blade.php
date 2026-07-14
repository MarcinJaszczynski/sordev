<div class="space-y-3">

    <p class="text-sm text-gray-600 dark:text-gray-400">

        {{ ($openInNewTab ?? false) ? 'Otwórz powiązany kontekst w nowej karcie:' : 'Wybierz, gdzie chcesz przejść:' }}

    </p>



    <div class="flex flex-col gap-2">

        @forelse ($links as $link)

            @if($openInNewTab ?? false)

                <x-filament::button

                    tag="a"

                    href="{{ $link['url'] }}"

                    target="_blank"

                    rel="noopener noreferrer"

                    icon="{{ $link['icon'] ?? 'heroicon-o-arrow-top-right-on-square' }}"

                    color="gray"

                    class="justify-start"

                >

                    {{ $link['label'] }}

                </x-filament::button>

            @else

                <x-filament::button

                    tag="a"

                    href="{{ $link['url'] }}"

                    icon="{{ $link['icon'] ?? 'heroicon-o-arrow-top-right-on-square' }}"

                    color="gray"

                    class="justify-start"

                >

                    {{ $link['label'] }}

                </x-filament::button>

            @endif

        @empty

            <p class="text-sm text-gray-500">Brak powiązanych miejsc docelowych.</p>

        @endforelse

    </div>

</div>

