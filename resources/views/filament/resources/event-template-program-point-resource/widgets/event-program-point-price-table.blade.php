<x-filament-widgets::widget>
<div class="overflow-x-auto mt-8">
    <div class="mb-4 flex items-center gap-4">
        <h3 class="text-lg font-bold mb-2">Ceny za osobę w szablonach z tym punktem programu</h3>
        <x-filament::button wire:click="recalculatePrices" color="primary" size="sm">
            Przelicz ceny
        </x-filament::button>
    </div>
    <table class="min-w-full bg-white border border-gray-200">
        <thead>
            <tr>
                <th class="px-4 py-2 border-b">Szablon</th>
                <th class="px-4 py-2 border-b">Wariant ilości</th>
                <th class="px-4 py-2 border-b">Waluta</th>
                <th class="px-4 py-2 border-b">Cena za osobę</th>
            </tr>
        </thead>
        <tbody>
            @forelse($priceRows as $row)
                <tr wire:key="epppt-{{ $loop->index }}">
                    <td class="px-4 py-2 border-b">{{ $row['event_template'] }}</td>
                    <td class="px-4 py-2 border-b">{{ $row['qty'] }}</td>
                    <td class="px-4 py-2 border-b">{{ $row['currency'] }}</td>
                    <td class="px-4 py-2 border-b font-semibold">{{ number_format($row['price_per_person'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-2 text-center text-gray-400">Brak danych do wyświetlenia</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
</x-filament-widgets::widget>
