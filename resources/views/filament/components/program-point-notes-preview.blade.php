@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();

    $items = collect([
        ['label' => 'Biuro', 'html' => (string) ($record?->resolvedOfficeNotes() ?? '')],
        ['label' => 'Pilot', 'html' => (string) ($record?->resolvedPilotNotes() ?? '')],
        ['label' => 'Kierowca', 'html' => (string) ($record?->notes ?? '')],
    ])->map(function (array $item): array {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($item['html'])) ?? '');

        return [
            'label' => $item['label'],
            'html' => $item['html'],
            'plain' => $plain,
        ];
    })->filter(fn (array $item): bool => $item['plain'] !== '')->values();
@endphp

@if ($items->isEmpty())
    <span class="text-gray-400">—</span>
@else
    <div class="group relative epp-notes-preview">
        <div class="epp-notes-preview__list">
            @foreach ($items as $item)
                <div class="epp-notes-preview__item">
                    <div class="epp-notes-preview__label">{{ $item['label'] }}:</div>
                    {{-- Pełna treść jak opis programu (line-clamp w CSS, bez limitu znaków). --}}
                    <div class="epp-point-sub" title="{{ $item['plain'] }}">
                        {{ $item['plain'] }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pointer-events-none invisible absolute left-0 top-full z-[120] mt-2 w-[34rem] max-w-[75vw] rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-2xl group-hover:visible group-hover:pointer-events-auto dark:border-gray-700 dark:bg-gray-900">
            <div class="space-y-4">
                @foreach ($items as $item)
                    <div>
                        <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $item['label'] }}</div>
                        <div class="prose prose-sm max-w-none dark:prose-invert [&_*]:my-0 [&_p]:mb-2">{!! $item['html'] !!}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
