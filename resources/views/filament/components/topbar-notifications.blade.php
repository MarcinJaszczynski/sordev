@php
    $taskIndexUrl = route('filament.admin.resources.tasks.index');
    $eventsIndexUrl = route('filament.admin.resources.events.index');
    $chatUrl = route('filament.admin.pages.chat');
    $inboxUrl = \App\Filament\Pages\NotificationsInboxPage::getUrl();
    $newEventsIndexUrl = $eventsIndexUrl . '?tableFilters[status][value]=inquiry';
    $confirmedEventsIndexUrl = $eventsIndexUrl . '?tableFilters[status][value]=confirmed';
    $pendingCancellationEventsIndexUrl = $eventsIndexUrl . '?tableFilters[status][value]=pending_cancellation';
    $commentsInboxUrl = $inboxUrl.'?typeFilter=comment';
    $invoiceRequestsInboxUrl = \App\Filament\Pages\ClientInvoiceRequestsInboxPage::getUrl();
    $itemsByType = array_merge(
        [
            'task' => [],
            'comment' => [],
            'new_event' => [],
            'event' => [],
            'pending_cancellation_event' => [],
            'invoice_request' => [],
            'message' => [],
        ],
        $notificationItemsByType ?? [],
    );
@endphp

<div class="custom-topbar-notifications flex items-center lg:items-center" wire:ignore x-data="{
    routes: {
        tasks: @js($taskIndexUrl),
        comments: @js($commentsInboxUrl),
        newEvents: @js($newEventsIndexUrl),
        events: @js($confirmedEventsIndexUrl),
        pendingCancellationEvents: @js($pendingCancellationEventsIndexUrl),
        messages: @js($chatUrl),
        invoiceRequests: @js($invoiceRequestsInboxUrl),
    },
    newTasksCount: {{ $newTasksCount }},
    unreadMessagesCount: {{ $unreadMessagesCount }},
    commentsCount: {{ $commentsCount ?? 0 }},
    newEventsCount: {{ $newEventsCount ?? 0 }},
    confirmedEventsCount: {{ $confirmedEventsCount ?? 0 }},
    pendingCancellationEventsCount: {{ $pendingCancellationEventsCount ?? 0 }},
    invoiceRequestsCount: {{ $invoiceRequestsCount ?? 0 }},
    totalUnread: {{ $totalUnread ?? 0 }},
    canSeeInvoiceRequests: @js($canSeeInvoiceRequests ?? false),
    itemsByType: @js($itemsByType),
    openPanel: null,
    mobileOpen: false,
    panelCloseTimeout: null,
    pollIntervalMs: 60000,
    alertsEnabled: false,
    snapshotCounts() {
        return {
            tasks: this.newTasksCount,
            messages: this.unreadMessagesCount,
            comments: this.commentsCount,
            new_events: this.newEventsCount,
            confirmed_events: this.confirmedEventsCount,
            pending_cancellation_events: this.pendingCancellationEventsCount,
            invoice_requests: this.invoiceRequestsCount,
            total_unread: this.totalUnread,
        };
    },
    polishCount(count, one, few, many) {
        const n = Math.abs(Number(count) || 0);
        const mod10 = n % 10;
        const mod100 = n % 100;
        const word = (mod10 === 1 && mod100 !== 11) ? one : ((mod10 >= 2 && mod10 <= 4) && (mod100 < 12 || mod100 > 14) ? few : many);

        return `${n} ${word}`;
    },
    notifyNewItems(previous, next) {
        const delta = (key) => (Number(next?.[key] ?? 0) - Number(previous?.[key] ?? 0));
        const parts = [];

        const taskDelta = delta('tasks');
        if (taskDelta > 0) {
            parts.push(this.polishCount(taskDelta, 'nowe zadanie', 'nowe zadania', 'nowych zadań'));
        }

        const commentDelta = delta('comments');
        if (commentDelta > 0) {
            parts.push(this.polishCount(commentDelta, 'nowy komentarz', 'nowe komentarze', 'nowych komentarzy'));
        }

        const messageDelta = delta('messages');
        if (messageDelta > 0) {
            parts.push(this.polishCount(messageDelta, 'nowa wiadomość', 'nowe wiadomości', 'nowych wiadomości'));
        }

        const newEventDelta = delta('new_events');
        if (newEventDelta > 0) {
            parts.push(this.polishCount(newEventDelta, 'nowa impreza', 'nowe imprezy', 'nowych imprez'));
        }

        const confirmedDelta = delta('confirmed_events');
        if (confirmedDelta > 0) {
            parts.push(this.polishCount(confirmedDelta, 'potwierdzona impreza', 'potwierdzone imprezy', 'potwierdzonych imprez'));
        }

        const pendingCancellationDelta = delta('pending_cancellation_events');
        if (pendingCancellationDelta > 0) {
            parts.push(this.polishCount(pendingCancellationDelta, 'impreza do anulacji', 'imprezy do anulacji', 'imprez do anulacji'));
        }

        const invoiceDelta = delta('invoice_requests');
        if (invoiceDelta > 0) {
            parts.push(this.polishCount(invoiceDelta, 'wniosek o fakture', 'wnioski o fakture', 'wnioskow o fakture'));
        }

        if (parts.length === 0 || typeof FilamentNotification === 'undefined' || !this.alertsEnabled) {
            return;
        }

        new FilamentNotification()
            .title('Otrzymałeś nowe powiadomienia')
            .body(parts.join(' · '))
            .icon('heroicon-o-bell-alert')
            .color('info')
            .duration(10000)
            .send();
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
        this.invoiceRequestsCount = counts.invoice_requests ?? 0;
        this.totalUnread = counts.total_unread ?? 0;
        this.itemsByType = {
            task: itemsByType.task ?? [],
            comment: itemsByType.comment ?? [],
            new_event: itemsByType.new_event ?? [],
            event: itemsByType.event ?? [],
            pending_cancellation_event: itemsByType.pending_cancellation_event ?? [],
            invoice_request: itemsByType.invoice_request ?? [],
            message: itemsByType.message ?? [],
        };
    },
    itemList(type) {
        return this.itemsByType[type] ?? [];
    },
    togglePanel(panel) {
        this.cancelPanelClose();
        this.openPanel = this.openPanel === panel ? null : panel;
    },
    openPanelHover(panel) {
        this.cancelPanelClose();
        this.openPanel = panel;
    },
    schedulePanelClose(panel) {
        this.cancelPanelClose();
        this.panelCloseTimeout = setTimeout(() => {
            if (this.openPanel === panel) {
                this.openPanel = null;
            }
        }, 250);
    },
    cancelPanelClose() {
        if (this.panelCloseTimeout) {
            clearTimeout(this.panelCloseTimeout);
            this.panelCloseTimeout = null;
        }
    },
    closePanel(panel) {
        this.schedulePanelClose(panel);
    },
    refreshNotifications() {
        const previous = this.snapshotCounts();

        fetch('{{ route("admin.notifications.counts") }}?_=' + Date.now(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then(response => {
                if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return null;
                return response.json();
            })
            .then(data => {
                if (!data) return;

                const nextCounts = data.counts ?? data;
                this.syncPayload(data);
                this.notifyNewItems(previous, nextCounts);
            })
            .catch(error => console.error('Blad odswiezania powiadomien:', error));
    },
    markNotificationRead(item) {
        if (!item?.fingerprint) return;

        fetch('{{ route("admin.notifications.mark-read") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
            },
            body: JSON.stringify({ fingerprint: item.fingerprint }),
        })
            .finally(() => this.refreshNotifications());
    },
}" x-init="
    setTimeout(() => { alertsEnabled = true; }, 5000);
    setInterval(() => refreshNotifications(), pollIntervalMs);
    window.addEventListener('focus', () => refreshNotifications());
    window.addEventListener('refresh-notifications', () => setTimeout(() => refreshNotifications(), 100));
    document.addEventListener('livewire:navigated', () => setTimeout(() => refreshNotifications(), 200));
