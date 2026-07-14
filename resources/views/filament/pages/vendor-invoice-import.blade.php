<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['tabs' => $this->getNavigationTabs()])

    <form wire:submit.prevent="runImport" class="space-y-6 max-w-3xl">
        <x-filament::section>
            <x-slot name="heading">Pliki KSeF</x-slot>
            <x-slot name="description">
                Możesz wgrać CSV, XML i zbiorczy PDF jednocześnie. Dane z CSV/XML trafią do rejestru,
                a PDF zostanie automatycznie rozdzielony i dopięty do dopasowanych faktur.
            </x-slot>

            <div class="space-y-5">
                <div>
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="csvFile">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">CSV KSeF</span>
                        <span class="text-xs text-gray-500">(opcjonalnie)</span>
                    </label>
                    <input
                        id="csvFile"
                        type="file"
                        wire:model.live.debounce.500ms="csvFile"
                        accept=".csv,text/csv,text/plain"
                        class="mt-1 block w-full text-sm text-gray-950 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100 dark:text-white"
                    />
                    <div wire:loading wire:target="csvFile" class="mt-1 text-xs text-gray-500">Przesyłanie CSV…</div>
                    @error('csvFile') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="xmlFile">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">XML KSeF</span>
                        <span class="text-xs text-gray-500">(opcjonalnie)</span>
                    </label>
                    <input
                        id="xmlFile"
                        type="file"
                        wire:model.live.debounce.500ms="xmlFile"
                        accept=".xml,text/xml,application/xml"
                        class="mt-1 block w-full text-sm text-gray-950 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100 dark:text-white"
                    />
                    <div wire:loading wire:target="xmlFile" class="mt-1 text-xs text-gray-500">Przesyłanie XML…</div>
                    @error('xmlFile') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="pdfFile">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">Zbiorczy PDF</span>
                        <span class="text-xs text-gray-500">(opcjonalnie)</span>
                    </label>
                    <input
                        id="pdfFile"
                        type="file"
                        wire:model.live.debounce.500ms="pdfFile"
                        accept=".pdf,application/pdf"
                        class="mt-1 block w-full text-sm text-gray-950 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100 dark:text-white"
                    />
                    <div wire:loading wire:target="pdfFile" class="mt-1 text-xs text-gray-500">Przesyłanie PDF…</div>
                    @error('pdfFile') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-filament::section>

        <div class="flex items-center gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runImport,csvFile,xmlFile,pdfFile">
                <span wire:loading.remove wire:target="runImport,csvFile,xmlFile,pdfFile">Importuj</span>
                <span wire:loading wire:target="runImport,csvFile,xmlFile,pdfFile">Przetwarzanie…</span>
            </x-filament::button>
            <p class="text-xs text-gray-500">Wymagany co najmniej jeden plik.</p>
        </div>
    </form>

    @if ($lastResult)
        <x-filament::section class="mt-6" heading="Wynik importu">
            <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div>
                    <dt class="font-medium text-gray-500">Zaimportowano faktur</dt>
                    <dd class="text-lg font-semibold">{{ $lastResult['imported'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Dopasowano</dt>
                    <dd class="text-lg font-semibold">{{ $lastResult['matched'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Do opracowania</dt>
                    <dd class="text-lg font-semibold">{{ $lastResult['unmatched'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">PDF dopięte</dt>
                    <dd class="text-lg font-semibold">{{ $lastResult['pdf_attached'] ?? 0 }}</dd>
                </div>
            </dl>
            @if (! empty($lastResult['errors']))
                <div class="mt-4">
                    <p class="font-medium text-danger-600">Ostrzeżenia / błędy:</p>
                    <ul class="mt-2 max-h-48 list-disc overflow-y-auto pl-5 text-sm">
                        @foreach ($lastResult['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
