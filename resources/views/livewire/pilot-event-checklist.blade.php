<div class="sor-lw-stack">
    <div class="sor-lw-card">
        <div class="mb-2 flex items-center justify-between gap-3">
            <h3 class="sor-lw-title">Postęp checklisty</h3>
            <span class="text-sm sor-lw-accent">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
        </div>
        <div class="sor-lw-progress">
            <div class="sor-lw-progress__bar" style="width: {{ $progress['percent'] }}%"></div>
        </div>
    </div>

    @if($canManage)
        <div class="sor-lw-card">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h3 class="sor-lw-title">Załaduj uniwersalny szablon</h3>
                <div class="flex flex-wrap gap-2 text-xs">
                    <a href="{{ $createTemplateUrl }}" target="_blank" rel="noopener" class="sor-lw-link">
                        Utwórz szablon
                    </a>
                    <span class="text-gray-300" aria-hidden="true">|</span>
                    <a href="{{ $templatesUrl }}" target="_blank" rel="noopener" class="sor-lw-link">
                        Zarządzaj szablonami
                    </a>
                </div>
            </div>

            @if($templates->isNotEmpty())
                <p class="mb-3 sor-lw-muted">Wybierz gotowy zestaw punktów. Możesz połączyć kilka szablonów oraz dodać własne punkty do tej imprezy poniżej. Pilot będzie je tylko odznaczał.</p>
                <div class="sor-lw-row sor-lw-row--inline">
                    <select wire:model.live="selectedTemplateId" class="sor-lw-field">
                        <option value="">— wybierz szablon —</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->items_count }})</option>
                        @endforeach
                    </select>
                    <button
                        type="button"
                        wire:click.stop="applyTemplate"
                        @disabled(! $selectedTemplateId)
                        class="sor-lw-btn sor-lw-btn--accent"
                    >
                        Załaduj punkty
                    </button>
                </div>
            @else
                <div class="sor-lw-alert">
                    <p class="font-medium">Brak aktywnych szablonów checklisty.</p>
                    <p class="mt-1 text-xs sor-lw-muted">
                        Utwórz szablon w słowniku (np. „Wyjazd krajowy”) i wróć tutaj, aby załadować punkty do tej imprezy.
                        Możesz też dodać własne punkty ręcznie poniżej.
                    </p>
                    <a
                        href="{{ $createTemplateUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="sor-lw-btn sor-lw-btn--accent mt-3"
                    >
                        Utwórz pierwszy szablon
                    </a>
                </div>
            @endif
        </div>
    @endif

    @if($tasks->isEmpty())
        <div class="sor-lw-empty">
            @if($canManage)
                Checklista jest pusta.
                @if($templates->isNotEmpty())
                    Załaduj szablon powyżej lub dodaj własne punkty.
                @else
                    Utwórz szablon checklisty lub dodaj własne punkty poniżej.
                @endif
            @else
                Checklista nie została jeszcze przygotowana przez biuro.
            @endif
        </div>
    @endif

    <ul class="space-y-2">
        @foreach($tasks as $task)
            @php($isDone = $doneStatusId && (int) $task->status_id === (int) $doneStatusId)
            <li class="sor-lw-task">
                <button
                    type="button"
                    wire:click="toggleTask({{ $task->id }})"
                    @disabled($readOnly)
                    class="sor-lw-check mt-0.5 {{ $isDone ? 'is-done' : '' }}"
                    aria-label="{{ $isDone ? 'Oznacz jako niewykonane' : 'Oznacz jako wykonane' }}"
                >
                    ✓
                </button>
                <div class="min-w-0 flex-1">
                    <p class="sor-lw-task__title {{ $isDone ? 'is-done' : '' }}">
                        {{ $task->title }}
                    </p>
                    @if($task->due_date)
                        <p class="mt-1 sor-lw-muted">Termin: {{ $task->due_date->format('d.m.Y H:i') }}</p>
                    @endif
                </div>
                @if($canManage)
                    <button
                        type="button"
                        wire:click="deleteItem({{ $task->id }})"
                        wire:confirm="Usunąć ten punkt z checklisty?"
                        class="mt-0.5 shrink-0 rounded-lg p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
                        aria-label="Usuń punkt"
                        title="Usuń punkt"
                    >
                        ✕
                    </button>
                @endif
            </li>
        @endforeach
    </ul>

    @if($canManage)
        <form wire:submit.prevent="addItem" class="sor-lw-row sor-lw-row--inline">
            <input
                type="text"
                wire:model.live.debounce.500ms="newItemTitle"
                placeholder="Dodaj własny punkt checklisty..."
                class="sor-lw-field"
            >
            <button type="submit" class="sor-lw-btn sor-lw-btn--accent">
                Dodaj
            </button>
        </form>
    @endif
</div>
