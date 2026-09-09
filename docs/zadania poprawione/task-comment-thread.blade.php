<div class="mt-6">
    <div class="flex items-center justify-between mb-2.5">
        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Dyskusja</h3>
        <span class="text-xs font-medium text-gray-400">
            {{ $this->comments->count() }} {{ Str::plural('wiadomość', $this->comments->count()) }}
        </span>
    </div>

    @if($this->comments->isEmpty())
        <p class="text-sm text-gray-400">Brak komentarzy</p>
    @else
        {{-- Przełącznik: pokazuje wcześniejsze komentarze tylko na żądanie --}}
        @if($this->earlierComments->isNotEmpty())
            <button
                type="button"
                wire:click="toggleEarlier"
                class="flex items-center gap-2 w-full text-left text-xs font-medium text-gray-500
                       bg-gray-50 dark:bg-white/5 border border-dashed border-gray-200 dark:border-white/10
                       rounded-md px-3.5 py-2.5 mb-2.5 hover:bg-gray-100 dark:hover:bg-white/10 transition"
            >
                <svg class="w-2.5 h-2.5 shrink-0 transition-transform {{ $showEarlier ? 'rotate-90' : '' }}"
                     viewBox="0 0 10 10" fill="none">
                    <path d="M2 1 L8 5 L2 9" stroke="currentColor" stroke-width="1.6"/>
                </svg>
                {{ $showEarlier
                    ? 'Ukryj wcześniejsze wiadomości'
                    : 'Pokaż ' . $this->earlierComments->count() . ' ' . Str::plural('wcześniejszą wiadomość', $this->earlierComments->count()) }}
            </button>

            @if($showEarlier)
                <div class="flex flex-col gap-2.5 mb-2.5">
                    @foreach($this->earlierComments as $comment)
                        <x-task-comment :comment="$comment" :variant="'earlier'" />
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Ostatni komentarz jest zawsze widoczny --}}
        <x-task-comment :comment="$this->latestComment" :variant="'latest'" />
    @endif

    {{-- Formularz odpowiedzi --}}
    <form wire:submit="submit" class="mt-3 border border-gray-200 dark:border-white/10 rounded-lg bg-gray-50 dark:bg-white/5">
        <textarea
            wire:model="newComment"
            rows="2"
            placeholder="Napisz odpowiedź…"
            class="w-full border-0 bg-transparent text-sm px-3.5 py-3 focus:ring-0 resize-none"
        ></textarea>
        @error('newComment')
            <p class="px-3.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
        <div class="flex items-center justify-between px-2.5 py-2 border-t border-gray-200 dark:border-white/10">
            <button type="button" class="text-xs font-medium text-gray-500 hover:text-primary-600 px-2">
                📎 Załącznik
            </button>
            <x-filament::button type="submit" size="sm">
                Odpowiedz
            </x-filament::button>
        </div>
    </form>
</div>
