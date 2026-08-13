@php
    use App\Models\VendorInvoice;
    use App\Support\MoneyFormatter;

    /** @var VendorInvoice $invoice */
    $currency = $invoice->currency ?: 'PLN';
    $hints = is_array($invoice->matching_hints) ? $invoice->matching_hints : [];
@endphp

<div class="space-y-6 text-sm">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Numer / KSeF</div>
            <div class="font-semibold text-gray-950 dark:text-white">{{ $invoice->invoice_number ?: '—' }}</div>
            <div class="mt-0.5 break-all text-xs text-gray-600 dark:text-gray-300">{{ $invoice->ksef_number ?: 'brak numeru KSeF' }}</div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Wystawca</div>
            <div class="font-semibold text-gray-950 dark:text-white">{{ $invoice->seller_name ?: '—' }}</div>
            <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">
                NIP: {{ $invoice->seller_nip ?: '—' }}
                @if ($invoice->seller_city)
                    · {{ $invoice->seller_city }}
                @endif
            </div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Nabywca</div>
            <div class="font-semibold text-gray-950 dark:text-white">{{ $invoice->buyer_name ?: '—' }}</div>
            <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">NIP: {{ $invoice->buyer_nip ?: '—' }}</div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Kwoty</div>
            <div class="font-semibold text-gray-950 dark:text-white">
                {{ MoneyFormatter::format($invoice->gross_amount, $currency) }} brutto
            </div>
            <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">
                netto {{ MoneyFormatter::format($invoice->net_amount, $currency) }}
                · VAT {{ MoneyFormatter::format($invoice->vat_amount, $currency) }}
                · opłacono {{ MoneyFormatter::format($invoice->paid_amount, $currency) }}
            </div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Daty</div>
            <div class="text-gray-950 dark:text-white">
                wystawienie: {{ optional($invoice->issue_date)->format('d.m.Y') ?: '—' }}
            </div>
            <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">
                sprzedaż: {{ optional($invoice->sale_date)->format('d.m.Y') ?: '—' }}
                · termin: {{ optional($invoice->due_date)->format('d.m.Y') ?: '—' }}
            </div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Statusy</div>
            <div class="text-gray-950 dark:text-white">
                {{ VendorInvoice::$paymentStatuses[$invoice->payment_status] ?? $invoice->payment_status }}
                @if ($invoice->payment_method)
                    · {{ $invoice->payment_method }}
                @endif
            </div>
            <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">
                dopasowanie: {{ VendorInvoice::$matchingStatuses[$invoice->matching_status] ?? $invoice->matching_status }}
                · akceptacja: {{ VendorInvoice::$approvalStatuses[$invoice->approval_status] ?? $invoice->approval_status }}
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Przypisanie</div>
            <div class="mt-1 text-gray-950 dark:text-white">
                @if ($invoice->event)
                    {{ $invoice->event->code }} — {{ $invoice->event->name }}
                @else
                    Brak imprezy
                @endif
            </div>
            <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                kontrahent: {{ $invoice->contractor?->name ?? '—' }}
            </div>
            <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                punkty:
                @php
                    $linkedPoints = $invoice->relationLoaded('programPoints') && $invoice->programPoints->isNotEmpty()
                        ? $invoice->programPoints
                        : collect($invoice->programPoint ? [$invoice->programPoint] : []);
                @endphp
                @if ($linkedPoints->isNotEmpty())
                    {{ $linkedPoints->map(fn ($p) => ($p->contractor?->name ?? $p->name ?? 'Punkt').' (dzień '.$p->day.')')->implode(', ') }}
                @else
                    —
                @endif
            </div>
        </div>
        <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Źródło / PDF</div>
            <div class="mt-1 text-gray-950 dark:text-white">
                {{ $invoice->source ?: '—' }}
                @if ($invoice->importBatch?->source_filename)
                    · {{ $invoice->importBatch->source_filename }}
                @endif
            </div>
            <div class="mt-2">
                @if ($invoice->pdf_url)
                    <a href="{{ $invoice->pdf_url }}" target="_blank" class="text-primary-600 underline dark:text-primary-400">
                        Otwórz skan PDF
                    </a>
                @else
                    <span class="text-xs text-gray-500">Brak dopiętego PDF</span>
                @endif
            </div>
        </div>
    </div>

    @if (filled($invoice->notes))
        <div>
            <div class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Notatki z importu</div>
            <div class="whitespace-pre-wrap rounded-xl bg-amber-50 p-3 text-gray-900 dark:bg-amber-500/10 dark:text-gray-100">{{ $invoice->notes }}</div>
        </div>
    @endif

    @if ($hints !== [])
        <div>
            <div class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Hinty dopasowania</div>
            <ul class="space-y-1 rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                @foreach ($hints as $hint)
                    <li class="text-xs text-gray-700 dark:text-gray-200">
                        <span class="font-medium">{{ $hint['rule'] ?? 'rule' }}</span>
                        @if (isset($hint['confidence']))
                            ({{ number_format((float) $hint['confidence'] * 100, 0) }}%)
                        @endif
                        @if (! empty($hint['code']))
                            · kod {{ $hint['code'] }}
                        @endif
                        @if (! empty($hint['contractor_id']))
                            · contractor #{{ $hint['contractor_id'] }}
                        @endif
                        @if (! empty($hint['program_point_id']))
                            · punkt #{{ $hint['program_point_id'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <div class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
            Pozycje ({{ $invoice->lines->count() }})
        </div>
        @if ($invoice->lines->isEmpty())
            <p class="text-xs text-gray-500">Brak pozycji w imporcie.</p>
        @else
            <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <table class="w-full min-w-[640px] divide-y divide-gray-200 text-left dark:divide-white/10">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">#</th>
                            <th class="px-3 py-2 font-medium">Nazwa</th>
                            <th class="px-3 py-2 font-medium text-right">Ilość</th>
                            <th class="px-3 py-2 font-medium">Jdn</th>
                            <th class="px-3 py-2 font-medium text-right">VAT</th>
                            <th class="px-3 py-2 font-medium text-right">Netto</th>
                            <th class="px-3 py-2 font-medium text-right">Brutto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($invoice->lines as $line)
                            <tr class="align-top">
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $line->line_order + 1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $line->name }}</div>
                                    @if (filled($line->description))
                                        <div class="mt-0.5 text-xs text-gray-500">{{ $line->description }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', ' '), '0'), ',') }}</td>
                                <td class="px-3 py-2">{{ $line->unit ?: '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $line->vat_rate !== null && $line->vat_rate !== '' ? $line->vat_rate.'%' : '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ MoneyFormatter::format($line->net_amount, $currency) }}</td>
                                <td class="px-3 py-2 text-right font-medium tabular-nums">{{ MoneyFormatter::format($line->gross_amount, $currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
