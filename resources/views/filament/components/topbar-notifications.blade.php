<div class="flex items-center space-x-3 mr-4" x-data="{ 
    newTasksCount: {{ $newTasksCount }},
    unreadMessagesCount: {{ $unreadMessagesCount }},
    refreshNotifications() {
        // Odśwież dane
        fetch('{{ route("admin.notifications.counts") }}')
            .then(response => response.json())
            .then(data => {
                this.newTasksCount = data.tasks;
                this.unreadMessagesCount = data.messages;
                
                // Wyświetl notyfikację jeśli są nowe
                if (data.tasks > this.newTasksCount || data.messages > this.unreadMessagesCount) {
                    // Można dodać toast notification tutaj
                }
            })
            .catch(error => console.error('Błąd odświeżania powiadomień:', error));
    }
}" x-init="
    // Odświeżaj co 30 sekund
    setInterval(() => refreshNotifications(), 30000);
    
    // Odświeżaj przy focus na oknie
    window.addEventListener('focus', () => refreshNotifications());
    
    // Nasłuchuj na event odświeżenia z Livewire
    window.addEventListener('refresh-notifications', () => {
        setTimeout(() => refreshNotifications(), 100);
    });
"
@refresh-notifications.window="refreshNotifications()"
>
    <!-- Zadania -->
    <a href="{{ route('filament.admin.resources.tasks.index') }}" 
       class="relative flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
        <div class="relative">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-500/20 group-hover:bg-orange-200 dark:group-hover:bg-orange-500/30 transition-colors">
                <svg class="h-4 w-4 text-orange-600 dark:text-orange-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3-7.5H21m-9.75-3.75h9.75m-9.75 3.75h9.75M3.375 7.5h.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.75a1.125 1.125 0 01-1.125-1.125V8.625c0-.621.504-1.125 1.125-1.125z" />
                </svg>
            </div>
            @if($newTasksCount > 0)
                <span x-show="newTasksCount > 0" class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full min-w-[1.25rem] h-5 ring-1 ring-white dark:ring-gray-900"
                      x-text="newTasksCount > 99 ? '99+' : newTasksCount">
                </span>
            @endif
        </div>
        <div class="hidden sm:block">
            <div class="text-xs font-medium text-gray-900 dark:text-white">
                Zadania
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <span x-show="newTasksCount > 0" x-text="newTasksCount + ' nowych'"></span>
                <span x-show="newTasksCount === 0">Brak nowych</span>
            </div>
        </div>
    </a>

    <!-- Separator -->
    <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>

    <!-- Wiadomości -->
    <a href="{{ route('filament.admin.pages.chat') }}"
       class="relative flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
        <div class="relative">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/20 group-hover:bg-blue-200 dark:group-hover:bg-blue-500/30 transition-colors">
                <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                </svg>
            </div>
            @if($unreadMessagesCount > 0)
                <span x-show="unreadMessagesCount > 0" class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-blue-600 rounded-full min-w-[1.25rem] h-5 ring-1 ring-white dark:ring-gray-900"
                      x-text="unreadMessagesCount > 99 ? '99+' : unreadMessagesCount">
                </span>
            @endif
        </div>
        <div class="hidden sm:block">
            <div class="text-xs font-medium text-gray-900 dark:text-white">
                Czat
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <span x-show="unreadMessagesCount > 0" x-text="unreadMessagesCount + ' nowych'"></span>
                <span x-show="unreadMessagesCount === 0">Brak nowych</span>
            </div>
        </div>
    </a>
</div>