" @refresh-notifications.window="refreshNotifications()" @click.away="openPanel = null">

    <button
        type="button"
        class="inline-flex lg:hidden items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
        @click="mobileOpen = !mobileOpen"
        :aria-expanded="mobileOpen"
    >
        <span>Powiadomienia</span>
        <span class="inline-flex min-w-[1.65rem] items-center justify-center rounded-full bg-amber-600 px-1.5 py-0.5 text-[11px] font-bold text-white" x-text="totalUnread > 99 ? '99+' : totalUnread"></span>
    </button>
    <a
        href="{{ $inboxUrl }}"
        class="ml-2 hidden text-xs font-semibold text-primary-600 hover:underline lg:inline"
    >
        Centrum powiadomień →
    </a>

    <div
        class="topbar-notifications-items w-full lg:w-auto lg:mr-4"
        :class="mobileOpen ? 'mt-2 flex flex-wrap items-center gap-2' : 'hidden lg:flex lg:items-center lg:gap-3'"
    >

    <div class="relative" @mouseenter="openPanelHover('tasks')" @mouseleave="closePanel('tasks')">
        <div class="flex items-center gap-2">
            <a href="{{ $taskIndexUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-500/20 group-hover:bg-orange-200 dark:group-hover:bg-orange-500/30 transition-colors">
                    <svg class="h-4 w-4 text-orange-600 dark:text-orange-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3-7.5H21m-9.75-3.75h9.75m-9.75 3.75h9.75M3.375 7.5h.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.75a1.125 1.125 0 01-1.125-1.125V8.625c0-.621.504-1.125 1.125-1.125z" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900">Zadania</div>
                    <div class="text-xs text-gray-600" x-text="newTasksCount > 0 ? newTasksCount + ' nowych' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('tasks')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="newTasksCount > 0 ? 'text-white bg-red-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'tasks'">
                <span x-text="newTasksCount > 99 ? '99+' : newTasksCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'tasks'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] lg:left-auto lg:right-0 lg:max-w-[90vw] z-[9999]">
            <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900 flex items-center justify-between gap-2">
                <span>Nowe zadania</span>
                <span class="text-xs font-medium text-gray-500" x-text="newTasksCount + ' nowych'"></span>
            </div>
            <div class="topbar-notification-scroll max-h-[14rem] overflow-y-auto divide-y divide-gray-100" @wheel.stop>
                <template x-if="itemList('task').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-600">Brak nowych zadań.</div>
                </template>
                <template x-for="item in itemList('task')" :key="item.fingerprint || ('task-' + item.id)">
                    <a :href="item.url || routes.tasks" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 transition-colors" :class="item.is_read ? 'opacity-60' : 'bg-orange-50/40'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate" :class="!item.is_read && 'font-semibold'" x-text="item.title || 'Zadanie'"></div>
                                <div class="text-xs text-gray-600 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
            </div>
        </div>
    </div>

    <div class="relative" @mouseenter="openPanelHover('comments')" @mouseleave="closePanel('comments')">
        <div class="flex items-center gap-2">
            <a href="{{ $commentsInboxUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/20 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-500/30 transition-colors">
                    <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0H12m3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0h-.375M21 12c0 4.97-4.03 9-9 9a8.965 8.965 0 01-4.255-1.071L3 21l1.071-4.745A8.965 8.965 0 013 12c0-4.97 4.03-9 9-9s9 4.03 9 9z" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900">Komentarze</div>
                    <div class="text-xs text-gray-600" x-text="commentsCount > 0 ? commentsCount + ' nowych' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('comments')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="commentsCount > 0 ? 'text-white bg-emerald-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'comments'">
                <span x-text="commentsCount > 99 ? '99+' : commentsCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'comments'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] lg:left-auto lg:right-0 lg:max-w-[90vw] z-[9999]">
            <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900 flex items-center justify-between gap-2">
                <span>Ostatnie komentarze</span>
                <span class="text-xs font-medium text-gray-500" x-text="commentsCount > 0 ? commentsCount + ' nowych' : 'Brak nowych'"></span>
            </div>
            <div class="topbar-notification-scroll max-h-[14rem] overflow-y-auto divide-y divide-gray-100" @wheel.stop>
                <template x-if="itemList('comment').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-600">Brak nowych komentarzy.</div>
                </template>
                <template x-for="item in itemList('comment')" :key="item.fingerprint || ('comment-' + item.id)">
                    <a :href="item.url || routes.comments" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 transition-colors" :class="item.is_read ? 'opacity-60' : 'bg-emerald-50/40'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate" :class="!item.is_read && 'font-semibold'" x-text="item.title || 'Komentarz'"></div>
                                <div class="text-xs text-gray-600 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
            </div>
        </div>
    </div>

    <div class="relative" @mouseenter="openPanelHover('new-events')" @mouseleave="closePanel('new-events')">
        <div class="flex items-center gap-2">
            <a href="{{ $newEventsIndexUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-500/20 group-hover:bg-sky-200 dark:group-hover:bg-sky-500/30 transition-colors">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900">Nowe imprezy</div>
                    <div class="text-xs text-gray-600" x-text="newEventsCount > 0 ? newEventsCount + ' nowych' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('new-events')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="newEventsCount > 0 ? 'text-white bg-sky-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'new-events'">
                <span x-text="newEventsCount > 99 ? '99+' : newEventsCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'new-events'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] lg:left-auto lg:right-0 lg:max-w-[90vw] z-[9999]">
            <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900 flex items-center justify-between gap-2">
                <span>Nowe imprezy</span>
                <span class="text-xs font-medium text-gray-500" x-text="newEventsCount + ' zdarzeń'"></span>
            </div>
            <div class="topbar-notification-scroll max-h-[14rem] overflow-y-auto divide-y divide-gray-100" @wheel.stop>
                <template x-if="itemList('new_event').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-600">Brak nowych imprez.</div>
                </template>
                <template x-for="(item, index) in itemList('new_event')" :key="'new-event-' + index">
                    <a :href="item.url || routes.newEvents" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate" x-text="item.title || 'Nowa impreza'"></div>
                                <div class="text-xs text-gray-600 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
            </div>
        </div>
    </div>

    <div class="relative" @mouseenter="openPanelHover('events')" @mouseleave="closePanel('events')">
        <div class="flex items-center gap-2">
            <a href="{{ $confirmedEventsIndexUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-500/20 group-hover:bg-violet-200 dark:group-hover:bg-violet-500/30 transition-colors">
                    <svg class="h-4 w-4 text-violet-600 dark:text-violet-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V8.25A2.25 2.25 0 015.25 6h13.5A2.25 2.25 0 0121 8.25v10.5M3 18.75A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75M3 18.75v-6.75A2.25 2.25 0 015.25 9.75h13.5A2.25 2.25 0 0121 12v6.75" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900">Nowe potwierdzenia</div>
                    <div class="text-xs text-gray-600" x-text="confirmedEventsCount > 0 ? confirmedEventsCount + ' nowych' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('events')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="confirmedEventsCount > 0 ? 'text-white bg-violet-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'events'">
                <span x-text="confirmedEventsCount > 99 ? '99+' : confirmedEventsCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'events'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] lg:left-auto lg:right-0 lg:max-w-[90vw] z-[9999]">
            <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900 flex items-center justify-between gap-2">
                <span>Nowe potwierdzenia</span>
                <span class="text-xs font-medium text-gray-500" x-text="confirmedEventsCount + ' zdarzeń'"></span>
            </div>
            <div class="topbar-notification-scroll max-h-[14rem] overflow-y-auto divide-y divide-gray-100" @wheel.stop>
                <template x-if="itemList('event').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-600">Brak nowych potwierdzen imprez.</div>
                </template>
                <template x-for="(item, index) in itemList('event')" :key="'event-' + index">
                    <a :href="item.url || routes.events" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate" x-text="item.title || 'Impreza'"></div>
                                <div class="text-xs text-gray-600 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
            </div>
        </div>
    </div>

    <div class="relative" @mouseenter="openPanelHover('pending-cancellation-events')" @mouseleave="closePanel('pending-cancellation-events')">
        <div class="flex items-center gap-2">
            <a href="{{ $pendingCancellationEventsIndexUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-500/20 group-hover:bg-rose-200 dark:group-hover:bg-rose-500/30 transition-colors">
                    <svg class="h-4 w-4 text-rose-600 dark:text-rose-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 12H6" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900">Do anulacji</div>
                    <div class="text-xs text-gray-600" x-text="pendingCancellationEventsCount > 0 ? pendingCancellationEventsCount + ' nowych' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('pending-cancellation-events')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="pendingCancellationEventsCount > 0 ? 'text-white bg-rose-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'pending-cancellation-events'">
                <span x-text="pendingCancellationEventsCount > 99 ? '99+' : pendingCancellationEventsCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'pending-cancellation-events'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] lg:left-auto lg:right-0 lg:max-w-[90vw] z-[9999]">
            <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900 flex items-center justify-between gap-2">
                <span>Imprezy do anulacji</span>
                <span class="text-xs font-medium text-gray-500" x-text="pendingCancellationEventsCount + ' zdarzeń'"></span>
            </div>
            <div class="topbar-notification-scroll max-h-[14rem] overflow-y-auto divide-y divide-gray-100" @wheel.stop>
                <template x-if="itemList('pending_cancellation_event').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-600">Brak imprez do anulacji.</div>
                </template>
                <template x-for="(item, index) in itemList('pending_cancellation_event')" :key="'pending-' + index">
                    <a :href="item.url || routes.pendingCancellationEvents" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate" x-text="item.title || 'Impreza do anulacji'"></div>
                                <div class="text-xs text-gray-600 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
            </div>
        </div>
    </div>



    @if ($canSeeInvoiceRequests ?? false)
    <div class="relative" @mouseenter="openPanelHover('invoice-requests')" @mouseleave="closePanel('invoice-requests')">
        <div class="flex items-center gap-2">
            <button type="button" @click="togglePanel('invoice-requests')" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-500/20 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-500/30 transition-colors">
                    <svg class="h-4 w-4 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900">Wnioski o fakture</div>
                    <div class="text-xs text-gray-600" x-text="invoiceRequestsCount > 0 ? invoiceRequestsCount + ' nowych' : 'Brak nowych'"></div>
                </div>
            </button>

            <button type="button" @click.stop="togglePanel('invoice-requests')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="invoiceRequestsCount > 0 ? 'text-white bg-indigo-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'invoice-requests'">
                <span x-text="invoiceRequestsCount > 99 ? '99+' : invoiceRequestsCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'invoice-requests'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] lg:left-auto lg:right-0 lg:max-w-[90vw] z-[9999]">
            <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900 flex items-center justify-between gap-2">
                <span>Wnioski o fakture</span>
                <a :href="routes.invoiceRequests" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Pelna lista</a>
            </div>
            <div class="topbar-notification-scroll max-h-[14rem] overflow-y-auto divide-y divide-gray-100" @wheel.stop>
                <template x-if="itemList('invoice_request').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-600">Brak nowych wnioskow o fakture.</div>
                </template>
                <template x-for="(item, index) in itemList('invoice_request')" :key="'invoice-' + index">
                    <a :href="item.url || '#'" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 transition-colors" :class="item.is_read ? 'opacity-60' : 'bg-indigo-50/40'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate" :class="!item.is_read && 'font-semibold'" x-text="item.title || 'Wniosek o fakture'"></div>
                                <div class="text-xs text-gray-600 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
            </div>
        </div>
    </div>
    @endif

    <div class="hidden lg:flex items-center space-x-1 px-3 py-2 border-l border-gray-200" x-data="{
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

    <!-- Wiadomości: przeniesione na koniec kontenera -->
    <div class="relative" @mouseenter="openPanelHover('messages')" @mouseleave="closePanel('messages')">
        <div class="flex items-center gap-2">
            <a href="{{ $chatUrl }}" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/20 group-hover:bg-blue-200 dark:group-hover:bg-blue-500/30 transition-colors">
                    <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                    </svg>
                </div>
                <div class="hidden sm:block text-left">
                    <div class="text-xs font-medium text-gray-900">Wiadomosci</div>
                    <div class="text-xs text-gray-600" x-text="unreadMessagesCount > 0 ? unreadMessagesCount + ' nowych' : 'Brak nowych'"></div>
                </div>
            </a>

            <button type="button" @click.stop="togglePanel('messages')" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none rounded-full min-w-[1.8rem] h-6 ring-1 transition-colors" :class="unreadMessagesCount > 0 ? 'text-white bg-blue-600 ring-white dark:ring-gray-900' : 'text-gray-500 bg-gray-100 ring-gray-200 dark:text-gray-400 dark:bg-gray-800 dark:ring-gray-700'" :aria-expanded="openPanel === 'messages'">
                <span x-text="unreadMessagesCount > 99 ? '99+' : unreadMessagesCount"></span>
            </button>
        </div>

        <div x-show="openPanel === 'messages'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] lg:left-auto lg:right-0 lg:max-w-[90vw] z-[9999]">
            <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900 flex items-center justify-between gap-2">
                <span>Ostatnie wiadomosci</span>
                <span class="text-xs font-medium text-gray-500" x-text="unreadMessagesCount + ' zdarzeń'"></span>
            </div>
            <div class="topbar-notification-scroll max-h-[14rem] overflow-y-auto divide-y divide-gray-100" @wheel.stop>
                <template x-if="itemList('message').length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-600">Brak nowych wiadomosci.</div>
                </template>
                <template x-for="(item, index) in itemList('message')" :key="'message-' + index">
                    <a :href="item.url || routes.messages" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate" x-text="item.title || 'Wiadomosc'"></div>
                                <div class="text-xs text-gray-600 mt-0.5" x-text="item.meta || ''"></div>
                            </div>
                            <div class="text-[11px] text-gray-500 whitespace-nowrap" x-text="item.time || ''"></div>
                        </div>
                    </a>
                </template>
            </div>
            </div>
        </div>
    </div>

</div>
