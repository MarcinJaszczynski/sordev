<div class="fi-resource-relation-manager flex flex-col gap-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h4 class="text-md font-semibold text-gray-950 dark:text-white">Wpłaty uczestników</h4>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Historia wpłat w rozliczeniu — jeden wiersz na uczestnika.
            </p>
        </div>

        <div>
            {{ $this->addParticipantAction }}
        </div>
    </div>

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
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Wpłaty</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Pozostało</th>
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
                        <tr wire:key="participant-payment-{{ $payment->id }}">
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
                                </div>
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
                                                    <span>{!! \App\Support\MoneyFormatter::html($entry->amount_pln) !!}</span>
                                                </div>
                                                {{ ($this->removeEntryAction)(['entryId' => $entry->id]) }}
                                            </div>
                                        @empty
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Brak wpłat</p>
                                        @endforelse
                                    </div>
                                    {{ ($this->addEntryAction)(['paymentId' => $payment->id]) }}
                                </div>
                            </td>
                            <td class="align-top px-4 py-4 text-right">
                                <span @class([
                                    'font-semibold',
                                    'text-danger-600 dark:text-danger-400' => $remaining > 0,
                                    'text-success-600 dark:text-success-400' => $remaining <= 0,
                                ])>
                                    {!! \App\Support\MoneyFormatter::html($remaining) !!}
                                </span>
                            </td>
                            <td class="align-top px-4 py-4">
                                <div class="flex flex-wrap items-center justify-end gap-2">
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
