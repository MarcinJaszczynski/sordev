@php
    $extraNotesHtml = trim((string) ($extra_notes_html ?? ''));
@endphp

@if($extraNotesHtml !== '')
    <div class="section">
        <div class="section-title">Dodatkowe uwagi (ręcznie)</div>
        <div class="section-body">
            <div class="notes-field">{!! $extraNotesHtml !!}</div>
        </div>
    </div>
@endif
