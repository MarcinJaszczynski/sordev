<x-filament-widgets::widget>
<div class="overflow-x-auto">
    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">Kosztorys imprezy</h3>
        <x-filament::button wire:click="refreshCalculations" color="primary" size="sm">
            Odśwież kalkulacje
        </x-filament::button>
    </div>

    @if($record)
        <!-- Informacje o imprezie -->
        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <h4 class="text-md font-semibold mb-3">Informacje o imprezie</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="font-medium text-gray-600 dark:text-gray-400">Nazwa:</span>
                    <p class="font-semibold">{{ $calculations['event_data']['name'] ?? 'Brak' }}</p>
                </div>
                <div>
                    <span class="font-medium text-gray-600 dark:text-gray-400">Klient:</span>
                    <p>{{ $calculations['event_data']['client_name'] ?? 'Brak' }}</p>
                </div>
                <div>
                    <span class="font-medium text-gray-600 dark:text-gray-400">Liczba uczestników:</span>
                    <p class="font-semibold">{{ $calculations['event_data']['participant_count'] ?? 0 }}</p>
                </div>
                <div>
                    <span class="font-medium text-gray-600 dark:text-gray-400">Status:</span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                        @if($calculations['event_data']['status'] === 'draft') bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200
                        @elseif($calculations['event_data']['status'] === 'confirmed') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                        @elseif($calculations['event_data']['status'] === 'in_progress') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                        @elseif($calculations['event_data']['status'] === 'completed') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                        @elseif($calculations['event_data']['status'] === 'cancelled') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                        @else bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 @endif">
                        {{ match($calculations['event_data']['status'] ?? '') {
                            'draft' => 'Szkic',
                            'confirmed' => 'Potwierdzona',
                            'in_progress' => 'W trakcie',
                            'completed' => 'Zakończona',
                            'cancelled' => 'Anulowana',
                            default => ucfirst($calculations['event_data']['status'] ?? 'Nieznany')
                        } }}
                    </span>
                </div>
                <div>
                    <span class="font-medium text-gray-600 dark:text-gray-400">Data rozpoczęcia:</span>
                    <p>{{ $calculations['event_data']['start_date'] ? \Carbon\Carbon::parse($calculations['event_data']['start_date'])->format('d.m.Y') : 'Brak' }}</p>
                </div>
                <div>
                    <span class="font-medium text-gray-600 dark:text-gray-400">Data zakończenia:</span>
                    <p>{{ $calculations['event_data']['end_date'] ? \Carbon\Carbon::parse($calculations['event_data']['end_date'])->format('d.m.Y') : 'Jednodniowa' }}</p>
                </div>
                <div>
                    <span class="font-medium text-gray-600 dark:text-gray-400">Szablon:</span>
                    <p>{{ $calculations['event_data']['template_name'] ?? 'Brak' }}</p>
                </div>
                <div>
                    <span class="font-medium text-gray-600 dark:text-gray-400">Liczba dni:</span>
                    <p class="font-semibold">{{ $calculations['days_count'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <!-- Podsumowanie kosztów -->
        <div class="mb-6">
            <h4 class="text-md font-semibold mb-3">Podsumowanie kosztów</h4>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Koszt programu</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($calculations['total_program_cost'] ?? 0, 2) }} PLN</p>
                </div>
                <div class="text-center p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Koszt transportu</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($transportCost ?? 0, 2) }} PLN</p>
                </div>
                <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Koszt całkowity</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($calculations['total_cost'] ?? 0, 2) }} PLN</p>
                </div>
                <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Koszt na osobę</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($calculations['cost_per_person'] ?? 0, 2) }} PLN</p>
                </div>
                <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Punkty w kalkulacji</p>
                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $calculations['calculation_points'] ?? 0 }}/{{ $calculations['total_points'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <!-- Szczegóły transportu -->
        @if($record->bus)
            <div class="mb-6 p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                <h4 class="text-md font-semibold mb-3">Szczegóły transportu</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-600 dark:text-gray-400">Autokar:</span>
                        <p>{{ $calculations['event_data']['bus_name'] ?? 'Brak' }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-gray-600 dark:text-gray-400">Transfer (km):</span>
                        <p>{{ $calculations['event_data']['transfer_km'] ?? 0 }} km</p>
                    </div>
                    <div>
                        <span class="font-medium text-gray-600 dark:text-gray-400">Program (km):</span>
                        <p>{{ $calculations['event_data']['program_km'] ?? 0 }} km</p>
                    </div>
                    <div>
                        <span class="font-medium text-gray-600 dark:text-gray-400">Koszt transportu:</span>
                        <p class="font-semibold">{{ number_format($transportCost ?? 0, 2) }} PLN</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Kalkulacje dla różnych wariantów uczestników -->
        @if(count($detailedCalculations) > 0)
            <div class="mb-6">
                <h4 class="text-md font-semibold mb-3">Kalkulacje dla różnych wariantów uczestników</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-4 py-3 border-b text-left">Wariant</th>
                                <th class="px-4 py-3 border-b text-right">Koszt programu</th>
                                <th class="px-4 py-3 border-b text-right">Koszt transportu</th>
                                <th class="px-4 py-3 border-b text-right">Koszt całkowity</th>
                                <th class="px-4 py-3 border-b text-right">Koszt na osobę</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($detailedCalculations as $calc)
                                <tr class="@if($calc['qty'] == $calculations['event_data']['participant_count']) bg-blue-50 dark:bg-blue-900/20 font-semibold @endif">
                                    <td class="px-4 py-3 border-b">{{ $calc['name'] }}</td>
                                    <td class="px-4 py-3 border-b text-right">{{ number_format($calc['program_cost'], 2) }} PLN</td>
                                    <td class="px-4 py-3 border-b text-right">{{ number_format($calc['transport_cost'], 2) }} PLN</td>
                                    <td class="px-4 py-3 border-b text-right font-bold">{{ number_format($calc['total_cost'], 2) }} PLN</td>
                                    <td class="px-4 py-3 border-b text-right font-bold">{{ number_format($calc['cost_per_person'], 2) }} PLN</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                    * Wiersz podświetlony na niebiesko odpowiada bieżącej liczbie uczestników ({{ $calculations['event_data']['participant_count'] ?? 0 }})
                </p>
            </div>
        @endif

        <!-- Koszty według dni -->
        @if(count($costsByDay) > 0)
            <div class="mb-6">
                <h4 class="text-md font-semibold mb-3">Podział kosztów według dni</h4>
                <div class="grid gap-4">
                    @foreach($costsByDay as $day => $dayData)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-3">
                                <h5 class="font-medium text-lg">Dzień {{ $day }}</h5>
                                <div class="text-right">
                                    <p class="text-lg font-bold">{{ number_format($dayData['total_cost'], 2) }} PLN</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $dayData['calculation_points'] }}/{{ $dayData['points_count'] }} punktów w kalkulacji</p>
                                </div>
                            </div>
                            
                            @if($dayData['points']->count() > 0)
                                <div class="space-y-2">
                                    @foreach($dayData['points'] as $point)
                                        <div class="flex justify-between items-center p-2 
                                            @if($point->include_in_calculation) bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-700 
                                            @else bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 @endif 
                                            rounded">
                                            <div class="flex-1">
                                                <p class="font-medium">{{ $point->templatePoint->name ?? 'Brak nazwy' }}</p>
                                                <div class="flex gap-2 text-xs mt-1">
                                                    <span class="text-gray-600 dark:text-gray-400">Kolejność: {{ $point->order }}</span>
                                                    <span class="text-gray-600 dark:text-gray-400">Ilość: {{ $point->quantity }}</span>
                                                    @if($point->include_in_program)
                                                        <span class="bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 px-2 py-0.5 rounded">Program</span>
                                                    @endif
                                                    @if($point->include_in_calculation)
                                                        <span class="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 px-2 py-0.5 rounded">Kalkulacja</span>
                                                    @endif
                                                    @if(!$point->active)
                                                        <span class="bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 px-2 py-0.5 rounded">Nieaktywny</span>
                                                    @endif
                                                </div>
                                                @if($point->notes)
                                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 italic">{{ $point->notes }}</p>
                                                @endif
                                            </div>
                                            <div class="text-right ml-4">
                                                <p class="font-bold">{{ number_format($point->total_price, 2) }} PLN</p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ number_format($point->unit_price, 2) }} PLN/szt</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Tabela wszystkich punktów programu -->
        @if(count($programPoints) > 0)
            <div>
                <h4 class="text-md font-semibold mb-3">Wszystkie punkty programu ({{ count($programPoints) }})</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-4 py-3 border-b text-left">Dzień</th>
                                <th class="px-4 py-3 border-b text-left">Kolejność</th>
                                <th class="px-4 py-3 border-b text-left">Punkt programu</th>
                                <th class="px-4 py-3 border-b text-center">Ilość</th>
                                <th class="px-4 py-3 border-b text-right">Cena jedn.</th>
                                <th class="px-4 py-3 border-b text-right">Cena całk.</th>
                                <th class="px-4 py-3 border-b text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($programPoints as $point)
                                <tr class="@if(!$point->active) opacity-50 @endif">
                                    <td class="px-4 py-3 border-b">{{ $point->day }}</td>
                                    <td class="px-4 py-3 border-b">{{ $point->order }}</td>
                                    <td class="px-4 py-3 border-b">
                                        <div>
                                            <p class="font-medium">{{ $point->templatePoint->name ?? 'Brak nazwy' }}</p>
                                            @if($point->notes)
                                                <p class="text-sm text-gray-600 dark:text-gray-400 italic">{{ $point->notes }}</p>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 border-b text-center">{{ $point->quantity }}</td>
                                    <td class="px-4 py-3 border-b text-right">{{ number_format($point->unit_price, 2) }} PLN</td>
                                    <td class="px-4 py-3 border-b text-right font-bold">{{ number_format($point->total_price, 2) }} PLN</td>
                                    <td class="px-4 py-3 border-b text-center">
                                        <div class="flex flex-wrap gap-1 justify-center">
                                            @if($point->include_in_program)
                                                <span class="text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 px-2 py-1 rounded">Program</span>
                                            @endif
                                            @if($point->include_in_calculation)
                                                <span class="text-xs bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 px-2 py-1 rounded">Kalkulacja</span>
                                            @endif
                                            @if(!$point->active)
                                                <span class="text-xs bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 px-2 py-1 rounded">Nieaktywny</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 dark:bg-gray-800 font-bold">
                                <td colspan="5" class="px-4 py-3 border-t text-right">Suma w kalkulacji:</td>
                                <td class="px-4 py-3 border-t text-right text-lg">{{ number_format($calculations['total_cost'] ?? 0, 2) }} PLN</td>
                                <td class="px-4 py-3 border-t"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
        
    <!-- Inline price edit modal (Livewire bound) -->
    <div x-data="{ editing: @entangle('editingPrice') }"
         x-cloak
         x-show="editing !== null"
         class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black opacity-50" @click="editing = null"></div>
        <div class="bg-white dark:bg-gray-900 rounded-lg p-6 z-50 w-full max-w-2xl">
                <h3 class="text-lg font-semibold mb-4">Edytuj cenę</h3>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">Cena za osobę (PLN)</label>
                        <input type="number" step="0.01" wire:model.defer="editingPrice.price_per_person" class="w-full mt-1 rounded border-gray-200 dark:border-gray-700">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Koszt transportu (PLN)</label>
                        <input type="number" step="0.01" wire:model.defer="editingPrice.transport_cost" class="w-full mt-1 rounded border-gray-200 dark:border-gray-700">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Cena z podatkiem (PLN)</label>
                        <input type="number" step="0.01" wire:model.defer="editingPrice.price_with_tax" class="w-full mt-1 rounded border-gray-200 dark:border-gray-700">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Rozbicie VAT (JSON)</label>
                        <input type="text" wire:model.defer="editingPrice.tax_breakdown" placeholder='{"vat": 23, "amount": 100}' class="w-full mt-1 rounded border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 mt-1">Podaj JSON z rozbiciem VAT lub zostaw puste.</p>
                    </div>
                </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <x-filament::button color="secondary" size="sm" @click="editing = null">Anuluj</x-filament::button>
                        <x-filament::button color="primary" size="sm" wire:click.prevent="saveEditingPrice" x-bind:disabled="editing === null">Zapisz</x-filament::button>
                    </div>
            </div>
        </div>
    @else
        <div class="text-center py-8">
            <p class="text-gray-600 dark:text-gray-400">Brak danych do wyświetlenia</p>
        </div>
    @endif
</div>
</x-filament-widgets::widget>
