
<x-filament-panels::page>
    <div class="space-y-6 max-w-xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Import / Export danych</h1>
        <form wire:submit.prevent="import" class="space-y-4">
            <div>
                <label for="model" class="block font-medium text-gray-900 dark:text-gray-200">Wybierz model:</label>
                <select wire:model="selectedModel" id="model" class="filament-forms-select w-full">
                    @foreach ($models as $key => $class)
                        <option value="{{ $key }}">{{ $key }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="importFile" class="block font-medium text-gray-900 dark:text-gray-200">Plik CSV do importu:</label>
                <input type="file" wire:model="importFile" id="importFile" class="filament-forms-input w-full" accept=".csv">
            </div>
            <div class="flex gap-4">
                <button type="submit" class="filament-button filament-button-primary">Importuj</button>
                <button type="button" wire:click="export" class="filament-button filament-button-secondary">Eksportuj</button>
            </div>
            @if ($importResult)
                <div class="mt-2 text-sm text-green-700 dark:text-green-400">{{ $importResult }}</div>
            @endif
        </form>
        <div class="mt-6 text-xs text-gray-600 dark:text-gray-400">
            <p>Import: plik CSV musi mieć nagłówki odpowiadające polom modelu.</p>
            <p>Eksport: pobierze wszystkie rekordy wybranego modelu.</p>
        </div>
    </div>
</x-filament-panels::page>
