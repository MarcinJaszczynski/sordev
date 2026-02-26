@php
    use App\Filament\Resources\TaskResource;
@endphp

<x-filament-panels::page>
    {{-- Enhanced Kanban Board inspired by filament-kanban --}}
    
    {{-- Advanced Filters & Controls --}}
    <div class="mb-6 flex flex-col sm:flex-row justify-between gap-4">
        <div class="flex flex-wrap gap-2">
            <select wire:model.live="filterBy" class="fi-select-input block w-full border-none bg-white py-1.5 pe-8 ps-3 text-base text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] disabled:bg-gray-50 sm:text-sm sm:leading-6 [&_optgroup]:bg-white [&_optgroup]:text-gray-950 [&_option]:bg-white [&_option]:text-gray-950 rounded-lg shadow-sm border-gray-300">
                <option value="">🎯 Wszystkie zadania</option>
                <option value="author">📝 Moje zadania</option>
                <option value="assignee">👤 Przypisane do mnie</option>
            </select>
            
            <select wire:model.live="priorityFilter" class="fi-select-input block w-full border-none bg-white py-1.5 pe-8 ps-3 text-base text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] disabled:bg-gray-50 sm:text-sm sm:leading-6 [&_optgroup]:bg-white [&_optgroup]:text-gray-950 [&_option]:bg-white [&_option]:text-gray-950 rounded-lg shadow-sm border-gray-300">
                <option value="">🔥 Wszystkie priorytety</option>
                <option value="high">🔴 Wysoki</option>
                <option value="medium">🟡 Średni</option>
                <option value="low">🟢 Niski</option>
            </select>
        </div>
        
        <div class="flex gap-2">
            <button wire:click="refreshBoard" 
                class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-gray fi-btn-color-gray fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-white text-gray-950 hover:bg-gray-50 focus-visible:ring-primary-600 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 ring-1 ring-gray-950/10 dark:ring-white/20">
                <x-heroicon-m-arrow-path class="w-4 h-4" />
                Odśwież
            </button>
            
            <a href="{{ TaskResource::getUrl('create') }}" 
                class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-primary fi-btn-color-primary fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-primary-600 text-white hover:bg-primary-500 focus-visible:ring-primary-600 dark:bg-primary-500 dark:hover:bg-primary-400">
                <x-heroicon-m-plus class="w-4 h-4" />
                Dodaj zadanie
            </a>
        </div>
    </div>

    {{-- Enhanced Kanban Board --}}
    <div 
        x-data="kanbanBoard()" 
        x-init="init()" 
        class="flex gap-4 overflow-x-auto pb-4"
        style="min-height: 70vh;"
    >
        @foreach ($statuses as $status)
            <div class="kanban-column flex-shrink-0 w-80 bg-gray-50 rounded-xl p-4 border border-gray-200 shadow-sm" 
                data-status-id="{{ $status->id }}">
                
                {{-- Enhanced Column Header --}}
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full shadow-sm" 
                            style="background-color: {{ $status->color ?: '#6B7280' }}"></div>
                        <h3 class="font-semibold text-gray-900">{{ $status->name }}</h3>
                        <span class="text-xs font-medium text-gray-500 bg-gray-200 px-2 py-1 rounded-full">
                            {{ $tasks->where('status_id', $status->id)->count() }}
                        </span>
                    </div>
                    
                    {{-- Quick Add Button --}}
                    <button 
                        wire:click="openQuickAdd({{ $status->id }})"
                        class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded hover:bg-gray-200"
                        title="Szybko dodaj zadanie">
                        <x-heroicon-m-plus class="w-4 h-4" />
                    </button>
                </div>

                {{-- Tasks Container --}}
                <div class="kanban-tasks space-y-3 min-h-[200px]" 
                    data-status-id="{{ $status->id }}">
                    @foreach ($tasks->where('status_id', $status->id)->sortBy('order') as $task)
                        <div class="task group bg-white rounded-lg shadow-sm border border-gray-200 p-4 cursor-move hover:shadow-md transition-all duration-200 transform hover:-translate-y-1" 
                            data-task-id="{{ $task->id }}">
                            
                            {{-- Task Header --}}
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="font-medium text-gray-900 text-sm leading-5 flex-1 pr-2">
                                    {{ $task->title }}
                                </h4>
                                
                                {{-- Priority Badge --}}
                                <span class="priority-badge flex-shrink-0 text-xs px-2 py-1 rounded-full font-medium" 
                                    style="background-color: {{ match ($task->priority) {
                                        'low' => '#E0F2FE',
                                        'medium' => '#FEF3C7', 
                                        'high' => '#FEE2E2',
                                        default => '#F3F4F6'
                                    } }}; color: {{ match ($task->priority) {
                                        'low' => '#0369A1',
                                        'medium' => '#D97706',
                                        'high' => '#DC2626', 
                                        default => '#6B7280'
                                    } }};">
                                    {{ match ($task->priority) {
                                        'low' => '🟢',
                                        'medium' => '🟡',
                                        'high' => '🔴',
                                        default => '⚪'
                                    } }}
                                </span>
                            </div>

                            {{-- Task Description --}}
                            @if($task->description)
                                <p class="text-xs text-gray-600 mb-3 line-clamp-2">
                                    {{ Str::limit(strip_tags($task->description), 80) }}
                                </p>
                            @endif

                            {{-- Task Meta Info --}}
                            <div class="space-y-2 mb-3">
                                @if($task->assignee)
                                    <div class="flex items-center text-xs text-gray-600">
                                        <x-heroicon-m-user class="w-3 h-3 mr-1.5" />
                                        <span class="font-medium">{{ $task->assignee->name }}</span>
                                    </div>
                                @endif

                                @if($task->due_date)
                                    <div class="flex items-center text-xs {{ $task->due_date->isPast() ? 'text-red-600' : 'text-gray-600' }}">
                                        <x-heroicon-m-clock class="w-3 h-3 mr-1.5" />
                                        <span>{{ $task->due_date->format('d.m.Y H:i') }}</span>
                                        @if($task->due_date->isPast())
                                            <span class="ml-1">⚠️</span>
                                        @elseif($task->due_date->diffInDays() <= 1)
                                            <span class="ml-1">🔥</span>
                                        @endif
                                    </div>
                                @endif

                                {{-- Subtasks Count --}}
                                @if($task->subtasks->count() > 0)
                                    <div class="flex items-center text-xs text-gray-600">
                                        <x-heroicon-m-list-bullet class="w-3 h-3 mr-1.5" />
                                        <span>{{ $task->subtasks->count() }} podzadań</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Task Footer --}}
                            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                <div class="flex items-center gap-1">
                                    @if($task->attachments && $task->attachments->count() > 0)
                                        <span class="text-xs text-gray-500 flex items-center">
                                            <x-heroicon-m-paper-clip class="w-3 h-3 mr-1" />
                                            {{ $task->attachments->count() }}
                                        </span>
                                    @endif
                                    
                                    @if($task->comments && $task->comments->count() > 0)
                                        <span class="text-xs text-gray-500 flex items-center ml-2">
                                            <x-heroicon-m-chat-bubble-left class="w-3 h-3 mr-1" />
                                            {{ $task->comments->count() }}
                                        </span>
                                    @endif
                                </div>
                                
                                {{-- Action Buttons --}}
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="{{ TaskResource::getUrl('edit', ['record' => $task]) }}"
                                        class="text-gray-400 hover:text-primary-600 transition-colors p-1 rounded hover:bg-gray-100"
                                        title="Edytuj zadanie">
                                        <x-heroicon-m-pencil class="w-3 h-3" />
                                    </a>
                                    
                                    <button 
                                        wire:click="deleteTask({{ $task->id }})"
                                        onclick="return confirm('Czy na pewno chcesz usunąć to zadanie?')"
                                        class="text-gray-400 hover:text-red-600 transition-colors p-1 rounded hover:bg-gray-100"
                                        title="Usuń zadanie">
                                        <x-heroicon-m-trash class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    
                    {{-- Empty State --}}
                    @if($tasks->where('status_id', $status->id)->count() === 0)
                        <div class="text-center py-8 text-gray-400">
                            <x-heroicon-o-inbox class="w-8 h-8 mx-auto mb-2" />
                            <p class="text-sm">Brak zadań</p>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Enhanced JavaScript --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        function kanbanBoard() {
            return {
                init() {
                    this.initDragAndDrop();
                    this.initKeyboardShortcuts();
                },
                
                initDragAndDrop() {
                    const containers = document.querySelectorAll('.kanban-tasks');
                    containers.forEach(container => {
                        new Sortable(container, {
                            group: 'shared',
                            animation: 200,
                            ghostClass: 'opacity-50',
                            dragClass: 'rotate-3 scale-105',
                            chosenClass: 'ring-2 ring-primary-500',
                            onStart: (evt) => {
                                evt.item.classList.add('shadow-xl', 'z-50');
                            },
                            onEnd: (evt) => {
                                evt.item.classList.remove('shadow-xl', 'z-50', 'rotate-3', 'scale-105');
                                
                                const taskId = evt.item.dataset.taskId;
                                const newStatusId = evt.to.closest('.kanban-column').dataset.statusId;
                                const newOrder = Array.from(evt.to.children).indexOf(evt.item);
                                
                                // Visual feedback
                                evt.item.classList.add('animate-pulse');
                                setTimeout(() => {
                                    evt.item.classList.remove('animate-pulse');
                                }, 1000);
                                
                                @this.updateTaskStatus(taskId, newStatusId, newOrder);
                            },
                            onMove: (evt) => {
                                // Prevent dropping on buttons or non-task elements
                                return !evt.related.closest('button');
                            }
                        });
                    });
                },
                
                initKeyboardShortcuts() {
                    document.addEventListener('keydown', (e) => {
                        // Ctrl+N = New task
                        if (e.ctrlKey && e.key === 'n') {
                            e.preventDefault();
                            window.location.href = '{{ TaskResource::getUrl("create") }}';
                        }
                        
                        // R = Refresh
                        if (e.key === 'r' && !e.ctrlKey && !e.altKey) {
                            e.preventDefault();
                            @this.refreshBoard();
                        }
                    });
                }
            }
        }
    </script>

    {{-- Custom Styles --}}
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .kanban-tasks .task:hover {
            transform: translateY(-2px);
        }
        
        .sortable-ghost {
            opacity: 0.4;
        }
        
        .sortable-chosen {
            transform: rotate(5deg);
        }
        
        .priority-badge {
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }
        
        @media (max-width: 768px) {
            .kanban-column {
                width: 280px;
            }
        }
    </style>
</x-filament-panels::page>
        @endforeach
    </div>
</x-filament-panels::page>