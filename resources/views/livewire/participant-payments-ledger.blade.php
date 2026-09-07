<div class="fi-resource-relation-manager flex flex-col gap-y-4">
    @php
        $statusColors = [
            'ok' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
            'due' => 'bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200',
            'overdue' => 'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-200',
            'shortfall' => 'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100',
            'n/a' => 'bg-slate-50 text-slate-600 dark:bg-slate-900 dark:text-slate-300',
        ];
    @endphp
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 space-y-1">
            <h4 class="text-md font-semibold text-gray-950 dark:text-white">Wpłaty uczestników</h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Jedna osoba = jeden wiersz. Wpłaty w wielu transzach (osobne daty i kwoty).
                Osoby z listy uczestników pojawiają się tu automatycznie.
            </p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                @if (filled($priceContext['event_code'] ?? null))
                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ $priceContext['event_code'] }}
                    </span>
                @endif
                <span>
                    Cena / os.:
                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $priceContext['price_label'] ?? '—' }}</span>
                </span>
                @if (filled($priceContext['foreign_hint'] ?? null))
                    <span class="text-amber-800 dark:text-amber-200">{{ $priceContext['foreign_hint'] }}</span>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{ $this->editScheduleTemplateAction }}
            {{ $this->applyScheduleTemplateAction }}
            {{ $this->applyLibraryScheduleTemplateAction }}
            {{ $this->addParticipantAction }}
        </div>
    </div>

    @if (($schedulePreview['count'] ?? 0) > 0)
        <div class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Harmonogram (podgląd / os.)</div>
                <div class="text-[11px] text-gray-500">
                    baza {!! \App\Support\MoneyFormatter::html($schedulePreview['unit_price'] ?? 0) !!}
                </div>
            </div>
            <ul class="mt-1.5 space-y-1">
                @foreach ($schedulePreview['rows'] as $tplRow)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-700 dark:text-gray-200">
                        <span class="font-medium">{{ $tplRow['label'] ?: 'Transza' }}</span>
                        <span class="tabular-nums">
                            @if (($tplRow['amount_foreign'] ?? 0) > 0)
                                {{ number_format((float) $tplRow['amount_foreign'], 2, ',', ' ') }}
                                {{ $tplRow['currency_code'] ?? '' }}
                                <span class="text-amber-700">({{ $tplRow['paid_by'] === 'pilot' ? 'pilot' : 'biuro' }})</span>
                            @else
                                {!! \App\Support\MoneyFormatter::html($tplRow['amount']) !!}
                            @endif
                            @if (! empty($tplRow['due_date']))
                                <span class="text-gray-400">· {{ $tplRow['due_date'] }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($gapAnalysis && (((int) ($gapAnalysis['expected_count'] ?? 0) > 0) || ((int) ($gapAnalysis['count'] ?? 0) > 0)))
        @php
            $expectedN = (int) ($gapAnalysis['expected_count'] ?? 0);
            $paidFull = (int) ($gapAnalysis['paid_full_count'] ?? $gapAnalysis['paid_count'] ?? 0);
            $registeredPaying = (int) ($gapAnalysis['registered_paying_count'] ?? $gapAnalysis['count'] ?? 0);
            $paidAny = (int) ($gapAnalysis['paid_any_count'] ?? 0);
            $unregistered = (int) ($gapAnalysis['unregistered_count'] ?? 0);
            $hasForeign = ! empty($gapAnalysis['expected_foreign']);
            $plnGaps = $gapAnalysis['installment_gaps_pln'] ?? array_values(array_filter($gapAnalysis['installment_gaps'] ?? [], fn ($g) => empty($g['is_foreign'])));
            $fxGaps = $gapAnalysis['installment_gaps_foreign'] ?? array_values(array_filter($gapAnalysis['installment_gaps'] ?? [], fn ($g) => ! empty($g['is_foreign'])));
        @endphp

        {{-- PLN — osobna ścieżka, bez walut --}}
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200">Rozliczenie PLN</div>
                <div class="text-[11px] text-gray-500">
                    {{ $expectedN }} × {!! \App\Support\MoneyFormatter::html($gapAnalysis['price_per_person'] ?? 0) !!}
                    @if (($gapAnalysis['gratis_count'] ?? 0) > 0)
                        <span class="text-gray-400">(+{{ (int) $gapAnalysis['gratis_count'] }} gratis poza należnością)</span>
                    @endif
                </div>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-800 dark:bg-gray-950">
                    <div class="text-[10px] font-medium uppercase tracking-wide text-gray-500">Należne PLN</div>
                    <div class="mt-0.5 text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100">
                        {!! \App\Support\MoneyFormatter::html($gapAnalysis['expected_due_pln'] ?? $gapAnalysis['total_due']) !!}
                    </div>
                </div>
                <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 px-3 py-2 dark:border-emerald-900/40 dark:bg-emerald-950/30">
                    <div class="text-[10px] font-medium uppercase tracking-wide text-emerald-700">Wpłacone PLN</div>
                    <div class="mt-0.5 text-lg font-bold tabular-nums text-emerald-800 dark:text-emerald-200">
                        {!! \App\Support\MoneyFormatter::html($gapAnalysis['total_paid']) !!}
                    </div>
                    <div class="text-[11px] text-emerald-800/80">pełne {{ $paidFull }} / {{ $expectedN }} · z wpłatą {{ $paidAny }} / {{ $expectedN }}</div>
                </div>
                <div class="rounded-lg border border-rose-100 bg-rose-50/60 px-3 py-2 dark:border-rose-900/40 dark:bg-rose-950/30">
                    <div class="text-[10px] font-medium uppercase tracking-wide text-rose-700">Różnica PLN</div>
                    <div class="mt-0.5 text-lg font-bold tabular-nums text-rose-800 dark:text-rose-200">
                        {!! \App\Support\MoneyFormatter::html($gapAnalysis['capacity_remaining_pln'] ?? $gapAnalysis['total_remaining']) !!}
                    </div>
                    <div class="text-[11px] text-rose-700/80">
                        @if ($unregistered > 0)
                            {{ $unregistered }} osób poza listą
                        @else
                            lista kompletna względem N
                        @endif
                    </div>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-800 dark:bg-gray-950">
                    <div class="text-[10px] font-medium uppercase tracking-wide text-gray-500">Status</div>
                    <div class="mt-1">
                        <span @class(['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', $statusColors[$gapAnalysis['coverage_status'] ?? ''] ?? 'bg-gray-100 text-gray-700'])>
                            {{ $gapAnalysis['coverage_label'] ?? '—' }}
                        </span>
                    </div>
                    <div class="mt-1 text-[11px] text-gray-500">
                        zarejestrowane {{ $registeredPaying }} / {{ $expectedN }}
                        @if (($gapAnalysis['waive_count'] ?? 0) > 0)
                            · {{ (int) $gapAnalysis['waive_count'] }} bez opłaty
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Waluty — osobny tor, NIGDY nie wchodzi do sum PLN powyżej --}}
        @if ($hasForeign)
            <div class="rounded-xl border-2 border-amber-300 bg-amber-50/70 p-3 dark:border-amber-700 dark:bg-amber-950/30">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-100">
                    Waluty (osobno — nie wliczone w PLN powyżej)
                </div>
                <p class="mb-2 text-[11px] text-amber-800/90 dark:text-amber-200/80">
                    Typowo gotówka u pilota w dniu startu. To nie jest przeliczenie należności PLN.
                </p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-[10px] uppercase tracking-wide text-amber-900/70 dark:text-amber-200/70">
                                <th class="pb-1 pr-3 font-medium">Waluta</th>
                                <th class="pb-1 pr-3 font-medium text-right">Należne</th>
                                <th class="pb-1 pr-3 font-medium text-right">Wpłacone</th>
                                <th class="pb-1 font-medium text-right">Różnica</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-200/60 dark:divide-amber-800/50">
                            @foreach ($gapAnalysis['expected_foreign'] as $fx)
                                <tr>
                                    <td class="py-1.5 pr-3 font-semibold text-amber-950 dark:text-amber-50">
                                        {{ $fx['currency'] }}
                                        @if (($fx['price_per_person'] ?? 0) > 0)
                                            <span class="ml-1 text-[11px] font-normal text-amber-800/80">
                                                ({{ $expectedN }} × {{ number_format((float) $fx['price_per_person'], 2, ',', ' ') }})
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-1.5 pr-3 text-right tabular-nums font-medium">
                                        {{ number_format((float) $fx['amount'], 2, ',', ' ') }} {{ $fx['currency'] }}
                                    </td>
                                    <td class="py-1.5 pr-3 text-right tabular-nums text-emerald-800 dark:text-emerald-200">
                                        {{ number_format((float) ($fx['paid'] ?? 0), 2, ',', ' ') }} {{ $fx['currency'] }}
                                    </td>
                                    <td class="py-1.5 text-right tabular-nums font-semibold text-rose-800 dark:text-rose-200">
                                        {{ number_format((float) ($fx['remaining'] ?? $fx['amount']), 2, ',', ' ') }} {{ $fx['currency'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($plnGaps !== [])
            <div class="rounded-xl border border-indigo-200/80 bg-indigo-50/40 px-3 py-2.5 dark:border-indigo-900/40 dark:bg-indigo-950/20">
                <div class="text-xs font-semibold uppercase tracking-wide text-indigo-900 dark:text-indigo-100">Transze PLN</div>
                <ul class="mt-1.5 space-y-1.5">
                    @foreach ($plnGaps as $gap)
                        <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="min-w-0">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $gap['label'] }}</span>
                                @if (! empty($gap['due_date']))
                                    <span class="ml-1 text-xs text-gray-500">termin {{ $gap['due_date'] }}</span>
                                @endif
                            </span>
                            <span class="flex flex-wrap items-center gap-2 text-xs">
                                <span @class(['inline-flex rounded-full px-2 py-0.5 font-semibold', $statusColors[$gap['status'] ?? ''] ?? 'bg-gray-100 text-gray-700'])>
                                    {{ $gap['status_label'] }}
                                </span>
                                <span class="tabular-nums text-rose-700">
                                    brakuje {!! \App\Support\MoneyFormatter::html($gap['remaining']) !!}
                                </span>
                                <span class="text-gray-500">
                                    / {{ number_format((float) $gap['expected'], 2, ',', ' ') }} PLN
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($fxGaps !== [])
            <div class="rounded-xl border border-amber-300/80 bg-amber-50/50 px-3 py-2.5 dark:border-amber-800/50 dark:bg-amber-950/25">
                <div class="text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-100">Transze walutowe (poza PLN)</div>
                <ul class="mt-1.5 space-y-1.5">
                    @foreach ($fxGaps as $gap)
                        <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="min-w-0">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $gap['label'] }}</span>
                                @if (! empty($gap['due_date']))
                                    <span class="ml-1 text-xs text-gray-500">termin {{ $gap['due_date'] }}</span>
                                @endif
                            </span>
                            <span class="tabular-nums font-semibold text-amber-900 dark:text-amber-100">
                                brakuje {{ number_format((float) $gap['remaining'], 2, ',', ' ') }} {{ $gap['currency'] ?? '' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($gapAnalysis['attention']))
            <div class="rounded-xl border border-amber-200/80 bg-amber-50/40 px-3 py-2.5 dark:border-amber-900/40 dark:bg-amber-950/20">
                <div class="text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-100">Wymaga uwagi (zarejestrowani, saldo PLN)</div>
                <ul class="mt-1.5 space-y-1">
                    @foreach (array_slice($gapAnalysis['attention'], 0, 8) as $item)
                        <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $item['name'] }}</span>
                            <span class="flex flex-wrap items-center gap-2 text-xs">
                                <span @class(['inline-flex rounded-full px-2 py-0.5 font-semibold', $statusColors[$item['status'] ?? ''] ?? 'bg-gray-100 text-gray-700'])>
                                    {{ $item['status_label'] }}
                                </span>
                                <span class="tabular-nums text-rose-700">brakuje {!! \App\Support\MoneyFormatter::html($item['remaining_pln']) !!}</span>
                                @if (! empty($item['next_due_date']))
                                    <span class="text-gray-500">nast. {{ $item['next_due_date'] }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if (count($gapAnalysis['attention']) > 8)
                    <p class="mt-1 text-[11px] text-gray-500">+ {{ count($gapAnalysis['attention']) - 8 }} kolejnych</p>
                @endif
            </div>
        @endif
    @endif

    @if ($rows->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            Brak uczestników w rozliczeniu. Dodaj pierwszego uczestnika, aby śledzić wpłaty.
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Uczestnik</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Wpłaty</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Saldo</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Akcje</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-950">
                    @foreach ($rows as $row)
                        @php
                            /** @var \App\Models\EventSettlementParticipantPayment $payment */
                            $payment = $row['payment'];
                            $remaining = (float) $row['remaining_pln'];
                        @endphp
                        <tr
                            id="participant-payment-focus-{{ $payment->id }}"
                            wire:key="participant-payment-{{ $payment->id }}"
                            @class([
                                'bg-white dark:bg-gray-950',
                                'bg-primary-50/80 ring-2 ring-inset ring-primary-400 dark:bg-primary-950/30 dark:ring-primary-500' => (int) ($this->focusPaymentId ?? 0) === (int) $payment->id,
                            ])
                            @if ((int) ($this->focusPaymentId ?? 0) === (int) $payment->id)
                                x-data
                                x-init="$nextTick(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }) })"
                            @endif
                        >
                            <td class="align-top px-4 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $row['participant_name'] ?: '—' }}
                                </div>
                                @if (filled($row['booking_reference']))
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Rezerwacja: {{ $row['booking_reference'] }}
                                    </div>
                                @endif
                                @if (filled($row['phone']) || filled($row['email']))
                                    <div class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        @if (filled($row['phone']))
                                            <div>{{ $row['phone'] }}</div>
                                        @endif
                                        @if (filled($row['email']))
                                            <div>{{ $row['email'] }}</div>
                                        @endif
                                    </div>
                                @endif
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Należne: {!! \App\Support\MoneyFormatter::html($row['due_pln']) !!}
                                    @if ((float) $row['due_pln'] <= 0.009)
                                        <span class="ml-1 inline-flex rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">bez opłaty</span>
                                    @endif
                                </div>
                                @if (! empty($row['has_invoice_file']))
                                    <div class="mt-2">
                                        @if (! empty($row['invoice_url']))
                                            <a
                                                href="{{ $row['invoice_url'] }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-800"
                                            >
                                                <x-heroicon-o-document-text class="h-3.5 w-3.5" />
                                                Faktura wgrana
                                            </a>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-800">
                                                <x-heroicon-o-document-text class="h-3.5 w-3.5" />
                                                Faktura wgrana
                                                @if (! empty($row['document_number']))
                                                    <span class="font-normal opacity-80">· {{ $row['document_number'] }}</span>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="align-top px-4 py-4">
                                @php
                                    $rowStatus = (string) ($row['coverage_status'] ?? 'shortfall');
                                    $rowStatusColor = $statusColors[$rowStatus] ?? 'bg-gray-100 text-gray-700';
                                @endphp
                                <span @class(['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', $rowStatusColor])>
                                    {{ $row['coverage_label'] ?: '—' }}
                                </span>
                                @if (! empty($row['next_due_date']))
                                    <div class="mt-1 text-[11px] text-gray-500">
                                        Nast. {{ $row['next_due_date'] }}
                                        @if ($row['next_due_amount'] !== null)
                                            · {!! \App\Support\MoneyFormatter::html($row['next_due_amount']) !!}
                                        @endif
                                    </div>
                                @elseif (! empty($row['installment_label']))
                                    <div class="mt-1 text-[11px] text-gray-500">{{ $row['installment_label'] }}</div>
                                @endif
                            </td>
                            <td class="align-top px-4 py-4">
                                <div class="flex items-start gap-2">
                                    <div class="min-w-0 flex-1 space-y-2">
                                        @forelse ($row['entries'] as $entry)
                                            <div
                                                wire:key="payment-entry-{{ $entry->id }}"
                                                class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 bg-gray-50 px-2 py-1.5 dark:border-gray-800 dark:bg-gray-900"
                                            >
                                                <div class="min-w-0 text-sm text-gray-800 dark:text-gray-200">
                                                    <span class="font-medium">
                                                        {{ $entry->paid_at?->format('d.m.Y') ?? '—' }}
                                                    </span>
                                                    <span class="mx-1 text-gray-400">—</span>
                                                    @php
                                                        $entryForeign = (float) ($entry->amount ?? 0);
                                                        $entryIsForeign = $entryForeign > 0.009
                                                            && \App\Support\CurrencyAmountDisplay::isForeignCurrency($entry->currency_id);
                                                    @endphp
                                                    @if ($entryIsForeign)
                                                        <span>
                                                            {{ number_format($entryForeign, 2, ',', ' ') }}
                                                            {{ $entry->currency?->code ?: $entry->currency?->symbol ?: '' }}
                                                            @if ((float) ($entry->amount_pln ?? 0) > 0.009)
                                                                <span class="text-gray-500">(≈ {!! \App\Support\MoneyFormatter::html($entry->amount_pln) !!})</span>
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span>{!! \App\Support\MoneyFormatter::html($entry->amount_pln) !!}</span>
                                                    @endif
                                                </div>
                                                {{ ($this->removeEntryAction)(['entryId' => $entry->id]) }}
                                            </div>
                                        @empty
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Brak wpłat</p>
                                        @endforelse
                                    </div>
                                    {{ ($this->addEntryAction)(['paymentId' => $payment->id]) }}
                                    @if (! empty($row['has_invoice_file']) && empty($row['invoice_url']) && ! empty($row['document_number']))
                                        <span class="text-[10px] text-emerald-700" title="Nr faktury">{{ $row['document_number'] }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="align-top px-4 py-4">
                                @include('filament.components.participant-payment-balance', ['row' => $row, 'compact' => true])
                            </td>
                            <td class="align-top px-4 py-4">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @if (\Illuminate\Support\Facades\Schema::hasTable('client_invoice_requests'))
                                        {{ ($this->createInvoiceRequestAction)(['paymentId' => $payment->id]) }}
                                    @endif
                                    @if ($remaining > 0)
                                        {{ ($this->sendReminderAction)(['paymentId' => $payment->id]) }}
                                    @endif
                                    {{ ($this->removeParticipantAction)(['paymentId' => $payment->id]) }}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
