<section
    class="fi-task-editor-panel flex h-full flex-col rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    x-data="{ scrollToBottom() { const el = $refs.thread; if (el) { el.scrollTop = el.scrollHeight; } } }"
    x-init="scrollToBottom()"
    @comment-added.window="scrollToBottom()"
>
    @php
        $commentsCount = $this->comments->count();
        $latestComment = $this->latestComment;
        $earlierComments = $this->earlierComments;
        $showEarlier = (bool) $this->showEarlierComments;
    @endphp

    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
            Dyskusja
            @if ($commentsCount > 0)
                <span class="font-normal text-gray-500 dark:text-gray-400">({{ $commentsCount }})</span>
            @endif
        </h3>
    </div>

    <div
        x-ref="thread"
        class="min-h-48 max-h-72 flex-1 space-y-3 overflow-y-auto px-4 py-4"
    >
        @if ($commentsCount === 0)
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Brak komentarzy. Napisz pierwszy poniżej.
            </p>
        @else
            @if ($earlierComments->isNotEmpty())
                <button
                    type="button"
                    wire:click="toggleEarlierComments"
                    class="task-thread-expand mb-1"
                >
                    <svg class="h-2.5 w-2.5 shrink-0 transition-transform {{ $showEarlier ? 'rotate-90' : '' }}" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                        <path d="M2 1 L8 5 L2 9" stroke="currentColor" stroke-width="1.6"/>
                    </svg>
                    {{ $showEarlier
                        ? 'Ukryj wcześniejsze wiadomości'
                        : 'Pokaż '.$earlierComments->count().' '.($earlierComments->count() === 1 ? 'wcześniejszą wiadomość' : 'wcześniejsze wiadomości') }}
                </button>

                @if ($showEarlier)
                    @foreach ($earlierComments as $comment)
                        @include('livewire.partials.task-editor-comment-item', ['comment' => $comment])
                    @endforeach
                @endif
            @endif

            @if ($latestComment)
                @include('livewire.partials.task-editor-comment-item', [
                    'comment' => $latestComment,
                    'emphasizeLatest' => true,
                ])
            @endif
        @endif
    </div>

    {{--
        Bez <form>: modal akcji Filament już jest formularzem, a zagnieżdżony <form>
        jest ignorowany przez HTML i psuje focus-trap (kursor wraca do tytułu).
        type="button" + wire:click, żeby Enter/Wyślij nie zamykały modala (callMountedAction).
    --}}
    <div
        class="border-t border-gray-200 px-4 py-3 dark:border-white/10"
        x-on:keydown.stop
        x-on:mousedown.stop
    >
        <div class="flex items-end gap-2">
            <div class="min-w-0 flex-1">
                <textarea
                    wire:model="newCommentContent"
                    rows="2"
                    placeholder="Napisz komentarz..."
                    class="block w-full resize-y rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500"
                ></textarea>
                @error('newCommentContent')
                    <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            </div>
            <x-filament::button
                type="button"
                size="sm"
                icon="heroicon-m-paper-airplane"
                wire:click="addComment"
                class="shrink-0"
            >
                Wyślij
            </x-filament::button>
        </div>
    </div>
</section>
