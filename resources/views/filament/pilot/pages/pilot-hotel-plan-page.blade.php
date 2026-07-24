<x-filament-panels::page>
    @if (empty($plan))
        <x-filament::section>
            <p class="text-sm text-gray-500">Brak planu noclegów dla tej imprezy.</p>
        </x-filament::section>
    @else
        @if ($this->canEditRoomNumbers())
            <x-filament::section class="mb-4">
                <p class="text-sm text-gray-600">
                    Po zakwaterowaniu wpisz numery pokoi nadane przez hotel — widoczne dla biura i w dokumentach.
                </p>
            </x-filament::section>
        @endif

        <form wire:submit.prevent="saveRoomNumbers" class="space-y-4">
            @foreach ($plan as $night)
                <x-filament::section>
                    <x-slot name="heading">
                        Noc {{ $night['day'] }}
                        @if (! empty($night['hotel_name']))
                            <span class="font-normal text-gray-500">— {{ $night['hotel_name'] }}@if(! empty($night['hotel_branch'])) ({{ $night['hotel_branch'] }})@endif</span>
                        @endif
                    </x-slot>

                    @if (! empty($night['hotel_name']))
                        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $night['hotel_name'] }}
                                @if (! empty($night['hotel_branch']))
                                    <span class="font-normal text-gray-600">— {{ $night['hotel_branch'] }}</span>
                                @endif
                            </p>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Adres podjazdu</p>
                            <x-contractor-contact-meta
                                :address="$night['hotel_address'] ?? null"
                                :phone="$night['hotel_phone'] ?? null"
                                :email="$night['hotel_email'] ?? null"
                                class="mt-1 text-xs text-gray-600 dark:text-gray-300"
                            />
                        </div>
                    @endif

                    @if (! empty($night['offer_notes']))
                        <div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900">
                            {!! $night['offer_notes'] !!}
                        </div>
                    @endif

                    @php
                        $sections = [
                            'qty' => 'Pokoje płatne',
                            'gratis' => \App\Support\EventParticipantGroupLabels::GRATIS,
                            'staff' => 'Obsługa',
                            'driver' => 'Kierowca',
                        ];
                    @endphp

                    @foreach ($sections as $role => $label)
                        @php $lines = $night[$role] ?? []; @endphp
                        @if (count($lines) > 0)
                            <div class="mb-4 last:mb-0">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                                <div class="pilot-hotel-table-wrap overflow-x-auto rounded-lg border border-gray-200">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                            <tr>
                                                <th class="px-3 py-2">Typ pokoju</th>
                                                <th class="px-3 py-2">Osób/pokój</th>
                                                <th class="px-3 py-2">Nr pokoju (hotel)</th>
                                                <th class="px-3 py-2">Uczestnicy</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach ($lines as $line)
                                                @foreach ($line['units'] ?? [] as $unit)
                                                @foreach ($unit['occupant_slots'] ?? [] as $slot)
                                                    <tr wire:key="unit-{{ $unit['id'] ?? $loop->parent->index }}-{{ $unit['unit_index'] }}-{{ $slot['bed_index'] }}">
                                                        <td class="px-3 py-2 font-medium text-gray-900">
                                                            {{ $unit['label'] }}
                                                            @if (($unit['people_count'] ?? 1) > 1)
                                                                <span class="block text-xs font-normal text-gray-500">miejsce {{ $slot['bed_index'] }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 text-gray-600">{{ $unit['people_count'] ?? '—' }}</td>
                                                        <td class="px-3 py-2">
                                                            @if ($this->canEditRoomNumbers() && ! empty($unit['id']) && (int) $slot['bed_index'] === 1)
                                                                <input
                                                                    type="text"
                                                                    wire:model.live.debounce.500ms="roomNumbers.{{ $unit['id'] }}"
                                                                    placeholder="np. 214"
                                                                    class="fi-input w-24 rounded-lg border px-2 py-1.5 text-sm"
                                                                />
                                                            @elseif ((int) $slot['bed_index'] === 1)
                                                                <span class="text-gray-700">{{ $unit['room_number'] ?: '—' }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 text-gray-600">{{ ($slot['name'] ?? '') !== '' ? $slot['name'] : '—' }}</td>
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @endforeach

                    <p class="mt-3 text-right text-xs text-gray-500">
                        Suma nocy: {{ number_format((float) ($night['day_total_pln'] ?? 0), 2, ',', ' ') }} PLN
                    </p>
                </x-filament::section>
            @endforeach

            @if ($this->canEditRoomNumbers())
                <div class="flex justify-end">
                    <x-filament::button type="submit" icon="heroicon-o-check">
                        Zapisz numery pokoi
                    </x-filament::button>
                </div>
            @endif
        </form>
    @endif
</x-filament-panels::page>
