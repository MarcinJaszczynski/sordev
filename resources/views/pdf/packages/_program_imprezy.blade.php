<div class="section">
    <div class="section-title">Program imprezy</div>
    <div class="section-body">
        @php
            $programDayRoutes = $programDayRoutes ?? [];
            $isPilotPackage = ($audience ?? null) === 'pilot';
            $pilotDueByPointId = $pilotDueByPointId ?? [];
        @endphp
        @forelse($programByDay as $day => $points)
            @php
                $dayRoute = $programDayRoutes[(string) $day] ?? $programDayRoutes[$day] ?? null;
            @endphp
            <div class="program-day">
                <div class="program-day-title">
                    Dzień {{ $day }}
                    @if(filled($dayRoute))
                        <span style="font-weight:normal; color:#6b7280;"> — Trasa: {{ $dayRoute }}</span>
                    @endif
                </div>
                @if($isPilotPackage)
                    <table class="program-table">
                        <colgroup>
                            <col style="width: 10%;">
                            <col style="width: 48%;">
                            <col style="width: 22%;">
                            <col style="width: 20%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Godzina</th>
                                <th>Punkt / opis / adres / kontakt</th>
                                <th>Uwagi pilota</th>
                                <th>Do zapłaty<br><span style="font-weight:normal; text-transform:none;">(komu / kwota)</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($points as $point)
                                @php
                                    $title = $point->name ?: (optional($point->templatePoint)->name ?? '—');
                                    $desc = $point->description ? trim(strip_tags($point->description)) : '';
                                    $meta = $point->contractor
                                        ? \App\Support\ContractorContactDetails::operationalMeta($point->contractor, $point->contractorLocation)
                                        : null;
                                    $due = $pilotDueByPointId[(int) $point->id] ?? null;
                                @endphp
                                <tr>
                                    <td>
                                        @php
                                            $displayStart = $point->displayStartTime();
                                            $displayEnd = $point->displayEndTime();
                                        @endphp
                                        @if($displayStart || $displayEnd)
                                            {{ $displayStart ?: '—' }}@if($displayEnd) – {{ $displayEnd }}@endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <div class="pt-title">{{ $title }}</div>
                                        @if($desc !== '')
                                            <div class="pt-desc">{{ $desc }}</div>
                                        @endif
                                        @if($point->contractor)
                                            <div class="pt-contact">
                                                <strong>{{ $point->contractor->name }}</strong>
                                                @if(! empty($meta['branch_name']))
                                                    — {{ $meta['branch_name'] }}
                                                @endif
                                                @if(! empty($meta['address']))
                                                    <br>{{ $meta['address'] }}
                                                @endif
                                                @if(! empty($meta['phone']) || ! empty($meta['email']))
                                                    <br>
                                                    @if(! empty($meta['phone']))tel. {{ $meta['phone'] }}@endif
                                                    @if(! empty($meta['phone']) && ! empty($meta['email'])) · @endif
                                                    @if(! empty($meta['email'])){{ $meta['email'] }}@endif
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($point->pilot_notes)
                                            {{ strip_tags($point->pilot_notes) }}
                                        @endif
                                        <span class="handwrite-tall"></span>
                                    </td>
                                    <td>
                                        @if($due)
                                            @if(! empty($due['payee']))
                                                <div><strong>{{ $due['payee'] }}</strong></div>
                                            @endif
                                            <div class="money-nowrap">{{ $due['amount_label'] }}</div>
                                        @else
                                            —
                                        @endif
                                        <span class="handwrite"></span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
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
                                        @php
                                            $displayStart = $point->displayStartTime();
                                            $displayEnd = $point->displayEndTime();
                                        @endphp
                                        @if($displayStart || $displayEnd)
                                            {{ $displayStart ?: '—' }} – {{ $displayEnd ?: '—' }}
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
                @endif
            </div>
        @empty
            <p class="muted">Brak punktów programu.</p>
        @endforelse
    </div>
</div>
