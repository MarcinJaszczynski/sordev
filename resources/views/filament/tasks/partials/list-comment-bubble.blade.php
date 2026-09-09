@php
    /** @var array{id?: int, variant: string, author: string, content: string, created_at: string} $bubble */
    $variant = $bubble['variant'] ?? \App\Support\Tasks\TaskCommentPresentation::VARIANT_OTHER;
    $isOwn = $variant === \App\Support\Tasks\TaskCommentPresentation::VARIANT_OWN;
    $isSystem = $variant === \App\Support\Tasks\TaskCommentPresentation::VARIANT_SYSTEM;
@endphp

@if ($isSystem)
    <div class="flex justify-center" @if (! empty($wireKey)) wire:key="{{ $wireKey }}" @endif>
        <div class="max-w-[95%] rounded-lg border border-dashed border-gray-300 bg-gray-50 px-2.5 py-1.5 text-center dark:border-white/15 dark:bg-white/5">
            <div class="mb-0.5 flex items-center justify-between gap-2 text-[0.65rem] text-gray-500 dark:text-gray-400">
                <span class="min-w-0 truncate font-semibold">{{ $bubble['author'] }}</span>
                <time class="shrink-0">{{ $bubble['created_at'] }}</time>
            </div>
            <div class="whitespace-pre-wrap text-[0.72rem] leading-snug text-gray-600 dark:text-gray-300">
                {{ $bubble['content'] }}
            </div>
        </div>
    </div>
@else
    <div
        @class(['flex', $isOwn ? 'justify-end' : 'justify-start'])
        @if (! empty($wireKey)) wire:key="{{ $wireKey }}" @endif
    >
        <div @class([
            'max-w-[92%] rounded-2xl px-2.5 py-1.5 shadow-sm',
            'rounded-br-md bg-emerald-600 text-white' => $isOwn,
            'rounded-bl-md bg-sky-50 text-gray-950 ring-1 ring-sky-200/80 dark:bg-sky-500/10 dark:text-white dark:ring-sky-400/20' => ! $isOwn,
        ])>
            <div @class([
                'mb-0.5 flex items-center justify-between gap-2 text-[0.65rem]',
                'text-emerald-100' => $isOwn,
                'text-sky-700/80 dark:text-sky-200/70' => ! $isOwn,
            ])>
                <span class="min-w-0 truncate font-semibold">{{ $bubble['author'] }}</span>
                <time class="shrink-0 opacity-80">{{ $bubble['created_at'] }}</time>
            </div>
            <div class="whitespace-pre-wrap text-[0.78rem] leading-snug">{{ $bubble['content'] }}</div>
        </div>
    </div>
@endif
