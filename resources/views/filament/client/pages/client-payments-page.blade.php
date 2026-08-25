@php
    use App\Support\MoneyFormatter;
@endphp

<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $event,
        'kicker' => 'Płatności',
    ])

    @if(filled($archiveMessage))
        <div class="portal-notice portal-notice--accent">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="space-y-4">
        @if($isParticipant || ($isGuardian && $contract))
            <section class="client-portal-section overflow-hidden !p-0">
                <div class="border-b border-[#E5E3DA] bg-gradient-to-r from-slate-50 to-white px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="client-portal-kicker">Twoja umowa</p>
                            <h3 class="mt-1 text-lg font-semibold text-[#2C2C2A]">
                                {{ $payerName ?: 'Płatności' }}
                            </h3>
                            <p class="mt-1 text-sm text-[#888780]">
                                @if(filled($bookingReference))
                                    Nr {{ $bookingReference }}
                                @endif
                                @if($contract?->contract_number && $contract->contract_number !== $bookingReference)
                                    · umowa {{ $contract->contract_number }}
                                @endif
                                @if(filled($accessHint))
                                    · {{ $accessHint }}
                                @endif
                            </p>
                        </div>
                        @if(filled($statusLabel))
                            <span @class([
                                'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                'bg-emerald-100 text-emerald-800' => str_contains((string) $statusLabel, 'Opłac'),
                                'bg-amber-100 text-amber-900' => str_contains((string) $statusLabel, 'zęści'),
                                'bg-[#F1EFE8] text-slate-700' => ! str_contains((string) $statusLabel, 'Opłac') && ! str_contains((string) $statusLabel, 'zęści'),
                            ])>
                                {{ $statusLabel }}
                            </span>
                        @endif
                    </div>
                </div>

                @if(is_array($dietBreakdown) && ($dietBreakdown['enabled'] ?? false) && (($dietBreakdown['surcharge_pln'] ?? 0) > 0.009 || ($dietBreakdown['base_pln'] ?? 0) > 0.009))
                    <dl class="grid gap-px border-b border-[#E5E3DA] bg-[#F1EFE8] sm:grid-cols-3">
                        <div class="bg-white px-5 py-3">
                            <dt class="text-xs uppercase tracking-wide text-[#888780]">Cena bazowa</dt>
                            <dd class="mt-1 text-sm font-semibold tabular-nums text-[#2C2C2A]">
                                {{ MoneyFormatter::format((float) $dietBreakdown['base_pln'], 'PLN') }}
                            </dd>
                        </div>
                        <div class="bg-white px-5 py-3">
                            <dt class="text-xs uppercase tracking-wide text-[#888780]">Dopłata diety</dt>
                            <dd class="mt-1 text-sm font-semibold tabular-nums text-[#0C447C]">
                                {{ MoneyFormatter::format((float) $dietBreakdown['surcharge_pln'], 'PLN') }}
                            </dd>
                        </div>
                        <div class="bg-white px-5 py-3">
                            <dt class="text-xs uppercase tracking-wide text-[#888780]">Razem</dt>
                            <dd class="mt-1 text-sm font-semibold tabular-nums text-[#2C2C2A]">
                                {{ MoneyFormatter::format((float) ($duePln ?? 0), 'PLN') }}
                            </dd>
                        </div>
                    </dl>
                @endif

                @if($duePln !== null)
                    <dl class="grid gap-px bg-[#F1EFE8] sm:grid-cols-3">
                        <div class="bg-white px-5 py-4">
                            <dt class="text-xs uppercase tracking-wide text-[#888780]">Należne PLN</dt>
                            <dd class="mt-1 text-xl font-semibold tabular-nums text-[#2C2C2A]">
                                {{ MoneyFormatter::format((float) $duePln, 'PLN') }}
                            </dd>
                        </div>
                        <div class="bg-white px-5 py-4">
                            <dt class="text-xs uppercase tracking-wide text-emerald-700">Wpłacone PLN</dt>
                            <dd class="mt-1 text-xl font-semibold tabular-nums text-emerald-800">
                                {{ MoneyFormatter::format((float) $paidPln, 'PLN') }}
                            </dd>
                        </div>
                        <div class="bg-white px-5 py-4">
                            <dt class="text-xs uppercase tracking-wide text-[#0C447C]">Różnica PLN</dt>
                            <dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">
                                {{ MoneyFormatter::format((float) $remainingPln, 'PLN') }}
                            </dd>
                        </div>
                    </dl>
                @endif

                @if($chargeNow !== null && $chargeNow > 0.009)
                    <div class="flex flex-wrap items-center gap-3 border-t border-[#E5E3DA] px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-[#2C2C2A]">
                                Do zapłaty teraz:
                                {{ MoneyFormatter::format((float) $chargeNow, 'PLN') }}
                            </p>
                            @if(filled($chargeLabel))
                                <p class="text-xs text-[#888780]">{{ $chargeLabel }}</p>
                            @endif
                        </div>
                        @if($chargeScheduleId)
                            <x-filament::button
                                wire:click="payInstallment({{ (int) $chargeScheduleId }})"
                                icon="heroicon-o-credit-card"
                            >
                                {{ $payCta }}
                            </x-filament::button>
                        @endif
                    </div>
                @elseif($remainingPln !== null && $remainingPln <= 0.009 && $duePln > 0.009)
                    <div class="border-t border-[#E5E3DA] px-5 py-3 text-sm text-emerald-700">
                        Kwota PLN z Twojej umowy jest rozliczona. Poniżej ewentualna waluta poza przelewem online.
                    </div>
                @endif
            </section>
        @elseif($isGuardian && ! $isParticipant)
            <section class="client-portal-section">
                <h3 class="text-lg font-semibold text-[#2C2C2A]">Płatności grupy</h3>
                <p class="mt-2 text-sm text-[#5F5E5A]">
                    Jako opiekun widzisz harmonogram umowy grupowej.
                    @if($groupPaymentsUrl)
                        Szczegóły wpłat uczestników:
                        <a href="{{ $groupPaymentsUrl }}" class="font-medium text-[#0C447C] underline">Wpłaty grupy</a>.
                    @endif
                </p>
            </section>
        @endif

        @if(! $contract || ! $presentation)
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-8 text-center text-sm text-[#888780]">
                Brak harmonogramu płatności powiązanego z Twoim dostępem do tej wycieczki.
            </div>
        @else
            <section class="client-portal-section overflow-hidden !p-0">
                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-[#E5E3DA] px-5 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-[#2C2C2A]">Harmonogram</h3>
                        <p class="text-xs text-[#888780]">
                            {{ $presentation['payment_scheme_label'] ?? '—' }}
                            · łącznie
                            {{ MoneyFormatter::format((float) ($presentation['total_amount'] ?? $duePln ?? 0), $contract->currency ?: 'PLN') }}
                        </p>
                    </div>
                </header>

                @if(empty($presentation['payment_schedules']))
                    <p class="px-5 py-4 text-sm text-[#888780]">Brak zdefiniowanych transz — obowiązuje kwota umowy.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#E5E3DA] text-left text-xs uppercase tracking-wide text-[#888780]">
                                    <th class="px-5 py-3 font-medium">Opis</th>
                                    <th class="px-5 py-3 font-medium">Termin</th>
                                    <th class="px-5 py-3 text-right font-medium">Kwota</th>
                                    <th class="px-5 py-3 text-right font-medium">Wpłacono</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($presentation['payment_schedules'] as $schedule)
                                    @php
                                        $isForeign = ((float) ($schedule['amount_foreign'] ?? 0) > 0.009)
                                            && ((float) ($schedule['amount'] ?? 0) <= 0.009);
                                        $amountLabel = $isForeign
                                            ? number_format((float) $schedule['amount_foreign'], 2, ',', ' ').' '.strtoupper((string) ($schedule['currency_code'] ?? ''))
                                            : MoneyFormatter::format((float) ($schedule['amount'] ?? 0), $contract->currency ?: 'PLN');
                                        $canPayOnline = ! $isForeign
                                            && ! ($schedule['is_paid'] ?? false)
                                            && ! empty($schedule['id'])
                                            && $contract instanceof \App\Models\Contract
                                            && (int) ($chargeScheduleId ?? 0) === (int) $schedule['id'];
                                        $fxPlace = ($schedule['paid_by'] ?? 'pilot') === 'office' ? 'w biurze' : 'u pilota / w autokarze';
                                    @endphp
                                    <tr @class(['bg-sky-50/70' => $canPayOnline])>
                                        <td class="px-5 py-3.5">
                                            <div class="font-medium text-[#2C2C2A]">{{ $schedule['label'] ?: 'Transza' }}</div>
                                            @if ($isForeign)
                                                <div class="text-xs text-amber-700">Waluta · {{ $fxPlace }} · poza przelewem PLN</div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 text-[#5F5E5A]">{{ $schedule['due_date'] ?: '—' }}</td>
                                        <td class="px-5 py-3.5 text-right font-medium tabular-nums">{{ $amountLabel }}</td>
                                        <td class="px-5 py-3.5 text-right tabular-nums">
                                            @if ($isForeign)
                                                —
                                            @else
                                                {{ MoneyFormatter::format((float) ($schedule['paid_amount'] ?? 0), $contract->currency ?: 'PLN') }}
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5">
                                            @if($schedule['is_paid'] ?? false)
                                                <span class="font-medium text-emerald-700">Opłacona</span>
                                            @elseif($isForeign)
                                                <span class="text-amber-700">Poza PLN</span>
                                            @elseif($canPayOnline)
                                                <span class="font-semibold text-[#0C447C]">Teraz</span>
                                            @else
                                                <span class="text-[#5F5E5A]">Oczekuje</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 text-right">
                                            @if($canPayOnline)
                                                <x-filament::button
                                                    size="sm"
                                                    color="primary"
                                                    wire:click="payInstallment({{ (int) $schedule['id'] }})"
                                                >
                                                    Zapłać
                                                </x-filament::button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        @if($isGuardian && $groupPaymentsUrl && $isParticipant)
            <p class="text-sm text-[#888780]">
                Jesteś też opiekunem —
                <a href="{{ $groupPaymentsUrl }}" class="font-medium text-[#0C447C] underline">zobacz wpłaty grupy</a>.
            </p>
        @endif
    </div>
</x-filament-panels::page>
