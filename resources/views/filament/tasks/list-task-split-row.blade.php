@php
    /** @var \App\Models\Task|null $record */
    $record = $getRecord();
@endphp

@if ($record)
    @php
        $record->loadMissing(['status', 'assignee', 'taskable', 'parent', 'attachments']);
        $isActive = (int) ($this->selectedTaskId ?? 0) === (int) $record->id;
        $dueLabel = $record->due_date?->format('d.m.Y H:i') ?? '—';
        $isOverdue = \App\Support\Tasks\TaskListColumn::isDueOverdue($record);
        $contextLine = $record->task_context_label;
        $ownershipLine = \App\Support\Tasks\TaskListColumn::ownershipLine($record);
        $statusLabel = \App\Support\Tasks\TaskStatusPresentation::labelForTask($record);
        $pillClass = \App\Support\Tasks\TaskStatusPresentation::pillClass($record->status);
        $initials = \App\Support\Tasks\TaskStatusPresentation::initials($record->assignee?->name);
        $isSubtask = filled($record->parent_id);
        $descriptionPreview = \App\Support\Tasks\TaskListColumn::sanitizeTaskText($record->description, 140);
        $descriptionFullPlain = \App\Support\Tasks\TaskListColumn::sanitizeTaskText($record->description, 5000);
        $descriptionHtml = \App\Support\Tasks\TaskListColumn::descriptionHtmlForTooltip($record->description);
        $canExpandDescription = $descriptionPreview !== '';

        $latestComment = null;
        if ($record->relationLoaded('comments') && $record->comments->isNotEmpty()) {
            $latestComment = $record->comments
                ->sortBy([
                    fn ($comment) => $comment->created_at?->timestamp ?? 0,
                    fn ($comment) => (int) $comment->id,
                ])
                ->last();
        } elseif ((int) ($record->comments_count ?? 0) > 0) {
            $latestComment = $record->comments()
                ->with('author')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();
        }
        if ($latestComment) {
            $latestComment->loadMissing('author');
        }
        $commentsCount = (int) ($record->comments_count ?? $record->comments?->count() ?? 0);
        $commentPreview = $latestComment
            ? \App\Support\Tasks\TaskListColumn::sanitizeTaskText($latestComment->content, 140)
            : '';
        $commentFull = $latestComment
            ? \App\Support\Tasks\TaskListColumn::sanitizeTaskText($latestComment->content, 5000)
            : '';
        $commentAuthor = $latestComment?->author?->name;
        $commentAt = $latestComment?->created_at?->format('d.m.Y H:i');
        $earlierCount = max(0, $commentsCount - 1);
        $isCommentsExpanded = (int) ($this->expandedCommentsTaskId ?? 0) === (int) $record->id;
        $expandedComments = $isCommentsExpanded ? $this->expandedCommentsFor($record->id) : [];
        $canExpandLatestComment = $commentFull !== '' && $commentFull !== $commentPreview;

        $attachments = $record->relationLoaded('attachments') ? $record->attachments : collect();
        $attachmentsCount = (int) ($record->attachments_count ?? $attachments->count());
    @endphp

    <div
        role="button"
        tabindex="0"
        wire:click="selectTask({{ $record->id }})"
        wire:keydown.enter="selectTask({{ $record->id }})"
        @class([
            'task-split-row',
            'is-active' => $isActive,
            'task-list-subtask' => $isSubtask,
        ])
    >
        <div class="task-split-row__top">
            <span class="task-split-row__title">
                @if ($isSubtask)
                    <span class="text-[0.7rem] font-normal text-[color:var(--task-text-faint)]" aria-hidden="true">↳ </span>
                @endif
                {{ $record->title ?: '—' }}
            </span>
            <span @class(['task-split-row__due', 'is-overdue' => $isOverdue])>
                {{ $dueLabel }}
            </span>
        </div>

        <div class="task-split-row__meta">
            {{ $contextLine }}
        </div>
        <div class="task-split-row__ownership">
            {{ $ownershipLine }}
        </div>

        @if ($descriptionPreview !== '')
            <div
                class="task-split-row__desc"
                x-data="{ open: false }"
                wire:click.stop
                x-on:click.stop
                x-on:mousedown.stop
            >
                <div class="task-split-row__desc-teaser" x-show="! open">
                    {{ $descriptionPreview }}
                </div>
                <div class="task-split-row__desc-full" x-show="open" x-cloak>
                    {!! $descriptionHtml !== '' ? $descriptionHtml : e($descriptionFullPlain) !!}
                </div>
                @if ($canExpandDescription)
                    <button
                        type="button"
                        class="task-split-row__expand"
                        x-on:click.stop="open = ! open"
                    >
                        <svg
                            class="h-2.5 w-2.5 shrink-0 transition-transform"
                            :class="open && 'rotate-90'"
                            viewBox="0 0 10 10"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path d="M2 1 L8 5 L2 9" stroke="currentColor" stroke-width="1.6"/>
                        </svg>
                        <span x-text="open ? 'Zwiń treść' : 'Rozwiń treść'"></span>
                    </button>
                @endif
            </div>
        @endif

        <div class="task-split-row__bottom">
            <span class="{{ $pillClass }}">{{ $statusLabel }}</span>
            <span class="task-avatar" title="{{ $record->assignee?->name ?: 'Nieprzypisane' }}">
                {{ $initials }}
            </span>
        </div>

        @if ($commentPreview !== '' || $isCommentsExpanded)
            <div
                class="task-split-row__comment"
                wire:click.stop
                x-on:click.stop
                x-on:mousedown.stop
                @if ($canExpandLatestComment && $earlierCount === 0)
                    x-data="{ bodyOpen: false }"
                @endif
            >
                <div class="task-split-row__comment-head">
                    <div class="task-split-row__comment-head-main">
                        <span class="task-split-row__comment-badge">
                            <x-heroicon-s-chat-bubble-left-right class="h-3 w-3" />
                            Dyskusja
                        </span>
                        @if ($commentsCount > 0)
                            <span class="task-split-row__comment-count">{{ $commentsCount }}</span>
                        @endif
                        @if ($commentAuthor && ! $isCommentsExpanded)
                            <span class="task-split-row__comment-author">{{ $commentAuthor }}</span>
                        @endif
                    </div>
                    @if ($commentAt && ! $isCommentsExpanded)
                        <time class="task-split-row__when" datetime="{{ $latestComment->created_at?->toIso8601String() }}">
                            {{ $commentAt }}
                        </time>
                    @endif
                </div>

                @if ($isCommentsExpanded && $expandedComments !== [])
                    <div class="task-split-row__comment-thread">
                        @foreach ($expandedComments as $expandedComment)
                            @include('filament.tasks.partials.list-comment-bubble', [
                                'bubble' => $expandedComment,
                                'wireKey' => 'task-split-comment-'.$record->id.'-'.$expandedComment['id'],
                            ])
                        @endforeach
                    </div>
                @elseif ($canExpandLatestComment && $earlierCount === 0)
                    <div class="task-split-row__comment-body" x-show="! bodyOpen">
                        {{ $commentPreview }}
                    </div>
                    <div class="task-split-row__comment-body is-expanded" x-show="bodyOpen" x-cloak>
                        {{ $commentFull }}
                    </div>
                @else
                    <div class="task-split-row__comment-body">
                        {{ $commentPreview }}
                    </div>
                @endif

                @if ($earlierCount > 0)
                    <button
                        type="button"
                        class="task-split-row__expand"
                        wire:click.stop="toggleExpandedComments({{ $record->id }})"
                    >
                        <svg
                            @class([
                                'h-2.5 w-2.5 shrink-0 transition-transform',
                                'rotate-90' => $isCommentsExpanded,
                            ])
                            viewBox="0 0 10 10"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path d="M2 1 L8 5 L2 9" stroke="currentColor" stroke-width="1.6"/>
                        </svg>
                        {{ $isCommentsExpanded
                            ? 'Zwiń dyskusję'
                            : 'Rozwiń dyskusję ('.$earlierCount.')' }}
                    </button>
                @elseif ($canExpandLatestComment)
                    <button
                        type="button"
                        class="task-split-row__expand"
                        x-on:click.stop="bodyOpen = ! bodyOpen"
                    >
                        <svg
                            class="h-2.5 w-2.5 shrink-0 transition-transform"
                            :class="bodyOpen && 'rotate-90'"
                            viewBox="0 0 10 10"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path d="M2 1 L8 5 L2 9" stroke="currentColor" stroke-width="1.6"/>
                        </svg>
                        <span x-text="bodyOpen ? 'Zwiń wiadomość' : 'Rozwiń wiadomość'"></span>
                    </button>
                @endif
            </div>
        @endif

        @if ($attachments->isNotEmpty())
            <div
                class="task-split-row__files"
                wire:click.stop
                x-on:click.stop
                x-on:mousedown.stop
            >
                <div class="task-split-row__files-label">
                    <x-heroicon-s-paper-clip class="h-3 w-3" />
                    Załączniki
                    <span class="task-split-row__files-count">{{ $attachmentsCount }}</span>
                </div>
                @foreach ($attachments->take(3) as $attachment)
                    @php
                        $previewUrl = $attachment->preview_url;
                        $attachmentAt = $attachment->created_at?->format('d.m.Y H:i');
                    @endphp
                    @if ($previewUrl)
                        <a
                            href="{{ $previewUrl }}"
                            target="_blank"
                            rel="noopener"
                            class="task-split-row__file"
                            title="Otwórz: {{ $attachment->filename }}{{ $attachmentAt ? ' · '.$attachmentAt : '' }}"
                            wire:click.stop
                            x-on:click.stop
                        >
                            <x-heroicon-o-document class="h-3.5 w-3.5 shrink-0" />
                            <span class="task-split-row__file-name truncate">{{ $attachment->filename }}</span>
                            @if ($attachmentAt)
                                <time class="task-split-row__when" datetime="{{ $attachment->created_at?->toIso8601String() }}">
                                    {{ $attachmentAt }}
                                </time>
                            @endif
                        </a>
                    @else
                        <span class="task-split-row__file is-missing" title="Brak linku do pliku{{ $attachmentAt ? ' · '.$attachmentAt : '' }}">
                            <x-heroicon-o-document class="h-3.5 w-3.5 shrink-0" />
                            <span class="task-split-row__file-name truncate">{{ $attachment->filename }}</span>
                            @if ($attachmentAt)
                                <time class="task-split-row__when" datetime="{{ $attachment->created_at?->toIso8601String() }}">
                                    {{ $attachmentAt }}
                                </time>
                            @endif
                        </span>
                    @endif
                @endforeach
                @if ($attachmentsCount > 3)
                    <span class="task-split-row__file-more">+{{ $attachmentsCount - 3 }} więcej</span>
                @endif
            </div>
        @endif
    </div>
@endif
