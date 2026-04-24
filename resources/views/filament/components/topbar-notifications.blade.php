@php
    $taskIndexUrl = route('filament.admin.resources.tasks.index');
    $eventsIndexUrl = route('filament.admin.resources.events.index');
    $chatUrl = route('filament.admin.pages.chat');
    $newEventsIndexUrl = $eventsIndexUrl . '?tableFilters[status][value]=inquiry';
    $confirmedEventsIndexUrl = $eventsIndexUrl . '?tableFilters[status][value]=confirmed';
    $pendingCancellationEventsIndexUrl = $eventsIndexUrl . '?tableFilters[status][value]=pending_cancellation';
    $itemsByType = array_merge(
        [
            'task' => [],
            'comment' => [],
            'new_event' => [],
            'event' => [],
            'pending_cancellation_event' => [],
            'message' => [],
        ],
        $notificationItemsByType ?? [],
    );
@endphp

<div class="custom-topbar-notifications flex items-center lg:items-center" wire:ignore x-data="{
    routes: {
        tasks: @js($taskIndexUrl),
        comments: @js($taskIndexUrl),
        newEvents: @js($newEventsIndexUrl),
        events: @js($confirmedEventsIndexUrl),
        pendingCancellationEvents: @js($pendingCancellationEventsIndexUrl),
        messages: @js($chatUrl),
    },
    newTasksCount: {{ $newTasksCount }},
    unreadMessagesCount: {{ $unreadMessagesCount }},
    commentsCount: {{ $commentsCount ?? 0 }},
    newEventsCount: {{ $newEventsCount ?? 0 }},
    confirmedEventsCount: {{ $confirmedEventsCount ?? 0 }},
    pendingCancellationEventsCount: {{ $pendingCancellationEventsCount ?? 0 }},
    importantCount: {{ $importantCount ?? 0 }},
    itemsByType: @js($itemsByType),
    importantItems: @js($notificationItems ?? []),
    openPanel: null,
    mobileOpen: false,
    fallbackImportantCount() {
        return this.newTasksCount + this.unreadMessagesCount + this.commentsCount + this.newEventsCount + this.confirmedEventsCount + this.pendingCancellationEventsCount;
    },
    syncPayload(data) {
        const counts = data.counts ?? data;
        const itemsByType = data.items_by_type ?? {};

        this.newTasksCount = counts.tasks ?? 0;
        this.unreadMessagesCount = counts.messages ?? 0;
        this.commentsCount = counts.comments ?? 0;
        this.newEventsCount = counts.new_events ?? 0;
        this.confirmedEventsCount = counts.confirmed_events ?? 0;
        this.pendingCancellationEventsCount = counts.pending_cancellation_events ?? 0;
        this.importantCount = counts.important ?? this.fallbackImportantCount();
        this.itemsByType = {
            task: itemsByType.task ?? [],
            comment: itemsByType.comment ?? [],
            new_event: itemsByType.new_event ?? [],
            event: itemsByType.event ?? [],
            pending_cancellation_event: itemsByType.pending_cancellation_event ?? [],
            message: itemsByType.message ?? [],
        };
        this.importantItems = data.items ?? [];
    },
    itemList(type) {
        return this.itemsByType[type] ?? [];
    },
    togglePanel(panel) {
        this.openPanel = this.openPanel === panel ? null : panel;
    },
    closePanel(panel) {
        if (this.openPanel === panel) {
            this.openPanel = null;
        }
    },
    refreshNotifications() {
        fetch('{{ route("admin.notifications.counts") }}')
            .then(response => response.json())
            .then(data => this.syncPayload(data))
            .catch(error => console.error('Blad odswiezania powiadomien:', error));
    }
}" x-init="
    setInterval(() => refreshNotifications(), 30000);
    window.addEventListener('focus', () => refreshNotifications());
    window.addEventListener('refresh-notifications', () => setTimeout(() => refreshNotifications(), 100));
