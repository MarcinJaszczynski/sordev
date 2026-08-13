<x-filament-panels::page>
    @php
        use App\Support\MoneyFormatter;
    @endphp

    @include('filament.client.components.trip-hero', [
        'event' => $event,
        'kicker' => 'Wpłaty grupy',
    ])

    @if(filled($archiveMessage))
        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="space-y-4">
        <section class="client-portal-section">
            <h3 class="text-base font-semibold text-slate-900">Podsumowanie grupy</h3>
            <p class="mt-1 text-sm text-slate-600">
                Raport zbiorczy. Płatność online:
                <a href="{{ \App\Filament\Client\Pages\ClientPaymentsPage::urlFor($event) }}" class="font-medium text-[#0663fc] underline">
                    Płatności
                </a>
            </p>
            <dl class="mt-3 grid gap-3 text-sm md:grid-cols-4">
                <div>
                    <dt class="text-slate-500">Uczestników</dt>
                    <dd class="font-medium">{{ $summary['count'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Należne łącznie</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) ($summary['total_due'] ?? 0), 'PLN') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Wpłacone łącznie</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) ($summary['total_paid'] ?? 0), 'PLN') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Różnica</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) ($summary['total_remaining'] ?? 0), 'PLN') }}</dd>
                </div>
            </dl>
        </section>

        <section class="client-portal-section overflow-hidden !p-0">
            <header class="border-b border-slate-100 bg-slate-50 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-900">Wpłaty uczestników</h3>
            </header>
            @if($rows->isEmpty())
                <p class="px-4 py-3 text-sm text-slate-500">Brak wpłat w rozliczeniu tej imprezy.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-slate-600">Uczestnik</th>
                                <th class="px-4 py-2 text-left font-medium text-slate-600">Referencja</th>
                                <th class="px-4 py-2 text-right font-medium text-slate-600">Należne</th>
                                <th class="px-4 py-2 text-right font-medium text-slate-600">Wpłacone</th>
                                <th class="px-4 py-2 text-right font-medium text-slate-600">Różnica</th>
                                <th class="px-4 py-2 text-left font-medium text-slate-600">Semafor</th>
                                <th class="px-4 py-2 text-left font-medium text-slate-600">Następna rata</th>
                                <th class="px-4 py-2 text-left font-medium text-slate-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
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
