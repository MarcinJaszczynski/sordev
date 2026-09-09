@php
    /** @var array{id?: int, author: string, content: string, created_at: string, initials?: string} $card */
    $variant = $variant ?? 'earlier';
@endphp

<article @class([
    'task-comment-card',
    'task-comment-card--latest' => $variant === 'latest',
    'task-comment-card--earlier' => $variant === 'earlier',
]) @if (! empty($wireKey)) wire:key="{{ $wireKey }}" @endif>
    <div class="task-comment-card__head">
        <span class="task-avatar" style="width:20px;height:20px;font-size:9.5px">
            {{ $card['initials'] ?? '?' }}
        </span>
        <span class="task-comment-card__author">{{ $card['author'] }}</span>
        <span class="task-comment-card__time">{{ $card['created_at'] }}</span>
    </div>
    <div class="task-comment-card__body">{{ $card['content'] }}</div>
</article>
