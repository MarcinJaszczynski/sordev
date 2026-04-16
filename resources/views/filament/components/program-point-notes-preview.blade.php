@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();

    $items = collect([
        ['label' => 'Biuro', 'html' => (string) ($record?->office_notes ?? '')],
        ['label' => 'Pilot', 'html' => (string) ($record?->pilot_notes ?? '')],
        ['label' => 'Kierowca', 'html' => (string) ($record?->notes ?? '')],
    ])->map(function (array $item): array {
        $plain = trim(strip_tags($item['html']));

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
    <div class="group relative max-w-sm">
        <div class="space-y-2 text-xs leading-5">
            @foreach ($items as $item)
                <div>
                    <div class="font-semibold text-gray-700">{{ $item['label'] }}:</div>
                    <div class="prose prose-sm max-w-none [&_*]:my-0" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        {!! $item['html'] !!}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pointer-events-none invisible absolute left-0 top-full z-[100] mt-2 w-[34rem] max-w-[75vw] rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-2xl group-hover:visible group-hover:pointer-events-auto">
            <div class="space-y-4">
                @foreach ($items as $item)
                    <div>
                        <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $item['label'] }}</div>
                        <div class="prose prose-sm max-w-none">{!! $item['html'] !!}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
