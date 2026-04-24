<x-filament-panels::page class="min-h-screen">
    {{-- Enhanced Kanban Board inspired by filament-kanban --}}
    
    {{-- Advanced Filters & Controls --}}
    <div class="mb-4 flex flex-col gap-4 p-4 rounded-lg f-kanban-controls sticky top-0 z-30 backdrop-blur bg-white/95 dark:bg-gray-900/95 border border-gray-200 dark:border-gray-700">
        <div class="flex flex-wrap gap-2">
            <input 
                wire:model.live="searchTerm" 
                type="text" 
                placeholder="Szukaj zadań po tytule lub opisie"
                class="fi-input block w-full border py-2 px-3 text-base outline-none transition duration-75 placeholder:text-gray-400 sm:text-sm sm:leading-6 rounded-lg shadow-sm"
            />
            
            <select wire:model.live="filterBy" class="fi-select-input block w-full border py-2 pe-8 ps-3 text-base outline-none transition duration-75 sm:text-sm sm:leading-6 rounded-lg shadow-sm">
                <option value="">Wszystkie zadania</option>
                <option value="author">Moje zadania</option>
                <option value="assignee">Przypisane do mnie</option>
            </select>
            
            <select wire:model.live="priorityFilter" class="fi-select-input block w-full border py-2 pe-8 ps-3 text-base outline-none transition duration-75 sm:text-sm sm:leading-6 rounded-lg shadow-sm">
                <option value="">Wszystkie priorytety</option>
                <option value="high">Wysoki</option>
                <option value="medium">Średni</option>
                <option value="low">Niski</option>
            </select>

            <select wire:model.live="contextFilter" class="fi-select-input block w-full border py-2 pe-8 ps-3 text-base outline-none transition duration-75 sm:text-sm sm:leading-6 rounded-lg shadow-sm">
                <option value="">Wszystkie konteksty</option>
                <option value="__unassigned">Wolne / nieprzypisane</option>
                @foreach($taskableTypes as $type => $label)
                    <option value="{{ $type }}">{{ $label }}</option>
                @endforeach
            </select>

            <select wire:model.live="dueFilter" class="fi-select-input block w-full border py-2 pe-8 ps-3 text-base outline-none transition duration-75 sm:text-sm sm:leading-6 rounded-lg shadow-sm">
                <option value="">Wszystkie terminy</option>
                <option value="has_due_date">Tylko z terminem</option>
                <option value="overdue">Tylko po terminie</option>
            </select>
        </div>
        
        <div class="flex gap-2 items-center">
            <button wire:click="refreshBoard" 
                class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm">
                <x-heroicon-m-arrow-path class="w-4 h-4" />
                Odśwież
            </button>
            <button wire:click="openQuickAddModal(null)"
                class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm">
                <x-heroicon-m-plus class="w-4 h-4" />
                Dodaj zadanie
            </button>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-3">
        <button type="button" wire:click="applyQuickFilter('all')" class="text-left rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/60 transition">
            <p class="text-xs text-gray-500 dark:text-gray-400">Wszystkie</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $tasks->count() }}</p>
        </button>
        <button type="button" wire:click="applyQuickFilter('assigned_to_me')" class="text-left rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/60 transition">
            <p class="text-xs text-gray-500 dark:text-gray-400">Przypisane do mnie</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $tasks->where('assignee_id', $currentUser?->id)->count() }}</p>
        </button>
        <button type="button" wire:click="applyQuickFilter('high_priority')" class="text-left rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/60 transition">
            <p class="text-xs text-gray-500 dark:text-gray-400">Wysoki priorytet</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $tasks->where('priority', 'high')->count() }}</p>
        </button>
        <button type="button" wire:click="applyQuickFilter('overdue')" class="text-left rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/60 transition">
            <p class="text-xs text-gray-500 dark:text-gray-400">Po terminie</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $tasks->filter(fn($task) => $task->due_date && $task->due_date->isPast())->count() }}</p>
        </button>
    </div>

    <div x-data="{ openCalendar: true, openKanban: true }" class="space-y-4">
    <div class="mb-0 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Kalendarz zadań (terminy)</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">Widoczne są zadania z ustawioną datą wykonania. Kliknij dzień, aby szybko dodać zadanie.</span>
            </div>
            <button type="button" @click="openCalendar = !openCalendar" class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300">
                <span x-text="openCalendar ? 'Zwiń' : 'Rozwiń'"></span>
            </button>
        </div>
        <div
            x-show="openCalendar"
            x-collapse
            wire:key="task-calendar-{{ md5(json_encode($calendarEvents ?? [])) }}"
            x-data="taskCalendarWidget({ events: @js($calendarEvents ?? []) })"
            x-init="init()"
            x-effect="if (openCalendar) { ensureVisible() }"
            class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-2"
        >
            <div x-ref="calendar" style="min-height: 520px;"></div>
        </div>
    </div>

    {{-- Enhanced Kanban Board using filament-kanban style --}}
    <div class="mb-2 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Tablica Kanban</h3>
        <button type="button" @click="openKanban = !openKanban" class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300">
            <span x-text="openKanban ? 'Zwiń' : 'Rozwiń'"></span>
        </button>
    </div>
    <div 
        x-show="openKanban"
        x-collapse
        x-data="kanbanBoard()" 
        x-init="init()" 
        class="kanban-board flex overflow-x-auto overflow-y-hidden gap-4 pb-4 p-4 rounded-xl f-kanban-root"
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

            <div class="kanban-column flex-1 min-w-[16rem] mb-5 md:min-h-full flex flex-col f-kanban-column border rounded-xl p-2 {{ $palette['column'] }}"> 
                
                {{-- Enhanced Column Header inspired by filament-kanban --}}
                <h3 class="kanban-column-header mb-3 px-3 py-2 font-bold text-base flex items-center justify-between rounded-lg f-kanban-column-header {{ $palette['header'] }}">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full {{ $palette['dot'] }}"></span>
                        <span class="text-gray-900 dark:text-gray-100">{{ $status->name }}</span>
                        <span class="text-xs font-black text-gray-700 dark:text-gray-200 px-2 py-1 rounded-full f-kanban-count">
                            {{ $tasks->where('status_id', $status->id)->count() }}
                        </span>
                    </div>
                    
                    <div class="flex items-center gap-1">
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
                </h3>

                {{-- Column progress bar --}}
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
                            id="{{ $task->id }}" 
                            wire:click="editTask({{ $task->id }})"
                            x-data="{}"
                            style="{{ $palette['cardStyle'] }}"
                            class="task-card record group f-kanban-task px-4 py-4 cursor-grab transition hover:shadow-xl transform hover:-translate-y-1 relative" 
                            @if($task->updated_at && now()->diffInSeconds($task->updated_at, true) < 3)
                                x-data
                                x-init="
                                    $el.classList.add('animate-pulse-twice', 'bg-blue-800', 'border-blue-400')
                                    $el.classList.remove('bg-gray-700', 'border-gray-600')
                                    setTimeout(() => {
                                        $el.classList.remove('bg-blue-800', 'dark:bg-blue-900', 'border-blue-400', 'dark:border-blue-500')
                                        $el.classList.add('bg-gray-700', 'dark:bg-gray-800', 'border-gray-600', 'dark:border-gray-700')
                                    }, 3000)
                                "
                            @endif
                        >
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

                                $priorityLabel = match ($task->priority) {
                                    'low' => 'Niski',
                                    'medium' => 'Średni',
                                    'high' => 'Wysoki',
                                    default => 'Brak priorytetu',
                                };
                                $priorityStyles = match ($task->priority) {
                                    'low' => 'background-color:#166534;color:#ffffff;border-color:#14532d;',
                                    'medium' => 'background-color:#b45309;color:#ffffff;border-color:#78350f;',
                                    'high' => 'background-color:#b91c1c;color:#ffffff;border-color:#7f1d1d;',
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
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <h4 class="font-bold text-base leading-5 flex-1 {{ $titleColorClass }}">
                                    {{ $task->title }}
                                </h4>
                                <span class="priority-badge flex-shrink-0 text-xs px-2 py-1 rounded-full font-bold border" style="{{ $priorityStyles }}">
                                    {{ $priorityLabel }}
                                </span>
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
                                    <div class="text-[13px] leading-6 font-medium text-black dark:text-gray-100 max-h-28 overflow-hidden [&_strong]:font-black [&_b]:font-black [&_em]:italic [&_i]:italic [&_u]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:mb-0.5 [&_blockquote]:border-l-2 [&_blockquote]:border-gray-300 dark:[&_blockquote]:border-gray-600 [&_blockquote]:pl-3 [&_p]:mb-1">
                                        {!! $descriptionPreviewHtml !!}
                                    </div>
                                    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-7 bg-gradient-to-t from-transparent via-white/70 to-transparent dark:via-gray-900/70"></div>
                                </div>
                            @endif

                            {{-- 3) Do kiedy --}}
                            <div class="mb-2 text-xs text-gray-700 dark:text-gray-200 px-3 py-2 rounded-lg f-kanban-pill">
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
                            <div class="mb-3 text-xs text-gray-700 dark:text-gray-200 px-3 py-2 rounded-lg f-kanban-pill">
                                <span class="font-semibold">Dla kogo:</span>
                                <span class="ml-1 font-medium">{{ $task->assignee?->name ?? 'Nie przypisano' }}</span>
                            </div>

                            {{-- 5) Bezpośrednie linki do kontekstu --}}
                            @if(!empty($taskContextTrees[$task->id]))
                                <div class="mb-3">
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Dotyczy:</p>
                                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                    @foreach($taskContextTrees[$task->id] as $node)
                                        @if(!empty($node['url']))
                                            <a
                                                href="{{ $node['url'] }}"
                                                target="_blank"
                                                rel="noopener"
                                                wire:click.stop
                                                class="inline-flex items-center px-2 py-1 rounded-md border border-cyan-200 dark:border-cyan-800 bg-cyan-50 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-200 hover:bg-cyan-100 dark:hover:bg-cyan-800/60 transition"
                                            >
                                                {{ $node['label'] }}
                                            </a>
                                        @else
                                            <span class="inline-flex items-center px-2 py-1 rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200">{{ $node['label'] }}</span>
                                        @endif

                                        @if(! $loop->last)
                                            <span class="text-gray-400">&gt;</span>
                                        @endif
                                    @endforeach
                                    </div>
                                </div>
                            @endif

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
                                <div class="mb-3 rounded-xl border border-emerald-200 dark:border-emerald-800 px-3 py-2.5 bg-emerald-50/85 dark:bg-emerald-900/20 shadow-sm ring-1 ring-emerald-200/50 dark:ring-emerald-900/30">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <span class="text-[10px] uppercase tracking-[0.12em] font-black text-emerald-800 dark:text-emerald-200">Ostatni komentarz</span>
                                        <span class="text-xs text-emerald-700/80 dark:text-emerald-300/90">{{ $latestComment->created_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-xs font-bold text-emerald-900 dark:text-emerald-100 mb-1.5">{{ $latestComment->author?->name ?? 'Nieznany autor' }}</p>
                                    <div class="relative rounded-lg border-l-4 border-emerald-400/80 dark:border-emerald-500/80 bg-white/75 dark:bg-emerald-950/25 pl-3 pr-2 py-1.5">
                                        <div class="text-[13px] leading-6 font-medium text-emerald-900 dark:text-emerald-50 max-h-28 overflow-hidden [&_strong]:font-black [&_b]:font-black [&_em]:italic [&_i]:italic [&_u]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:mb-0.5 [&_blockquote]:border-l-2 [&_blockquote]:border-emerald-300 dark:[&_blockquote]:border-emerald-700 [&_blockquote]:pl-3 [&_p]:mb-1">
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

                                        @if($attachments->count() > 3)
                                            <button
                                                type="button"
                                                wire:click.stop="showAttachments({{ $task->id }})"
                                                class="inline-flex items-center px-2 py-1 text-xs rounded-md border border-gray-300 dark:border-gray-600 bg-white/80 dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                            >
                                                +{{ $attachments->count() - 3 }} więcej
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- 9) Podzadania X/Y --}}
                            @if($totalSubtasks > 0)
                                <div class="mb-3 px-3 py-2 rounded-lg f-kanban-pill">
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
                                    <button
                                        wire:click.stop="showSubtasks({{ $task->id }})"
                                        class="text-xs text-gray-700 dark:text-gray-300 flex items-center px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white/90 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        title="Pokaż podzadania"
                                    >
                                        <x-heroicon-m-squares-plus class="w-3.5 h-3.5 mr-1" />
                                        Podzadania
                                    </button>
                                    <button
                                        wire:click.stop="showComments({{ $task->id }})"
                                        class="text-xs text-gray-700 dark:text-gray-300 flex items-center px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white/90 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        title="Pokaż komentarze"
                                    >
                                        <x-heroicon-m-chat-bubble-left-ellipsis class="w-3.5 h-3.5 mr-1" />
                                        Komentarze
                                    </button>
                                    <button
                                        wire:click.stop="showAttachments({{ $task->id }})"
                                        class="text-xs text-gray-700 dark:text-gray-300 flex items-center px-2 py-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white/90 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        title="Pokaż załączniki"
                                    >
                                        <x-heroicon-m-paper-clip class="w-3.5 h-3.5 mr-1" />
                                        Pliki
                                    </button>
                                </div>

                                <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button 
                                        wire:click.stop="deleteTask({{ $task->id }})"
                                        onclick="return confirm('Czy na pewno chcesz usunąć to zadanie?')"
                                        class="text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-300 transition-colors p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                        title="Usuń zadanie">
                                        <x-heroicon-m-trash class="w-4 h-4" />
                                    </button>
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
            </div>
        @endforeach
    </div>
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
                    wire:model="quickTaskTitle"
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
                    wire:model="quickTaskDescription"
                    rows="3" 
                    class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                    placeholder="Wprowadź opis zadania (opcjonalnie)"
                ></textarea>
                @error('quickTaskDescription') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Termin (data i godzina)</label>
                <input
                    wire:model="quickTaskDueDate"
                    type="datetime-local"
                    class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                />
                @error('quickTaskDueDate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priorytet</label>
                    <select 
                        wire:model="quickTaskPriority"
                        class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                    >
                        <option value="low">🟢 Niski</option>
                        <option value="medium" selected>🟡 Średni</option>
                        <option value="high">🔴 Wysoki</option>
                    </select>
                    @error('quickTaskPriority') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Przypisz do</label>
                    <select 
                        wire:model="quickTaskAssigneeId"
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
                            wire:model="quickTaskableId"
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

    {{-- Edit Task Modal --}}
    <x-filament::modal id="edit-task-modal" width="4xl">
        <x-slot name="header">
            <x-filament::modal.heading>
                Edytuj zadanie
            </x-filament::modal.heading>
        </x-slot>

        @if($editingTask)
        <div class="space-y-6">
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Szczegóły zadania</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">ID</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">#{{ $editingTask->id }}</p>
                    </div>
                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Utworzone</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $editingTask->created_at?->format('d.m.Y H:i') ?? '—' }}</p>
                    </div>
                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ostatnia aktualizacja</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $editingTask->updated_at?->diffForHumans() ?? '—' }}</p>
                    </div>
                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Kontekst</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $editingTask->task_context_label ?: 'Wolne / nieprzypisane' }}</p>
                    </div>
                </div>

                @if(!empty($editingTaskContextUrl))
                    <a
                        href="{{ $editingTaskContextUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-3 py-2 rounded-md border border-cyan-200 dark:border-cyan-800 bg-cyan-50 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-200 hover:bg-cyan-100 dark:hover:bg-cyan-800/60 transition"
                    >
                        <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                        Otwórz powiązany element
                    </a>
                @endif

                @if(!empty($editingTaskContextTree))
                    <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                        @foreach($editingTaskContextTree as $node)
                            @if(!empty($node['url']))
                                <a href="{{ $node['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center px-2 py-1 rounded-md border border-cyan-200 dark:border-cyan-800 bg-cyan-50 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-200 hover:bg-cyan-100 dark:hover:bg-cyan-800/60 transition">{{ $node['label'] }}</a>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200">{{ $node['label'] }}</span>
                            @endif
                            @if(! $loop->last)
                                <span class="text-gray-400">&gt;</span>
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Podzadania</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $editingTask->subtasks->count() ?? 0 }}</p>
                    </div>
                    <div class="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Komentarze</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $editingTask->comments->count() ?? 0 }}</p>
                    </div>
                    <div class="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Załączniki</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $editingTask->attachments->count() ?? 0 }}</p>
                    </div>
                </div>
            </div>

            {{-- Basic Task Info --}}
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tytuł zadania</label>
                    <input 
                        wire:model="editModalData.title"
                        type="text" 
                        class="block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm dark:bg-gray-700 dark:text-gray-300"
                        placeholder="Wprowadź tytuł zadania"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Opis</label>
                    <textarea
                        wire:model="editModalData.description"
                        placeholder="Wprowadź opis zadania"
                        rows="5"
                        class="block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm dark:bg-gray-700 dark:text-gray-300"
                    ></textarea>
                </div>
            </div>

            {{-- Task Properties --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priorytet</label>
                    <select 
                        wire:model="editModalData.priority"
                        class="block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm dark:bg-gray-700 dark:text-gray-300"
                    >
                        <option value="">Wybierz priorytet</option>
                        <option value="low">🟢 Niski</option>
                        <option value="medium">🟡 Średni</option>
                        <option value="high">🔴 Wysoki</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <select 
                        wire:model="editModalData.status_id"
                        class="block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm dark:bg-gray-700 dark:text-gray-300"
                    >
                        <option value="">Wybierz status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Termin wykonania</label>
                    <input 
                        wire:model="editModalData.due_date"
                        type="datetime-local" 
                        class="block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm dark:bg-gray-700 dark:text-gray-300"
                    />
                </div>
            </div>

            {{-- Task Assignment --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Autor</label>
                    <div class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 sm:text-sm">
                        {{ $editingTask->author->name ?? 'Nieznany' }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Przypisany do</label>
                    <select 
                        wire:model="editModalData.assignee_id"
                        class="block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm dark:bg-gray-700 dark:text-gray-300"
                    >
                        <option value="">Nie przypisane</option>
                        @foreach(\App\Models\User::all() as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kontekst zadania</label>
                    <select 
                        wire:model.live="editModalData.taskable_type"
                        class="block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm dark:bg-gray-700 dark:text-gray-300"
                    >
                        <option value="">Wolne / nieprzypisane</option>
                        @foreach($taskableTypes as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('editModalData.taskable_type') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                @if(!empty($editModalData['taskable_type']))
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Powiązany rekord</label>
                        <select 
                            wire:model="editModalData.taskable_id"
                            class="block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm dark:bg-gray-700 dark:text-gray-300"
                        >
                            <option value="">Wybierz rekord</option>
                            @foreach($editTaskableRecords as $recordId => $recordLabel)
                                <option value="{{ $recordId }}">{{ $recordLabel }}</option>
                            @endforeach
                        </select>
                        @error('editModalData.taskable_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Ostatnie komentarze</h4>
                    <div class="space-y-3 max-h-44 overflow-y-auto">
                        @forelse(($editingTask->comments ?? collect())->sortByDesc('created_at')->take(3) as $comment)
                            <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $comment->author?->name ?? 'Nieznany autor' }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $comment->created_at?->format('d.m.Y H:i') }}</span>
                                </div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($comment->content, 140) }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">Brak komentarzy.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Podzadania (podgląd)</h4>
                    <div class="space-y-2 max-h-44 overflow-y-auto">
                        @forelse(($editingTask->subtasks ?? collect())->take(5) as $subtask)
                            <div class="rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-2">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $subtask->title }}</p>
                                <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ $subtask->status->name ?? '—' }}</span>
                                    @if($subtask->assignee)
                                        <span>• {{ $subtask->assignee->name }}</span>
                                    @endif
                                    @if($subtask->due_date)
                                        <span>• {{ $subtask->due_date->format('d.m.Y H:i') }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">Brak podzadań.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Szybkie akcje</h4>
                <div class="flex flex-wrap gap-2">
                    <button 
                        wire:click.stop="showSubtasks({{ $editingTask->id ?? 0 }})"
                        class="px-3 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded text-sm hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                        🔧 Podzadania ({{ $editingTask->subtasks->count() ?? 0 }})
                    </button>
                    <button 
                        wire:click.stop="showComments({{ $editingTask->id ?? 0 }})"
                        class="px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded text-sm hover:bg-green-200 dark:hover:bg-green-800 transition-colors">
                        💬 Komentarze ({{ $editingTask->comments->count() ?? 0 }})
                    </button>
                    <button 
                        wire:click.stop="showAttachments({{ $editingTask->id ?? 0 }})"
                        class="px-3 py-1 bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded text-sm hover:bg-purple-200 dark:hover:bg-purple-800 transition-colors">
                        📎 Załączniki ({{ $editingTask->attachments->count() ?? 0 }})
                    </button>
                    <a
                        href="{{ \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $editingTask]) }}"
                        class="px-3 py-1 bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100 rounded text-sm hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                    >
                        ↗ Pełny widok
                    </a>
                </div>
            </div>
        </div>
        @endif

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-filament::button color="gray" x-on:click="isOpen = false">
                    Anuluj
                </x-filament::button>
                <x-filament::button color="primary" wire:click="saveTask">
                    Zapisz
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    {{-- Subtasks Modal --}}
    <x-filament::modal id="subtasks-modal" width="5xl">
        <x-slot name="header">
            <x-filament::modal.heading>
                Podzadania: {{ $currentTaskForDetails?->title ?? '' }}
            </x-filament::modal.heading>
        </x-slot>

        @if($currentTaskForDetails)
        <div class="space-y-4">
            @if(!empty($currentTaskHierarchy))
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2">
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        @foreach($currentTaskHierarchy as $node)
                            <button
                                type="button"
                                wire:click="openSubtaskDetails({{ $node['id'] }})"
                                class="inline-flex items-center px-2 py-1 rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            >
                                {{ $node['title'] }}
                            </button>

                            @if(! $loop->last)
                                <span class="text-gray-400">&gt;</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($currentTaskContextUrl))
                <div>
                    <a
                        href="{{ $currentTaskContextUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-3 py-2 rounded-md border border-cyan-200 dark:border-cyan-800 bg-cyan-50 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-200 hover:bg-cyan-100 dark:hover:bg-cyan-800/60 transition"
                    >
                        <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                        Przejdź do powiązanego elementu
                    </a>
                </div>
            @endif

            @if(!empty($currentTaskContextTree))
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    @foreach($currentTaskContextTree as $node)
                        @if(!empty($node['url']))
                            <a href="{{ $node['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center px-2 py-1 rounded-md border border-cyan-200 dark:border-cyan-800 bg-cyan-50 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-200 hover:bg-cyan-100 dark:hover:bg-cyan-800/60 transition">{{ $node['label'] }}</a>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200">{{ $node['label'] }}</span>
                        @endif
                        @if(! $loop->last)
                            <span class="text-gray-400">&gt;</span>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    @if($currentTaskForDetails->parent_id)
                        <x-filament::button color="gray" size="sm" wire:click="goToParentTaskDetails">
                            ↑ Wróć do nadrzędnego
                        </x-filament::button>
                    @endif
                    <x-filament::button color="info" size="sm" wire:click="showComments({{ $currentTaskForDetails->id }})">
                        Komentarze ({{ $currentTaskForDetails->comments->count() }})
                    </x-filament::button>
                    <x-filament::button color="gray" size="sm" wire:click="showAttachments({{ $currentTaskForDetails->id }})">
                        Załączniki ({{ $currentTaskForDetails->attachments->count() }})
                    </x-filament::button>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">
                        {{ $editingSubtask ? 'Edycja podzadania' : 'Nowe podzadanie (pełne pola jak zadanie)' }}
                    </h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tytuł *</label>
                            <input wire:model="editSubtaskData.title" type="text" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" />
                            @error('editSubtaskData.title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Opis</label>
                            <textarea wire:model="editSubtaskData.description" rows="3" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"></textarea>
                            @error('editSubtaskData.description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                <select wire:model="editSubtaskData.status_id" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                                    <option value="">Wybierz status</option>
                                    @foreach($statuses as $status)
                                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                                    @endforeach
                                </select>
                                @error('editSubtaskData.status_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priorytet</label>
                                <select wire:model="editSubtaskData.priority" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                                    <option value="low">Niski</option>
                                    <option value="medium">Średni</option>
                                    <option value="high">Wysoki</option>
                                </select>
                                @error('editSubtaskData.priority') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Przypisane do</label>
                                <select wire:model="editSubtaskData.assignee_id" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                                    <option value="">Nie przypisano</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                @error('editSubtaskData.assignee_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Termin wykonania</label>
                                <input wire:model="editSubtaskData.due_date" type="datetime-local" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" />
                                @error('editSubtaskData.due_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            @if($editingSubtask)
                                <x-filament::button color="gray" wire:click="cancelEditSubtask">Anuluj edycję</x-filament::button>
                                <x-filament::button color="primary" wire:click="saveSubtask">Zapisz podzadanie</x-filament::button>
                            @else
                                <x-filament::button color="primary" wire:click="addSubtask">Dodaj podzadanie</x-filament::button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-2">
                    <h4 class="font-semibold text-gray-700 dark:text-gray-200 mb-2">Podzadania tego elementu</h4>
                    @if($currentTaskForDetails->subtasks && $currentTaskForDetails->subtasks->count() > 0)
                        <div class="space-y-2">
                            @foreach($currentTaskForDetails->subtasks as $subtask)
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
                                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $subtask->title }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                                <span class="px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">{{ $subtask->status->name ?? 'Brak statusu' }}</span>
                                                <span class="px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-200">Priorytet: {{ ucfirst($subtask->priority ?? 'medium') }}</span>
                                                @if($subtask->assignee)
                                                    <span class="px-2 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-200">{{ $subtask->assignee->name }}</span>
                                                @endif
                                                @if($subtask->due_date)
                                                    <span class="px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-200">{{ $subtask->due_date->format('d.m.Y H:i') }}</span>
                                                @endif
                                            </div>
                                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                <span>Podzadania: {{ $subtask->subtasks_count ?? 0 }}</span>
                                                <span>•</span>
                                                <span>Komentarze: {{ $subtask->comments_count ?? 0 }}</span>
                                                <span>•</span>
                                                <span>Załączniki: {{ $subtask->attachments_count ?? 0 }}</span>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <x-filament::button size="sm" color="gray" wire:click="openSubtaskDetails({{ $subtask->id }})">Otwórz</x-filament::button>
                                            <x-filament::button size="sm" color="info" wire:click="showComments({{ $subtask->id }})">Komentarze</x-filament::button>
                                            <x-filament::button size="sm" color="gray" wire:click="showAttachments({{ $subtask->id }})">Załączniki</x-filament::button>
                                            <x-filament::button size="sm" color="warning" wire:click="editSubtask({{ $subtask->id }})">Edytuj</x-filament::button>
                                            <x-filament::button size="sm" color="danger" wire:click="deleteSubtask({{ $subtask->id }})">Usuń</x-filament::button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-gray-400 text-sm">Brak podzadań</div>
                    @endif
                </div>
            </div>
        @endif

        <x-slot name="footer">
            <x-filament::button color="gray" x-on:click="isOpen = false">
                Zamknij
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Comments Modal --}}
    <x-filament::modal id="comments-modal" width="4xl">
        <x-slot name="header">
            <x-filament::modal.heading>
                Komentarze: {{ $currentTaskForDetails?->title ?? '' }}
            </x-filament::modal.heading>
        </x-slot>

        @if($currentTaskForDetails)
        <div class="space-y-4">
            @if(!empty($currentTaskContextUrl))
                <div>
                    <a
                        href="{{ $currentTaskContextUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-3 py-2 rounded-md border border-cyan-200 dark:border-cyan-800 bg-cyan-50 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-200 hover:bg-cyan-100 dark:hover:bg-cyan-800/60 transition"
                    >
                        <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                        Przejdź do powiązanego elementu
                    </a>
                </div>
            @endif

            {{-- Add New Comment --}}
            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                <div class="space-y-2">
                    <textarea 
                        wire:model="newComment"
                        placeholder="Dodaj komentarz..."
                        rows="3"
                        class="w-full block border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm text-black dark:text-gray-200 bg-white dark:bg-gray-700"
                    ></textarea>
                    <div class="flex justify-end">
                        <button 
                            wire:click="addComment"
                            class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors">
                            Dodaj komentarz
                        </button>
                    </div>
                </div>
            </div>

            {{-- Comments List --}}
            <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @forelse($currentTaskForDetails->comments ?? [] as $comment)
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $comment->author ? $comment->author->name : 'Nieznany autor' }}
                                    </span>
                                    <span class="text-sm text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm text-gray-500 whitespace-pre-wrap">{{ $comment->content }}</p>
                            </div>
                            
                            @if($comment->author_id === auth()->id() || (auth()->user() && auth()->user()->roles->contains('name', 'admin')))
                                <div class="flex items-center gap-2 mb-2">
                                    <button wire:click="deleteComment({{ $comment->id }})" class="text-red-500 hover:underline text-xs" onclick="return confirm('Czy na pewno chcesz usunąć ten komentarz?')">Usuń</button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <x-heroicon-o-chat-bubble-left-ellipsis class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                            <p>Brak komentarzy</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        @endif

        <x-slot name="footer">
            <x-filament::button color="gray" x-on:click="isOpen = false">
                Zamknij
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Attachments Modal --}}
    <x-filament::modal id="attachments-modal" width="4xl">
        <x-slot name="header">
            <x-filament::modal.heading>
                Załączniki: {{ $currentTaskForDetails?->title ?? '' }}
            </x-filament::modal.heading>
        </x-slot>

        @if($currentTaskForDetails)
        <div class="space-y-4">
            @if(!empty($currentTaskContextUrl))
                <div>
                    <a
                        href="{{ $currentTaskContextUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-3 py-2 rounded-md border border-cyan-200 dark:border-cyan-800 bg-cyan-50 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-200 hover:bg-cyan-100 dark:hover:bg-cyan-800/60 transition"
                    >
                        <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                        Przejdź do powiązanego elementu
                    </a>
                </div>
            @endif

            {{-- Attachments List --}}
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @forelse($currentTaskForDetails->attachments ?? [] as $attachment)
                    <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <x-heroicon-m-document class="w-8 h-8 text-gray-400" />                    
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ $attachment->filename }}</h4>
                                    <p class="text-sm text-gray-500">{{ $attachment->created_at->format('d.m.Y H:i') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($attachment->file_path)                        
                                <a 
                                    href="{{ $attachment->public_url }}" 
                                    target="_blank"
                                    class="px-3 py-1 bg-blue-100 text-blue-800 rounded text-sm hover:bg-blue-200 transition-colors">
                                    Pobierz
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500">
                        <x-heroicon-o-paper-clip class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                        <p>Brak załączników</p>
                    </div>
                @endforelse
            </div>
        </div>
        @endif

        <x-slot name="footer">
            <x-filament::button color="gray" x-on:click="isOpen = false">
                Zamknij
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Enhanced JavaScript with filament-kanban integration --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        function taskCalendarWidget(config) {
            return {
                calendar: null,
                events: config?.events ?? [],
                calendarInitAttempts: 0,
                init() {
                    this.initializeCalendarWhenReady();
                },
                initializeCalendarWhenReady() {
                    const el = this.$refs.calendar;

                    if (!el) {
                        return;
                    }

                    if (typeof window.FullCalendar === 'undefined') {
                        // FullCalendar is loaded from CDN and can be unavailable for a short time.
                        if (this.calendarInitAttempts < 30) {
                            this.calendarInitAttempts += 1;
                            setTimeout(() => this.initializeCalendarWhenReady(), 150);
                        }

                        return;
                    }

                    this.mountCalendar(el);
                },
                mountCalendar(el) {
                    if (!el || typeof window.FullCalendar === 'undefined') {
                        return;
                    }

                    if (this.calendar) {
                        this.calendar.destroy();
                    }

                    this.calendar = new window.FullCalendar.Calendar(el, {
                        initialView: 'dayGridMonth',
                        locale: 'pl',
                        firstDay: 1,
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay',
                        },
                        buttonText: {
                            today: 'Dzisiaj',
                            month: 'Miesiąc',
                            week: 'Tydzień',
                            day: 'Dzień',
                        },
                        events: this.events,
                        height: 'auto',
                        navLinks: true,
                        eventTimeFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false,
                        },
                        dateClick: (info) => {
                            this.$wire.openQuickAddModal(null, info.dateStr);
                        },
                        eventClick: function (info) {
                            if (info.event.url) {
                                info.jsEvent.preventDefault();
                                window.location.href = info.event.url;
                            }
                        },
                    });

                    this.calendar.render();
                },
                ensureVisible() {
                    if (typeof window.FullCalendar === 'undefined') {
                        this.initializeCalendarWhenReady();
                        return;
                    }

                    if (!this.calendar) {
                        this.mountCalendar(this.$refs.calendar);
                        return;
                    }

                    this.$nextTick(() => {
                        requestAnimationFrame(() => {
                            this.calendar.updateSize();
                        });
                    });
                },
            };
        }

        function kanbanBoard() {
            return {
                init() {
                    if (window.Livewire && @this) {
                        // Small delay to ensure DOM is fully rendered
                        setTimeout(() => {
                            this.initDragAndDrop();
                            this.initKeyboardShortcuts();
                        }, 100);
                    }
                },
                initDragAndDrop() {
                    // Initialize Sortable for each tasks container
                    const containers = document.querySelectorAll('.tasks-container[data-status-id]');
                    
                    containers.forEach((container) => {
                        new Sortable(container, {
                            group: 'kanban-tasks',
                            animation: 200,
                            ghostClass: 'sortable-ghost',
                            dragClass: 'sortable-drag',
                            chosenClass: 'sortable-chosen',
                            handle: '.record',
                            forceFallback: false,
                            fallbackTolerance: 0,
                            
                            onStart: (evt) => {
                                document.body.classList.add("grabbing");
                                evt.item.classList.add('shadow-2xl', 'z-50');
                            },
                            
                            onEnd: (evt) => {
                                document.body.classList.remove("grabbing");
                                evt.item.classList.remove('shadow-2xl', 'z-50');
                                
                                const taskId = evt.item.id;
                                const newStatusId = evt.to.dataset.statusId;
                                const newOrder = evt.newIndex;
                                
                                // Visual feedback
                                evt.item.classList.add('animate-pulse');
                                setTimeout(() => {
                                    evt.item.classList.remove('animate-pulse');
                                }, 1000);
                                
                                // Call Livewire method
                                if (window.Livewire && @this) {
                                    @this.call('updateTaskStatus', taskId, newStatusId, newOrder)
                                        .then(() => null)
                                        .catch((error) => {
                                            console.error('Error updating task status:', error);
                                            // Show user-friendly error
                                            alert('Wystąpił błąd podczas przenoszenia zadania. Strona zostanie odświeżona.');
                                            window.location.reload();
                                        });
                                } else {
                                    console.error('Livewire not available');
                                }
                            },
                            onMove: () => true,
                        });
                    });
                },
                initKeyboardShortcuts() {
                    document.addEventListener('keydown', (e) => {
                        // Ctrl+N = New task
                        if (e.ctrlKey && e.key === 'n') {
                            e.preventDefault();
                            window.location.href = '{{ \App\Filament\Resources\TaskResource::getUrl("create") }}';
                        } 
                        // R = Refresh
                        else if (e.key === 'r' && !e.ctrlKey && !e.altKey) {
                            e.preventDefault();
                            @this.refreshBoard();
                        }
                    });
                }
            }
        }
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

        .kanban-column-header {
            border: 1px solid var(--f-border);
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

        .kanban-column {
            min-width: 16rem;
            flex: 1 1 16rem;
        }

        @media (max-width: 1280px) {
            .kanban-column {
                min-width: 18rem;
                flex: 0 0 18rem;
            }
        }

        @media (max-width: 768px) {
            .kanban-column {
                min-width: 16rem;
                flex: 0 0 16rem;
            }
        }
    </style>
</x-filament-panels::page>
