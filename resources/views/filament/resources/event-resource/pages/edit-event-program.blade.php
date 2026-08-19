<x-filament-panels::page>
    @vite('resources/js/filament-calendar.js')

    @php
        $filterCounts = $this->getProgramFilterCounts();
    @endphp

    <div class="admin-program-toolbar mb-2 space-y-2" wire:key="event-program-toolbar-{{ $record->id }}">
        <div class="admin-program-toolbar__row">
            <x-filament::tabs class="admin-program-view-tabs !mb-0">
                <x-filament::tabs.item
                    :active="$programView === 'days'"
                    icon="heroicon-o-calendar-days"
                    wire:click="setProgramView('days')"
                >
                    Dzień
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$programView === 'list'"
                    icon="heroicon-o-list-bullet"
                    wire:click="setProgramView('list')"
                >
                    Lista
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$programView === 'planner'"
                    icon="heroicon-o-clock"
                    wire:click="setProgramView('planner')"
                >
                    Planer
                </x-filament::tabs.item>
            </x-filament::tabs>

            @if (in_array($programView, ['days', 'list'], true))
                <div class="admin-program-scope-filters" role="group" aria-label="Zakres punktów programu">
                    <button
                        type="button"
                        wire:click="setProgramFilter('program')"
                        @class([
                            'admin-program-scope-btn',
                            'admin-program-scope-btn--active' => $programFilter === 'program',
                        ])
                    >
                        W programie <span class="admin-program-scope-btn__count">{{ $filterCounts['program'] }}</span>
                    </button>
                    <button
                        type="button"
                        wire:click="setProgramFilter('all')"
                        @class([
                            'admin-program-scope-btn',
                            'admin-program-scope-btn--active' => $programFilter === 'all',
                        ])
                    >
                        Wszystkie <span class="admin-program-scope-btn__count">{{ $filterCounts['all'] }}</span>
                    </button>
                </div>
            @endif
        </div>

        @if ($programView === 'days')
            @php
                $dayTabs = $this->getProgramDayTabs();
            @endphp

            <div class="epp-day-tabs admin-program-day-tabs" role="tablist" aria-label="Dni programu">
                @foreach ($dayTabs as $tab)
                    <button
                        type="button"
                        role="tab"
                        wire:click="setProgramDay({{ $tab['day'] }})"
                        @class([
                            'epp-day-tab',
                            'epp-day-tab--active' => $programDay === $tab['day'],
                        ])
                        aria-selected="{{ $programDay === $tab['day'] ? 'true' : 'false' }}"
                    >
                        <span class="epp-day-tab__label">{{ $tab['label'] }}</span>
                        @if ($tab['date'])
                            <span class="epp-day-tab__date">{{ $tab['date'] }}</span>
                        @endif
                        <span class="epp-day-tab__date">{{ $tab['start_time'] }}</span>
                        <span class="epp-day-tab__count">{{ $tab['count'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="admin-program-day-start flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                @unless ($record->isFacultativeProgramDay($programDay))
                    <div class="min-w-[12rem] flex-1">
                        <label for="program-day-route" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                            Trasa przejazdu — dzień {{ $programDay }}
                        </label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Widoczna w portalu pilota, w zakładce Transport i w pakiecie kierowcy / teczce imprezy.
                        </p>
                    </div>
                    <div class="min-w-[16rem] flex-[2]">
                        <input
                            id="program-day-route"
                            type="text"
                            wire:key="program-day-route-{{ $programDay }}"
                            wire:model="programDayRoute"
                            wire:change="updateProgramDayRoute"
                            placeholder="Wpisz trasę przejazdu"
                            maxlength="500"
                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                @endunless
                <div>
                    <label for="program-day-start-time" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        @if ($record->isFacultativeProgramDay($programDay))
                            Start realizacji — opcje fakultatywne
                        @else
                            Start realizacji programu — dzień {{ $programDay }}
                        @endif
                    </label>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Od tej godziny układane są kolejne punkty (śniadanie, zwiedzanie itd.).
                    </p>
                </div>
                <div class="min-w-[8rem]">
                    <select
                        id="program-day-start-time"
                        wire:key="program-day-start-{{ $programDay }}"
                        wire:model="programDayStartTime"
                        wire:change="updateProgramDayStartTime"
                        class="fi-select-input block w-full rounded-lg border-gray-300 text-sm shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    >
                        @foreach ($this->getProgramDayStartTimeOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($programDayStartTime === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
    </div>

    @if (in_array($programView, ['days', 'list'], true))
        @php
            $relationManagers = $this->getRelationManagers();
        @endphp

        @if (count($relationManagers))
            {{-- Pełny remount RM przy zmianie kontekstu — resetTable() w trakcie x-load tabeli psuje Alpine (selectedRecords, table). --}}
            <div wire:key="event-program-rm-{{ $record->id }}-{{ $programView }}-{{ $programDay }}-{{ $programFilter }}-{{ $programDayStartTime }}-{{ md5($programDayRoute) }}">
                <x-filament-panels::resources.relation-managers
                    :active-manager="array_key_first($relationManagers)"
                    :managers="$relationManagers"
                    :owner-record="$record"
                    :page-class="static::class"
                />
            </div>
        @endif
    @else
        <livewire:event-program-planner
            :event-id="$record->id"
            :key="'event-program-planner-' . $record->id . '-' . $programView"
        />
    @endif
</x-filament-panels::page>
