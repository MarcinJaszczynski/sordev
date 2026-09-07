<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->event,
        'kicker' => 'Dokumenty',
    ])

    <div class="portal-card mb-3">
        <div class="portal-card-title"><p>Pakiet pilota</p></div>
        <p class="portal-muted" style="margin:0;">
            Wygenerowany PDF z danymi operacyjnymi i załącznikami oznaczonymi dla pilota.
        </p>
        <div class="mt-3">
            <a
                href="{{ route('pilot.events.pdf', ['event' => $this->event, 'audience' => 'pilot']) }}"
                target="_blank"
                rel="noopener"
                class="portal-btn-primary"
            >
                Otwórz pakiet pilota PDF
            </a>
        </div>
    </div>

    <div class="portal-card mb-3">
        <div class="portal-card-title"><p>Ubezpieczenie</p></div>
        <p class="portal-muted" style="margin:0;">
            Polisa i oryginalna lista ubezpieczonych wgrane przez biuro w Operacje → Ubezpieczenia.
        </p>

        @php
            $insuranceDocs = $this->insuranceDocuments;
        @endphp

        @if($insuranceDocs->isEmpty())
            <p class="mt-4 portal-muted">Brak wgranych plików ubezpieczenia.</p>
        @else
            <ul class="mt-4 divide-y divide-[#E5E3DA] rounded-lg border border-[#E5E3DA]">
                @foreach($insuranceDocs as $document)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium text-[#2C2C2A]">{{ $document['label'] }}</p>
                        </div>
                        <a
                            href="{{ $document['url'] }}"
                            target="_blank"
                            rel="noopener"
                            class="portal-link text-sm"
                        >Otwórz</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="portal-card">
        <div class="portal-card-title"><p>Dokumenty udostępnione pilotowi</p></div>
        <p class="portal-muted" style="margin:0;">
            Tylko pliki tej imprezy oznaczone w biurze jako „Pakiet pilota” (bez odrzuconych). Inne dokumenty imprezy nie są tu widoczne.
        </p>

        @php
            $eventDocs = $this->sharedEventDocuments;
            $settlementDocs = $this->sharedSettlementDocuments;
        @endphp

        @if($eventDocs->isEmpty() && $settlementDocs->isEmpty())
            <p class="mt-4 portal-muted">Brak dodatkowych dokumentów udostępnionych pilotowi.</p>
        @else
            <ul class="mt-4 divide-y divide-[#E5E3DA] rounded-lg border border-[#E5E3DA]">
                @foreach($eventDocs as $document)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium text-[#2C2C2A]">{{ $document->name ?: ($document->original_filename ?: 'Dokument') }}</p>
                            @if(filled($document->notes))
                                <p class="mt-0.5 text-xs text-[#888780]">{{ $document->notes }}</p>
                            @endif
                        </div>
                        @if(filled($document->file_path))
                            <a
                                href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}"
                                target="_blank"
                                rel="noopener"
                                class="portal-link text-sm"
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
                            <p class="font-medium text-[#2C2C2A]">
                                {{ $document->vendor_name ?: (\App\Models\EventSettlementDocument::$documentTypes[$document->document_type] ?? 'Dokument rozliczenia') }}
                            </p>
                            @if($document->document_number)
                                <p class="mt-0.5 text-xs text-[#888780]">nr {{ $document->document_number }}</p>
                            @endif
                        </div>
                        @if($first)
                            <a
                                href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($first) }}"
                                target="_blank"
                                rel="noopener"
                                class="portal-link text-sm"
                            >Otwórz</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-panels::page>
