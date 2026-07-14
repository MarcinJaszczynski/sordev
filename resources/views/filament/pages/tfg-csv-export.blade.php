<x-filament-panels::page>
    <div class="space-y-6 max-w-4xl">
        <x-filament::section>
            <x-slot name="heading">Przygotuj plik CSV (Wykaz umów)</x-slot>
            <x-slot name="description">
                Główny kanał przekazywania umów do TFG. Wybierz typ operacji i zakres umów, sprawdź poprawność,
                a następnie pobierz gotowy plik CSV (UTF-8, separator średnik) i wgraj go w Portalu TFG:
                <strong>Wykaz umów &rarr; Dodaj umowy z pliku CSV / JSON</strong>.
            </x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-950 dark:text-white">Typ operacji</label>
                    <select wire:model.live.debounce.500ms="operation" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700">
                        @foreach ($this->operationOptions() as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-950 dark:text-white">Impreza (opcjonalnie)</label>
                    <select wire:model.live.debounce.500ms="eventId" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700">
                        <option value="">— wszystkie —</option>
                        @foreach ($this->eventOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-950 dark:text-white">Data umowy od</label>
                    <input type="date" wire:model.live.debounce.500ms="dateFrom" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700" />
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-950 dark:text-white">Data umowy do</label>
                    <input type="date" wire:model.live.debounce.500ms="dateTo" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700" />
                </div>

                <div class="sm:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-950 dark:text-white">
                        <input type="checkbox" wire:model.live.debounce.500ms="onlyNotSynced" class="rounded border-gray-300" />
                        Tylko umowy jeszcze nieprzekazane do TFG (bez statusu)
                    </label>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-filament::button color="gray" wire:click="preview" wire:loading.attr="disabled" wire:target="preview">
                    <span wire:loading.remove wire:target="preview">Sprawdź i podejrzyj</span>
                    <span wire:loading wire:target="preview">Sprawdzanie…</span>
                </x-filament::button>

                <x-filament::button icon="heroicon-o-arrow-down-tray" wire:click="download" wire:loading.attr="disabled" wire:target="download">
                    <span wire:loading.remove wire:target="download">Pobierz CSV</span>
                    <span wire:loading wire:target="download">Generowanie…</span>
                </x-filament::button>
            </div>
        </x-filament::section>

        @if ($previewed)
            <x-filament::section>
                <x-slot name="heading">Wynik sprawdzenia</x-slot>

                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="font-medium text-gray-500">Umowy w zakresie</dt>
                        <dd class="text-lg font-semibold">{{ $previewContracts }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Wiersze w pliku CSV</dt>
                        <dd class="text-lg font-semibold">{{ $previewRows }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Umowy z błędami</dt>
                        <dd class="text-lg font-semibold {{ count($previewErrors) ? 'text-danger-600' : 'text-success-600' }}">
                            {{ count($previewErrors) }}
                        </dd>
                    </div>
                </dl>

                @if ($fileErrors !== [])
                    <div class="mt-4 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10">
                        @foreach ($fileErrors as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                @if ($previewErrors !== [])
                    <div class="mt-4 space-y-3">
                        @foreach ($previewErrors as $row)
                            <div class="rounded-lg border border-danger-200 p-3 dark:border-danger-500/30">
                                <div class="text-sm font-semibold text-danger-700">Umowa {{ $row['number'] }}</div>
                                <ul class="mt-1 list-disc pl-5 text-sm text-gray-700 dark:text-gray-300">
                                    @foreach ($row['errors'] as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4 rounded-lg bg-success-50 p-3 text-sm text-success-700 dark:bg-success-500/10">
                        Wszystkie umowy w zakresie są poprawne. Możesz pobrać plik CSV.
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
