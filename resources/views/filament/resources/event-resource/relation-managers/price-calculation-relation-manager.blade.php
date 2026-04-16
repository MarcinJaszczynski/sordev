<div class="fi-resource-relation-manager flex flex-col gap-y-6">
    <div class="mb-8">
        <h4 class="text-md mb-4 font-semibold">Szczegółowa kalkulacja kosztów</h4>
        <div class="mb-4 rounded border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">
            <b>Wyjaśnienie:</b> Koszt całkowity liczony jest dla sumy: <b>uczestnicy + gratis + obsługa + kierowcy</b>.<br>
            <b>Cena za osobę</b> to koszt całkowity podzielony przez liczbę uczestników (bez gratis, obsługi i kierowców).<br>
            <b>Wielkość grupy</b> oznacza ile osób przypada na jedną jednostkę ceny punktu programu.
        </div>

        <x-event-price-calculation
            :template="$template"
            :participant-count="$participantCount"
            :gratis-count="$gratisCount"
            :start-place-id="$startPlaceId"
            :calculated-total="$calculatedTotal"
        />
    </div>
</div>
