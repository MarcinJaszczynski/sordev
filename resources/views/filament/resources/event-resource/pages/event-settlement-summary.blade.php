<x-filament-panels::page>
    @include('filament.resources.event-resource.components.finance-sub-navigation', ['record' => $event])

    @php
        $dashboard = $this->settlementDashboard;
        $report = $this->settlementReport;
        $counts = $dashboard['counts'] ?? [];
        $office = $dashboard['office'] ?? [];
        $pilot = $dashboard['pilot'] ?? [];
        $attention = $dashboard['attention_items'] ?? [];
        $controlRows = $dashboard['control_rows'] ?? [];
        $statusColors = \App\Services\SettlementPaymentHealthService::$statusColors;
        $statusLabels = \App\Services\SettlementPaymentHealthService::$statusLabels;
        $costsUrl = \App\Filament\Resources\EventResource::getUrl('settlement-costs', ['record' => $event]);
    @endphp

    <div class="space-y-6">
        @php $margin = $this->marginPlanVsActual; @endphp
        <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Marża plan vs rzeczywista</h3>
                @if (($margin['health_overdue'] ?? 0) > 0 || ($margin['health_shortfalls'] ?? 0) > 0)
                    <span class="text-xs font-medium text-rose-700">
                        Semafor: {{ (int) ($margin['health_shortfalls'] ?? 0) }} braków,
                        {{ (int) ($margin['health_overdue'] ?? 0) }} po terminie
                    </span>
                @endif
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                <div>
                    <div class="text-xs text-gray-500">Koszt plan</div>
                    <div class="font-semibold text-right">{{ $margin['labels']['planned_cost'] }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Koszt real</div>
                    <div class="font-semibold text-right">{{ $margin['labels']['actual_cost'] }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Marża plan</div>
                    <div class="font-semibold text-right text-emerald-700">{{ $margin['labels']['planned_margin'] }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Marża real (Δ)</div>
                    <div class="font-semibold text-right {{ ($margin['margin_delta'] ?? 0) < 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                        {{ $margin['labels']['actual_margin'] }}
                        <span class="text-xs font-normal">({{ $margin['labels']['margin_delta'] }}@if($margin['margin_delta_percent'] !== null), {{ number_format($margin['margin_delta_percent'], 1, ',', ' ') }}%@endif)</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                \App\Services\SettlementPaymentHealthService::STATUS_OK => 'Zgadza się',
                \App\Services\SettlementPaymentHealthService::STATUS_DUE => 'W terminie',
                \App\Services\SettlementPaymentHealthService::STATUS_OVERDUE => 'Po terminie',
                \App\Services\SettlementPaymentHealthService::STATUS_SHORTFALL => 'Brakuje',
            ] as $status => $label)
                @php [$bg, $fg] = $statusColors[$status] ?? ['#f3f4f6', '#374151']; @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-bold" style="color: {{ $fg }}">{{ $counts[$status] ?? 0 }}</div>
                    <div class="mt-1 h-1 rounded-full" style="background: {{ $bg }}"></div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ([['Biuro', $office, 'office'], ['Pilot', $pilot, 'pilot']] as [$title, $bucket, $key])
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Plan</dt><dd class="font-semibold">{{ $bucket['planned_label'] ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Wpłacono</dt><dd class="font-semibold text-emerald-700">{{ $bucket['paid_label'] ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Brakuje</dt><dd class="font-semibold text-rose-700">{{ $bucket['remaining_label'] ?? '—' }}</dd></div>
                    </dl>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Kontrola planu</h3>
                <a href="{{ $costsUrl }}" class="text-xs font-semibold text-primary-600 hover:underline">Przejdź do kosztów →</a>
            </div>
            @if ($controlRows === [])
                <p class="text-sm text-gray-500">Brak pozycji planu do kontroli.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="px-3 py-2">Pozycja</th>
                                <th class="px-3 py-2">Płatnik</th>
                                <th class="px-3 py-2">Plan</th>
                                <th class="px-3 py-2">Wpłacono</th>
                                <th class="px-3 py-2">Brakuje</th>
                                <th class="px-3 py-2">Semafor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($controlRows as $row)
                                @php
                                    $status = $row['coverage_status'] ?? 'shortfall';
                                    [$bg, $fg] = $statusColors[$status] ?? ['#f3f4f6', '#374151'];
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ ($row['paid_by'] ?? 'office') === 'pilot' ? 'Pilot' : 'Biuro' }}</td>
                                    <td class="px-3 py-2">{{ number_format((float) ($row['planned_pln'] ?? 0), 2, ',', ' ') }} PLN</td>
                                    <td class="px-3 py-2">{{ number_format((float) ($row['paid_pln'] ?? 0), 2, ',', ' ') }} PLN</td>
                                    <td class="px-3 py-2">{{ number_format((float) ($row['remaining_pln'] ?? 0), 2, ',', ' ') }} PLN</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" style="background: {{ $bg }}; color: {{ $fg }}">
                                            {{ $statusLabels[$status] ?? $status }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($attention !== [])
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
                <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-100">Wymaga korekty ({{ count($attention) }})</h3>
                <ul class="mt-2 space-y-1 text-sm text-amber-900 dark:text-amber-100">
                    @foreach ($attention as $item)
                        <li>
                            <a href="{{ $costsUrl }}" class="hover:underline">
                                {{ $item['name'] ?? 'Pozycja' }} — brakuje {{ number_format((float) ($item['remaining_pln'] ?? 0), 2, ',', ' ') }} PLN
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-gray-100">Wpłaty uczestników</h3>
            @php $participantAgg = $report['participant_aggregate'] ?? []; @endphp
            <div class="grid gap-3 text-sm md:grid-cols-4">
                <div><span class="text-gray-500">Należne:</span> <strong>{{ number_format((float) ($participantAgg['total_due'] ?? 0), 2, ',', ' ') }} PLN</strong></div>
                <div><span class="text-gray-500">Wpłacono:</span> <strong>{{ number_format((float) ($participantAgg['total_paid'] ?? 0), 2, ',', ' ') }} PLN</strong></div>
                <div><span class="text-gray-500">Brakuje:</span> <strong>{{ number_format((float) ($participantAgg['total_remaining'] ?? 0), 2, ',', ' ') }} PLN</strong></div>
                <div><span class="text-gray-500">Zapłaciło:</span> <strong>{{ $participantAgg['paid_count'] ?? 0 }}/{{ $participantAgg['count'] ?? 0 }}</strong></div>
            </div>
        </div>
    </div>

    @capture($form)
        <x-filament-panels::form
            id="form"
            :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
            wire:submit="save"
        >
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>
    @endcapture

    <div class="mt-8">
        {{ $form() }}
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
