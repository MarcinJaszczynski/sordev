@php
    $isOverview = ($variant ?? 'default') === 'overview';
@endphp

<div @class([
    'hotel-card' => $isOverview,
    'rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900' => ! $isOverview,
])>
    <div class="hotel-card-header mb-3">
        <div>
            <h3 @class(['hotel-card-title' => $isOverview, 'text-sm font-semibold text-gray-900 dark:text-gray-100' => ! $isOverview])>
                {{ $isOverview ? 'Noce — zajętość i finanse' : 'Finanse noclegów (per noc)' }}
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                @if ($isOverview)
                    Zajętość per noc; płatności i rezerwacja — jeden drawer na hotel (wspólna rezerwacja dla wielu nocy).
                @else
                    Plan, wpłaty zaliczek i rezerwacja u kontrahenta — osobno dla każdej nocy hotelowej.
                @endif
            </p>
        </div>
    </div>

    @if ($stays->isEmpty())
        <p class="text-sm text-gray-600 dark:text-gray-300">Uzupełnij plan noclegów, aby zarządzać finansami hotelu.</p>
    @else
        @if ($isOverview && count($hotelFinanceGroups) > 0)
            <div class="mb-5">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Płatności wg hotelu</p>
                <div class="overflow-x-auto">
                    <table class="hotel-overview-table">
                        <thead>
                            <tr>
                                <th>Hotel</th>
                                <th>Noce</th>
                                <th>Rezerwacja</th>
                                <th>Koszt planu</th>
                                <th>Planowane</th>
                                <th>Zapłacono</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($hotelFinanceGroups as $group)
                                @php $finance = $group['finance']; @endphp
                                <tr wire:key="hotel-finance-group-{{ $group['key'] }}">
                                    <td>{{ $group['hotel_name'] }}</td>
                                    <td>{{ $group['days_label'] }}</td>
                                    <td>
                                        @if ($group['contractor_id'])
                                            <span @class([
                                                'hotel-status-pill',
                                                'hotel-status-pill--success' => $group['reservation_confirmed'],
                                                'hotel-status-pill--warning' => ! $group['reservation_confirmed'] && filled($group['reservation_status']),
                                                'hotel-status-pill--gray' => ! $group['reservation_confirmed'] && blank($group['reservation_status']),
                                            ])>
                                                @if ($group['reservation_confirmed'])
                                                    Potwierdzona
                                                @elseif (filled($group['reservation_status']))
                                                    {{ \App\Models\Reservation::$statuses[$group['reservation_status']] ?? $group['reservation_status'] }}
                                                @else
                                                    Brak
                                                @endif
                                            </span>
                                        @else
                                            <span class="hotel-status-pill hotel-status-pill--gray">—</span>
                                        @endif
                                    </td>
                                    <td class="tabular-nums">{{ $finance['stayTotal'] ?? '—' }}</td>
                                    <td class="tabular-nums">{{ $finance['planned'] ?? '—' }}</td>
                                    <td class="tabular-nums">{{ $finance['paid'] ?? '—' }}</td>
                                    <td>
                                        <span @class([
                                            'hotel-status-pill',
                                            'hotel-status-pill--success' => ($finance['statusColor'] ?? '') === 'success',
                                            'hotel-status-pill--warning' => ($finance['statusColor'] ?? '') === 'warning',
                                            'hotel-status-pill--gray' => ($finance['statusColor'] ?? 'gray') === 'gray',
                                        ])>
                                            {{ $finance['statusLabel'] ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        @if ($group['contractor_id'])
                                            <x-filament::button
                                                size="sm"
                                                color="gray"
                                                wire:click="openHotelGroupFinance({{ (int) $group['contractor_id'] }})"
                                                icon="heroicon-o-banknotes"
                                            >
                                                Płatności
                                            </x-filament::button>
                                        @else
                                            <x-filament::button
                                                size="sm"
                                                color="gray"
                                                wire:click="openStayFinance({{ (int) ($group['stay_ids'][0] ?? 0) }})"
                                                icon="heroicon-o-banknotes"
                                            >
                                                Płatności
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table @class(['hotel-overview-table' => $isOverview, 'w-full text-sm' => ! $isOverview])>
                <thead>
                    <tr @class(['border-b border-gray-200 text-left dark:border-gray-700' => ! $isOverview])>
                        <th @class(['py-2 pr-3 font-medium' => ! $isOverview])>Noc</th>
                        <th @class(['py-2 pr-3 font-medium' => ! $isOverview])>Hotel</th>
                        @if ($isOverview)
                            <th>Miejsca / przypisani</th>
                            <th>Wolne</th>
                            <th>Zajętość</th>
                        @endif
                        <th @class(['py-2 pr-3 font-medium' => ! $isOverview])>Koszt planu pokoi</th>
                        @unless ($isOverview)
                            <th class="py-2 pr-3 font-medium">Planowane</th>
                            <th @class(['py-2 pr-3 font-medium' => ! $isOverview])>Zapłacono</th>
                            <th @class(['py-2 pr-3 font-medium' => ! $isOverview])>Status</th>
                            <th @class(['py-2 font-medium' => ! $isOverview])></th>
                        @endunless
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stays as $stay)
                        @php
                            $finance = $financeViewData($stay);
                            $dayDate = $event->dateForProgramDay((int) $stay->day)?->format('d.m.Y');
                            $occ = $occupancyByStayId[(int) $stay->id] ?? null;
                            $hotelName = $stay->contractor?->name;
                            $beds = (int) ($occ['beds'] ?? 0);
                            $assigned = (int) ($occ['assigned'] ?? 0);
                            $occupancyPct = $beds > 0 ? (int) round(($assigned / $beds) * 100) : 0;
                            $occupancyClass = match (true) {
                                $beds === 0 => 'hotel-status-pill--gray',
                                $occupancyPct >= 85 => 'hotel-status-pill--success',
                                $occupancyPct >= 50 => 'hotel-status-pill--warning',
                                default => 'hotel-status-pill--danger',
                            };
                        @endphp
                        <tr @class(['border-b border-gray-100 dark:border-gray-800' => ! $isOverview]) wire:key="hotel-stay-finance-{{ $stay->id }}">
                            <td @class(['py-2 pr-3' => ! $isOverview])>
                                {{ $stay->day }}
                                @if ($dayDate)
                                    <span class="text-gray-500">({{ $dayDate }})</span>
                                @endif
                            </td>
                            <td @class(['py-2 pr-3' => ! $isOverview, 'is-muted' => $isOverview && blank($hotelName)])>
                                {{ $hotelName ?: ($isOverview ? 'brak' : '—') }}
                            </td>
                            @if ($isOverview)
                                <td>{{ $beds }} / {{ $assigned }}</td>
                                <td>{{ (int) ($occ['free'] ?? 0) }}</td>
                                <td>
                                    @if ($beds > 0)
                                        <span @class(['hotel-status-pill', $occupancyClass])>{{ $occupancyPct }}%</span>
                                    @else
                                        <span class="hotel-status-pill hotel-status-pill--gray">—</span>
                                    @endif
                                </td>
                            @endif
                            <td @class(['py-2 pr-3 tabular-nums' => ! $isOverview, 'tabular-nums' => $isOverview])>
                                {{ $finance['stayTotal'] ?? '—' }}
                            </td>
                            @unless ($isOverview)
                                <td class="py-2 pr-3 tabular-nums">{{ $finance['planned'] ?? '—' }}</td>
                                <td @class(['py-2 pr-3 tabular-nums' => ! $isOverview, 'tabular-nums' => $isOverview])>
                                    {{ $finance['paid'] ?? '—' }}
                                </td>
                                <td @class(['py-2 pr-3' => ! $isOverview])>
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => ($finance['statusColor'] ?? '') === 'success',
                                        'bg-amber-100 text-amber-800' => ($finance['statusColor'] ?? '') === 'warning',
                                        'bg-gray-100 text-gray-700' => ($finance['statusColor'] ?? 'gray') === 'gray',
                                    ])>
                                        {{ $finance['statusLabel'] ?? '—' }}
                                    </span>
                                </td>
                                <td @class(['py-2 text-right' => ! $isOverview])>
                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        wire:click="openStayFinance({{ $stay->id }})"
                                        icon="heroicon-o-banknotes"
                                    >
                                        Płatności i rezerwacja
                                    </x-filament::button>
                                </td>
                            @endunless
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @include('filament.resources.event-resource.pages.partials.event-finance-cost-drawer')
</div>
