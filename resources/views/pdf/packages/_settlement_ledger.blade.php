{{-- Kompletne rozliczenie dokumentów (teczka imprezy) --}}
@php
    $ledger = collect($settlementLedger ?? []);
    $totals = collect($settlementTotalsByCurrency ?? []);
@endphp
<div class="section">
    <div class="section-title">Rozliczenie — dokumenty i faktury</div>
    <div class="section-body">
        <table class="program-table">
            <colgroup>
                <col style="width: 16%;">
                <col style="width: 14%;">
                <col style="width: 28%;">
                <col style="width: 14%;">
                <col style="width: 28%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Nr</th>
                    <th>Typ</th>
                    <th>Kontrahent</th>
                    <th>Data</th>
                    <th>Kwota</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ledger as $row)
                    <tr>
                        <td>{{ $row['number'] }}</td>
                        <td>{{ $row['type'] }}</td>
                        <td>{{ $row['vendor'] }}</td>
                        <td>{{ $row['issue_date'] }}</td>
                        <td class="money-nowrap">{{ $row['amount_label'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">Brak dokumentów rozliczeniowych.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($totals->isNotEmpty())
            <p style="margin:8px 0 0; font-size:11px;">
                <strong>Sumy wg walut:</strong>
                {{ $totals->pluck('total_label')->implode(' · ') }}
            </p>
        @endif
    </div>
</div>
