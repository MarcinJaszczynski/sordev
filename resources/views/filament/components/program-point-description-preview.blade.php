@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();

    $showDescription = (bool) ($record?->show_description ?? true);
    $html = trim((string) ($record?->description ?? $record?->templatePoint?->description ?? ''));
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
@endphp

@if (! $showDescription || $plain === '')
    <span class="text-gray-400">—</span>
@else
    <div class="group relative max-w-md">
        <div class="program-point-description-preview text-xs leading-5 text-gray-700">
            {{ \Illuminate\Support\Str::limit($plain, 180) }}
        </div>

        @if (strlen($plain) > 180 || $html !== $plain)
            <div class="pointer-events-none invisible absolute left-0 top-full z-[120] mt-2 w-[34rem] max-w-[80vw] rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-800 shadow-2xl group-hover:visible group-hover:pointer-events-auto dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Opis punktu</div>
                <div class="program-point-description-full prose prose-sm max-w-none dark:prose-invert [&_*]:my-0 [&_p]:mb-2">
                    {!! $html !!}
                </div>
            </div>
        @endif
    </div>
@endif
