@php
    $summary = $report['summary'] ?? [];
    $rows = $report['rows'] ?? collect();
@endphp

<div class="space-y-4">
    <div class="grid gap-3 md:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">Zawarte umowy</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['total'] ?? 0 }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">Opłacone</div>
            <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ $summary['payment_progress_label'] ?? '0/0' }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">Nieopłacone</div>
            <div class="mt-1 text-2xl font-semibold text-amber-700">{{ $summary['unpaid'] ?? 0 }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">Wpłacono</div>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ number_format((float) ($summary['amount_paid'] ?? 0), 2, ',', ' ') }} PLN</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">Pozostało do zapłaty</div>
            <div class="mt-1 text-lg font-semibold text-rose-700">{{ number_format((float) ($summary['amount_remaining'] ?? 0), 2, ',', ' ') }} PLN</div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Nr umowy</th>
                    <th class="px-4 py-3">Uczestnik</th>
                    <th class="px-4 py-3">Płatnik</th>
                    <th class="px-4 py-3">Kontakt</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Płatność</th>
                    <th class="px-4 py-3 text-right">Należność</th>
                    <th class="px-4 py-3 text-right">Wpłata</th>
                    <th class="px-4 py-3 text-right">Do zapłaty</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white text-gray-700">
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $row['agreement_number'] }}</td>
                        <td class="px-4 py-3">{{ $row['participant_name'] }}</td>
                        <td class="px-4 py-3">{{ $row['payer_name'] }}</td>
                        <td class="px-4 py-3">
                            <div>{{ $row['payer_email'] }}</div>
                            <div class="text-xs text-gray-500">{{ $row['payer_phone'] }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $row['status_label'] }}</td>
                        <td class="px-4 py-3">{{ $row['payment_status_label'] }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $row['amount_due'], 2, ',', ' ') }} {{ $row['currency'] }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $row['amount_paid'], 2, ',', ' ') }} {{ $row['currency'] }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ ((float) $row['amount_remaining'] > 0) ? 'text-rose-700' : 'text-emerald-700' }}">
                            {{ number_format((float) $row['amount_remaining'], 2, ',', ' ') }} {{ $row['currency'] }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-gray-500">Brak zawartych umów indywidualnych dla tej imprezy.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
