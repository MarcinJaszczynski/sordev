<?php
$content = file_get_contents('resources/views/filament/resources/task-resource/pages/tasks-kanban-board-page.blade.php');
$startMarker = '{{-- Advanced Filters & Controls --}}';
$endMarker = '<div class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-3">';

$startPos = strpos($content, $startMarker);
$endPos = strpos($content, $endMarker);

if ($startPos !== false && $endPos !== false) {
    $before = substr($content, 0, $startPos);
    $after = substr($content, $endPos);
    
    $newMiddle = <<<'HTML'
    {{-- Advanced Filters & Controls (Compact) --}}
    <div class="mb-4 flex flex-col md:flex-row items-center justify-between gap-4">
        {{-- Search Bar --}}
        <div class="w-full md:w-80 relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <x-heroicon-m-magnifying-glass class="h-5 w-5 text-gray-400 dark:text-gray-500" />
            </div>
            <input 
                wire:model.live="searchTerm" 
                type="text" 
                placeholder="Szukaj zadań..." 
                class="block w-full pl-10 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm transition duration-75"
            />
        </div>

        {{-- Actions / Filters --}}
        <div class="flex items-center gap-2 w-full md:w-auto justify-end">
            {{-- Reset --}}
            @if($searchTerm || $filterBy || $priorityFilter || $contextFilter || $dueFilter)
            <button wire:click="refreshBoard" class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1 transition mr-2">
                <x-heroicon-m-x-mark class="w-4 h-4" />
                Wyczyść filtry
            </button>
            @endif

            {{-- Filters Dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" type="button" class="relative flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <x-heroicon-m-funnel class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                    Filtry
                    @if($filterBy || $priorityFilter || $contextFilter || $dueFilter)
                        <span class="absolute top-0 right-0 -mt-1 -mr-1 flex h-3 w-3 items-center justify-center rounded-full bg-primary-600 ring-2 ring-white dark:ring-gray-900"></span>
                    @endif
                </button>
                
                <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 top-full mt-2 w-72 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl p-4 z-50 flex flex-col gap-4" style="display: none;">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Przypisanie</label>
                        <select wire:model.live="filterBy" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">Wszystkie zadania</option>
                            <option value="author">Moje zadania</option>
                            <option value="assignee">Przypisane do mnie</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Priorytet</label>
                        <select wire:model.live="priorityFilter" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">Wszystkie priorytety</option>
                            <option value="high">Wysoki</option>
                            <option value="medium">Średni</option>
                            <option value="low">Niski</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Kontekst</label>
                        <select wire:model.live="contextFilter" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">Wszystkie konteksty</option>
                            <option value="__unassigned">Wolne / nieprzypisane</option>
                            @foreach($taskableTypes as $type => $label)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Termin</label>
                        <select wire:model.live="dueFilter" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">Wszystkie terminy</option>
                            <option value="has_due_date">Tylko z terminem</option>
                            <option value="overdue">Tylko po terminie</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Kolumny Dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" type="button" class="relative flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <x-heroicon-m-view-columns class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                    Kolumny
                </button>
                <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 top-full mt-2 min-w-48 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl p-2 z-50 flex flex-col gap-1" style="display: none;">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-gray-500 dark:text-gray-400 mb-1 px-2">Widoczność</p>
                    @foreach ($statuses as $status)
                        @php $isHidden = in_array($status->id, $hiddenColumns); @endphp
                        <button type="button" wire:click="toggleColumn({{ $status->id }})" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs transition hover:bg-gray-100 dark:hover:bg-gray-800 {{ $isHidden ? 'text-gray-400 dark:text-gray-500' : 'text-gray-800 dark:text-gray-200' }}">
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded border {{ $isHidden ? 'border-gray-300 dark:border-gray-600' : 'border-primary-500 bg-primary-500' }}">
                                @if(!$isHidden)
                                    <svg class="h-2.5 w-2.5 text-white" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                @endif
                            </span>
                            {{ $status->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    
    HTML;
    
    file_put_contents('resources/views/filament/resources/task-resource/pages/tasks-kanban-board-page.blade.php', $before . $newMiddle . $after);
    echo "Replaced successfully!\n";
} else {
    echo "Markers not found!\n";
}
?>
