<x-filament-panels::page>
    @include('filament.resources.event-resource.components.documents-sub-navigation', ['record' => $this->record])

    @php
        $relationManagers = $this->getRelationManagers();
        $packages = $this->getPackageCards();
        $offers = $this->getOfferDocuments();
        $settlementRows = $this->getSettlementDocumentRows();
        $settlementDocumentsUrl = \Illuminate\Support\Facades\Schema::hasTable('event_settlement_documents')
            ? \App\Filament\Resources\EventResource::getUrl('finance-settlement-documents', ['record' => $this->record->id])
            : null;
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a
            href="{{ route('admin.events.invoices.pdf', ['event' => $this->record->id]) }}"
            target="_blank"
            class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
        >
            Wszystkie faktury (PDF)
        </a>
        @if ($settlementDocumentsUrl)
            <a
                href="{{ $settlementDocumentsUrl }}"
                class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
            >
                Dokumenty rozliczenia
            </a>
        @endif
    </div>

    <div class="space-y-6">
        {{-- 1. Oferta dla klienta --}}
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Oferta dla klienta</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Generujesz DOCX z danych imprezy. Status odpowiedzi klienta prowadzisz tutaj.
                    </p>
                </div>
                <a
                    href="{{ route('admin.events.offer.word', ['event' => $this->record->id]) }}"
                    target="_blank"
                    class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-danger-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-danger-500"
                >
                    Generuj ofertę DOCX
                </a>
            </div>

            @if (! $this->hasOfferDocuments())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Brak wygenerowanych ofert dla tej imprezy.</p>
            @else
                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Dodano od</label>
                        <input
                            type="date"
                            wire:model.live="offersCreatedFrom"
                            class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Dodano do</label>
                        <input
                            type="date"
                            wire:model.live="offersCreatedUntil"
                            class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                    </div>
                    @if (filled($this->offersCreatedFrom) || filled($this->offersCreatedUntil))
                        <button
                            type="button"
                            wire:click="resetOffersDateFilter"
                            class="rounded-md px-2.5 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        >Wyczyść filtr</button>
                    @endif
                </div>

                @if ($offers->isEmpty())
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Brak ofert w wybranym zakresie dat.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700">
                                <tr>
                                    <th class="py-2 pr-3 font-medium">Nazwa</th>
                                    <th class="py-2 pr-3 font-medium">Status</th>
                                    <th class="py-2 pr-3 font-medium">
                                        <button type="button" wire:click="sortOffersBy('created_at')" class="inline-flex items-center gap-1 uppercase hover:text-gray-800 dark:hover:text-gray-200">
                                            Dodano
                                            @if ($this->offersSort === 'created_at')
                                                <span aria-hidden="true">{{ $this->offersSortDirection === 'asc' ? '↑' : '↓' }}</span>
                                            @endif
                                        </button>
                                    </th>
                                    <th class="py-2 pr-3 font-medium">
                                        <button type="button" wire:click="sortOffersBy('updated_at')" class="inline-flex items-center gap-1 uppercase hover:text-gray-800 dark:hover:text-gray-200">
                                            Zmieniono
                                            @if ($this->offersSort === 'updated_at')
                                                <span aria-hidden="true">{{ $this->offersSortDirection === 'asc' ? '↑' : '↓' }}</span>
                                            @endif
                                        </button>
                                    </th>
                                    <th class="py-2 pr-3 font-medium">
                                        <button type="button" wire:click="sortOffersBy('offer_sent_at')" class="inline-flex items-center gap-1 uppercase hover:text-gray-800 dark:hover:text-gray-200">
                                            Wysłano
                                            @if ($this->offersSort === 'offer_sent_at')
                                                <span aria-hidden="true">{{ $this->offersSortDirection === 'asc' ? '↑' : '↓' }}</span>
                                            @endif
                                        </button>
                                    </th>
                                    <th class="py-2 pr-3 font-medium">Plik</th>
                                    <th class="py-2 font-medium text-right">Akcje</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($offers as $offer)
                                    <tr wire:key="offer-{{ $offer->id }}">
                                        <td class="py-2.5 pr-3 font-medium text-gray-900 dark:text-gray-100">{{ $offer->name }}</td>
                                        <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-300">
                                            {{ \App\Models\EventDocument::$offerStatuses[$offer->offer_status ?? 'draft'] ?? $offer->offer_status }}
                                        </td>
                                        <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            {{ $offer->created_at?->format('d.m.Y H:i') ?: '—' }}
                                        </td>
                                        <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            {{ $offer->updated_at?->format('d.m.Y H:i') ?: '—' }}
                                        </td>
                                        <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            {{ $offer->offer_sent_at?->format('d.m.Y H:i') ?: '—' }}
                                        </td>
                                        <td class="py-2.5 pr-3">
                                            @if($offer->file_path)
                                                <a
                                                    href="{{ \App\Support\StoragePath::publicUrl($offer->file_path) }}"
                                                    target="_blank"
                                                    class="text-primary-600 hover:underline dark:text-primary-400"
                                                >Pobierz</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="py-2.5 text-right">
                                            <div class="inline-flex flex-wrap items-center justify-end gap-3">
                                                @if(blank($offer->offer_sent_at))
                                                    <button
                                                        type="button"
                                                        wire:click="markOfferSent({{ $offer->id }})"
                                                        class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                    >Oznacz jako wysłaną</button>
                                                @endif
                                                <button
                                                    type="button"
                                                    wire:click="deleteOfferDocument({{ $offer->id }})"
                                                    wire:confirm="Na pewno usunąć tę ofertę? Tej operacji nie da się cofnąć."
                                                    class="text-sm font-medium text-danger-600 hover:underline dark:text-danger-400"
                                                >Usuń</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </section>

        {{-- 2. Dokumenty z programu / rozliczenia --}}
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Dokumenty z programu i rozliczenia</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Faktury i dowody dodane przy punktach programu, hotelach i transporcie.
                    </p>
                </div>
                @if ($settlementDocumentsUrl)
                    <a
                        href="{{ $settlementDocumentsUrl }}"
                        class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                    >Otwórz w Finansach</a>
                @endif
            </div>

            @if (! $this->hasSettlementDocuments())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Brak dokumentów rozliczenia dla tej imprezy.</p>
            @else
                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Dodano od</label>
                        <input
                            type="date"
                            wire:model.live="settlementCreatedFrom"
                            class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Dodano do</label>
                        <input
                            type="date"
                            wire:model.live="settlementCreatedUntil"
                            class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                    </div>
                    @if (filled($this->settlementCreatedFrom) || filled($this->settlementCreatedUntil))
                        <button
                            type="button"
                            wire:click="resetSettlementDateFilter"
                            class="rounded-md px-2.5 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        >Wyczyść filtr</button>
                    @endif
                </div>

                @if ($settlementRows->isEmpty())
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Brak dokumentów w wybranym zakresie dat.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[820px] text-left text-sm">
                            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700">
                                <tr>
                                    <th class="py-2 pr-3 font-medium">Typ</th>
                                    <th class="py-2 pr-3 font-medium">Numer</th>
                                    <th class="py-2 pr-3 font-medium">Pozycja</th>
                                    <th class="py-2 pr-3 font-medium">Pliki</th>
                                    <th class="py-2 pr-3 font-medium">
                                        <button type="button" wire:click="sortSettlementDocumentsBy('created_at')" class="inline-flex items-center gap-1 uppercase hover:text-gray-800 dark:hover:text-gray-200">
                                            Dodano
                                            @if ($this->settlementSort === 'created_at')
                                                <span aria-hidden="true">{{ $this->settlementSortDirection === 'asc' ? '↑' : '↓' }}</span>
                                            @endif
                                        </button>
                                    </th>
                                    <th class="py-2 pr-3 font-medium">
                                        <button type="button" wire:click="sortSettlementDocumentsBy('updated_at')" class="inline-flex items-center gap-1 uppercase hover:text-gray-800 dark:hover:text-gray-200">
                                            Zmieniono
                                            @if ($this->settlementSort === 'updated_at')
                                                <span aria-hidden="true">{{ $this->settlementSortDirection === 'asc' ? '↑' : '↓' }}</span>
                                            @endif
                                        </button>
                                    </th>
                                    <th class="py-2 font-medium text-right">Akcje</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($settlementRows as $row)
                                    <tr wire:key="settlement-doc-{{ $row['id'] }}">
                                        <td class="py-2.5 pr-3">
                                            <span class="epp-doc-badge epp-doc-badge--has">{{ $row['badge_label'] }}</span>
                                            <span class="sr-only">{{ $row['type_label'] }}</span>
                                        </td>
                                        <td class="py-2.5 pr-3 text-gray-700 dark:text-gray-200">
                                            {{ $row['number'] ?: '—' }}
                                        </td>
                                        <td class="py-2.5 pr-3 text-gray-700 dark:text-gray-200">
                                            @if ($row['linked_labels'] === [])
                                                —
                                            @else
                                                {{ implode(', ', $row['linked_labels']) }}
                                            @endif
                                        </td>
                                        <td class="py-2.5 pr-3">
                                            @forelse ($row['files'] as $file)
                                                <a
                                                    href="{{ $file['url'] }}"
                                                    target="_blank"
                                                    class="mr-2 text-primary-600 hover:underline dark:text-primary-400"
                                                >{{ \Illuminate\Support\Str::limit($file['name'], 28) }}</a>
                                            @empty
                                                —
                                            @endforelse
                                        </td>
                                        <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            {{ $row['created_at'] ?: '—' }}
                                        </td>
                                        <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            {{ $row['updated_at'] ?: '—' }}
                                        </td>
                                        <td class="py-2.5 text-right">
                                            <button
                                                type="button"
                                                wire:click="deleteSettlementDocument({{ $row['id'] }})"
                                                wire:confirm="Na pewno usunąć ten dokument rozliczenia? Tej operacji nie da się cofnąć."
                                                class="text-sm font-medium text-danger-600 hover:underline dark:text-danger-400"
                                            >Usuń</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </section>

        {{-- 3. Pakiety PDF --}}
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Pakiety PDF</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Pilot, hotel, kierowca i teczka — osobno albo wszystkie razem. Edycja jak przy umowach: dane imprezy, poprawki, snapshot lub wgrany PDF.
                    </p>
                </div>
                <a
                    href="{{ route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'all']) }}"
                    target="_blank"
                    class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
                >
                    Pobierz wszystkie (ZIP)
                </a>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($packages as $card)
                    <div
                        wire:key="package-{{ $card['audience'] }}"
                        class="flex flex-col rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                    >
                        <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $card['label'] }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $card['edit_mode_label'] }} · {{ $card['status_label'] }}
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                wire:click="openEditPackage('{{ $card['audience'] }}')"
                                class="rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-800 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
                            >Edytuj</button>
                            <a
                                href="{{ $card['preview_url'] }}"
                                target="_blank"
                                class="rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-800 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
                            >Podgląd</a>
                            <a
                                href="{{ $card['download_url'] }}"
                                target="_blank"
                                class="rounded-md bg-primary-50 px-2.5 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-100 dark:bg-primary-950 dark:text-primary-300"
                            >Pobierz</a>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                wire:click="refreshPackage('{{ $card['audience'] }}')"
                                wire:confirm="Przywrócić pakiet do danych imprezy (bez zamrożenia / uploadu)?"
                                class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"
                            >Odśwież</button>
                            <button
                                type="button"
                                wire:click="freezePackage('{{ $card['audience'] }}')"
                                class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"
                            >Zamróź</button>
                            <button
                                type="button"
                                wire:click="finalizePackage('{{ $card['audience'] }}')"
                                class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"
                            >Finalizuj</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 4. Załączniki ręczne --}}
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Załączniki ręczne</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Pliki dodane tu ręcznie (pakiety PDF). Faktury z programu są w sekcji powyżej.
                </p>
            </div>

            @if (count($relationManagers))
                <x-filament-panels::resources.relation-managers
                    :active-manager="array_key_first($relationManagers)"
                    :managers="$relationManagers"
                    :owner-record="$record"
                    :page-class="static::class"
                />
            @endif
        </section>
    </div>
</x-filament-panels::page>
