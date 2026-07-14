<x-filament-panels::page class="min-h-screen">
    {{-- Enhanced Kanban Board inspired by filament-kanban --}}

    @include('filament.tasks.ownership-quick-filters', ['tasksScope' => $this->tasksScope])

    <div class="mb-4"></div>
    
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
        @if($searchTerm || $tasksScope !== 'assigned' || $priorityFilter || $contextFilter || $dueFilter || $showFinishedTasks)
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
                @if($tasksScope !== 'assigned' || $priorityFilter || $contextFilter || $dueFilter || $showFinishedTasks)
                    <span class="absolute top-0 right-0 -mt-1 -mr-1 flex h-3 w-3 items-center justify-center rounded-full bg-primary-600 ring-2 ring-white dark:ring-gray-900"></span>
                @endif
            </button>
            
            <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 top-full mt-2 w-72 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl p-4 z-50 flex flex-col gap-4" style="display: none;">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Priorytet</label>
                    <select wire:model.live="priorityFilter" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                        <option value="">Wszystkie priorytety</option>
                        <option value="urgent">Pilne</option>
                        <option value="normal">Domyślny</option>
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
                <div>
                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="showFinishedTasks" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500" />
                        Pokaż zakończone i anulowane
                    </label>
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
<div class="mb-6 grid grid-cols-2 lg:grid-cols-2 gap-3">
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
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Tablica Kanban</h3>
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
                                class="task-card record group px-4 py-4 cursor-pointer transition hover:shadow-xl transform hover:-translate-y-1 relative" 
                            >
                                <button
                                    type="button"
                                    wire:click.stop
                                    class="task-drag-handle absolute right-2 top-2 z-10 rounded-md p-1 text-gray-400 hover:bg-white/80 hover:text-gray-700 dark:hover:bg-gray-800/80 dark:hover:text-gray-200 cursor-grab"
                                    title="Przeciągnij zadanie"
                                >
                                    <x-heroicon-m-bars-3 class="w-4 h-4" />
                                </button>
                                @php
                                    $statusName = mb_strtolower($task->status?->name ?? '');
                                    $isNewTask = str_contains($statusName, 'nowe') || str_contains($statusName, 'nowy');
                                    $isInProgressTask = str_contains($statusName, 'w trakcie');
                                    $isOverdueTask = $task->due_date && $task->due_date->isPast();
                                    $hasRecentComment = $task->comments?->contains(fn ($comment) => $comment->created_at && now()->diffInHours($comment->created_at, true) <= 24) ?? false;
                                    $isRecentlyUpdated = $task->updated_at && now()->diffInHours($task->updated_at, true) <= 24;
                                    $isRecentlyActive = $hasRecentComment || $isRecentlyUpdated;

                                    $titleColorClass = 'text-gray-900 dark:text-gray-100';

                                    if ($isOverdueTask) {
                                        $titleColorClass = 'text-red-700 dark:text-red-300';
                                    } elseif ($isRecentlyActive) {
                                        $titleColorClass = 'text-orange-700 dark:text-orange-300';
                                    } elseif ($isInProgressTask) {
                                        $titleColorClass = 'text-blue-700 dark:text-blue-300';
                                    } elseif ($isNewTask) {
                                        $titleColorClass = 'text-green-700 dark:text-green-300';
                                    }

                                    $priorityLabel = match (\App\Enums\TaskPriority::normalize($task->priority)) {
                                        'urgent' => 'Pilne',
                                        default => 'Domyślny',
                                    };
                                    $priorityStyles = match (\App\Enums\TaskPriority::normalize($task->priority)) {
                                        'urgent' => 'background-color:#b91c1c;color:#ffffff;border-color:#7f1d1d;',
                                        default => 'background-color:#4b5563;color:#ffffff;border-color:#374151;',
                                    };

                                    $latestComment = $task->comments?->sortByDesc('created_at')->first();
                                    $latestAttachmentAt = $task->attachments?->sortByDesc('created_at')->first()?->created_at;
                                    $latestSubtaskAt = $task->subtasks?->sortByDesc('updated_at')->first()?->updated_at;

                                    $latestActivity = collect([
                                        ['label' => 'zadanie', 'at' => $task->updated_at ?: $task->created_at],
                                        ['label' => 'komentarz', 'at' => $latestComment?->created_at],
                                        ['label' => 'załącznik', 'at' => $latestAttachmentAt],
                                        ['label' => 'podzadanie', 'at' => $latestSubtaskAt],
                                    ])
                                        ->filter(fn (array $item): bool => filled($item['at']))
                                        ->sortByDesc(fn (array $item): int => $item['at']->timestamp)
                                        ->first();

                                    $attachments = $task->attachments?->sortByDesc('created_at') ?? collect();
                                    $attachmentPreview = $attachments->take(3);

                                    $completedSubtasks = $task->subtasks?->where('status.name', 'Zakończone')->count() ?? 0;
                                    $totalSubtasks = $task->subtasks?->count() ?? 0;
                                    $progressPercent = $totalSubtasks > 0 ? round(($completedSubtasks / $totalSubtasks) * 100) : 0;
                                @endphp

                                {{-- 1) Tytuł + priorytet --}}
                                <div class="flex items-start justify-between gap-3 mb-2 pr-8">
                                    <h4 class="font-bold text-base leading-5 flex-1 text-left {{ $titleColorClass }}">
                                        {{ $task->title }}
                                    </h4>
                                    <span class="priority-badge flex-shrink-0 text-xs px-2 py-1 rounded-full font-bold border" style="{{ $priorityStyles }}">
                                        {{ $priorityLabel }}
                                    </span>
                                </div>

                                @if($task->parent)
                                    <div class="mb-2 text-xs font-semibold text-indigo-700 dark:text-indigo-300 px-2 py-1 rounded-md bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800">
                                        Podzadanie: {{ $task->parent->title }}
                                    </div>
                                @endif

                                <div class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                                    Utworzono: {{ $task->created_at?->format('d.m.Y H:i') ?? '—' }}
                                    @if($latestComment?->created_at)
                                        · Ostatni komentarz: {{ $latestComment->created_at->format('d.m.Y H:i') }}
                                    @endif
                                </div>

                                {{-- 2) Skrócony opis --}}
                                @if($task->description)
                                    @php
                                        $rawDescription = (string) $task->description;
                                        $descriptionHasHtml = $rawDescription !== strip_tags($rawDescription);
                                        $descriptionPreviewHtml = $descriptionHasHtml
                                            ? strip_tags($rawDescription, '<p><br><strong><b><em><i><u><ul><ol><li><blockquote>')
                                            : nl2br(e(\Illuminate\Support\Str::limit($rawDescription, 420)));
                                    @endphp
                                    <div class="mb-3 relative">
                                        <div class="text-[16px] leading-7 font-semibold text-gray-950 dark:text-gray-50 max-h-32 overflow-hidden [&_strong]:font-black [&_b]:font-black [&_em]:italic [&_i]:italic [&_u]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:mb-0.5 [&_blockquote]:border-l-2 [&_blockquote]:border-gray-300 dark:[&_blockquote]:border-gray-600 [&_blockquote]:pl-3 [&_p]:mb-1">
                                            {!! $descriptionPreviewHtml !!}
                                        </div>
                                        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-7 bg-gradient-to-t from-transparent via-white/70 to-transparent dark:via-gray-900/70"></div>
                                    </div>
                                @endif

                                {{-- 3) Do kiedy --}}
                                <div class="mb-2 text-xs text-gray-700 dark:text-gray-200 px-3 py-2 rounded-lg bg-white/50 dark:bg-black/20">
                                    <span class="font-semibold">Do kiedy:</span>
                                    @if($task->due_date)
                                        <span class="ml-1 font-medium">{{ $task->due_date->format('d.m.Y H:i') }}</span>
                                        @if($task->due_date->isPast())
                                            <span class="ml-2 font-bold text-red-700 dark:text-red-300">Po terminie</span>
                                        @endif
                                    @else
                                        <span class="ml-1">Brak terminu</span>
                                    @endif
                                </div>

                                {{-- 4) Dla kogo --}}
                                <div class="mb-3 text-xs text-gray-700 dark:text-gray-200 px-3 py-2 rounded-lg bg-white/50 dark:bg-black/20">
                                    <span class="font-semibold">Dla kogo:</span>
                                    <span class="ml-1 font-medium">{{ $task->assignee?->name ?? 'Nie przypisano' }}</span>
                                </div>

                                {{-- 6) Ostatnia aktywność --}}
                                @if($latestActivity)
                                    <div class="mb-3 text-xs text-orange-700 dark:text-orange-200 rounded-lg border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-900/30 px-3 py-2 font-semibold">
                                        <x-heroicon-m-bolt class="w-3.5 h-3.5" />
                                        <span class="ml-1">Ostatnia aktywność: {{ ucfirst($latestActivity['label']) }} {{ $latestActivity['at']->diffForHumans() }}</span>
                                    </div>
                                @endif

                                {{-- 7) Ostatni komentarz --}}
                                @if($latestComment)
                                    @php
                                        $rawComment = (string) ($latestComment->content ?? '');
                                        $commentHasHtml = $rawComment !== strip_tags($rawComment);
                                        $commentPreviewHtml = $commentHasHtml
                                            ? strip_tags($rawComment, '<p><br><strong><b><em><i><u><ul><ol><li><blockquote>')
                                            : nl2br(e(\Illuminate\Support\Str::limit($rawComment, 360)));
                                    @endphp
                                    <div class="mb-3 rounded-xl border-2 border-emerald-300 dark:border-emerald-700 px-3 py-3 bg-emerald-50 dark:bg-emerald-900/30 shadow-md ring-2 ring-emerald-200/70 dark:ring-emerald-900/40">
                                        <div class="flex items-center justify-between gap-2 mb-1.5">
                                            <span class="text-[11px] uppercase tracking-[0.14em] font-black text-emerald-900 dark:text-emerald-100">Ostatni komentarz</span>
                                            <span class="text-xs font-semibold text-emerald-800 dark:text-emerald-200">{{ $latestComment->created_at?->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-sm font-black text-emerald-950 dark:text-emerald-50 mb-2">{{ $latestComment->author?->name ?? 'Nieznany autor' }}</p>
                                        <div class="relative rounded-lg border-l-4 border-emerald-500 dark:border-emerald-400 bg-white/90 dark:bg-emerald-950/40 pl-3 pr-2 py-2">
                                            <div class="text-[16px] leading-7 font-semibold text-emerald-950 dark:text-emerald-50 max-h-32 overflow-hidden [&_strong]:font-black [&_b]:font-black [&_em]:italic [&_i]:italic [&_u]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:mb-0.5 [&_blockquote]:border-l-2 [&_blockquote]:border-emerald-300 dark:[&_blockquote]:border-emerald-700 [&_blockquote]:pl-3 [&_p]:mb-1">
                                                {!! $commentPreviewHtml !!}
                                            </div>
                                            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-7 bg-gradient-to-t from-emerald-50/95 via-emerald-50/70 to-transparent dark:from-emerald-950/95 dark:via-emerald-950/70"></div>
                                        </div>
                                    </div>
                                @endif

                                {{-- 8) Bezpośrednie linki do plików --}}
                                @if($attachmentPreview->isNotEmpty())
                                    <div class="mb-3">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Powiązane pliki:</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($attachmentPreview as $attachment)
                                                @if($attachment->public_url)
                                                    <a
                                                        href="{{ $attachment->public_url }}"
                                                        target="_blank"
                                                        rel="noopener"
                                                        wire:click.stop
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-md border border-purple-200 dark:border-purple-800 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-200 hover:bg-purple-100 dark:hover:bg-purple-800/50 transition"
                                                    >
                                                        <x-heroicon-m-paper-clip class="w-3 h-3" />
                                                        {{ \Illuminate\Support\Str::limit($attachment->filename, 28) }}
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- 9) Podzadania X/Y --}}
                                @if($totalSubtasks > 0)
                                    <div class="mb-3 px-3 py-2 rounded-lg bg-white/50 dark:bg-black/20">
                                        <div class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-200 mb-1.5">
                                            <span class="font-semibold">Podzadania {{ $completedSubtasks }}/{{ $totalSubtasks }}</span>
                                            <span class="text-xs font-bold text-green-700 dark:text-green-400">{{ $progressPercent }}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 border border-gray-300 dark:border-gray-600">
                                            <div class="bg-green-500 dark:bg-green-400 h-1.5 rounded-full transition-all duration-300" style="width: {{ $progressPercent }}%"></div>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs text-gray-500 flex items-center">
                                            <x-heroicon-m-squares-plus class="w-3.5 h-3.5 mr-1" />
                                            {{ $totalSubtasks }}
                                        </span>
                                        <span class="text-xs text-gray-500 flex items-center">
                                            <x-heroicon-m-chat-bubble-left-ellipsis class="w-3.5 h-3.5 mr-1" />
                                            {{ $task->comments->count() ?? 0 }}
                                        </span>
                                        <span class="text-xs text-gray-500 flex items-center">
                                            <x-heroicon-m-paper-clip class="w-3.5 h-3.5 mr-1" />
                                            {{ $attachments->count() ?? 0 }}
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

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priorytet</label>
                    <select 
                        wire:model.live.debounce.500ms="quickTaskPriority"
                        class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                    >
                        <option value="normal">Domyślny</option>
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
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kontekst zadania</label>
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
                        window.location.href = '{{ \App\Filament\Resources\TaskResource::getUrl("create") }}';
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
        
        .kanban-column {
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12), 0 10px 24px rgba(15, 23, 42, 0.08);
            border-width: 2px !important;
        }

        .task-card,
        .record {
            border-width: 3px;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
        }

        .dark .task-card,
        .dark .record {
            border-color: #9ca3af;
        }

        .task-card:hover,
        .record:hover {
            border-color: #1d4ed8 !important;
            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.28);
        }

        .priority-badge {
            letter-spacing: 0.03em;
            box-shadow: inset 0 -1px 0 rgba(0, 0, 0, 0.12);
        }
        
        .kanban-board {
            align-items: stretch;
        }
    </style>
</x-filament-panels::page>
