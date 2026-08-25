{{-- Zbiorcza lista adresów i kontaktów operacyjnych --}}
@php
    $places = collect($pilotContactPlaces ?? [])->values();
@endphp

<div class="section">
    <div class="section-title">Adresy i kontakty</div>
    <div class="section-body">
        @if($places->isEmpty())
            <p class="muted">Brak uzupełnionych adresów / kontaktów w trasie, hotelach i programie.</p>
        @else
            <table class="rows">
                @foreach($places as $place)
                    <tr>
                        <td class="lbl">{{ $place['role'] }}</td>
                        <td class="val">
                            {{ $place['name'] }}
                            @if(! empty($place['address']))
                                <br><small>{{ $place['address'] }}</small>
                            @endif
                            @if(! empty($place['phone']) || ! empty($place['email']))
                                <br><small>
                                    @if(! empty($place['phone']))tel. {{ $place['phone'] }}@endif
                                    @if(! empty($place['phone']) && ! empty($place['email'])) · @endif
                                    @if(! empty($place['email'])){{ $place['email'] }}@endif
                                </small>
                            @endif
                            @if(! empty($place['note']))
                                <br><small>{{ $place['note'] }}</small>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
</div>
