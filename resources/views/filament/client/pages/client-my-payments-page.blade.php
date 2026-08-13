@php
    use App\Support\MoneyFormatter;
@endphp

<x-filament-panels::page>
    @if(filled($archiveMessage))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $archiveMessage }}
        </div>
    @endif

    @if(! $payment)
        <div class="sor-lw-card text-sm text-gray-600">
            Brak danych o wpłatach powiązanych z Twoim uczestnictwem.
        </div>
    @else
        <section class="sor-lw-card">
            <h3 class="sor-lw-title">Status płatności</h3>
            <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <dt class="text-gray-500">Uczestnik</dt>
                    <dd class="font-medium">{{ $payment->participant_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Nr rezerwacji</dt>
                    <dd class="font-medium">{{ $payment->booking_reference ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Należne</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) $payment->due_amount_pln, 'PLN') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Wpłacone</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) $payment->paid_amount_pln, 'PLN') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Saldo</dt>
                    <dd class="font-medium">{{ MoneyFormatter::format((float) $payment->balance, 'PLN') }}</dd>
                </div>
                @if($balance)
                    <div>
                        <dt class="text-gray-500">Różnica</dt>
                        <dd class="font-medium">{{ MoneyFormatter::format((float) ($balance['remaining_pln'] ?? 0), 'PLN') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Semafor</dt>
                        <dd class="font-medium">{{ $balance['coverage_label'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Następna rata</dt>
                        <dd class="font-medium">{{ $balance['installment_label'] ?? '—' }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500">Status</dt>
                    <dd class="font-medium">
                        @if($balance)
                            {{ $balance['display_status_label'] ?? ($balance['coverage_label'] ?? '—') }}
                        @else
                            {{ \App\Models\EventSettlementParticipantPayment::$paymentStatuses[$payment->payment_status] ?? $payment->payment_status }}
                        @endif
                    </dd>
                </div>
                @if($payment->payment_date)
                    <div>
                        <dt class="text-gray-500">Data wpłaty</dt>
                        <dd class="font-medium">{{ $payment->payment_date->format('d.m.Y') }}</dd>
                    </div>
                @endif
            </dl>

            @if(! empty($balance['next_schedule_id']) && (float) ($balance['remaining_pln'] ?? 0) > 0)
                <div class="mt-5">
                    <x-filament::button
                        wire:click="payInstallment({{ (int) $balance['next_schedule_id'] }})"
                        icon="heroicon-o-credit-card"
                    >
                        {{ \App\Support\PaymentCta::label() }}
                    </x-filament::button>
                    <a
                        href="{{ \App\Filament\Client\Pages\ClientPaymentSchedulePage::urlFor($event) }}"
                        class="ml-3 text-sm font-medium text-primary-600 underline"
                    >
                        Harmonogram płatności
                    </a>
                </div>
            @endif
        </section>
    @endif
</x-filament-panels::page>
