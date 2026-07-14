<x-filament-panels::page>

    @include('filament.components.finance-module-nav', ['activeTab' => 'pending-payments'])



    <div class="mb-4 flex flex-wrap items-center gap-2">

        <x-filament::button :color="$displayMode === 'list' ? 'primary' : 'gray'" wire:click="$set('displayMode', 'list')">

            Lista

        </x-filament::button>

        <x-filament::button :color="$displayMode === 'calendar' ? 'primary' : 'gray'" wire:click="$set('displayMode', 'calendar')">

            Kalendarz

        </x-filament::button>



        <span class="mx-1 text-gray-300">|</span>



        @foreach([

            'all' => 'Wszystkie',

            'settlement' => 'Rozliczenia',

            'program_payment' => 'Zaliczki punktów',

            'vendor_invoice' => 'Faktury KSeF',

            'contract' => 'Kontrakty TFG',

            'agreement' => 'Umowy',

        ] as $filter => $label)

            <x-filament::button

                size="sm"

                :color="$typeFilter === $filter ? 'info' : 'gray'"

                wire:click="$set('typeFilter', '{{ $filter }}')"

            >

                {{ $label }}

            </x-filament::button>

        @endforeach

        <span class="mx-1 text-gray-300">|</span>

        @foreach([
            'all' => 'Wszyscy płatnicy',
            'office' => 'Biuro',
            'pilot' => 'Pilot',
        ] as $filter => $label)
            <x-filament::button
                size="sm"
                :color="$payerFilter === $filter ? 'warning' : 'gray'"
                wire:click="$set('payerFilter', '{{ $filter }}')"
            >
                {{ $label }}
            </x-filament::button>
        @endforeach

    </div>



    @if($displayMode === 'calendar')

        <div

            id="pending-payments-calendar"

            wire:ignore

            class="min-h-[28rem] rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900"

            data-calendar-events='@json($this->calendarEvents)'

        ></div>



        @include('filament.components.fullcalendar-boot', ['elementId' => 'pending-payments-calendar'])

    @else

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">

            <table class="admin-zebra-table w-full min-w-[52rem] text-sm">

                <thead class="bg-gray-50 text-left text-xs font-bold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">

                    <tr>

                        <th class="px-3 py-2">Termin</th>

                        <th class="px-3 py-2">Typ</th>

                        <th class="px-3 py-2">Impreza</th>

                        <th class="px-3 py-2">Pozycja</th>

                        <th class="px-3 py-2 text-right">Kwota</th>

                        <th class="px-3 py-2">Płatnik</th>

                        <th class="px-3 py-2">Status</th>

                        <th class="px-3 py-2 text-right">Akcje</th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($this->inboxEntries as $row)

                        <tr wire:key="pending-payment-{{ $row['id'] }}">

                            <td class="px-3 py-2 whitespace-nowrap font-medium">{{ $row['due_date'] ? \Carbon\Carbon::parse($row['due_date'])->format('d.m.Y') : '—' }}</td>

                            <td class="px-3 py-2">

                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" style="background: {{ $row['color'] }}22; color: {{ $row['color'] }}">

                                    {{ $row['type_label'] }}

                                </span>

                            </td>

                            <td class="px-3 py-2">{{ $row['event_code'] ?? '—' }}</td>

                            <td class="px-3 py-2">{{ $row['title'] }}</td>

                            <td class="px-3 py-2 text-right money-nowrap">{{ $row['amount_label'] }}</td>

                            <td class="px-3 py-2 text-gray-600">{{ $row['payer'] ?? '—' }}</td>

                            <td class="px-3 py-2 text-gray-600">{{ $row['status'] }}</td>

                            <td class="px-3 py-2 text-right whitespace-nowrap">

                                <div class="inline-flex items-center gap-2">

                                    @if($row['url'])

                                        <a href="{{ $row['url'] }}" class="text-primary-600 hover:underline text-xs font-semibold">Otwórz</a>

                                    @endif

                                    @if($row['can_complete'] ?? false)

                                        <button

                                            type="button"

                                            wire:click="markCompleted('{{ $row['id'] }}')"

                                            wire:confirm="Oznaczyć tę pozycję jako opłaconą / wykonaną?"

                                            class="text-xs font-semibold text-emerald-700 hover:underline"

                                        >

                                            Wykonane

                                        </button>

                                    @endif

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="8" class="px-3 py-8 text-center text-gray-500">Brak oczekujących płatności w wybranym zakresie.</td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    @endif

</x-filament-panels::page>

