<x-filament-widgets::widget class="fi-wi-notifications">
    <x-filament::section class="bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <!-- Zadania -->
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-500/20">
                                <x-heroicon-o-clipboard-document-list class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                                @if($newTasksCount > 0)
                                    <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full min-w-[1.5rem] h-6 ring-2 ring-white dark:ring-gray-900">
                                        {{ $newTasksCount > 99 ? '99+' : $newTasksCount }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                Zadania
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                @if($newTasksCount > 0)
                                    {{ $newTasksCount }} nowych
                                @else
                                    Brak nowych
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Separator -->
                    <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>

                    <!-- Wiadomości -->
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/20">
                                <x-heroicon-o-chat-bubble-left-right class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                @if($unreadMessagesCount > 0)
                                    <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full min-w-[1.5rem] h-6 ring-2 ring-white dark:ring-gray-900">
                                        {{ $unreadMessagesCount > 99 ? '99+' : $unreadMessagesCount }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                Wiadomości
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                @if($unreadMessagesCount > 0)
                                    {{ $unreadMessagesCount }} nowych
                                @else
                                    Brak nowych
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Przyciski akcji -->
                <div class="flex items-center space-x-2">
                    <a href="{{ route('filament.admin.resources.tasks.index') }}" 
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        <x-heroicon-o-clipboard-document-list class="h-4 w-4 mr-2" />
                        Zadania
                    </a>
                    <a href="{{ route('filament.admin.pages.chat') }}" 
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        <x-heroicon-o-chat-bubble-left-right class="h-4 w-4 mr-2" />
                        Czat
                    </a>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
