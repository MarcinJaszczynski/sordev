<x-filament-panels::page>
    @include('filament.resources.event-resource.components.finance-sub-navigation', ['record' => $record])

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($this::documentFilterOptions() as $key => $label)
            <button
                type="button"
                wire:click="setDocumentFilter('{{ $key }}')"
                @class([
                    'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                    'bg-primary-600 text-white shadow-sm' => $documentFilter === $key,
                    'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $documentFilter !== $key,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="flex flex-col gap-y-8">
        @if ($this->showsVendorInvoices())
            <section wire:key="event-documents-ksef-{{ $documentFilter }}" class="flex flex-col gap-y-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Faktury KSeF / kosztowe</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Faktury dostawców powiązane z imprezą (import KSeF, PDF).
                    </p>
                </div>

                @livewire(
                    \App\Filament\Resources\EventResource\RelationManagers\VendorInvoicesRelationManager::class,
                    [
                        'ownerRecord' => $record,
                        'pageClass' => static::class,
                    ],
                    key('vendor-invoices-'.$record->getKey().'-'.$documentFilter),
                )
            </section>
        @endif

        @if ($this->showsSettlementDocuments())
            <section wire:key="event-documents-settlement-{{ $documentFilter }}" class="flex flex-col gap-y-3">
                @if ($this->showsVendorInvoices())
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Załączniki rozliczenia</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Skany faktur, paragonów i innych dokumentów przypisanych do rozliczenia.
                        </p>
                    </div>
                @endif

                @livewire(
                    \App\Filament\Resources\EventSettlementResource\RelationManagers\DocumentsRelationManager::class,
                    [
                        'ownerRecord' => $settlement,
                        'pageClass' => static::class,
                        'documentTypeFilter' => $this->settlementDocumentTypeFilter(),
                    ],
                    key('settlement-documents-'.$settlement->getKey().'-'.$documentFilter),
                )
            </section>
        @endif
    </div>
</x-filament-panels::page>
