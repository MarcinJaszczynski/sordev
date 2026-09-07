<x-filament-panels::page>
    @php
        use App\Support\MoneyFormatter;
    @endphp

    @include('filament.client.components.trip-hero', [
        'event' => $event,
        'kicker' => 'Wpłaty grupy',
    ])

    @if(filled($archiveMessage))
        <div class="portal-notice portal-notice--accent">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="space-y-4">
        <section class="client-portal-section">
            <h3 class="text-base font-semibold text-[#2C2C2A]">Podsumowanie grupy</h3>
            <p class="mt-1 text-sm text-[#5F5E5A]">
                Raport zbiorczy. Płatność online:
                <a href="{{ \App\Filament\Client\Pages\ClientPaymentsPage::urlFor($event) }}" class="font-medium text-[#0C447C] underline">
                    Płatności
                </a>
            </p>
            <dl class="mt-3 grid gap-3 text-sm md:grid-cols-4">
                <div>
                    <dt class="text-[#888780]">Uczestników</dt>
                    <dd class="font-medium">{{ $summary['count'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-[#888780]">Należne łącznie</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) ($summary['total_due'] ?? 0), 'PLN') }}</dd>
                </div>
                <div>
                    <dt class="text-[#888780]">Wpłacone łącznie</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) ($summary['total_paid'] ?? 0), 'PLN') }}</dd>
                </div>
                <div>
                    <dt class="text-[#888780]">Różnica</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) ($summary['total_remaining'] ?? 0), 'PLN') }}</dd>
                </div>
            </dl>
        </section>

        <section class="client-portal-section overflow-hidden !p-0">
            <header class="border-b border-[#E5E3DA] bg-[#F1EFE8] px-4 py-3">
                <h3 class="text-sm font-semibold text-[#2C2C2A]">Wpłaty uczestników</h3>
            </header>
            @if($rows->isEmpty())
                <p class="px-4 py-3 text-sm text-[#888780]">Brak wpłat w rozliczeniu tej imprezy.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-[#F1EFE8]">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-[#5F5E5A]">Uczestnik</th>
                                <th class="px-4 py-2 text-left font-medium text-[#5F5E5A]">Referencja</th>
                                <th class="px-4 py-2 text-right font-medium text-[#5F5E5A]">Należne</th>
                                <th class="px-4 py-2 text-right font-medium text-[#5F5E5A]">Wpłacone</th>
                                <th class="px-4 py-2 text-right font-medium text-[#5F5E5A]">Różnica</th>
                                <th class="px-4 py-2 text-left font-medium text-[#5F5E5A]">Semafor</th>
                                <th class="px-4 py-2 text-left font-medium text-[#5F5E5A]">Następna rata</th>
                                <th class="px-4 py-2 text-left font-medium text-[#5F5E5A]">Status</th>
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
