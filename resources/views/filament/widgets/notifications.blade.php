<x-filament-widgets::widget class="fi-wi-notifications">
    <x-filament::section class="bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="p-4" x-data="{ 
            newTasksCount: {{ $newTasksCount }},
            unreadMessagesCount: {{ $unreadMessagesCount }},
            refreshNotifications() {
                fetch('{{ route("admin.notifications.counts") }}')
                    .then(response => response.json())
                    .then(data => {
                        this.newTasksCount = data.tasks;
                        this.unreadMessagesCount = data.messages;
                    });
            }
        }" x-init="
            // Odświeżaj co 30 sekund
            setInterval(() => refreshNotifications(), 30000);
            
            // Nasłuchuj na event odświeżenia z Livewire
            window.addEventListener('refresh-notifications', () => {
                setTimeout(() => refreshNotifications(), 100);
            });
        "
        @refresh-notifications.window="refreshNotifications()"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <!-- Zadania -->
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-500/20">
                                <x-heroicon-o-clipboard-document-list class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                                @if($newTasksCount > 0)
                                    <span x-show="newTasksCount > 0" class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full min-w-[1.5rem] h-6 ring-2 ring-white dark:ring-gray-900"
                                          x-text="newTasksCount > 99 ? '99+' : newTasksCount">
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                Zadania
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <span x-show="newTasksCount > 0">
                                    <span class="text-orange-600 dark:text-orange-400 font-medium" x-text="newTasksCount"></span>
                                    <span x-text="newTasksCount == 1 ? 'nowe zadanie' : (newTasksCount < 5 ? 'nowe zadania' : 'nowych zadań')"></span>
                                </span>
                                <span x-show="newTasksCount === 0">Brak nowych zadań</span>
                            </div>
                        </div>
                    </div>

                    <!-- Separator -->
                    <div class="h-10 w-px bg-gray-200 dark:bg-gray-700"></div>

                    <!-- Wiadomości -->
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/20">
                                <x-heroicon-o-chat-bubble-left-right class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                @if($unreadMessagesCount > 0)
                                    <span x-show="unreadMessagesCount > 0" class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-blue-600 rounded-full min-w-[1.5rem] h-6 ring-2 ring-white dark:ring-gray-900"
                                          x-text="unreadMessagesCount > 99 ? '99+' : unreadMessagesCount">
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                Wiadomości
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <span x-show="unreadMessagesCount > 0">
                                    <span class="text-blue-600 dark:text-blue-400 font-medium" x-text="unreadMessagesCount"></span>
                                    <span x-text="unreadMessagesCount == 1 ? 'nieprzeczytana' : (unreadMessagesCount < 5 ? 'nieprzeczytane' : 'nieprzeczytanych')"></span>
                                </span>
                                <span x-show="unreadMessagesCount === 0">Brak nowych wiadomości</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Przyciski akcji -->
                <div class="flex items-center space-x-2">
                    <x-filament::button
                        href="{{ route('filament.admin.resources.tasks.index') }}"
                        color="warning"
                        size="sm"
                        outlined
                        icon="heroicon-o-clipboard-document-list"
                    >
                        Zadania
                    </x-filament::button>
                    
                    <x-filament::button
                        href="{{ url('/admin/chat') }}"
                        color="info"
                        size="sm"
                        outlined
                        icon="heroicon-o-chat-bubble-left-right"  
                    >
                        Czat
                    </x-filament::button>
                    
                    <x-filament::button
                        wire:click="$refresh"
                        color="gray"
                        size="sm"
                        outlined
                        icon="heroicon-o-arrow-path"
                        title="Odśwież powiadomienia"
                    >
                        <span class="sr-only">Odśwież</span>
                    </x-filament::button>
                </div>
            </div>

            <!-- Dropdown z detalami (opcjonalny) -->
            <div x-show="newTasksCount > 0 || unreadMessagesCount > 0" class="mt-6 border-t pt-4 dark:border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Ostatnie zadania -->
                        @if($recentTasks->count() > 0)
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                    <x-heroicon-o-clipboard-document-list class="h-4 w-4 mr-2 text-orange-500" />
                                    Ostatnie zadania
                                </h4>
                                        <div class="space-y-2">
                                    @foreach($recentTasks as $task)
                                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-750 transition-colors">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                    {{ $task->title }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $task->created_at->diffForHumans() }}
                                                </div>
                                            </div>
                                            <div class="ml-3">
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-500/20 dark:text-orange-400 rounded-full">
                                                    {{ $task->status->name ?? 'Nowe' }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Ostatnie wiadomości -->
                        @if($recentMessages->count() > 0)
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                    <x-heroicon-o-chat-bubble-left-right class="h-4 w-4 mr-2 text-blue-500" />
                                    Ostatnie wiadomości
                                </h4>
                                <div class="space-y-2">
                                    @foreach($recentMessages as $message)
                                        <div class="flex items-start justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                    {{ $message->user->name ?? 'Nieznany użytkownik' }}
                                                </div>
                                                <div class="text-xs text-gray-600 dark:text-gray-300 truncate mt-1">
                                                    {{ Str::limit($message->content, 60) }}
                                                </div>
                                                <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                    {{ $message->created_at->diffForHumans() }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
    </div>
    </x-filament::section>
</x-filament-widgets::widget>

@push('scripts')
<script>
// Filament ma własny polling, ale dodamy dodatkowe funkcje
document.addEventListener('livewire:navigated', function () {
    // Refresh powiadomień przy nawigacji
    @this.call('$refresh');
});

// Refresh przy focus na oknie
window.addEventListener('focus', function() {
    @this.call('$refresh');
});

// Websocket listener (jeśli jest)
if (typeof Echo !== 'undefined') {
    Echo.private('notifications.{{ auth()->id() }}')
        .listen('NewTaskAssigned', (e) => {
            @this.call('$refresh');
        })
        .listen('NewMessage', (e) => {
            @this.call('$refresh');
        });
}
</script>
@endpush
