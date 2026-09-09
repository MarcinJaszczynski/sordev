{{--
    resources/views/components/task-comment.blade.php
    Użycie: <x-task-comment :comment="$comment" :variant="'latest' | 'earlier'" />
--}}
@props(['comment', 'variant' => 'earlier'])

<div @class([
    'rounded-lg border px-3.5 py-3',
    'bg-primary-50 border-primary-200 dark:bg-primary-500/10 dark:border-primary-500/30' => $variant === 'latest',
    'bg-gray-50 border-gray-200 dark:bg-white/5 dark:border-white/10' => $variant === 'earlier',
])>
    <div class="flex items-center gap-2 mb-1.5">
        <span class="w-5 h-5 rounded-full bg-primary-600 text-white text-[10px] font-bold flex items-center justify-center shrink-0">
            {{ Str::of($comment->author->name)->explode(' ')->map(fn ($p) => Str::substr($p, 0, 1))->join('') }}
        </span>
        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $comment->author->name }}</span>
        <span class="text-xs text-gray-400">{{ $comment->created_at->format('d.m.Y, H:i') }}</span>
    </div>
    <div class="text-sm text-gray-700 dark:text-gray-300">
        {{ $comment->body }}
    </div>
</div>
