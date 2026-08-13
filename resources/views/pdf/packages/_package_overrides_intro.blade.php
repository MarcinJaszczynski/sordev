{{-- Wspólne bloki override dla pakietów PDF --}}
@php
    $hideSections = $hide_sections ?? [];
    $introHtml = trim((string) ($intro_html ?? ''));
    $extraNotesHtml = trim((string) ($extra_notes_html ?? ''));
@endphp

@if($introHtml !== '')
    <div class="section">
        <div class="section-title">Wstęp / uwagi do pakietu</div>
        <div class="section-body">
            <div class="notes-field">{!! $introHtml !!}</div>
        </div>
    </div>
@endif
