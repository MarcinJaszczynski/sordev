<x-filament-panels::page>
    @include('filament.resources.event-resource.components.finance-sub-navigation', ['record' => $record])

    <div class="mb-4 rounded-xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
        <p class="font-medium text-gray-900 dark:text-gray-100">Wpłaty uczestników</p>
        <p class="mt-1">Ledger = źródło prawdy. Brak pozycji? Zsynchronizuj umowy albo dodaj wpłatę ręcznie poniżej.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <x-filament::button
                tag="a"
                :href="\App\Filament\Resources\EventResource::getUrl('contracts', ['record' => $record])"
                color="gray"
                size="sm"
                icon="heroicon-o-document-check"
            >
                Przejdź do umów
            </x-filament::button>
            <x-filament::button
                tag="a"
                :href="\App\Filament\Resources\EventResource::getUrl('participants', ['record' => $record])"
                color="gray"
                size="sm"
                icon="heroicon-o-users"
            >
                Lista uczestników
            </x-filament::button>
        </div>
    </div>

    @livewire('event-bank-payment-import-panel', ['eventId' => $record->id], key('bank-import-'.$record->id))

    @php
        $invoiceRequests = $this->invoiceRequests();
    @endphp

    <x-filament::section
        class="mb-6"
        icon="heroicon-o-document-text"
        heading="Wnioski o fakturę"
        description="Wszystkie wnioski powiązane z tą imprezą (WWW, portal, biuro)."
    >
        @if ($invoiceRequests->isEmpty())
            <p class="text-sm text-gray-500">Brak wniosków. Dodaj ręcznie przyciskiem „Wniosek o fakturę” albo poczekaj na klienta.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3">Data</th>
                            <th class="py-2 pr-3">Nabywca</th>
                            <th class="py-2 pr-3">Źródło</th>
                            <th class="py-2 pr-3">Kwota</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoiceRequests as $req)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-3 whitespace-nowrap">{{ $req->created_at?->format('d.m.Y H:i') }}</td>
                                <td class="py-2 pr-3">
                                    <div class="font-medium">{{ $req->company_name }}</div>
                                    <div class="text-xs text-gray-500">{{ $req->invoice_email }}</div>
                                </td>
                                <td class="py-2 pr-3">{{ $req->source_label }}</td>
                                <td class="py-2 pr-3">
                                    {{ $req->amount !== null ? \App\Support\MoneyFormatter::format((float) $req->amount, 'PLN') : '—' }}
                                </td>
                                <td class="py-2">{{ $req->status_label }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                Pełna obsługa:
                <a href="{{ \App\Filament\Pages\ClientInvoiceRequestsInboxPage::getUrl() }}" class="text-primary-600 underline">Skrzynka wniosków</a>
            </p>
        @endif
    </x-filament::section>

    @php
        $relationManagers = $this->getRelationManagers();
        $managerLivewireProperties = [
            'ownerRecord' => $settlement,
            'pageClass' => static::class,
            'focusPaymentId' => $this->focusPaymentId,
        ];
    @endphp

    <div class="flex flex-col gap-y-8">
        @foreach ($relationManagers as $managerKey => $manager)
            @php
                $normalizedManagerClass = $manager instanceof \Filament\Resources\RelationManagers\RelationManagerConfiguration
                    ? $manager->relationManager
                    : $manager;
                $managerProperties = $manager instanceof \Filament\Resources\RelationManagers\RelationManagerConfiguration
                    ? [...$normalizedManagerClass::getDefaultProperties(), ...$manager->getProperties()]
                    : $normalizedManagerClass::getDefaultProperties();
            @endphp

            <div wire:key="event-finance-participant-payments-{{ $managerKey }}" class="flex flex-col gap-y-4">
                @livewire(
                    $normalizedManagerClass,
                    [...$managerLivewireProperties, ...$managerProperties],
                    key($normalizedManagerClass.'-'.$managerKey.'-'.$settlement->getKey()),
                )
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
