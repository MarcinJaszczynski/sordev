<x-filament-panels::page>
    @include('filament.resources.event-resource.components.documents-sub-navigation', ['record' => $this->record])

    @php
        $relationManagers = $this->getRelationManagers();
        $packages = $this->getPackageCards();
        $offers = $this->getOfferDocuments();
    @endphp

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

            @if($offers->isEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Brak wygenerowanych ofert dla tej imprezy.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700">
                            <tr>
                                <th class="py-2 pr-3 font-medium">Nazwa</th>
                                <th class="py-2 pr-3 font-medium">Status</th>
                                <th class="py-2 pr-3 font-medium">Wysłano</th>
                                <th class="py-2 pr-3 font-medium">Plik</th>
                                <th class="py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($offers as $offer)
                                <tr wire:key="offer-{{ $offer->id }}">
                                    <td class="py-2.5 pr-3 font-medium text-gray-900 dark:text-gray-100">{{ $offer->name }}</td>
                                    <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-300">
                                        {{ \App\Models\EventDocument::$offerStatuses[$offer->offer_status ?? 'draft'] ?? $offer->offer_status }}
                                    </td>
                                    <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-300">
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
                                        @if(blank($offer->offer_sent_at))
                                            <button
                                                type="button"
                                                wire:click="markOfferSent({{ $offer->id }})"
                                                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >Oznacz jako wysłaną</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- 2. Pakiety PDF --}}
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

        {{-- 3. Załączniki --}}
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Załączniki</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Pliki dołączane do pakietów PDF (po akceptacji). Oferty są w sekcji powyżej.
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
