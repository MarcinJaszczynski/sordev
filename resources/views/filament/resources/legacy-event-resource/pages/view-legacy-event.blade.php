<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @php
        $summary = $this->legacyPageSummary();
        $archive = $summary['archive'];
        $status = $summary['status'];
        $statusBadge = match ($status) {
            'Zakończona' => 'legacy-badge--success',
            'Anulowana' => 'legacy-badge--danger',
            'Potwierdzona' => 'legacy-badge--accent',
            default => 'legacy-badge--warning',
        };
        $bits = array_values(array_filter([
            $summary['code'] ? 'Kod '.$summary['code'] : null,
            $summary['client'] ?: null,
            $summary['dates'] !== '—' ? $summary['dates'] : null,
        ]));
    @endphp

    <div class="legacy-event-page-root">
        @include('filament.resources.legacy-event-resource.pages.partials.legacy-event-page-styles')

        <div class="legacy-status-bar">
            <div class="min-w-0 flex-1">
                <p class="legacy-status-main">{{ $summary['name'] }}</p>
                @if ($bits !== [])
                    <p class="legacy-status-sub">{{ implode(' · ', $bits) }}</p>
                @endif
            </div>
            <div class="legacy-badges">
                @if ($status)
                    <span class="legacy-badge {{ $statusBadge }}">{{ $status }}</span>
                @endif
                @if ($summary['duration'])
                    <span class="legacy-badge legacy-badge--muted">{{ $summary['duration'] }} dni</span>
                @endif
            </div>
        </div>

        <div class="legacy-stat-grid">
            <div class="legacy-stat-card">
                <p class="legacy-stat-label">Uczestnicy</p>
                <p class="legacy-stat-value">{{ $summary['participants'] }}</p>
                <p class="legacy-stat-hint">opiek. {{ $summary['guardians'] }} · gratis {{ $summary['free'] }}</p>
            </div>
            <div class="legacy-stat-card legacy-stat-card--success">
                <p class="legacy-stat-label">U klienta: pojechało</p>
                <p class="legacy-stat-value">{{ $archive['completed'] ?? 0 }}</p>
                <p class="legacy-stat-hint">{{ number_format((int) ($archive['participants_completed'] ?? 0), 0, ',', ' ') }} osób łącznie</p>
            </div>
            <div class="legacy-stat-card legacy-stat-card--danger">
                <p class="legacy-stat-label">U klienta: anulowane</p>
                <p class="legacy-stat-value">{{ $archive['cancelled'] ?? 0 }}</p>
                <p class="legacy-stat-hint">z {{ $archive['total'] ?? 0 }} imprez archiwalnych</p>
            </div>
            <div class="legacy-stat-card">
                <p class="legacy-stat-label">Okres współpracy</p>
                <p class="legacy-stat-value">{{ $archive['years_label'] ?? '—' }}</p>
                <p class="legacy-stat-hint">
                    @if ($summary['contractor_url'])
                        <a href="{{ $summary['contractor_url'] }}" class="text-[var(--legacy-accent-text)] underline-offset-2 hover:underline">Otwórz kontrahenta</a>
                    @else
                        brak powiązania
                    @endif
                </p>
            </div>
        </div>

        {{ $this->infolist }}
    </div>
</x-filament-panels::page>
