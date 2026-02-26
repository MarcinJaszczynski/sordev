<x-filament-widgets::widget class="fi-wi-notifications">
    <x-filament::section>
        <div>
            <h3 class="text-gray-900 dark:text-white">Powiadomienia</h3>
            <p class="text-gray-700 dark:text-gray-300">Zadania: {{ $newTasksCount ?? 0 }}</p>
            <p class="text-gray-700 dark:text-gray-300">Wiadomości: {{ $unreadMessagesCount ?? 0 }}</p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
