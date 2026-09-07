<x-filament-widgets::widget class="fi-wi-notifications">
    <x-filament::section class="bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="px-4 py-3">
            <div class="flex flex-wrap items-center gap-3 text-xs md:text-sm">

                {{-- ZADANIA: link nawiguje, badge otwiera dropdown --}}
                <div x-data="{ open: false }" class="relative flex items-center gap-0.5">
                    <a wire:navigate href="{{ url('/admin/tasks') }}"
                       class="flex items-center gap-1 px-2 py-1 rounded hover:bg-orange-50 dark:hover:bg-orange-900 transition font-semibold text-gray-800 dark:text-gray-200"
                       title="Przejdź do zadań">
                        <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-orange-500 dark:text-orange-400 shrink-0" />
                        <span>Zadania</span>
                    </a>
                    @if($newTasksCount > 0)
                        <button type="button"
                                @click="open = !open"
                                class="bg-orange-500 hover:bg-orange-600 text-white rounded-full min-w-[1.3rem] h-5 px-1.5 text-[11px] font-bold leading-none cursor-pointer transition"
                                title="Pokaż {{ $newTasksCount }} zadań">
                            {{ $newTasksCount }}
                        </button>
                        <div x-show="open"
                             x-transition
                             @click.away="open = false"
                             class="absolute top-full left-0 mt-1 z-30 w-72 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl p-2 space-y-1">
                            @foreach($recentTasks->take(5) as $task)
                                <a href="{{ \App\Support\Tasks\TaskNavigation::editUrl($task) }}"
                                   class="flex flex-col px-2 py-1.5 rounded hover:bg-orange-50 dark:hover:bg-orange-900/50 text-sm text-gray-900 dark:text-white">
                                    <span class="font-medium truncate">{{ $task->title }}</span>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ \App\Support\Tasks\TaskListColumn::ownershipLine($task) }}</span>
                                    <span class="text-[11px] text-gray-400">{{ $task->created_at->diffForHumans() }}</span>
                                </a>
                            @endforeach
                            <a wire:navigate href="{{ url('/admin/tasks') }}"
                               class="block text-center text-xs text-orange-600 font-semibold pt-1 border-t border-gray-100 dark:border-gray-700 hover:underline">
                                Wszystkie zadania →
                            </a>
                        </div>
                    @endif
                </div>

                {{-- CZAT: link nawiguje, badge otwiera dropdown --}}
                <div x-data="{ open: false }" class="relative flex items-center gap-0.5">
                    <a wire:navigate href="{{ url('/admin/chat') }}"
                       class="flex items-center gap-1 px-2 py-1 rounded hover:bg-blue-50 dark:hover:bg-blue-900 transition font-semibold text-gray-800 dark:text-gray-200"
                       title="Przejdź do czatu">
                        <x-heroicon-o-chat-bubble-left-right class="h-5 w-5 text-blue-500 dark:text-blue-400 shrink-0" />
                        <span>Czat</span>
                    </a>
                    @if($unreadMessagesCount > 0)
                        <button type="button"
                                @click="open = !open"
                                class="bg-blue-500 hover:bg-blue-600 text-white rounded-full min-w-[1.3rem] h-5 px-1.5 text-[11px] font-bold leading-none cursor-pointer transition"
                                title="Pokaż {{ $unreadMessagesCount }} wiadomości">
                            {{ $unreadMessagesCount }}
                        </button>
                        <div x-show="open"
                             x-transition
                             @click.away="open = false"
                             class="absolute top-full left-0 mt-1 z-30 w-72 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl p-2 space-y-1">
                            @foreach($recentMessages->take(5) as $message)
                                <a wire:navigate href="{{ url('/admin/chat') }}"
                                   class="flex flex-col px-2 py-1.5 rounded hover:bg-blue-50 dark:hover:bg-blue-900/50 text-sm text-gray-900 dark:text-white">
                                    <span class="font-medium truncate">{{ $message->user->name ?? 'Nieznany' }}</span>
                                    <span class="truncate text-[11px] text-gray-500">{{ Str::limit($message->content, 60) }}</span>
                                    <span class="text-[11px] text-gray-400">{{ $message->created_at->diffForHumans() }}</span>
                                </a>
                            @endforeach
                            <a wire:navigate href="{{ url('/admin/chat') }}"
                               class="block text-center text-xs text-blue-600 font-semibold pt-1 border-t border-gray-100 dark:border-gray-700 hover:underline">
                                Otwórz czat →
                            </a>
                        </div>
                    @endif
                </div>

                {{-- DO ANULACJI: link nawiguje, badge otwiera dropdown --}}
                <div x-data="{ open: false }" class="relative flex items-center gap-0.5">
                    <a wire:navigate href="{{ url('/admin/events?tableFilters[status][value]=pending_cancellation') }}"
                       class="flex items-center gap-1 px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900 transition font-semibold text-gray-800 dark:text-gray-200"
                       title="Imprezy do anulacji">
                        <x-heroicon-o-x-circle class="h-5 w-5 text-red-500 dark:text-red-400 shrink-0" />
                        <span>Do anulacji</span>
                    </a>
                    @if($pendingCancellationEventsCount > 0)
                        <button type="button"
                                @click="open = !open"
                                class="bg-red-500 hover:bg-red-600 text-white rounded-full min-w-[1.3rem] h-5 px-1.5 text-[11px] font-bold leading-none cursor-pointer transition"
                                title="Pokaż {{ $pendingCancellationEventsCount }} imprezy">
                            {{ $pendingCancellationEventsCount }}
                        </button>
                        <div x-show="open"
                             x-transition
                             @click.away="open = false"
                             class="absolute top-full left-0 mt-1 z-30 w-72 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl p-2 space-y-1">
                            @foreach($pendingCancellationEvents as $event)
                                <a wire:navigate href="{{ $event['url'] }}"
                                   class="flex flex-col px-2 py-1.5 rounded hover:bg-red-50 dark:hover:bg-red-900/50 text-sm text-gray-900 dark:text-white">
                                    <span class="font-medium truncate">{{ $event['title'] }}</span>
                                    <span class="text-[11px] text-gray-500">{{ $event['meta'] }}</span>
                                    <span class="text-[11px] text-gray-400">{{ $event['time'] }}</span>
                                </a>
                            @endforeach
                            <a wire:navigate href="{{ url('/admin/events?tableFilters[status][value]=pending_cancellation') }}"
                               class="block text-center text-xs text-red-600 font-semibold pt-1 border-t border-gray-100 dark:border-gray-700 hover:underline">
                                Wszystkie do anulacji →
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Odśwież --}}
                <button wire:click="$refresh"
                        class="ml-auto flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition text-gray-500 dark:text-gray-400"
                        title="Odśwież">
                    <x-heroicon-o-arrow-path class="h-4 w-4" />
                </button>

            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
