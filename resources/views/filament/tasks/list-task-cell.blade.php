@php
    /** @var \App\Models\Task|null $record */
    $record = $getRecord();
@endphp

@if ($record)
    @php
        $title = $record->title ?: '—';
        $ownershipLine = \App\Support\Tasks\TaskListColumn::ownershipLine($record);
        $description = \App\Support\Tasks\TaskListColumn::sanitizeTaskText($record->description, 512);
        $comment = $record->relationLoaded('comments') ? $record->comments->first() : null;
        if ($comment) {
            $comment->loadMissing('author');
        }
        $attachments = $record->relationLoaded('attachments') ? $record->attachments : collect();
        $commentsCount = (int) ($record->comments_count ?? $record->comments?->count() ?? 0);
        $isExpanded = $this->expandedCommentsTaskId === $record->id;
        $expandedComments = $isExpanded ? $this->expandedCommentsFor($record->id) : [];
        $isSubtask = filled($record->parent_id);
        if ($isSubtask) {
            $record->loadMissing('parent');
        }
    @endphp

    <div @class([
        'task-list-cell min-w-0 w-full',
        'task-list-subtask pl-3 border-l border-gray-300 dark:border-gray-600' => $isSubtask,
    ])>
        @if ($isSubtask && $record->parent)
            <div class="mb-0.5 flex min-w-0 items-baseline gap-1 text-[0.7rem] leading-tight text-gray-500 dark:text-gray-400">
                <span class="shrink-0" aria-hidden="true">↳</span>
                <button
                    type="button"
                    class="min-w-0 truncate text-left hover:text-primary-600 hover:underline dark:hover:text-primary-400"
                    title="Otwórz zadanie nadrzędne"
                    x-on:click.stop="$wire.openEditTaskModal({{ $record->parent_id }})"
                >
                    {{ $record->parent->title }}
                </button>
            </div>
        @endif

        <button
            type="button"
            @class([
                'block w-full min-w-0 text-left text-[0.84rem] leading-snug hover:text-primary-600 dark:hover:text-primary-400',
                'font-medium text-gray-700 dark:text-gray-300' => $isSubtask,
                'font-semibold text-gray-950 dark:text-white' => ! $isSubtask,
            ])
            x-on:click.stop="$wire.openEditTaskModal({{ $record->id }})"
        >
            {{ $title }}
        </button>

        <div class="mt-0.5 text-[0.72rem] leading-tight text-gray-500 dark:text-gray-400">
            {{ $ownershipLine }}
        </div>

        @if ($description !== '')
            <div class="mt-0.5 text-[0.78rem] leading-snug text-gray-700 dark:text-gray-300">
                {{ $description }}
            </div>
        @endif

        @if ($attachments->isNotEmpty())
            <div class="mt-0.5 space-y-0.5">
                @foreach ($attachments as $attachment)
                    @php
                        $path = (string) ($attachment->file_path ?? '');
                        $fileExists = $path !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($path);
                    @endphp
                    @if ($fileExists)
                        <a
                            href="{{ $attachment->preview_url }}"
                            target="_blank"
                            rel="noopener"
                            class="block text-[0.78rem] leading-snug text-primary-600 underline dark:text-primary-400"
                            x-on:click.stop
                        >
                            {{ $attachment->filename }}
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

        @if ($comment)
            <div class="mt-0.5 text-[0.78rem] leading-tight text-gray-700 dark:text-gray-300">
                <div class="flex items-start gap-1">
                    <div class="min-w-0 flex-1">
                        @if ($isExpanded && $expandedComments !== [])
                            <div class="space-y-1.5">
                                @foreach ($expandedComments as $expandedComment)
                                    <div wire:key="task-list-comment-{{ $record->id }}-{{ $expandedComment['id'] }}">
                                        <div class="font-semibold italic leading-tight">
                                            {{ $expandedComment['author'] }} · {{ $expandedComment['created_at'] }}
                                        </div>
                                        <div class="mt-0.5 italic leading-snug whitespace-pre-wrap">{{ $expandedComment['content'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="font-semibold italic leading-tight">
                                Ostatni komentarz
                                @if ($commentsCount > 1)
                                    <span class="ml-1 inline-flex items-center rounded-full bg-slate-200 px-1.5 py-0 text-[0.65rem] font-bold not-italic text-slate-800 dark:bg-slate-700 dark:text-slate-100">{{ $commentsCount }}</span>
                                @endif
                                · {{ $comment->author?->name ?? '—' }} · {{ $comment->created_at?->format('d.m.Y H:i') ?? '—' }}
                            </div>
                            <div class="mt-0.5 italic leading-snug whitespace-pre-wrap">
                                {{ \App\Support\Tasks\TaskListColumn::sanitizeTaskText($comment->content, 512) }}
                            </div>
                        @endif
                    </div>

                    @if ($commentsCount > 1)
                        <button
                            type="button"
                            title="{{ $isExpanded ? 'Zwiń komentarze' : 'Pokaż wszystkie komentarze ('.$commentsCount.')' }}"
                            class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-primary-400"
                            x-on:click.stop="$wire.toggleExpandedComments({{ $record->id }})"
                        >
                            <x-heroicon-o-chevron-down
                                @class([
                                    'h-4 w-4 transition-transform',
                                    'rotate-180' => $isExpanded,
                                ])
                            />
                        </button>
                    @endif
                </div>
            </div>
        @endif

        <div class="mt-1 flex items-center gap-1.5">
            <button
                type="button"
                title="Dodaj komentarz"
                class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-primary-200 bg-primary-50 text-primary-600 hover:bg-primary-100 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-400"
                x-on:click.stop="$wire.openAddCommentModal({{ $record->id }})"
            >
                <x-heroicon-o-pencil-square class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Dodaj załącznik"
                class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400"
                x-on:click.stop="$wire.openAddAttachmentModal({{ $record->id }})"
            >
                <x-heroicon-o-paper-clip class="h-4 w-4" />
            </button>
        </div>
    </div>
@endif
