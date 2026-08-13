<div class="section">
    <div class="section-title">Program imprezy</div>
    <div class="section-body">
        @forelse($programByDay as $day => $points)
            <div class="program-day">
                <div class="program-day-title">Dzień {{ $day }}</div>
                <table class="program-table">
                    <colgroup>
                        <col style="width: 11%;">
                        <col style="width: 18%;">
                        <col style="width: 32%;">
                        <col style="width: 20%;">
                        <col style="width: 19%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Godziny</th>
                            <th>Punkt</th>
                            <th>Opis</th>
                            <th>Informacje dla pilota</th>
                            <th>Kontakt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($points as $point)
                            <tr>
                                <td>
                                    @if($point->start_time || $point->end_time)
                                        {{ $point->start_time ? \Illuminate\Support\Str::of((string) $point->start_time)->substr(0, 5) : '—' }}
                                        –
                                        {{ $point->end_time ? \Illuminate\Support\Str::of((string) $point->end_time)->substr(0, 5) : '—' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $point->name ?: (optional($point->templatePoint)->name ?? '—') }}</td>
                                <td>{{ $point->description ? strip_tags($point->description) : '—' }}</td>
                                <td>{{ $point->pilot_notes ? strip_tags($point->pilot_notes) : '—' }}</td>
                                <td>
                                    @if($point->contractor)
                                        @php
                                            $meta = \App\Support\ContractorContactDetails::operationalMeta($point->contractor, $point->contractorLocation);
                                        @endphp
                                        {{ $point->contractor->name }}
                                        @if(! empty($meta['branch_name']))
                                            <br><small>{{ $meta['branch_name'] }}</small>
                                        @endif
                                        @if(! empty($meta['address']))
                                            <br><small>{{ $meta['address'] }}</small>
                                        @endif
                                        @if(! empty($meta['phone']))
                                            <br><small>{{ $meta['phone'] }}</small>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="muted">Brak punktów programu.</p>
        @endforelse
    </div>
</div>
