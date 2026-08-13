<div class="event-program-day-tree" wire:ignore.self>
    <nav class="epp-day-tabs" aria-label="Dni programu">
        @foreach($dayTabs as $tab)
            <button
                type="button"
                wire:click="setActiveDay({{ $tab['day'] }})"
                @class([
                    'epp-day-tab',
                    'epp-day-tab--active' => (int) $activeDay === (int) $tab['day'],
                ])
            >
                <span class="epp-day-tab__label">Dzień {{ $tab['day'] }}</span>
                @if($tab['date'])
                    <span class="epp-day-tab__date">{{ $tab['date'] }}</span>
                @endif
                <span class="epp-day-tab__count">{{ $tab['count'] }}</span>
            </button>
        @endforeach
    </nav>

    @if(count($selectedPointIds) > 0)
        <div class="epp-bulk-bar">
            <span class="epp-bulk-bar__count">Zaznaczono: {{ count($selectedPointIds) }}</span>
            <div class="epp-bulk-bar__actions">
                <button type="button" wire:click="bulkSetProperty('include_in_program', true)" class="epp-bulk-btn">+ Program</button>
                <button type="button" wire:click="bulkSetProperty('include_in_program', false)" class="epp-bulk-btn">− Program</button>
                <button type="button" wire:click="bulkSetProperty('include_in_calculation', true)" class="epp-bulk-btn">+ Kalkulacja</button>
                <button type="button" wire:click="bulkSetProperty('include_in_calculation', false)" class="epp-bulk-btn">− Kalkulacja</button>
                <button type="button" wire:click="bulkSetProperty('active', true)" class="epp-bulk-btn">+ Aktywne</button>
                <button type="button" wire:click="bulkSetProperty('active', false)" class="epp-bulk-btn">− Aktywne</button>
                <button type="button" wire:click="clearSelection" class="epp-bulk-btn epp-bulk-btn--muted">Wyczyść</button>
            </div>
        </div>
    @endif

    <div class="epp-day-toolbar">
        <label class="epp-select-all">
            <input
                type="checkbox"
                @checked($allDaySelected)
                wire:click="toggleSelectAllDay"
            >
            <span>Zaznacz dzień</span>
        </label>
        <span class="epp-day-toolbar__hint">P — program · K — kalkulacja · A — aktywny</span>
        <button
            type="button"
            wire:click="openAddBlock({{ $activeDay }})"
            class="epp-btn epp-btn--primary"
        >+ Dodaj blok</button>
    </div>

    <div id="event-program-days-container" wire:key="epp-day-panel-{{ $activeDay }}">
        <section class="epp-day-panel" data-day="{{ $activeDay }}">
            @if($activeDayData['blocks']->isEmpty())
                <x-filament.components.sor-empty-state
                    variant="program"
                    heading="Brak punktów w tym dniu"
                    description="Dodaj pierwszy blok programu lub skopiuj punkty z szablonu imprezy."
                    class="mx-2 my-4"
                >
                    <x-slot name="actions">
                        <button type="button" wire:click="openAddBlock({{ $activeDay }})" class="epp-btn epp-btn--primary">
                            Dodaj pierwszy blok
                        </button>
                    </x-slot>
                </x-filament.components.sor-empty-state>
            @else
                <div class="epp-table-head" aria-hidden="true">
                    <div class="epp-tr epp-tr--head">
                        <div class="epp-td epp-td--check"></div>
                        <div class="epp-td epp-td--drag"></div>
                        <div class="epp-td epp-td--order">#</div>
                        <div class="epp-td epp-td--time">Godziny</div>
                        <div class="epp-td epp-td--name">Punkt programu</div>
                        <div class="epp-td epp-td--flags">P · K · A</div>
                        <div class="epp-td epp-td--actions"></div>
                    </div>
                </div>

                <ul class="epp-blocks program-day-list" data-day-id="{{ $activeDay }}">
                    @foreach($activeDayData['blocks'] as $block)
                        @php
                            $parent = $block['parent'];
                            $children = $block['children'];
                            $parentSelected = in_array($parent->id, $selectedPointIds, true);
                        @endphp

                        <li
                            @class([
                                'epp-block program-point-item',
                                'epp-block--set' => $block['is_set'],
                                'epp-block--selected' => $parentSelected,
                            ])
                            data-id="{{ $parent->id }}"
                            wire:key="program-block-{{ $parent->id }}"
                        >
                            @include('livewire.partials.event-program-point-row', [
                                'point' => $parent,
                                'selected' => $parentSelected,
                                'isChild' => false,
                                'isSetParent' => $block['is_set'],
                                'childCount' => $children->count(),
                                'dragClass' => 'drag-handle',
                            ])

                            @if($children->isNotEmpty())
                                <ul class="epp-set-children program-set-children-list" data-parent-id="{{ $parent->id }}">
                                    @foreach($children as $child)
                                        @php $childSelected = in_array($child->id, $selectedPointIds, true); @endphp
                                        <li
                                            @class([
                                                'epp-set-child program-set-child-item',
                                                'epp-block--selected' => $childSelected,
                                            ])
                                            data-id="{{ $child->id }}"
                                            wire:key="program-child-{{ $child->id }}"
                                        >
                                            @include('livewire.partials.event-program-point-row', [
                                                'point' => $child,
                                                'selected' => $childSelected,
                                                'isChild' => true,
                                                'dragClass' => 'drag-handle child-drag',
                                            ])
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    @if($showAddModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4" wire:keydown.escape.window="closeAdd">
            <div class="w-full max-w-xl rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
                <h3 class="mb-4 text-base font-bold text-gray-900 dark:text-gray-100">
                    {{ $addModalMode === 'child' ? 'Dodaj podpunkt do setu' : 'Dodaj blok programu' }}
                </h3>

                <div class="space-y-3">
                    @if($addModalMode === 'block')
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Dzień
                            <select wire:model.live.debounce.500ms="addForm.day" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                                @foreach($dayOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Szukaj w katalogu szablonów
                        <input type="search" wire:model.live.debounce.300ms="templateSearch" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="Min. 2 znaki…">
                    </label>

                    @if($templateResults->isNotEmpty())
                        <ul class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($templateResults as $template)
                                <li>
                                    <button
                                        type="button"
                                        wire:click="selectTemplatePoint({{ $template->id }})"
                                        class="w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800 @if($selectedTemplatePointId === $template->id) bg-primary-50 dark:bg-primary-950/30 @endif"
                                    >
                                        <span class="font-medium">{{ $template->name }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @elseif(strlen(trim($templateSearch)) >= 2)
                        <p class="text-xs text-gray-500">Brak wyników w katalogu.</p>
                    @endif

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Lub nazwa nowego punktu
                        <input type="text" wire:model.live.debounce.500ms="addForm.name" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    </label>

                    @if($addModalMode === 'child' && isset($detachablePointsByDay[$addForm['day'] ?? 1]))
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Lub przypnij istniejący blok
                            <select wire:model.live.debounce.500ms="addForm.existing_point_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="">— wybierz —</option>
                                @foreach($detachablePointsByDay[$addForm['day']] as $candidate)
                                    @if($candidate->id !== $addParentId)
                                        <option value="{{ $candidate->id }}">{{ $candidate->name ?? $candidate->templatePoint?->name ?? ('Punkt #'.$candidate->id) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </label>
                    @endif

                    @error('addForm.name')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeAdd" class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">Anuluj</button>
                    <button type="button" wire:click="saveAdd" class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">Dodaj</button>
                </div>
            </div>
        </div>
    @endif

    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4" wire:keydown.escape.window="closeEdit">
            <div class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-4 text-base font-bold">Edycja punktu programu</h3>
                <div class="space-y-3">
                    <label class="block text-sm font-medium">Nazwa
                        <input type="text" wire:model.live.debounce.500ms="editForm.name" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-medium">Dzień
                        <select wire:model.live.debounce.500ms="editForm.day" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            @foreach($dayOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="editForm.hide_times" class="rounded border-gray-300">
                        Ukryj godziny
                    </label>
                    @if(!($editForm['hide_times'] ?? false))
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="block text-sm">Start
                                <select wire:model.live.debounce.500ms="editForm.start_time" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">—</option>
                                    @foreach($timeSlots as $slot => $slotLabel)
                                        <option value="{{ $slot }}">{{ $slotLabel }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm">Koniec
                                <select wire:model.live.debounce.500ms="editForm.end_time" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">—</option>
                                    @foreach($timeSlots as $slot => $slotLabel)
                                        <option value="{{ $slot }}">{{ $slotLabel }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endif
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live.debounce.500ms="editForm.include_in_program" class="rounded"> Program</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live.debounce.500ms="editForm.include_in_calculation" class="rounded"> Kalkulacja</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live.debounce.500ms="editForm.active" class="rounded"> Aktywny</label>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeEdit" class="rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">Anuluj</button>
                    <button type="button" wire:click="saveEdit" class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white">Zapisz</button>
                </div>
            </div>
        </div>
    @endif

    @include('filament.components.event-program-tree-sortable-boot', ['rootId' => 'event-program-days-container'])

    <x-filament-actions::modals />
</div>
