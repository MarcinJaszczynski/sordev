<x-filament-panels::page class="min-h-screen">
    {{-- Enhanced Kanban Board inspired by filament-kanban --}}

    <div class="mb-3 rounded-xl border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
        @include('filament.tasks.ownership-quick-filters', [
            'tasksScope' => $this->tasksScope,
            'dueFilter' => $this->dueFilter,
            'tasksOnlyUrgent' => $this->tasksOnlyUrgent,
            'showFinishedTasks' => $this->showFinishedTasks,
            'sourceFilter' => $this->sourceFilter,
            'showSource' => true,
            'showFinishedToggle' => true,
        ])
    </div>
    
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
        @if($searchTerm || $this->hasActiveTaskQuickFilters() || $priorityFilter || $contextFilter)
        <button wire:click="refreshBoard" class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1 transition mr-2">
            <x-heroicon-m-x-mark class="w-4 h-4" />
            Wyczyść wszystko
        </button>
        @endif

        {{-- Filters Dropdown --}}
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" type="button" class="relative flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition focus:outline-none focus:ring-2 focus:ring-primary-500">
                <x-heroicon-m-funnel class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                Więcej
                @if($priorityFilter || $contextFilter)
                    <span class="absolute top-0 right-0 -mt-1 -mr-1 flex h-3 w-3 items-center justify-center rounded-full bg-primary-600 ring-2 ring-white dark:ring-gray-900"></span>
                @endif
            </button>
            
            <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 top-full mt-2 w-72 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl p-4 z-50 flex flex-col gap-4" style="display: none;">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Priorytet (doprecyzuj)</label>
                    <select wire:model.live="priorityFilter" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                        <option value="">Wszystkie priorytety</option>
                        <option value="urgent">Pilne</option>
                        <option value="normal">Zwykły</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Dotyczy</label>
                    <select wire:model.live="contextFilter" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                        <option value="">Wszystkie konteksty</option>
                        <option value="__unassigned">Wolne / nieprzypisane</option>
                        @foreach($taskableTypes as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
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
<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <button type="button" wire:click="applyQuickFilter('high_priority')" class="text-left rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/60 transition bg-white dark:bg-gray-900">
            <p class="text-xs text-gray-500 dark:text-gray-400">Pilne</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $boardStats['urgent'] ?? $tasks->where('priority', 'urgent')->count() }}</p>
        </button>
        <button type="button" wire:click="applyQuickFilter('overdue')" class="text-left rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/60 transition bg-white dark:bg-gray-900">
            <p class="text-xs text-gray-500 dark:text-gray-400">Po terminie</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $boardStats['overdue'] ?? $tasks->filter(fn($task) => $task->due_date && $task->due_date->isPast())->count() }}</p>
        </button>
    </div>

    {{-- Enhanced Kanban Board using filament-kanban style --}}
    <div class="mb-2 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" title="Zadania pogrupowane według statusu — przeciągaj karty między kolumnami.">Tablica zadań</h3>
    </div>
    <div 
        x-data="{}"
        class="kanban-board flex overflow-x-auto overflow-y-hidden gap-4 pb-4 p-4 rounded-xl"
        style="min-height: 70vh;"
    >
            @foreach ($statuses as $status)
                @php
                    $columnPalette = [
                        [
                            'column' => 'bg-slate-50/60 dark:bg-slate-900/20 border-slate-200 dark:border-slate-800',
                            'header' => 'bg-slate-100/80 dark:bg-slate-900/40',
                            'dot' => 'bg-slate-500',
                            'cardStyle' => 'background-color:#f8fafc;border-color:#64748b;',
                        ],
                        [
                            'column' => 'bg-blue-50/50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-900/40',
                            'header' => 'bg-blue-100/70 dark:bg-blue-900/20',
                            'dot' => 'bg-blue-500',
                            'cardStyle' => 'background-color:#eff6ff;border-color:#3b82f6;',
                        ],
                        [
                            'column' => 'bg-emerald-50/50 dark:bg-emerald-900/10 border-emerald-200 dark:border-emerald-900/40',
                            'header' => 'bg-emerald-100/70 dark:bg-emerald-900/20',
                            'dot' => 'bg-emerald-500',
                            'cardStyle' => 'background-color:#ecfdf5;border-color:#10b981;',
                        ],
                        [
                            'column' => 'bg-amber-50/50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-900/40',
                            'header' => 'bg-amber-100/70 dark:bg-amber-900/20',
                            'dot' => 'bg-amber-500',
                            'cardStyle' => 'background-color:#fffbeb;border-color:#f59e0b;',
                        ],
                        [
                            'column' => 'bg-violet-50/50 dark:bg-violet-900/10 border-violet-200 dark:border-violet-900/40',
                            'header' => 'bg-violet-100/70 dark:bg-violet-900/20',
                            'dot' => 'bg-violet-500',
                            'cardStyle' => 'background-color:#f5f3ff;border-color:#8b5cf6;',
                        ],
                    ];
                    $palette = $columnPalette[$loop->index % count($columnPalette)];
                @endphp

                <div class="kanban-column flex-1 min-w-[16rem] xl:min-w-[18rem] mb-5 lg:min-h-full flex flex-col border rounded-xl p-2 {{ $palette['column'] }} {{ in_array($status->id, $hiddenColumns) ? '!flex-none !min-w-0 xl:!min-w-0 !w-14' : '' }}"> 
                    
                    {{-- Enhanced Column Header inspired by filament-kanban --}}
                    <h3 class="mb-3 px-3 py-2 font-bold text-base flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 {{ $palette['header'] }} {{ in_array($status->id, $hiddenColumns) ? 'mb-0' : '' }}">
                        @if(in_array($status->id, $hiddenColumns))
                            {{-- Kompaktowy nagłówek ukrytej kolumny --}}
                            <button type="button" wire:click="toggleColumn({{ $status->id }})" title="Pokaż kolumnę: {{ $status->name }}"
                                class="flex flex-col items-center gap-1 w-full py-1 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 transition">
                                <span class="h-2.5 w-2.5 rounded-full {{ $palette['dot'] }}"></span>
                                <span class="text-[10px] font-bold" style="writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;">{{ $status->name }}</span>
                                <span class="text-[10px] font-black text-gray-500 dark:text-gray-400 mt-0.5">{{ $tasks->where('status_id', $status->id)->count() }}</span>
                            </button>
                        @else
                            <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ $palette['dot'] }}"></span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $status->name }}</span>
                            <span class="text-xs font-black text-gray-700 dark:text-gray-200 px-2 py-1 rounded-full bg-white/50 dark:bg-black/20">
                                {{ $tasks->where('status_id', $status->id)->count() }}
                            </span>
                        </div>
                        
                        <div class="flex items-center gap-1">
                            {{-- Hide column button --}}
                            <button
                                wire:click="toggleColumn({{ $status->id }})"
                                class="text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 transition-colors p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                title="Ukryj kolumnę">
                                <x-heroicon-m-eye-slash class="w-4 h-4" />
                            </button>
                            {{-- Sort dropdown --}}
                            <div class="relative" x-data="{ open: false }">
                                <button 
                                    @click="open = !open"
                                    class="text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-100 transition-colors p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                    title="Sortuj zadania">
                                    <x-heroicon-m-bars-3-bottom-left class="w-4 h-4" />
                                </button>
                                
                                <div x-show="open" @click.away="open = false" class="absolute right-0 top-8 z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg min-w-48">
                                    <div class="py-1">
                                        <button wire:click="sortColumn({{ $status->id }}, 'activity_desc')" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Aktywność (najnowsza)</button>
                                        <button wire:click="sortColumn({{ $status->id }}, 'activity_asc')" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Aktywność (najstarsza)</button>
                                        <button wire:click="sortColumn({{ $status->id }}, 'priority_desc')" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Priorytet (wysoki → niski)</button>
                                        <button wire:click="sortColumn({{ $status->id }}, 'priority_asc')" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Priorytet (niski → wysoki)</button>
                                        <button wire:click="sortColumn({{ $status->id }}, 'due_date_asc')" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Termin (najwcześniej)</button>
                                        <button wire:click="sortColumn({{ $status->id }}, 'due_date_desc')" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Termin (najpóźniej)</button>
                                        <button wire:click="sortColumn({{ $status->id }}, 'title_asc')" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Tytuł (A-Z)</button>
                                        <button wire:click="sortColumn({{ $status->id }}, 'title_desc')" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Tytuł (Z-A)</button>
                                        <button wire:click="sortColumn({{ $status->id }}, 'created_desc')" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Najnowsze</button>
                                        <button wire:click="sortColumn({{ $status->id }}, 'created_asc')" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Najstarsze</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </h3>

                    {{-- Column progress bar + tasks — hidden when column is collapsed --}}
                    @if(!in_array($status->id, $hiddenColumns))
                    @php
                        $colCount = $tasks->where('status_id', $status->id)->count();
                        $totalCount = max($tasks->count(), 1);
                        $colPercent = round(($colCount / $totalCount) * 100);
                    @endphp
                    @if($colCount > 0)
                    <div class="mx-1 mb-2">
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full transition-all duration-500 {{ $palette['dot'] }}" style="width: {{ $colPercent }}%"></div>
                        </div>
                    </div>
                    @endif

                    {{-- Tasks Container using filament-kanban styling --}}
                    <div 
                        class="tasks-container flex flex-col flex-1 gap-3 p-0"
                        data-status-id="{{ $status->id }}"
                    >
                        @foreach ($tasks->where('status_id', $status->id) as $task)
                            <div 
                                wire:key="kanban-task-{{ $task->id }}"
                                id="{{ $task->id }}" 
                                style="{{ $palette['cardStyle'] }}"
                                wire:click="openEditTaskModal({{ $task->id }})"
                                class="task-card record group px-3 py-3 cursor-pointer transition hover:shadow-md relative rounded-lg border {{ $task->source === \App\Enums\TaskSource::System ? 'border-amber-300/80 bg-amber-50/40 dark:border-amber-500/40 dark:bg-amber-500/5' : '' }}" 
                            >
                                @php
                                    $isOverdueTask = $task->due_date && $task->due_date->isPast();
                                    $titleColorClass = $isOverdueTask
                                        ? 'text-red-700 dark:text-red-300'
                                        : 'text-gray-900 dark:text-gray-100';

                                    $priorityLabel = match (\App\Enums\TaskPriority::normalize($task->priority)) {
                                        'urgent' => 'Pilne',
                                        default => 'Zwykły',
                                    };
                                    $priorityStyles = match (\App\Enums\TaskPriority::normalize($task->priority)) {
                                        'urgent' => 'background-color:#b91c1c;color:#ffffff;border-color:#7f1d1d;',
                                        default => 'background-color:#4b5563;color:#ffffff;border-color:#374151;',
                                    };

                                    $totalSubtasks = $task->subtasks?->count() ?? 0;
                                    $taskComments = $task->relationLoaded('comments')
                                        ? $task->comments->sortBy('created_at')->values()
                                        : collect();
                                    $commentsCount = (int) ($task->comments_count ?? $taskComments->count());
                                    $attachmentsCount = (int) ($task->attachments_count ?? $task->attachments?->count() ?? 0);

                                    $descriptionFull = \App\Support\Tasks\TaskListColumn::sanitizeTaskText($task->description, 5000);
                                    $descriptionPreview = \App\Support\Tasks\TaskListColumn::sanitizeTaskText($task->description, 180);

                                    $contextType = $task->taskable_type_label ?: 'Wolne / nieprzypisane';
                                    $contextRecord = $task->taskable_label;
                                    $contextRecordPreview = \Illuminate\Support\Str::limit($contextRecord, 72);

                                    $latestComment = $taskComments->last();
                                    $latestCommentPreview = $latestComment
                                        ? \App\Support\Tasks\TaskListColumn::sanitizeTaskText($latestComment->content, 140)
                                        : '';
                                    $ownershipLine = \App\Support\Tasks\TaskListColumn::ownershipLine($task);
                                @endphp

                                <div class="flex items-start gap-2 mb-2">
                                    <h4 class="min-w-0 flex-1 break-words font-semibold text-sm leading-5 text-left {{ $titleColorClass }}">
                                        {{ $task->title }}
                                    </h4>
                                    @if ($task->source === \App\Enums\TaskSource::System)
                                        <span class="flex-shrink-0 text-[10px] px-1.5 py-0.5 rounded-full font-bold border border-amber-300 bg-amber-100 text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-200">
                                            System
                                        </span>
                                    @endif
                                    <span class="priority-badge flex-shrink-0 text-[10px] px-1.5 py-0.5 rounded-full font-bold border" style="{{ $priorityStyles }}">
                                        {{ $priorityLabel }}
                                    </span>
                                    <button
                                        type="button"
                                        wire:click.stop
                                        class="task-drag-handle -mr-1 flex-shrink-0 rounded-md p-1 text-gray-400 hover:bg-white/80 hover:text-gray-700 dark:hover:bg-gray-800/80 dark:hover:text-gray-200 cursor-grab"
                                        title="Przeciągnij zadanie"
                                    >
                                        <x-heroicon-m-bars-3 class="w-4 h-4" />
                                    </button>
                                </div>

                                @if($task->parent)
                                    <div class="mb-1.5 flex min-w-0 items-baseline gap-1 text-[11px] leading-tight text-gray-500 dark:text-gray-400">
                                        <span class="shrink-0" aria-hidden="true">↳</span>
                                        <p class="min-w-0 truncate font-medium">
                                            {{ $task->parent->title }}
                                        </p>
                                    </div>
                                @endif

                                @if($descriptionFull !== '')
                                    <div
                                        class="mb-2 relative"
                                        x-data="{ open: false }"
                                        @mouseenter="open = true"
                                        @mouseleave="open = false"
                                    >
                                        <p class="text-xs leading-snug text-gray-700 dark:text-gray-300 line-clamp-2 whitespace-pre-wrap">
                                            {{ $descriptionPreview }}
                                        </p>
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition.opacity.duration.100ms
                                            class="kanban-card-tooltip absolute left-0 bottom-full z-[80] mb-1.5 w-72 max-w-[18rem] rounded-md border border-gray-200 bg-white p-2.5 text-xs leading-snug text-gray-800 shadow-lg dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                            wire:click.stop
                                        >
                                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Treść zadania</div>
                                            <div class="max-h-48 overflow-y-auto whitespace-pre-wrap">{{ $descriptionFull }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if($task->taskable_type && $task->taskable_id)
                                    <div
                                        class="mb-2 relative"
                                        x-data="{ open: false }"
                                        @mouseenter="open = true"
                                        @mouseleave="open = false"
                                    >
                                        <div class="flex items-start gap-1.5 text-[11px] text-sky-700 dark:text-sky-300">
                                            <x-heroicon-m-link class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-sky-500" />
                                            <div class="min-w-0">
                                                <div class="text-[10px] font-semibold uppercase tracking-wide text-sky-600/80 dark:text-sky-400/80">
                                                    {{ $contextType }}
                                                </div>
                                                <span class="line-clamp-2 leading-snug">{{ $contextRecordPreview }}</span>
                                            </div>
                                        </div>
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition.opacity.duration.100ms
                                            class="kanban-card-tooltip absolute left-0 bottom-full z-[80] mb-1.5 w-72 max-w-[18rem] rounded-md border border-gray-200 bg-white p-2.5 text-xs leading-snug text-gray-800 shadow-lg dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                            wire:click.stop
                                        >
                                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $contextType }}</div>
                                            <div class="whitespace-pre-wrap">{{ $contextRecord }}</div>
                                        </div>
                                    </div>
                                @endif

                                <div class="space-y-1 mb-2 text-xs text-gray-600 dark:text-gray-300">
                                    <div class="flex items-center gap-1.5">
                                        <x-heroicon-m-calendar class="w-3.5 h-3.5 flex-shrink-0 text-gray-400" />
                                        @if($task->due_date)
                                            <span class="{{ $isOverdueTask ? 'font-semibold text-red-700 dark:text-red-300' : '' }}">
                                                {{ $task->due_date->format('d.m.Y H:i') }}
                                                @if($isOverdueTask)
                                                    · Po terminie
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-gray-400">Brak terminu</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <x-heroicon-m-user class="w-3.5 h-3.5 flex-shrink-0 text-gray-400" />
                                        <span class="truncate" title="{{ $ownershipLine }}">
                                            {{ $ownershipLine }}
                                        </span>
                                    </div>
                                </div>

                                @if($latestComment)
                                    <div
                                        class="mb-2 relative rounded-md border border-gray-200/80 bg-white/50 px-2 py-1.5 dark:border-gray-700/80 dark:bg-black/20"
                                        x-data="{ open: false }"
                                        @mouseenter="open = true"
                                        @mouseleave="open = false"
                                    >
                                        <div class="text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                                            Ostatni komentarz
                                            @if($commentsCount > 1)
                                                <span class="ml-1 inline-flex items-center rounded-full bg-slate-200 px-1.5 py-0 text-[10px] font-bold text-slate-800 dark:bg-slate-700 dark:text-slate-100">{{ $commentsCount }}</span>
                                            @endif
                                            · {{ $latestComment->author?->name ?? '—' }} · {{ $latestComment->created_at?->format('d.m.Y H:i') ?? '—' }}
                                        </div>
                                        <p class="mt-0.5 text-xs italic leading-snug text-gray-700 dark:text-gray-300 line-clamp-2 whitespace-pre-wrap">
                                            {{ $latestCommentPreview }}
                                        </p>
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition.opacity.duration.100ms
                                            class="kanban-card-tooltip absolute left-0 bottom-full z-[80] mb-1.5 w-80 max-w-[20rem] rounded-md border border-gray-200 bg-white p-2.5 text-xs leading-snug text-gray-800 shadow-lg dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                            wire:click.stop
                                        >
                                            <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                Wątek komentarzy ({{ $commentsCount }})
                                            </div>
                                            <div class="max-h-56 space-y-2 overflow-y-auto">
                                                @foreach($taskComments as $threadComment)
                                                    <div class="border-b border-gray-100 pb-2 last:border-0 last:pb-0 dark:border-gray-700">
                                                        <div class="font-semibold text-gray-600 dark:text-gray-300">
                                                            {{ $threadComment->author?->name ?? '—' }} · {{ $threadComment->created_at?->format('d.m.Y H:i') ?? '—' }}
                                                        </div>
                                                        <div class="mt-0.5 whitespace-pre-wrap text-gray-800 dark:text-gray-100">
                                                            {{ \App\Support\Tasks\TaskListColumn::sanitizeTaskText($threadComment->content, 2000) }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between pt-2 border-t border-gray-200/80 dark:border-gray-700/80">
                                    <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                        <span class="text-xs flex items-center" title="Podzadania">
                                            <x-heroicon-m-squares-plus class="w-3.5 h-3.5 mr-0.5" />
                                            {{ $totalSubtasks }}
                                        </span>
                                        <span class="text-xs flex items-center" title="Komentarze">
                                            <x-heroicon-m-chat-bubble-left-ellipsis class="w-3.5 h-3.5 mr-0.5" />
                                            {{ $commentsCount }}
                                        </span>
                                        <span class="text-xs flex items-center" title="Załączniki">
                                            <x-heroicon-m-paper-clip class="w-3.5 h-3.5 mr-0.5" />
                                            {{ $attachmentsCount }}
                                        </span>
                                    </div>

                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                        @if((int) $task->author_id === (int) ($currentUser?->id ?? 0))
                                        <button 
                                            wire:click.stop="deleteTask({{ $task->id }})"
                                            onclick="return confirm('Czy na pewno chcesz usunąć to zadanie?')"
                                            class="text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-300 transition-colors p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                            title="Usuń zadanie">
                                            <x-heroicon-m-trash class="w-4 h-4" />
                                        </button>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        @endforeach
                        
                        {{-- Empty State --}}
                        @if($tasks->where('status_id', $status->id)->count() === 0)
                            <div class="text-center py-8 text-gray-400 dark:text-gray-500">
                                <x-heroicon-o-inbox class="w-8 h-8 mx-auto mb-2 text-gray-500 dark:text-gray-600" />
                                <p class="text-sm font-medium">Brak zadań</p>
                            </div>
                        @endif
                    </div>

                    {{-- Per-column quick add button --}}
                    <button
                        wire:click="openQuickAddModal({{ $status->id }})"
                        class="mt-2 w-full flex items-center justify-center gap-1.5 px-3 py-2 text-sm text-gray-500 dark:text-gray-400 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-gray-400 dark:hover:border-gray-500 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-white/60 dark:hover:bg-gray-800/50 transition-all duration-150"
                    >
                        <x-heroicon-m-plus class="w-4 h-4" />
                        Dodaj zadanie
                    </button>
                    @endif {{-- end @if(!in_array hidden) --}}
                </div>
            @endforeach
        </div>

    {{-- Quick Add Task Modal --}}
    <x-filament::modal id="quick-add-modal" width="2xl">
        <x-slot name="header">
            <x-filament::modal.heading>
                Szybko dodaj zadanie
            </x-filament::modal.heading>
        </x-slot>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tytuł zadania *</label>
                <input 
                    wire:model.live.debounce.500ms="quickTaskTitle"
                    type="text" 
                    class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                    placeholder="Wprowadź tytuł zadania"
                    required
                />
                @error('quickTaskTitle') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Opis</label>
                <textarea 
                    wire:model.live.debounce.500ms="quickTaskDescription"
                    rows="3" 
                    class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                    placeholder="Wprowadź opis zadania (opcjonalnie)"
                ></textarea>
                @error('quickTaskDescription') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Termin (data i godzina)</label>
                <input
                    wire:model.live.debounce.500ms="quickTaskDueDate"
                    type="datetime-local"
                    class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                />
                @error('quickTaskDueDate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priorytet</label>
                    <select 
                        wire:model.live.debounce.500ms="quickTaskPriority"
                        class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                    >
                        <option value="normal">Zwykły</option>
                        <option value="urgent">Pilne</option>
                    </select>
                    @error('quickTaskPriority') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Przypisz do</label>
                    <select 
                        wire:model.live.debounce.500ms="quickTaskAssigneeId"
                        class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                    >
                        <option value="">Nie przypisano</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('quickTaskAssigneeId') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Powiązane z</label>
                    <select 
                        wire:model.live="quickTaskableType"
                        class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                    >
                        <option value="">Wolne / nieprzypisane</option>
                        @foreach($taskableTypes as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('quickTaskableType') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                @if($quickTaskableType)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Powiązany rekord</label>
                        <select 
                            wire:model.live.debounce.500ms="quickTaskableId"
                            class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                        >
                            <option value="">Wybierz rekord</option>
                            @foreach($quickTaskableRecords as $recordId => $recordLabel)
                                <option value="{{ $recordId }}">{{ $recordLabel }}</option>
                            @endforeach
                        </select>
                        @error('quickTaskableId') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-filament::button color="gray" wire:click="cancelQuickAdd">
                    Anuluj
                </x-filament::button>
                <x-filament::button color="primary" wire:click="createQuickTask">
                    Utwórz zadanie
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    {{-- Enhanced JavaScript with filament-kanban integration --}}
    @include('filament.components.filament-sortable-boot')
    <script>
        (function () {
            const sortableInstances = [];

            const destroySortables = () => {
                sortableInstances.forEach((instance) => instance?.destroy?.());
                sortableInstances.length = 0;
            };

            const initDragAndDrop = () => {
                if (typeof Sortable === 'undefined') {
                    setTimeout(initDragAndDrop, 50);
                    return;
                }

                destroySortables();

                document.querySelectorAll('.tasks-container[data-status-id]').forEach((container) => {
                    const instance = new Sortable(container, {
                        group: 'kanban-tasks',
                        animation: 200,
                        ghostClass: 'sortable-ghost',
                        dragClass: 'sortable-drag',
                        chosenClass: 'sortable-chosen',
                        handle: '.task-drag-handle',
                        forceFallback: false,
                        fallbackTolerance: 0,
                        onStart: (evt) => {
                            document.body.classList.add('grabbing');
                            evt.item.classList.add('shadow-2xl', 'z-50');
                        },
                        onEnd: (evt) => {
                            document.body.classList.remove('grabbing');
                            evt.item.classList.remove('shadow-2xl', 'z-50');

                            const taskId = evt.item.id;
                            const newStatusId = evt.to.dataset.statusId;
                            const newOrder = evt.newIndex;

                            evt.item.classList.add('animate-pulse');
                            setTimeout(() => evt.item.classList.remove('animate-pulse'), 1000);

                            if (window.Livewire && @this) {
                                @this.call('updateTaskStatus', taskId, newStatusId, newOrder)
                                    .catch((error) => console.error('Error updating task status:', error));
                            }
                        },
                        onMove: () => true,
                    });

                    sortableInstances.push(instance);
                });
            };

            const initKeyboardShortcuts = () => {
                document.addEventListener('keydown', (e) => {
                    if (e.ctrlKey && e.key === 'n') {
                        e.preventDefault();
                        window.location.href = '{{ \App\Support\Tasks\TaskNavigation::createUrl($eventFilter ? \App\Models\Event::class : null, $eventFilter) }}';
                    } else if (e.key === 'r' && !e.ctrlKey && !e.altKey) {
                        e.preventDefault();
                        @this.refreshBoard();
                    }
                });
            };

            const boot = () => {
                initDragAndDrop();
                initKeyboardShortcuts();

                if (window.Livewire?.hook) {
                    Livewire.hook('morph.updated', () => {
                        setTimeout(initDragAndDrop, 50);
                    });
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
    </script>

    {{-- Custom Styles inspired by filament-kanban --}}
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        [x-cloak] {
            display: none !important;
        }

        .kanban-card-tooltip {
            pointer-events: none;
        }

        /* Dymki wychodzą poza kartę — podbij stacking przy hoverze, inaczej chowają się pod sąsiadami */
        .kanban-column {
            position: relative;
            z-index: 1;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            border-width: 1px !important;
        }

        .kanban-column:hover,
        .kanban-column:focus-within {
            z-index: 30;
        }

        .task-card {
            z-index: 1;
        }

        .task-card:hover,
        .task-card:focus-within {
            z-index: 40;
        }
        
        .grabbing {
            cursor: grabbing !important;
        }
        
        /* Sortable.js specific styles */
        .sortable-ghost {
            opacity: 0.4 !important;
            background: rgba(59, 130, 246, 0.1) !important;
            border: 2px dashed #3b82f6 !important;
            border-radius: 8px;
        }
        
        .sortable-drag {
            opacity: 0.9 !important;
            background: var(--f-surface) !important;
            border: 2px solid #3b82f6 !important;
            transform: rotate(3deg) scale(1.05) !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5) !important;
            z-index: 9999 !important;
        }
        
        .sortable-chosen {
            transform: scale(1.02) !important;
        }
        
        .sortable-fallback {
            opacity: 0.8 !important;
            background: var(--f-surface) !important;
            border: 2px dashed #3b82f6 !important;
        }
        
        /* Tasks container should accept drops */
        .tasks-container {
            min-height: 100px;
        }
        
        .tasks-container:empty {
            background: repeating-linear-gradient(
                45deg,
                rgba(107, 114, 128, 0.1),
                rgba(107, 114, 128, 0.1) 10px,
                transparent 10px,
                transparent 20px
            );
            border: 2px dashed rgba(107, 114, 128, 0.3);
            border-radius: 8px;
        }
        
        .tasks-container:empty::after {
            content: "Upuść zadanie tutaj";
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100px;
            color: rgba(107, 114, 128, 0.5);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .task-card,
        .record {
            border-width: 1px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }

        .dark .task-card,
        .dark .record {
            border-color: #4b5563;
        }

        .task-card:hover,
        .record:hover {
            border-color: #3b82f6 !important;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.12);
        }

        .priority-badge {
            letter-spacing: 0.02em;
        }
        
        .kanban-board {
            align-items: stretch;
        }
    </style>
</x-filament-panels::page>
