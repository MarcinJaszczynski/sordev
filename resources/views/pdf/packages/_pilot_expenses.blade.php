{{-- Tabela wydatków pilota: komu + plan + pola ręczne (zapłacono / uwagi) --}}
@php
    $expenseRows = collect($pilotExpenseRows ?? [])->values();
    $blankRows = 3;
@endphp

<div class="section">
    <div class="section-title">Tabela wydatków</div>
    <div class="section-body">
        <p class="muted" style="margin:4px 0 6px;">
            Kolumny „Kwota zapłacona” i „Uwagi” uzupełnij ręcznie w terenie. Pokazane są tylko pozycje planowane dla pilota (komu płacisz).
        </p>
        <table class="program-table">
            <colgroup>
                <col style="width: 7%;">
                <col style="width: 22%;">
                <col style="width: 28%;">
                <col style="width: 14%;">
                <col style="width: 14%;">
                <col style="width: 15%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Dzień</th>
                    <th>Punkt / tytuł</th>
                    <th>Komu / adres</th>
                    <th>Kwota planowana</th>
                    <th>Kwota zapłacona</th>
                    <th>Uwagi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenseRows as $row)
                    <tr>
                        <td>{{ $row['day'] ?? '—' }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>
                            <strong>{{ $row['payee'] }}</strong>
                            @if(! empty($row['address']))
                                <br><small>{{ $row['address'] }}</small>
                            @endif
                        </td>
                        <td class="money-nowrap">{{ $row['planned_amount_label'] }}</td>
                        <td><span class="handwrite-tall"></span></td>
                        <td><span class="handwrite-tall"></span></td>
                    </tr>
                @endforeach
                @for($i = 0; $i < $blankRows; $i++)
                    <tr>
                        <td><span class="handwrite"></span></td>
                        <td><span class="handwrite"></span></td>
                        <td><span class="handwrite"></span></td>
                        <td><span class="handwrite"></span></td>
                        <td><span class="handwrite-tall"></span></td>
                        <td><span class="handwrite-tall"></span></td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
</div>
