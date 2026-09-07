@php
    $finance = $finance ?? null;
    $labels = is_array($finance) ? ($finance['labels'] ?? []) : [];
    $pilotCashUrl = \App\Filament\Resources\EventResource::getUrl('finance-pilot-cash', ['record' => $record]);
@endphp

@if (is_array($finance))
    <div class="event-finance-summary-bar workflow-info-bar mb-1 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="event-finance-summary-bar__grid">
            @if (! empty($finance['offer_ready']))
                <section class="event-finance-summary-bar__section">
                    <div class="workflow-info-bar__section-title">
                        <x-heroicon-m-calculator class="h-3.5 w-3.5 text-gray-400" />
                        Zysk z oferty
                    </div>
                    <div class="event-finance-summary-bar__tiles event-finance-summary-bar__tiles--offer">
                        <div class="workflow-info-bar__tile workflow-info-bar__tile--ok">
                            <div class="workflow-info-bar__tile-label">
                                {{ $labels['offer_markup'] ?? 'Narzut (zysk)' }}
                                @if ((float) ($finance['offer_markup_percent'] ?? 0) > 0)
                                    ({{ rtrim(rtrim(number_format((float) $finance['offer_markup_percent'], 2, ',', ' '), '0'), ',') }}%)
                                @endif
                            </div>
                            <div class="workflow-info-bar__tile-value text-sm">{{ $finance['offer_markup'] ?? '—' }}</div>
                        </div>
                        <div class="workflow-info-bar__tile" title="{{ $finance['offer_tax_hint'] ?? '' }}">
                            <div class="workflow-info-bar__tile-label">{{ $labels['offer_tax'] ?? 'Podatki' }}</div>
                            <div class="workflow-info-bar__tile-value text-sm">{{ $finance['offer_tax'] ?? '—' }}</div>
                            @if (! empty($finance['offer_tax_hint']))
                                <div class="mt-0.5 text-[10px] leading-tight text-gray-500 dark:text-gray-400">{{ $finance['offer_tax_hint'] }}</div>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            <section class="event-finance-summary-bar__section">
                <div class="workflow-info-bar__section-title">
                    <x-heroicon-m-building-office-2 class="h-3.5 w-3.5 text-gray-400" />
                    Rozliczenie dostawców
                </div>
                <div class="event-finance-summary-bar__tiles event-finance-summary-bar__tiles--vendors">
                    <div class="workflow-info-bar__tile">
                        <div class="workflow-info-bar__tile-label">{{ $labels['planned'] ?? 'Koszty (planowane)' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['planned'] ?? '—' }}</div>
                    </div>
                    <div class="workflow-info-bar__tile">
                        <div class="workflow-info-bar__tile-label">{{ $labels['calculation'] ?? 'Koszty (szablon)' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['calculation'] ?? '—' }}</div>
                    </div>
                    <div class="workflow-info-bar__tile workflow-info-bar__tile--ok">
                        <div class="workflow-info-bar__tile-label">{{ $labels['paid'] ?? 'Zapłacono' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['paid'] ?? '—' }}</div>
                        <div class="mt-1 space-y-0.5 text-[10px] leading-tight text-emerald-800/80 dark:text-emerald-200/80">
                            <div>
                                <span class="font-medium">{{ $labels['paid_office'] ?? 'Zapłacono przez biuro' }}:</span>
                                {{ $finance['paid_office'] ?? '—' }}
                            </div>
                            <div>
                                <span class="font-medium">{{ $labels['paid_pilot'] ?? 'Zapłacono przez pilota' }}:</span>
                                {{ $finance['paid_pilot'] ?? '—' }}
                            </div>
                        </div>
                    </div>
                    <div class="workflow-info-bar__tile workflow-info-bar__tile--danger">
                        <div class="workflow-info-bar__tile-label">{{ $labels['remaining'] ?? 'Do zapłaty dostawcom' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['remaining'] ?? '—' }}</div>
                    </div>
                    <div class="workflow-info-bar__tile workflow-info-bar__tile--pilot">
                        <div class="workflow-info-bar__tile-label">{{ $labels['pilot_cash'] ?? 'Gotówka pilota (planowane)' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['pilot_cash'] ?? '—' }}</div>
                        <a href="{{ $pilotCashUrl }}" class="mt-0.5 inline-block text-[10px] font-medium text-indigo-700 underline hover:text-indigo-900 dark:text-indigo-300">
                            Gotówka dla pilota →
                        </a>
                    </div>
                    <div class="workflow-info-bar__tile workflow-info-bar__tile--pilot">
                        <div class="workflow-info-bar__tile-label">{{ $labels['pilot_cash_paid'] ?? 'Wypłacono pilotowi' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['pilot_cash_paid'] ?? '—' }}</div>
                    </div>
                </div>
            </section>

            <section class="event-finance-summary-bar__section">
                <div class="workflow-info-bar__section-title">
                    <x-heroicon-m-user-group class="h-3.5 w-3.5 text-gray-400" />
                    Rozliczenie klientów
                </div>
                <div class="event-finance-summary-bar__tiles event-finance-summary-bar__tiles--clients">
                    <div class="workflow-info-bar__tile">
                        <div class="workflow-info-bar__tile-label">{{ $labels['price_per_person'] ?? 'Cena za osobę (umowa / szablon)' }}</div>
                        <div class="workflow-info-bar__tile-value" title="{{ $finance['price_per_person_hint'] ?? '' }}">{{ $finance['price_per_person'] ?? '—' }}</div>
                    </div>
                    <div class="workflow-info-bar__tile workflow-info-bar__tile--info">
                        <div class="workflow-info-bar__tile-label">{{ $labels['client_paid'] ?? 'Wpłacono od klientów' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['client_paid'] ?? '—' }}</div>
                    </div>
                    <div class="workflow-info-bar__tile">
                        <div class="workflow-info-bar__tile-label">{{ $labels['client_due'] ?? 'Należne od klientów' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['client_due'] ?? '—' }}</div>
                    </div>
                    @php
                        $remainingTone = (string) ($finance['client_remaining_tone'] ?? 'due');
                        $remainingTileClass = match ($remainingTone) {
                            'ok' => 'workflow-info-bar__tile--ok',
                            'over' => 'workflow-info-bar__tile--warn',
                            default => 'workflow-info-bar__tile--danger',
                        };
                    @endphp
                    <div @class(['workflow-info-bar__tile', $remainingTileClass])>
                        <div class="workflow-info-bar__tile-label">{{ $labels['client_remaining'] ?? 'Do dopłaty od klientów' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['client_remaining'] ?? '—' }}</div>
                    </div>
                </div>
            </section>
        </div>

        @if (! empty($finance['calc_plan_hint']))
            <div class="mt-2 flex items-start gap-1 border-t border-gray-100 pt-2 text-[11px] text-amber-700 dark:border-gray-800 dark:text-amber-400">
                <x-heroicon-m-information-circle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                <span class="leading-snug">{{ $finance['calc_plan_hint'] }}</span>
            </div>
        @endif
    </div>
@endif
