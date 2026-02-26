<x-filament-panels::page>
    <div class="h-[calc(100vh-200px)] min-h-[600px] bg-white dark:bg-gray-900 rounded-lg overflow-hidden shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        @livewire('chat-interface', ['conversationId' => $conversationId])
    </div>
</x-filament-panels::page>
