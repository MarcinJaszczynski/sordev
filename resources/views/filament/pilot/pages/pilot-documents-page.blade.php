<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->event,
        'kicker' => 'Dokumenty',
    ])

    <div class="client-portal-section mb-4">
        <h2 class="text-base font-semibold text-slate-900">Pakiet pilota</h2>
        <p class="mt-1 text-sm text-slate-600">
            Wygenerowany PDF z danymi operacyjnymi i załącznikami oznaczonymi dla pilota.
        </p>
        <div class="mt-3">
            <a
                href="{{ route('pilot.events.pdf', ['event' => $this->event, 'audience' => 'pilot']) }}"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 rounded-lg bg-[#0663fc] px-4 py-2 text-sm font-semibold text-white hover:bg-[#055bda]"
            >
                Otwórz pakiet pilota PDF
            </a>
        </div>
    </div>

    <div class="client-portal-section mb-4">
        <h2 class="text-base font-semibold text-slate-900">Ubezpieczenie</h2>
        <p class="mt-1 text-sm text-slate-600">
            Polisa i oryginalna lista ubezpieczonych wgrane przez biuro w Operacje → Ubezpieczenia.
        </p>

        @php
            $insuranceDocs = $this->insuranceDocuments;
        @endphp

        @if($insuranceDocs->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Brak wgranych plików ubezpieczenia.</p>
        @else
            <ul class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
                @foreach($insuranceDocs as $document)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium text-slate-900">{{ $document['label'] }}</p>
                        </div>
                        <a
                            href="{{ $document['url'] }}"
                            target="_blank"
                            rel="noopener"
                            class="text-sm font-semibold text-[#0663fc] hover:underline"
                        >Otwórz</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="client-portal-section">
        <h2 class="text-base font-semibold text-slate-900">Dokumenty udostępnione pilotowi</h2>
        <p class="mt-1 text-sm text-slate-600">
            Pliki zaznaczone w biurze jako „Pakiet pilota” (także oczekujące na akceptację — poza odrzuconymi).
        </p>

        @php
            $eventDocs = $this->sharedEventDocuments;
            $settlementDocs = $this->sharedSettlementDocuments;
        @endphp

        @if($eventDocs->isEmpty() && $settlementDocs->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Brak dodatkowych dokumentów udostępnionych pilotowi.</p>
        @else
            <ul class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
                @foreach($eventDocs as $document)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium text-slate-900">{{ $document->name ?: ($document->original_filename ?: 'Dokument') }}</p>
                            @if(filled($document->notes))
                                <p class="mt-0.5 text-xs text-slate-500">{{ $document->notes }}</p>
                            @endif
                        </div>
                        @if(filled($document->file_path))
                            <a
                                href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}"
                                target="_blank"
                                rel="noopener"
                                class="text-sm font-semibold text-[#0663fc] hover:underline"
                            >Otwórz</a>
                        @endif
                    </li>
                @endforeach

                @foreach($settlementDocs as $document)
                    @php
                        $files = collect($document->files ?? [])->filter()->values();
                        $first = $files->first();
                    @endphp
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium text-slate-900">
                                {{ $document->vendor_name ?: (\App\Models\EventSettlementDocument::$documentTypes[$document->document_type] ?? 'Dokument rozliczenia') }}
                            </p>
                            @if($document->document_number)
                                <p class="mt-0.5 text-xs text-slate-500">nr {{ $document->document_number }}</p>
                            @endif
                        </div>
                        @if($first)
                            <a
                                href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($first) }}"
                                target="_blank"
                                rel="noopener"
                                class="text-sm font-semibold text-[#0663fc] hover:underline"
                            >Otwórz</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-panels::page>
