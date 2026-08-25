@props([
    'record' => null,
    'transportCost' => 0,
    'eventTransportKm' => null,
])

@if($record?->use_manual_transport_cost && ($transportCost ?? 0) > 0)
    <div class="transport-info-box">
        <span><b>Koszt transportu (ręczny):</b> za całą imprezę — trafia do kosztów zamiast ceny autokaru.</span>
        <span><b>{{ number_format((float) $transportCost, 2, ',', ' ') }} PLN</b></span>
    </div>
@elseif($record?->bus && ($transportCost ?? 0) > 0)
    <div class="transport-info-box">
        <span>
            <b>Koszt transportu:</b> {{ $record->bus->name }}
            — {{ number_format((float) ($eventTransportKm ?? 0), 0, ',', ' ') }} km
            (1,1 × (transfer {{ number_format((float) ($record->transfer_km ?? 0), 0, ',', ' ') }} km
            + program {{ number_format((float) ($record->program_km ?? 0), 0, ',', ' ') }} km) + 50)
        </span>
        <span><b>{{ number_format((float) $transportCost, 2, ',', ' ') }} PLN</b></span>
    </div>
@elseif($record?->use_manual_transport_cost)
    <div class="transport-info-box transport-info-box--warning">
        Włączono ręczny koszt transportu — uzupełnij kwotę.
    </div>
@elseif($record?->bus_id && ! $record?->bus)
    <div class="transport-info-box transport-info-box--warning">
        Wybrano autokar, ale brak danych autokaru — uzupełnij bus w imprezie.
    </div>
@elseif($record && ! $record->bus_id && ! $record->use_manual_transport_cost)
    <div class="transport-info-box transport-info-box--warning">
        Brak przypisanego autokaru — koszt nie jest liczony automatycznie. Możesz włączyć ręczną kwotę transportu.
    </div>
@endif
