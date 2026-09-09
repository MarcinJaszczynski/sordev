@php
    /** @var \App\Models\TaskComment $comment */
    $presentation = \App\Support\Tasks\TaskCommentPresentation::for($comment, auth()->id());
    $variant = $presentation['variant'];
    $emphasizeLatest = (bool) ($emphasizeLatest ?? false);
@endphp

@if ($variant === \App\Support\Tasks\TaskCommentPresentation::VARIANT_RESERVATION)
    @php
        $card = $presentation['reservation'];
    @endphp
    <article
        wire:key="task-comment-{{ $comment->id }}"
        @class([
            'rounded-xl border p-3 shadow-sm ring-1',
            'border-teal-200/80 bg-teal-50/80 ring-teal-950/5 dark:border-teal-400/30 dark:bg-teal-500/10 dark:ring-teal-300/10' => ! $emphasizeLatest,
            'border-primary-300 bg-primary-50/90 ring-primary-950/5 dark:border-primary-400/40 dark:bg-primary-500/15 dark:ring-primary-300/10' => $emphasizeLatest,
        ])
    >
        <div class="mb-2 flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-200">
                    <x-filament::icon icon="heroicon-o-building-office-2" class="h-4 w-4" />
                </span>
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-teal-950 dark:text-teal-50">
                        {{ $card['title'] }}
                    </div>
                    <div class="truncate text-[0.68rem] text-teal-800/70 dark:text-teal-200/70">
                        {{ $presentation['author'] }}
                    </div>
                </div>
            </div>
            <time class="shrink-0 text-[0.68rem] text-teal-800/60 dark:text-teal-200/60">
                {{ $presentation['created_at'] }}
            </time>
        </div>

        @if ($card['fields'] !== [])
            <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($card['fields'] as $field)
                    <div class="rounded-lg bg-white/80 px-2.5 py-1.5 ring-1 ring-teal-950/5 dark:bg-gray-950/30 dark:ring-white/10">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-teal-800/60 dark:text-teal-200/60">
                            {{ $field['label'] }}
                        </dt>
                        <dd class="mt-0.5 text-sm font-medium text-gray-950 dark:text-white">
                            {{ $field['value'] }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if ($card['remainder'] !== '')
            <div class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-gray-800 dark:text-gray-200">
                {{ $card['remainder'] }}
            </div>
        @elseif ($card['fields'] === [])
            <div class="whitespace-pre-wrap text-sm leading-relaxed text-gray-800 dark:text-gray-200">
                {{ $presentation['body'] }}
            </div>
        @endif
    </article>
@elseif ($variant === \App\Support\Tasks\TaskCommentPresentation::VARIANT_SYSTEM)
    <div wire:key="task-comment-{{ $comment->id }}" class="flex justify-center">
        <div class="max-w-[92%] rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-2 text-center dark:border-white/15 dark:bg-white/5">
            <div class="mb-1 flex items-center justify-between gap-3 text-[0.68rem] text-gray-500 dark:text-gray-400">
                <span class="font-semibold">{{ $presentation['author'] }}</span>
                <time class="shrink-0">{{ $presentation['created_at'] }}</time>
            </div>
            <div class="whitespace-pre-wrap text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                {{ $presentation['body'] }}
            </div>
        </div>
    </div>
@else
    @php
        $isOwn = $variant === \App\Support\Tasks\TaskCommentPresentation::VARIANT_OWN;
    @endphp
    <div wire:key="task-comment-{{ $comment->id }}" class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}">
        <div @class([
            'max-w-[85%] rounded-2xl px-3 py-2 shadow-sm',
            'bg-emerald-600 text-white' => $isOwn && ! $emphasizeLatest,
            'bg-sky-50 text-gray-950 ring-1 ring-sky-200/80 dark:bg-sky-500/10 dark:text-white dark:ring-sky-400/20' => ! $isOwn && ! $emphasizeLatest,
            'bg-primary-50 text-gray-950 ring-1 ring-primary-200 dark:bg-primary-500/15 dark:text-white dark:ring-primary-400/30' => $emphasizeLatest && ! $isOwn,
            'bg-emerald-600 text-white ring-2 ring-emerald-300/80' => $emphasizeLatest && $isOwn,
        ])>
            <div @class([
                'mb-1 flex items-center justify-between gap-3 text-[0.68rem]',
                'text-emerald-100' => $isOwn,
                'text-sky-700/70 dark:text-sky-200/70' => ! $isOwn && ! $emphasizeLatest,
                'text-primary-700/70 dark:text-primary-200/70' => $emphasizeLatest && ! $isOwn,
            ])>
                <span class="min-w-0 truncate font-semibold">{{ $presentation['author'] }}</span>
                <time class="shrink-0 opacity-80">{{ $presentation['created_at'] }}</time>
            </div>
            <div class="whitespace-pre-wrap text-sm leading-relaxed">{{ $presentation['body'] }}</div>
        </div>
    </div>
@endif
