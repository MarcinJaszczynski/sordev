<x-filament-panels::page>
    <div class="mb-5">
        <p class="client-portal-kicker">Portal pilota</p>
        <p class="mt-1 text-sm text-slate-600">Wybierz wycieczkę, żeby zobaczyć program, checklistę i rozliczenie.</p>
    </div>

    @include('filament.pilot.components.trip-cards', ['trips' => $this->trips])
</x-filament-panels::page>
