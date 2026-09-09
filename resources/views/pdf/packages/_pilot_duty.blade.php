{{-- Regulamin obowiązków pilota z AppSetting::pilot_duty_text (opcjonalnie) --}}
@php
    $duty = trim((string) ($pilotDutyText ?? ''));
@endphp
@if($duty !== '')
    <div class="section">
        <div class="section-title">Regulamin obowiązków pilota</div>
        <div class="section-body">
            <div class="notes-field" style="min-height:auto; border-style:solid;">
                {!! nl2br(e($duty)) !!}
            </div>
        </div>
    </div>
@endif
