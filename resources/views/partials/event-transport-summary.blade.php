@props([
    'record' => null,
    'transportCost' => 0,
    'eventTransportKm' => null,
])

@if($record?->use_manual_transport_cost && ($transportCost ?? 0) > 0)
    <div class="mb-4 rounded border border-sky-200 bg-sky-50 p-3 text-sm text-sky-950">
        <b>Transport (ryczałt):</b>
        koszt ustalony ręcznie za całą imprezę:
        <b>{{ number_format((float) $transportCost, 2, ',', ' ') }} PLN</b>
        (w kalkulacji jako „Koszt transportu (ryczałt)”).
    </div>
@elseif($record?->bus && ($transportCost ?? 0) > 0)
    <div class="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
        <b>Transport (impreza):</b> {{ $record->bus->name }}
        — {{ number_format((float) ($eventTransportKm ?? 0), 0, ',', ' ') }} km
        (2× transfer {{ number_format((float) ($record->transfer_km ?? 0), 0, ',', ' ') }} km + program {{ number_format((float) ($record->program_km ?? 0), 0, ',', ' ') }} km),
        koszt autokaru: <b>{{ number_format((float) $transportCost, 2, ',', ' ') }} PLN</b>
        (w tabeli poniżej jako „Koszt transportu (autokar)”).
    </div>
@elseif($record?->use_manual_transport_cost)
    <div class="mb-4 rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
        Włączono ręczny koszt transportu — uzupełnij kwotę ryczałtu.
    </div>
@elseif($record?->bus_id && ! $record?->bus)
    <div class="mb-4 rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
        Wybrano autokar, ale brak danych autokaru — uzupełnij bus w imprezie.
    </div>
@elseif($record && ! $record->bus_id && ! $record->use_manual_transport_cost)
    <div class="mb-4 rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
        Brak przypisanego autokaru — koszt transportu nie jest liczony automatycznie. Możesz włączyć ręczny ryczałt.
    </div>
@endif