" @refresh-notifications.window="refreshNotifications()" @click.away="openPanel = null">

    <button
        type="button"
        class="inline-flex lg:hidden items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
        @click="mobileOpen = !mobileOpen"
        :aria-expanded="mobileOpen"
    >
        <span>Powiadomienia</span>
        <span class="inline-flex min-w-[1.65rem] items-center justify-center rounded-full bg-amber-600 px-1.5 py-0.5 text-[11px] font-bold text-white" x-text="importantCount > 99 ? '99+' : importantCount"></span>
    </button>

    <div
        class="topbar-notifications-items w-full lg:w-auto lg:mr-4"
        :class="mobileOpen ? 'mt-2 flex flex-wrap items-center gap-2' : 'hidden lg:flex lg:items-center lg:gap-3'"
    >

    <div class="relative" @mouseenter="openPanel = 'tasks'" @mouseleave="closePanel('tasks')">
        <div class="flex items-center gap-2">
            <a href="{{ $taskIndexUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-500/20 group-hover:bg-orange-200 dark:group-hover:bg-orange-500/30 transition-colors">
                    <svg class="h-4 w-4 text-orange-600 dark:text-orange-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3-7.5H21m-9.75-3.75h9.75m-9.75 3.75h9.75M3.375 7.5h.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.75a1.125 1.125 0 01-1.125-1.125V8.625c0-.621.504-1.125 1.125-1.125z" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900 dark:text-white">Zadania</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="newTasksCount > 0 ? newTasksCount + ' zdarzen' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('tasks')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="newTasksCount > 0 ? 'text-white bg-red-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'tasks'">
                <span x-text="newTasksCount > 99 ? '99+' : newTasksCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'tasks'" x-transition class="topbar-notification-panel absolute left-0 right-auto mt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl z-[9999] lg:left-auto lg:right-0 lg:max-w-[90vw]">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">Ostatnie zadania</div>
            <div class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                <template x-if="itemList('task').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Brak zdarzen dla zadan.</div>
                </template>
                <template x-for="(item, index) in itemList('task')" :key="'task-' + index">
                    <a :href="item.url || routes.tasks" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.title || 'Zadanie'"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <div class="relative" @mouseenter="openPanel = 'comments'" @mouseleave="closePanel('comments')">
        <div class="flex items-center gap-2">
            <a href="{{ $taskIndexUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/20 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-500/30 transition-colors">
                    <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0H12m3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0h-.375M21 12c0 4.97-4.03 9-9 9a8.965 8.965 0 01-4.255-1.071L3 21l1.071-4.745A8.965 8.965 0 013 12c0-4.97 4.03-9 9-9s9 4.03 9 9z" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900 dark:text-white">Komentarze</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="commentsCount > 0 ? commentsCount + ' nowych' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('comments')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="commentsCount > 0 ? 'text-white bg-emerald-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'comments'">
                <span x-text="commentsCount > 99 ? '99+' : commentsCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'comments'" x-transition class="topbar-notification-panel absolute left-0 right-auto mt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl z-[9999] lg:left-auto lg:right-0 lg:max-w-[90vw]">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">Ostatnie komentarze</div>
            <div class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                <template x-if="itemList('comment').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Brak nowych komentarzy.</div>
                </template>
                <template x-for="(item, index) in itemList('comment')" :key="'comment-' + index">
                    <a :href="item.url || routes.comments" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.title || 'Komentarz'"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <div class="relative" @mouseenter="openPanel = 'new-events'" @mouseleave="closePanel('new-events')">
        <div class="flex items-center gap-2">
            <a href="{{ $newEventsIndexUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-500/20 group-hover:bg-sky-200 dark:group-hover:bg-sky-500/30 transition-colors">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900 dark:text-white">Nowe imprezy</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="newEventsCount > 0 ? newEventsCount + ' nowych' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('new-events')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="newEventsCount > 0 ? 'text-white bg-sky-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'new-events'">
                <span x-text="newEventsCount > 99 ? '99+' : newEventsCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'new-events'" x-transition class="topbar-notification-panel absolute left-0 right-auto mt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl z-[9999] lg:left-auto lg:right-0 lg:max-w-[90vw]">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">Nowe imprezy</div>
            <div class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                <template x-if="itemList('new_event').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Brak nowych imprez.</div>
                </template>
                <template x-for="(item, index) in itemList('new_event')" :key="'new-event-' + index">
                    <a :href="item.url || routes.newEvents" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.title || 'Nowa impreza'"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <div class="relative" @mouseenter="openPanel = 'events'" @mouseleave="closePanel('events')">
        <div class="flex items-center gap-2">
            <a href="{{ $confirmedEventsIndexUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-500/20 group-hover:bg-violet-200 dark:group-hover:bg-violet-500/30 transition-colors">
                    <svg class="h-4 w-4 text-violet-600 dark:text-violet-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V8.25A2.25 2.25 0 015.25 6h13.5A2.25 2.25 0 0121 8.25v10.5M3 18.75A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75M3 18.75v-6.75A2.25 2.25 0 015.25 9.75h13.5A2.25 2.25 0 0121 12v6.75" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900 dark:text-white">Imprezy</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="confirmedEventsCount > 0 ? confirmedEventsCount + ' potwierdzen' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('events')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="confirmedEventsCount > 0 ? 'text-white bg-violet-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'events'">
                <span x-text="confirmedEventsCount > 99 ? '99+' : confirmedEventsCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'events'" x-transition class="topbar-notification-panel absolute left-0 right-auto mt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl z-[9999] lg:left-auto lg:right-0 lg:max-w-[90vw]">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">Potwierdzone imprezy</div>
            <div class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                <template x-if="itemList('event').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Brak nowych potwierdzen imprez.</div>
                </template>
                <template x-for="(item, index) in itemList('event')" :key="'event-' + index">
                    <a :href="item.url || routes.events" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.title || 'Impreza'"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <div class="relative" @mouseenter="openPanel = 'pending-cancellation-events'" @mouseleave="closePanel('pending-cancellation-events')">
        <div class="flex items-center gap-2">
            <a href="{{ $pendingCancellationEventsIndexUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-500/20 group-hover:bg-rose-200 dark:group-hover:bg-rose-500/30 transition-colors">
                    <svg class="h-4 w-4 text-rose-600 dark:text-rose-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 12H6" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900 dark:text-white">Do anulacji</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="pendingCancellationEventsCount > 0 ? pendingCancellationEventsCount + ' pozycji' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('pending-cancellation-events')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="pendingCancellationEventsCount > 0 ? 'text-white bg-rose-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'pending-cancellation-events'">
                <span x-text="pendingCancellationEventsCount > 99 ? '99+' : pendingCancellationEventsCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'pending-cancellation-events'" x-transition class="topbar-notification-panel absolute left-0 right-auto mt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl z-[9999] lg:left-auto lg:right-0 lg:max-w-[90vw]">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">Imprezy do anulacji</div>
            <div class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                <template x-if="itemList('pending_cancellation_event').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Brak imprez do anulacji.</div>
                </template>
                <template x-for="(item, index) in itemList('pending_cancellation_event')" :key="'pending-' + index">
                    <a :href="item.url || routes.pendingCancellationEvents" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.title || 'Impreza do anulacji'"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <div class="relative" @mouseenter="openPanel = 'messages'" @mouseleave="closePanel('messages')">
        <div class="flex items-center gap-2">
            <a href="{{ $chatUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/20 group-hover:bg-blue-200 dark:group-hover:bg-blue-500/30 transition-colors">
                    <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900 dark:text-white">Wiadomosci</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="unreadMessagesCount > 0 ? unreadMessagesCount + ' nowych' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('messages')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="unreadMessagesCount > 0 ? 'text-white bg-blue-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'messages'">
                <span x-text="unreadMessagesCount > 99 ? '99+' : unreadMessagesCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'messages'" x-transition class="topbar-notification-panel absolute left-0 right-auto mt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl z-[9999] lg:left-auto lg:right-0 lg:max-w-[90vw]">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">Ostatnie wiadomosci</div>
            <div class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                <template x-if="itemList('message').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Brak nowych wiadomosci.</div>
                </template>
                <template x-for="(item, index) in itemList('message')" :key="'message-' + index">
                    <a :href="item.url || routes.messages" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.title || 'Wiadomosc'"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <div class="relative" @mouseenter="openPanel = 'important'" @mouseleave="closePanel('important')">
        <div class="flex items-center gap-2">
            <button type="button" @click="togglePanel('important')" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/20 group-hover:bg-amber-200 dark:group-hover:bg-amber-500/30 transition-colors">
                    <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900 dark:text-white">Wazne</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="importantCount > 0 ? importantCount + ' zdarzen' : 'Brak nowych'"></div>
                </div>
            </button>

            <button type="button" @click.stop="togglePanel('important')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="importantCount > 0 ? 'text-white bg-amber-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'important'">
                <span x-text="importantCount > 99 ? '99+' : importantCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'important'" x-transition class="topbar-notification-panel absolute left-0 right-auto mt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl z-[9999] lg:left-auto lg:right-0 lg:max-w-[90vw]">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">10 ostatnich zdarzen</div>
                <div class="mt-1 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                    <span>Zadania: <span class="font-semibold" x-text="newTasksCount"></span></span>
                    <span>Komentarze: <span class="font-semibold" x-text="commentsCount"></span></span>
                    <span>Nowe imprezy: <span class="font-semibold" x-text="newEventsCount"></span></span>
                    <span>Potwierdzone: <span class="font-semibold" x-text="confirmedEventsCount"></span></span>
                    <span>Do anulacji: <span class="font-semibold" x-text="pendingCancellationEventsCount"></span></span>
                    <span>Wiadomosci: <span class="font-semibold" x-text="unreadMessagesCount"></span></span>
                </div>
            </div>

            <div class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                <template x-if="importantItems.length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Brak nowych waznych powiadomien.</div>
                </template>
                <template x-for="(item, index) in importantItems" :key="'important-' + index">
                    <a :href="item.url || '#'" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.title || 'Powiadomienie'"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <div class="hidden lg:flex items-center space-x-1 px-3 py-2 border-l border-gray-200 dark:border-gray-700" x-data="{
        fontSize: 18,
        minSize: 16,
        maxSize: 24,
        initSize() {
            const saved = localStorage.getItem('ui-font-size');
            if (saved) {
                this.fontSize = parseInt(saved);
                this.applySize();
            }
        },
        decreaseSize() {
            if (this.fontSize > this.minSize) {
                this.fontSize--;
                this.applySize();
                localStorage.setItem('ui-font-size', this.fontSize);
            }
        },
        resetSize() {
            this.fontSize = 18;
            this.applySize();
            localStorage.setItem('ui-font-size', this.fontSize);
        },
        increaseSize() {
            if (this.fontSize < this.maxSize) {
                this.fontSize++;
                this.applySize();
                localStorage.setItem('ui-font-size', this.fontSize);
            }
        },
        applySize() {
            document.documentElement.style.fontSize = this.fontSize + 'px';
        }
    }" x-init="initSize()">
        <button @click="decreaseSize()" type="button" class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors" title="Zmniejsz rozmiar">
            <span class="text-sm font-semibold">A-</span>
        </button>
        <button @click="resetSize()" type="button" class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors" title="Resetuj rozmiar">
            <span class="text-xs font-semibold">Reset</span>
        </button>
        <button @click="increaseSize()" type="button" class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors" title="Powieksz rozmiar">
            <span class="text-sm font-semibold">A+</span>
        </button>
    </div>

</div>
