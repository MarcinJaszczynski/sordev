@props([
    'variant' => 'generic',
    'heading',
    'description' => null,
    'icon' => null,
])

@php
    $variants = [
        'events' => [
            'icon' => 'heroicon-o-map',
            'accent' => 'sor-empty-state--events',
            'defaultHeading' => 'Brak imprez',
            'defaultDescription' => 'Utwórz pierwszą imprezę lub wygeneruj ją z szablonu.',
        ],
        'finance' => [
            'icon' => 'heroicon-o-banknotes',
            'accent' => 'sor-empty-state--finance',
            'defaultHeading' => 'Brak pozycji finansowych',
            'defaultDescription' => 'Rozliczenia i faktury pojawią się po utworzeniu imprezy.',
        ],
        'program' => [
            'icon' => 'heroicon-o-calendar-days',
            'accent' => 'sor-empty-state--program',
            'defaultHeading' => 'Brak punktów programu',
            'defaultDescription' => 'Dodaj pierwszy blok dnia lub skopiuj program z szablonu.',
        ],
        'contacts' => [
            'icon' => 'heroicon-o-users',
            'accent' => 'sor-empty-state--contacts',
            'defaultHeading' => 'Brak kontaktów',
            'defaultDescription' => 'Dodaj kontrahenta lub kontakt, aby przypisać go do imprezy.',
        ],
        'tasks' => [
            'icon' => 'heroicon-o-clipboard-document-list',
            'accent' => 'sor-empty-state--tasks',
            'defaultHeading' => 'Brak zadań',
            'defaultDescription' => 'Zadania możesz tworzyć z poziomu imprezy lub punktu programu.',
        ],
        'generic' => [
            'icon' => 'heroicon-o-inbox',
            'accent' => 'sor-empty-state--generic',
            'defaultHeading' => 'Brak danych',
            'defaultDescription' => 'Nie znaleziono rekordów spełniających kryteria.',
        ],
    ];

    $config = $variants[$variant] ?? $variants['generic'];
    $iconName = $icon ?? $config['icon'];
    $title = $heading ?? $config['defaultHeading'];
    $body = $description ?? $config['defaultDescription'];
@endphp

<div {{ $attributes->class(['sor-empty-state', $config['accent']]) }}>
    <div class="sor-empty-state__icon" aria-hidden="true">
        <x-filament::icon :icon="$iconName" class="sor-empty-state__icon-svg" />
    </div>

    <h3 class="sor-empty-state__heading">{{ $title }}</h3>

    @if (filled($body))
        <p class="sor-empty-state__description">{{ $body }}</p>
    @endif

    @if (isset($actions))
        <div class="sor-empty-state__actions">
            {{ $actions }}
        </div>
    @endif
</div>
