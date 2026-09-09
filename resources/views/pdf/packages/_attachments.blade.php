@php
    $files = collect($attachedFiles ?? [])->values();
    $linksExpireAt = $packageFileLinksExpireAt ?? $files->first()['link_expires_at'] ?? null;
@endphp
<div class="files">
    <div class="files-title">Załączniki — otwórz na telefonie</div>
    @if($files->isEmpty())
        <p class="files-empty">Brak plików przypisanych do tego pakietu (zaznacz odbiorcę w Dokumentach i status Zaakceptowany).</p>
    @else
        <ul class="files-list">
            @foreach($files as $file)
                <li>
                    <span class="file-name">{{ $file['document_label'] ?? $file['base_name'] }}</span>
                    @if(! empty($file['document_type']))
                        <span class="file-meta">{{ $file['document_type'] }}</span>
                    @endif
                    @if(! empty($file['description']))
                        <span class="file-desc">{{ $file['description'] }}</span>
                    @endif
                    @if(! empty($file['download_url']))
                        <a class="file-link" href="{{ $file['download_url'] }}">Otwórz plik: {{ $file['base_name'] }}</a>
                    @else
                        <span class="file-meta">{{ $file['base_name'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        @if($linksExpireAt)
            <p class="files-note">
                Linki ważne do {{ $linksExpireAt->format('d.m.Y H:i') }} (koniec imprezy + 5 dni).
                Przy pobraniu z panelu pliki są też w ZIP.
            </p>
        @else
            <p class="files-note">Pełne pliki znajdują się w archiwum ZIP przy pobraniu pakietu z panelu.</p>
        @endif
    @endif
</div>
