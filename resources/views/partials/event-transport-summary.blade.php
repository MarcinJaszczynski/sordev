@props([
    'record' => null,
    'transportCost' => 0,
    'eventTransportKm' => null,
])

@if($record?->use_manual_transport_cost && ($transportCost ?? 0) > 0)
    <div class="rounded border border-sky-200 bg-sky-50 p-3 text-sm text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
        <b>Koszt transportu (ryczałt):</b>
        <b>{{ number_format((float) $transportCost, 2, ',', ' ') }} PLN</b>
        za całą imprezę — trafia do kalkulacji zamiast ceny autokaru.
        <span class="mt-1 block text-xs opacity-80">Podgląd na żywo; zapis przelicza imprezę.</span>
    </div>
@elseif($record?->bus && ($transportCost ?? 0) > 0)
    <div class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
        <b>Koszt transportu:</b> {{ $record->bus->name }}
        — {{ number_format((float) ($eventTransportKm ?? 0), 0, ',', ' ') }} km
        (1,1 × (transfer {{ number_format((float) ($record->transfer_km ?? 0), 0, ',', ' ') }} km
        + program {{ number_format((float) ($record->program_km ?? 0), 0, ',', ' ') }} km) + 50),
        kwota: <b>{{ number_format((float) $transportCost, 2, ',', ' ') }} PLN</b>
        <span class="mt-1 block text-xs opacity-80">Podgląd na żywo; zapis przelicza imprezę i plan rozliczenia.</span>
    </div>
@elseif($record?->use_manual_transport_cost)
    <div class="rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800 dark:border-yellow-700 dark:bg-yellow-950/40 dark:text-yellow-100">
        Włączono ręczny koszt transportu — uzupełnij kwotę ryczałtu.
    </div>
@elseif($record?->bus_id && ! $record?->bus)
    <div class="rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800 dark:border-yellow-700 dark:bg-yellow-950/40 dark:text-yellow-100">
        Wybrano autokar, ale brak danych autokaru — uzupełnij bus w imprezie.
    </div>
@elseif($record && ! $record->bus_id && ! $record->use_manual_transport_cost)
    <div class="rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800 dark:border-yellow-700 dark:bg-yellow-950/40 dark:text-yellow-100">
        Brak przypisanego autokaru — koszt nie jest liczony automatycznie. Możesz włączyć ręczny ryczałt.
    </div>
@endif
