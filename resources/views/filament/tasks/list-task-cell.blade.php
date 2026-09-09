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
        $latestBubble = $comment
            ? \App\Support\Tasks\TaskCommentPresentation::forListBubble($comment, auth()->id())
            : null;
        $record->loadMissing('status');
        $statusLabel = \App\Support\Tasks\TaskStatusPresentation::labelForTask($record);
        $pillClass = \App\Support\Tasks\TaskStatusPresentation::pillClass($record->status);
        $earlierCount = max(0, $commentsCount - 1);
    @endphp

    <div @class([
        'task-list-cell min-w-0 w-full',
        'task-list-subtask pl-3 border-l border-gray-300 dark:border-gray-600' => $isSubtask,
    ])>
        {{-- Szczegóły zadania — odseparowane od dyskusji --}}
        <div class="rounded-lg bg-slate-50/90 px-2.5 py-2 ring-1 ring-slate-950/5 dark:bg-white/5 dark:ring-white/10">
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

            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                <span class="{{ $pillClass }}">{{ $statusLabel }}</span>
                <span class="text-[0.72rem] leading-tight text-gray-500 dark:text-gray-400">
                    {{ $ownershipLine }}
                </span>
            </div>

            @if ($description !== '')
                <div class="mt-1 whitespace-pre-wrap text-[0.78rem] leading-snug text-gray-700 dark:text-gray-300">
                    {{ $description }}
                </div>
            @endif

            @if ($attachments->isNotEmpty())
                <div class="mt-1 space-y-0.5" x-on:click.stop>
                    @foreach ($attachments as $attachment)
                        @php
                            $previewUrl = $attachment->preview_url;
                        @endphp
                        @if ($previewUrl)
                            <a
                                href="{{ $previewUrl }}"
                                target="_blank"
                                rel="noopener"
                                class="block text-[0.78rem] leading-snug text-primary-600 underline dark:text-primary-400"
                                x-on:click.stop
                                wire:click.stop
                            >
                                {{ $attachment->filename }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Dyskusja / komentarze — tylko ostatni; wcześniejsze pod przełącznikiem --}}
        <div class="mt-2 border-t-2 border-slate-200 pt-2 dark:border-white/15">
            <div class="mb-1.5 flex items-center justify-between gap-2">
                <div class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Dyskusja
                    @if ($commentsCount > 0)
                        <span class="font-normal normal-case tracking-normal text-slate-400 dark:text-slate-500">({{ $commentsCount }})</span>
                    @endif
                </div>
            </div>

            @if ($comment && $latestBubble)
                <div class="space-y-1.5">
                    @if ($earlierCount > 0)
                        <button
                            type="button"
                            class="task-thread-expand"
                            x-on:click.stop="$wire.toggleExpandedComments({{ $record->id }})"
                        >
                            <svg class="h-2.5 w-2.5 shrink-0 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                                <path d="M2 1 L8 5 L2 9" stroke="currentColor" stroke-width="1.6"/>
                            </svg>
                            {{ $isExpanded
                                ? 'Ukryj wcześniejsze wiadomości'
                                : 'Pokaż '.$earlierCount.' '.($earlierCount === 1 ? 'wcześniejszą wiadomość' : 'wcześniejsze wiadomości') }}
                        </button>
                    @endif

                    @if ($isExpanded && $expandedComments !== [])
                        @foreach ($expandedComments as $index => $expandedComment)
                            @if ($index < count($expandedComments) - 1)
                                @include('filament.tasks.partials.list-comment-bubble', [
                                    'bubble' => $expandedComment,
                                    'wireKey' => 'task-list-comment-'.$record->id.'-'.$expandedComment['id'],
                                ])
                            @endif
                        @endforeach
                    @endif

                    @include('filament.tasks.partials.list-comment-bubble', [
                        'bubble' => $latestBubble,
                        'wireKey' => 'task-list-comment-latest-'.$record->id,
                    ])
                </div>
            @else
                <p class="text-[0.72rem] text-slate-400 dark:text-slate-500">Brak komentarzy</p>
            @endif
        </div>

        {{-- Stopka akcji — oddzielona od wątku i krawędzi wiersza --}}
        <div class="mt-2 flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1.5 dark:border-white/10 dark:bg-gray-950/40">
            <button
                type="button"
                title="Dodaj komentarz"
                class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-2.5 py-1 text-[0.72rem] font-semibold text-white shadow-sm transition hover:bg-emerald-500"
                x-on:click.stop="$wire.openAddCommentModal({{ $record->id }})"
            >
                <x-heroicon-o-paper-airplane class="h-3.5 w-3.5" />
                Odpowiedz
            </button>
            <button
                type="button"
                title="Dodaj załącznik"
                class="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-[0.72rem] font-medium text-amber-800 transition hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
                x-on:click.stop="$wire.openAddAttachmentModal({{ $record->id }})"
            >
                <x-heroicon-o-paper-clip class="h-3.5 w-3.5" />
                Załącznik
            </button>
        </div>
    </div>
@endif
