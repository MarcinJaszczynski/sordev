<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Dokumenty i korespondencja z obiektem</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Faktury hotelu przypisane do imprezy oraz dokumenty w teczce imprezy.
            </p>
        </div>
        <a
            href="{{ $documentsUrl }}"
            class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
        >
            Pełna zakładka Dokumenty →
        </a>
    </div>

    @if ($contractors->isEmpty())
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Przypisz hotel w planie noclegów, aby zobaczyć powiązane dokumenty kontrahenta.
        </p>
    @else
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
            Hotele w planie:
            {{ $contractors->pluck('name')->filter()->join(', ') ?: '—' }}
        </p>

        @if ($invoices->isNotEmpty())
            <div class="mb-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Faktury obiektu (impreza)</p>
                <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100 dark:divide-gray-800 dark:border-gray-800">
                    @foreach ($invoices as $invoice)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                            <span>{{ $invoice->invoice_number ?: $invoice->ksef_number ?: 'Faktura #'.$invoice->id }}</span>
                            <span class="text-xs text-gray-500">
                                {{ $invoice->contractor?->name }}
                                @if ($invoice->due_date)
                                    · termin {{ $invoice->due_date->format('d.m.Y') }}
                                @endif
                            </span>
                            <a
                                href="{{ \App\Filament\Resources\VendorInvoiceResource::getUrl('edit', ['record' => $invoice->id]) }}"
                                class="text-xs text-primary-600 hover:underline"
                            >
                                Otwórz
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($eventDocuments->isNotEmpty())
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Dokumenty imprezy</p>
                <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100 dark:divide-gray-800 dark:border-gray-800">
                    @foreach ($eventDocuments as $document)
                        <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                            <span>{{ $document->name }}</span>
                            <a href="{{ $documentsUrl }}" class="text-xs text-primary-600 hover:underline">Zobacz</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($invoices->isEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-300">Brak faktur i dokumentów powiązanych z hotelem tej imprezy.</p>
        @endif
    @endif
</div>
