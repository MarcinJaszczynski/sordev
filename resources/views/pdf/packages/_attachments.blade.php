@php
    $names = collect($attachedFiles ?? [])
        ->pluck('base_name')
        ->filter()
        ->unique()
        ->values();
@endphp
<div class="files">
    <div class="files-title">Załączniki dołączone do pobrania (ZIP)</div>
    @if($names->isEmpty())
        <p class="files-empty">Brak plików przypisanych do tego pakietu (zaznacz „Dołącz do pakietów PDF” w zakładce Dokumenty i ustaw status Zaakceptowany).</p>
    @else
        <ul class="files-list">
            @foreach($names as $n)
                <li>{{ $n }}</li>
            @endforeach
        </ul>
        <p class="files-note">Pełne pliki znajdują się w archiwum ZIP przy pobraniu pakietu (gdy są załączniki).</p>
    @endif
</div>
