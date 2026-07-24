<div class="sticky-notes-stack">
    <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-4 shadow-sm dark:border-amber-800/60 dark:bg-amber-950/20">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-amber-200/80 text-amber-900 dark:bg-amber-900/50 dark:text-amber-100">
                    <x-filament::icon icon="heroicon-o-document-text" class="h-4 w-4" />
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-amber-950 dark:text-amber-50">{{ $title }}</h3>
                    <p class="text-xs text-amber-800/80 dark:text-amber-200/70">Stos notatek — najnowsza na górze. Edycja tylko własnej, aktualnej notatki.</p>
                </div>
            </div>

            @if($this->notes->count() > 1)
                <button
                    type="button"
                    wire:click="toggleHistory"
                    class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100 dark:hover:bg-amber-900/40"
                >
                    {{ $historyExpanded ? 'Zwiń historię' : 'Pokaż historię ('.$this->notes->count().')' }}
                </button>
            @endif
        </div>

        <div class="mb-4 rounded-lg border border-amber-200 bg-white/90 p-3 dark:border-amber-800 dark:bg-gray-900/50">
            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-amber-900/70 dark:text-amber-200/70">
                Nowa notatka
            </label>
            <div class="space-y-2">
                <textarea
                    wire:model="newBody"
                    rows="{{ $compact ? 2 : 3 }}"
                    placeholder="Wpisz notatkę dla zespołu..."
                    class="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-400 focus:ring-amber-400 dark:border-amber-800 dark:bg-gray-900 dark:text-gray-100"
                ></textarea>
                <div class="flex justify-end">
                    <x-filament::button type="button" wire:click="addNote" size="sm" color="warning">
                        Dodaj notatkę
                    </x-filament::button>
                </div>
            </div>
        </div>

        @if($this->notes->isEmpty())
            <div class="rounded-lg border border-dashed border-amber-300 bg-amber-100/40 p-4 text-center text-sm text-amber-900/70 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-200/70">
                Brak notatek — dodaj pierwszą powyżej.
            </div>
        @else
            @php
                $visibleNotes = $historyExpanded ? $this->notes : $this->notes->take(1);
            @endphp

            <div class="space-y-3">
                @foreach($visibleNotes as $index => $note)
                    @php($isLatest = $index === 0)
                    <div
                        wire:key="sticky-note-{{ $note->id }}"
                        @class([
                            'relative rounded-lg border p-4 shadow-sm',
                            'border-amber-300 bg-amber-100/70 dark:border-amber-700 dark:bg-amber-900/30' => $isLatest,
                            'border-amber-200/80 bg-white/70 dark:border-amber-900 dark:bg-gray-900/30' => ! $isLatest,
                        ])
                        style="{{ $isLatest ? 'transform: rotate(-0.4deg);' : '' }}"
                    >
                        <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($isLatest)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                                            Najnowsza
                                        </span>
                                    @endif
                                    <span class="inline-flex rounded-full bg-amber-200/80 px-2 py-0.5 text-[11px] font-medium text-amber-900 dark:bg-amber-900/50 dark:text-amber-100">
                                        {{ $note->category_label }}
                                    </span>
                                </div>
                                <div class="text-xs text-amber-900/70 dark:text-amber-200/70">
                                    {{ $note->author?->name ?? 'Nieznany użytkownik' }}
                                    • {{ $note->created_at?->format('d.m.Y H:i') }}
                                    @if($note->wasEdited())
                                        <span class="italic">(edytowano {{ $note->edited_at?->format('d.m.Y H:i') }})</span>
                                    @endif
                                </div>
                            </div>

                            @if($this->canEditNote($note) && $editingNoteId !== $note->id)
                                <button
                                    type="button"
                                    wire:click="startEdit({{ $note->id }})"
                                    class="rounded-md border border-amber-300 bg-white px-2 py-1 text-xs font-medium text-amber-900 hover:bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100"
                                >
                                    Edytuj
                                </button>
                            @endif
                        </div>

                        @if($editingNoteId === $note->id)
                            <div class="space-y-2">
                                <textarea
                                    wire:model="editBody"
                                    rows="{{ $compact ? 2 : 3 }}"
                                    class="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-400 focus:ring-amber-400 dark:border-amber-800 dark:bg-gray-900 dark:text-gray-100"
                                ></textarea>
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        wire:click="cancelEdit"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                                    >
                                        Anuluj
                                    </button>
                                    <x-filament::button type="button" wire:click="saveEdit" size="xs" color="warning">
                                        Zapisz
                                    </x-filament::button>
                                </div>
                            </div>
                        @else
                            <div class="whitespace-pre-wrap text-sm leading-6 text-gray-900 dark:text-gray-100">{{ $note->body }}</div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if(! $historyExpanded && $this->notes->count() > 1)
                <p class="mt-3 text-xs text-amber-900/70 dark:text-amber-200/70">
                    W stosie jest jeszcze {{ $this->notes->count() - 1 }} starszych notatek.
                </p>
            @endif
        @endif
    </div>
</div>
