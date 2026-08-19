<section
    class="fi-task-editor-panel flex h-full flex-col rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    x-data="{ scrollToBottom() { const el = $refs.thread; if (el) { el.scrollTop = el.scrollHeight; } } }"
    x-init="scrollToBottom()"
    @comment-added.window="scrollToBottom()"
>
    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
            Komentarze
            @if ($this->comments->isNotEmpty())
                <span class="font-normal text-gray-500 dark:text-gray-400">({{ $this->comments->count() }})</span>
            @endif
        </h3>
    </div>

    <div
        x-ref="thread"
        class="min-h-48 max-h-72 flex-1 space-y-3 overflow-y-auto px-4 py-4"
    >
        @forelse ($this->comments as $comment)
            @php
                $isOwn = (int) $comment->user_id === (int) auth()->id();
            @endphp
            <div wire:key="task-comment-{{ $comment->id }}" class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[85%] rounded-2xl px-3 py-2 {{ $isOwn ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white' }}">
                    <div class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[0.68rem] {{ $isOwn ? 'text-primary-100' : 'text-gray-500 dark:text-gray-400' }}">
                        <span class="font-semibold">{{ $comment->author?->name ?? '—' }}</span>
                        <span>{{ $comment->created_at?->format('d.m.Y H:i') }}</span>
                    </div>
                    <div class="whitespace-pre-wrap text-sm leading-relaxed">{{ $comment->content }}</div>
                </div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Brak komentarzy. Napisz pierwszy poniżej.
            </p>
        @endforelse
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
        <div class="space-y-3">
            <textarea
                wire:model="newCommentContent"
                rows="3"
                placeholder="Napisz komentarz..."
                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500"
            ></textarea>
            @error('newCommentContent')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror
            <div class="flex justify-end">
                <x-filament::button type="button" size="sm" wire:click="addComment">
                    Wyślij
                </x-filament::button>
            </div>
        </div>
    </div>
</section>
