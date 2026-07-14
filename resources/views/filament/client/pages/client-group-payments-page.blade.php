@php
    use App\Support\MoneyFormatter;
@endphp

<x-filament-panels::page>
    @if(filled($archiveMessage))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="space-y-4">
        <section class="sor-lw-card">
            <h3 class="sor-lw-title">Podsumowanie grupy</h3>
            <dl class="mt-3 grid gap-3 text-sm md:grid-cols-4">
                <div>
                    <dt class="text-gray-500">Uczestników</dt>
                    <dd class="font-medium">{{ $summary['count'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Należność łącznie</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) ($summary['total_due'] ?? 0), 'PLN') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Wpłacono łącznie</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) ($summary['total_paid'] ?? 0), 'PLN') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Saldo łącznie</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) ($summary['total_balance'] ?? 0), 'PLN') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Zapłaciło</dt>
                    <dd class="font-medium">{{ $summary['paid_count'] ?? 0 }}/{{ $summary['count'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Brakuje łącznie</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) ($summary['total_remaining'] ?? 0), 'PLN') }}</dd>
                </div>
            </dl>
        </section>

        <section class="sor-lw-card overflow-hidden !p-0">
            <header class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <h3 class="sor-lw-title">Wpłaty uczestników</h3>
            </header>
            @if($rows->isEmpty())
                <p class="px-4 py-3 text-sm text-gray-500">Brak wpłat w rozliczeniu tej imprezy.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Uczestnik</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Referencja</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Należność</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Wpłacono</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Brakuje</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Semafor</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Następna rata</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($rows as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row['participant_name'] ?: '—' }}</td>
                                    <td class="px-4 py-2">{{ $row['booking_reference'] ?: '—' }}</td>
                                    <td class="px-4 py-2 text-right">{{ MoneyFormatter::format($row['due_amount_pln'], 'PLN') }}</td>
                                    <td class="px-4 py-2 text-right">{{ MoneyFormatter::format($row['paid_amount_pln'], 'PLN') }}</td>
                                    <td class="px-4 py-2 text-right">{{ MoneyFormatter::format($row['remaining_pln'] ?? max(0, $row['due_amount_pln'] - $row['paid_amount_pln']), 'PLN') }}</td>
                                    <td class="px-4 py-2">{{ $row['coverage_label'] ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $row['installment_label'] ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $row['payment_status_label'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
