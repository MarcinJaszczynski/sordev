@php
    $activeScope = $tasksScope ?? 'assigned';
    $activeDue = $dueFilter ?? '';
    $activeSource = $sourceFilter ?? 'all';
    $showDue = $showDue ?? true;
    $showUrgent = $showUrgent ?? true;
    $showFinishedToggle = $showFinishedToggle ?? true;
    $showSource = $showSource ?? false;
    $showReset = $showReset ?? true;
    $resetMethod = $resetMethod ?? 'resetTaskQuickFilters';
    $hasActive = $hasActive ?? (method_exists($this, 'hasActiveTaskQuickFilters') ? $this->hasActiveTaskQuickFilters() : false);
    $chipBase = 'rounded-full border px-2 py-0.5 text-xs font-medium leading-5 transition whitespace-nowrap';
    $chipOn = 'border-primary-600 bg-primary-600 text-white';
    $chipOff = 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800';
    $groupLabel = 'text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 whitespace-nowrap';
    $divider = 'hidden sm:block h-4 w-px shrink-0 bg-gray-200 dark:bg-gray-700';
@endphp

<div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
    <div class="inline-flex flex-wrap items-center gap-1">
        <span class="{{ $groupLabel }}">Widoczność</span>
        @foreach([
            'assigned' => ['label' => 'Moje', 'tip' => 'Autor lub przypisane do Ciebie — w tym Twoje kopie zadań systemowych.'],
            'for_me' => ['label' => 'Dla mnie', 'tip' => 'Tylko przypisane do Ciebie.'],
            'authored' => ['label' => 'Zlecone przeze mnie', 'tip' => 'Zadania biurowe, które sam utworzyłeś — także gdy assignee to ktoś inny. Bez zadań systemowych.'],
            'all' => ['label' => 'Wszystkie', 'tip' => 'Wszystkie zadania w tym widoku, także cudze kopie systemowe.'],
        ] as $scope => $meta)
            <button
                type="button"
                wire:click="setTasksScope('{{ $scope }}')"
                title="{{ $meta['tip'] }}"
                class="{{ $chipBase }} {{ $activeScope === $scope ? $chipOn : $chipOff }}"
            >
                {{ $meta['label'] }}
            </button>
        @endforeach
    </div>

    @if($showDue)
        <span class="{{ $divider }}" aria-hidden="true"></span>
        <div class="inline-flex flex-wrap items-center gap-1">
            <span class="{{ $groupLabel }}">Termin</span>
            @foreach([
                'overdue' => ['label' => 'Po terminie', 'tip' => 'Termin już minął.'],
                'today' => ['label' => 'Dziś', 'tip' => 'Termin przypada na dzisiaj.'],
                'this_week' => ['label' => 'Ten tydzień', 'tip' => 'Termin w bieżącym tygodniu (poniedziałek–niedziela).'],
                'has_due_date' => ['label' => 'Z terminem', 'tip' => 'Tylko zadania z ustawioną datą.'],
                'no_due_date' => ['label' => 'Bez terminu', 'tip' => 'Zadania bez daty wykonania.'],
            ] as $due => $meta)
                <button
                    type="button"
                    wire:click="setDueFilter('{{ $due }}')"
                    title="{{ $meta['tip'] }} {{ $activeDue === $due ? '(aktywne — kliknij, aby wyłączyć)' : '' }}"
                    class="{{ $chipBase }} {{ $activeDue === $due ? $chipOn : $chipOff }}"
                >
                    {{ $meta['label'] }}
                </button>
            @endforeach
        </div>
    @endif

    @if($showSource)
        <span class="{{ $divider }}" aria-hidden="true"></span>
        <div class="inline-flex flex-wrap items-center gap-1">
            <span class="{{ $groupLabel }}">Źródło</span>
            @foreach([
                'all' => 'Wszystkie',
                'office' => 'Biuro',
                'system' => 'Systemowe',
            ] as $source => $label)
                <button
                    type="button"
                    wire:click="setSourceFilter('{{ $source }}')"
                    title="{{ match ($source) {
                        'office' => 'Zadania utworzone ręcznie w biurze.',
                        'system' => 'Zadania wygenerowane przez system (osobna kopia per użytkownik).',
                        default => 'Biuro i systemowe (bez checklisty pilota).',
                    } }}"
                    class="{{ $chipBase }} {{ $activeSource === $source ? $chipOn : $chipOff }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    @if($showUrgent || $showFinishedToggle || ($showReset && $hasActive))
        <span class="{{ $divider }}" aria-hidden="true"></span>
        <div class="inline-flex flex-wrap items-center gap-1">
            @if($showUrgent)
                <label class="{{ $chipBase }} {{ $chipOff }} inline-flex cursor-pointer items-center gap-1.5" title="Pokaż tylko zadania oznaczone jako pilne.">
                    <input type="checkbox" wire:model.live="tasksOnlyUrgent" class="rounded border-gray-400" />
                    <span>Tylko pilne</span>
                </label>
            @endif

            @if($showFinishedToggle)
                <label class="{{ $chipBase }} {{ $chipOff }} inline-flex cursor-pointer items-center gap-1.5" title="Domyślnie zakończone i anulowane zadania są ukryte — włącz, aby je pokazać.">
                    <input type="checkbox" wire:model.live="showFinishedTasks" class="rounded border-gray-400" />
                    <span>Zakończone</span>
                </label>
            @endif

            @if($showReset && $hasActive)
                <button
                    type="button"
                    wire:click="{{ $resetMethod }}"
                    class="{{ $chipBase }} {{ $chipOff }}"
                    title="Przywróć domyślny układ filtrów tego widoku."
                >
                    Wyczyść
                </button>
            @endif
        </div>
    @endif
</div>
