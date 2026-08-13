@php
    use App\Models\EventSettlementDocument;
    use Illuminate\Support\Facades\Storage;

    $documents = $documents ?? collect();
    $editable = $editable ?? true;
    $compact = $compact ?? false;
    $emptyLabel = $emptyLabel ?? null;
@endphp

@if ($documents->isNotEmpty())
    <ul class="mt-2 space-y-2">
        @foreach ($documents as $document)
            @php
                $typeLabel = EventSettlementDocument::$documentTypes[$document->document_type] ?? ($document->document_type ?: 'Dokument');
                $files = collect($document->files ?? [])->filter()->values();
            @endphp
            <li class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900/40" wire:key="settlement-doc-{{ $document->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $document->vendor_name ?: $typeLabel }}
                        </div>
                        <div class="text-[11px] text-gray-500">
                            {{ $typeLabel }}
                            @if ($document->document_number)
                                · nr {{ $document->document_number }}
                            @endif
                            · {{ $files->count() }} plik(ów)
                        </div>
                    </div>
                    @if ($editable)
                        <button
                            type="button"
                            wire:click="deleteDocument({{ $document->id }})"
                            wire:confirm="Usunąć ten dokument i wszystkie jego pliki?"
                            class="text-xs text-red-700 hover:underline dark:text-red-300"
                        >Usuń</button>
                    @endif
                </div>

                @if ($files->isNotEmpty())
                    <ul class="mt-1.5 space-y-1">
                        @foreach ($files as $index => $path)
                            @php
                                $name = basename((string) $path);
                                $url = Storage::disk('public')->url($path);
                                $isImage = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $name);
                            @endphp
                            <li class="flex flex-wrap items-center gap-2 text-xs">
                                <a
                                    href="{{ $url }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 font-medium text-primary-700 underline dark:text-primary-300"
                                >
                                    {{ $isImage ? 'Podgląd' : 'Otwórz' }}: {{ $name }}
                                </a>
                                @if ($editable)
                                    <button
                                        type="button"
                                        wire:click="deleteDocumentFile({{ $document->id }}, {{ $index }})"
                                        wire:confirm="Usunąć ten plik z dokumentu?"
                                        class="text-red-600 hover:underline dark:text-red-300"
                                    >Usuń plik</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-200">Dokument bez wgranego pliku.</p>
                @endif
            </li>
        @endforeach
    </ul>
@elseif ($emptyLabel)
    <p class="mt-1 text-xs text-gray-500">{{ $emptyLabel }}</p>
@endif
