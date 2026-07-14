@php
    $hotelDays = isset($page->hotel_days) ? $page->hotel_days : [];
    $hotelRooms = \App\Models\HotelRoom::orderBy('name')->pluck('name', 'id')->toArray();
@endphp

<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Plan noclegów szablonu</h3>
            <p class="text-xs text-gray-500">Liczba noclegów = dni wycieczki − 1. Wybierz dozwolone typy pokoi per rola.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="saveHotelDays"
                class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                Zapisz noclegi
            </button>
            @if (!empty($hotelDays))
                <button type="button" wire:click="copyToAllDays(0)"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Kopiuj noc 1 na wszystkie
                </button>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto hotel-plan-table-scroll">
        <table class="min-w-full divide-y divide-gray-200 hotel-day-table-row">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nocleg</th>
                    @foreach (['qty' => 'Uczestnicy', 'gratis' => 'Gratis', 'staff' => 'Obsługa', 'driver' => 'Kierowca'] as $role => $label)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</th>
                    @endforeach
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Notatka</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Akcje</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @foreach ($hotelDays as $i => $day)
                    <tr wire:key="hotel-day-{{ $i }}">
                        <td class="px-4 py-4 align-top">
                            <div class="text-sm font-semibold text-gray-900">Noc {{ $day['day'] ?? ($i + 1) }}</div>
                        </td>
                        @foreach (['qty', 'gratis', 'staff', 'driver'] as $role)
                            <td class="px-4 py-4 align-top" wire:key="hotel-day-{{ $i }}-{{ $role }}">
                                @php
                                    $selectedIds = $day["hotel_room_ids_{$role}"] ?? [];
                                    $fieldName = "hotel_days.{$i}.hotel_room_ids_{$role}";
                                @endphp
                                <select
                                    multiple
                                    wire:model.live="{{ $fieldName }}"
                                    class="block w-full min-h-[7rem] min-h-[var(--admin-touch-min)] rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    size="5"
                                >
                                    @foreach ($hotelRooms as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @if (!empty($selectedIds))
                                    <p class="mt-1 text-xs text-gray-500">Wybrano: {{ count($selectedIds) }}</p>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-4 align-top">
                            <textarea
                                wire:model.live.debounce.500ms="hotel_days.{{ $i }}.notes"
                                rows="4"
                                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm"
                                placeholder="Uwagi do tej nocy…"
                            ></textarea>
                        </td>
                        <td class="px-4 py-4 align-top whitespace-nowrap">
                            <button type="button"
                                wire:click="copyToNextDay({{ $i }})"
                                @class([
                                    'inline-flex items-center rounded-md px-3 py-2 text-xs font-medium',
                                    'bg-gray-100 text-gray-400 cursor-not-allowed' => $i >= count($hotelDays) - 1,
                                    'bg-blue-50 text-blue-700 hover:bg-blue-100' => $i < count($hotelDays) - 1,
                                ])
                                @disabled($i >= count($hotelDays) - 1)>
                                Kopiuj → następna
                            </button>
                            <button type="button"
                                wire:click="copyToAllDays({{ $i }})"
                                class="mt-2 inline-flex items-center rounded-md bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 hover:bg-amber-100">
                                Kopiuj → wszystkie
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if (empty($hotelDays))
        <div class="py-8 text-center text-sm text-gray-500">
            Brak noclegów — ustaw liczbę dni wycieczki (min. 2), aby wygenerować plan.
        </div>
    @endif
</div>
