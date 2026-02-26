<x-filament-widgets::widget>
<div class="mt-8 mb-8">
    @php
        $programDays = $programDays ?? collect();
        $dayInsurances = $dayInsurances ?? collect();
        $basePricePerPerson = $basePricePerPerson ?? null;
    @endphp

    @if($programDays->isNotEmpty())
        <h2 class="text-xl font-bold mb-4">Program imprezy</h2>
    @endif

    @foreach($programDays as $day => $points)
        <div class="mb-8">
            <h3 class="text-lg font-semibold mb-3">Dzień {{ $day }}</h3>
            @php
                $insurance = $dayInsurances->get($day)?->insurance ?? null;
                $insurancePrice = $insurance?->price_per_person ?? 0;
                $basePrice = $basePricePerPerson?->price_per_person ?? 0;
                $currency = $basePricePerPerson?->currency->symbol ?? 'zł';
                $totalPrice = $basePrice + $insurancePrice;
            @endphp
            @if($insurance)
                <div class="mb-2 text-sm text-blue-700">
                    Ubezpieczenie: <b>{{ $insurance->name }}</b> ({{ number_format($insurance->price_per_person, 2) }} zł/os.)
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800">
                            <th class="border px-4 py-2 text-left">Nr</th>
                            <th class="border px-4 py-2 text-left">Punkt programu</th>
                            <th class="border px-4 py-2 text-left">Opis do programu</th>
                            <th class="border px-4 py-2 text-left">Uwagi dla biura</th>
                            <th class="border px-4 py-2 text-left">Uwagi dla pilota</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($points as $index => $point)
                            <tr class="{{ $index % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800/50' }}">
                                <td class="border px-4 py-2">{{ $point->pivot->order }}</td>
                                <td class="border px-4 py-2">
                                    <div class="font-medium">{{ $point->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Czas trwania:
                                        {{ $point->duration_hours }}:{{ str_pad($point->duration_minutes, 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                </td>
                                <td class="border px-4 py-2">{{ $point->description }}</td>
                                <td class="border px-4 py-2">{{ $point->office_notes }}</td>
                                <td class="border px-4 py-2">{{ $point->pilot_notes }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="border px-4 py-2 text-center">Brak punktów programu dla tego dnia</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2 text-right text-sm">
                <b>Cena za osobę: {{ number_format($totalPrice, 2) }} {{ $currency }}</b>
                @if($insurance)
                    <span class="text-xs text-blue-700">(w tym ubezpieczenie: {{ number_format($insurancePrice, 2) }} zł)</span>
                @endif
            </div>
        </div>
    @endforeach

    @if($programDays->every(fn($points) => $points->isEmpty()))
        <div class="text-center py-4 text-gray-500">
            Nie dodano jeszcze żadnych punktów programu
        </div>
    @endif
</div>
</x-filament-widgets::widget>