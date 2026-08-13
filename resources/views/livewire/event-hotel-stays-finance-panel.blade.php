<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="mb-3">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Finanse noclegów (per noc)</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Plan, wpłaty zaliczek i rezerwacja u kontrahenta — osobno dla każdej nocy hotelowej.
        </p>
    </div>

    @if ($stays->isEmpty())
        <p class="text-sm text-gray-600 dark:text-gray-300">Uzupełnij plan noclegów powyżej, aby zarządzać finansami hotelu.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                        <th class="py-2 pr-3 font-medium">Noc</th>
                        <th class="py-2 pr-3 font-medium">Hotel</th>
                        <th class="py-2 pr-3 font-medium">Koszt planu pokoi</th>
                        <th class="py-2 pr-3 font-medium">Plan rozliczenia</th>
                        <th class="py-2 pr-3 font-medium">Zapłacono</th>
                        <th class="py-2 pr-3 font-medium">Status</th>
                        <th class="py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stays as $stay)
                        @php
                            $finance = $financeViewData($stay);
                            $dayDate = $event->dateForProgramDay((int) $stay->day)?->format('d.m.Y');
                        @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800" wire:key="hotel-stay-finance-{{ $stay->id }}">
                            <td class="py-2 pr-3">
                                {{ $stay->day }}
                                @if ($dayDate)
                                    <span class="text-gray-500">({{ $dayDate }})</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3">{{ $stay->contractor?->name ?? '—' }}</td>
                            <td class="py-2 pr-3 tabular-nums">{{ $finance['stayTotal'] ?? '—' }}</td>
                            <td class="py-2 pr-3 tabular-nums">{{ $finance['planned'] ?? '—' }}</td>
                            <td class="py-2 pr-3 tabular-nums">{{ $finance['paid'] ?? '—' }}</td>
                            <td class="py-2 pr-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => ($finance['statusColor'] ?? '') === 'success',
                                    'bg-amber-100 text-amber-800' => ($finance['statusColor'] ?? '') === 'warning',
                                    'bg-gray-100 text-gray-700' => ($finance['statusColor'] ?? 'gray') === 'gray',
                                ])>
                                    {{ $finance['statusLabel'] ?? '—' }}
                                </span>
                            </td>
                            <td class="py-2 text-right">
                                <x-filament::button
                                    size="sm"
                                    wire:click="openStayFinance({{ $stay->id }})"
                                    icon="heroicon-o-banknotes"
                                >
                                    Płatności i rezerwacja
                                </x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @include('filament.resources.event-resource.pages.partials.event-finance-cost-drawer')
</div>
