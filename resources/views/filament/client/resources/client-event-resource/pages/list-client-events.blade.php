<x-filament-panels::page>
    <div class="mb-5">
        <p class="client-portal-kicker">Portal klienta</p>
        <p class="mt-1 text-sm text-slate-600">Wybierz wycieczkę, żeby zobaczyć program, umowę i płatności.</p>
    </div>

    @include('filament.client.components.trip-cards', ['trips' => $this->trips])
</x-filament-panels::page>
