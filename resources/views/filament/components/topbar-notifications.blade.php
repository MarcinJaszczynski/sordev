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
    $canSeeInvoiceRequests = (bool) ($canSeeInvoiceRequests ?? false);
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
    $workCount = (int) ($workCount ?? (($newTasksCount ?? 0) + ($commentsCount ?? 0)));
    $eventsCount = (int) ($eventsCount ?? (
        ($newEventsCount ?? 0)
        + ($confirmedEventsCount ?? 0)
        + ($pendingCancellationEventsCount ?? 0)
        + ($invoiceRequestsCount ?? 0)
    ));
@endphp

<div class="custom-topbar-notifications flex items-center gap-2" wire:ignore x-data="{
    routes: {
        tasks: @js($taskIndexUrl),
        comments: @js($commentsInboxUrl),
        newEvents: @js($newEventsIndexUrl),
        events: @js($confirmedEventsIndexUrl),
        pendingCancellationEvents: @js($pendingCancellationEventsIndexUrl),
        messages: @js($chatUrl),
        invoiceRequests: @js($invoiceRequestsInboxUrl),
        inbox: @js($inboxUrl),
    },
    newTasksCount: {{ (int) ($newTasksCount ?? 0) }},
    unreadMessagesCount: {{ (int) ($unreadMessagesCount ?? 0) }},
    commentsCount: {{ (int) ($commentsCount ?? 0) }},
    newEventsCount: {{ (int) ($newEventsCount ?? 0) }},
    confirmedEventsCount: {{ (int) ($confirmedEventsCount ?? 0) }},
    pendingCancellationEventsCount: {{ (int) ($pendingCancellationEventsCount ?? 0) }},
    invoiceRequestsCount: {{ (int) ($invoiceRequestsCount ?? 0) }},
    workCount: {{ $workCount }},
    eventsCount: {{ $eventsCount }},
    totalUnread: {{ (int) ($totalUnread ?? 0) }},
    canSeeInvoiceRequests: @js($canSeeInvoiceRequests),
    itemsByType: @js($itemsByType),
    openPanel: null,
    mobileOpen: false,
    panelCloseTimeout: null,
    pollIntervalMs: 15000,
    alertsEnabled: false,
    typeLabels: {
        task: 'Zadanie',
        comment: 'Komentarz',
        new_event: 'Nowa impreza',
        event: 'Potwierdzenie',
        pending_cancellation_event: 'Do anulacji',
        invoice_request: 'Wniosek o fakturę',
        message: 'Wiadomość',
    },
    typeTone: {
        task: 'orange',
        comment: 'emerald',
        new_event: 'sky',
        event: 'violet',
        pending_cancellation_event: 'rose',
        invoice_request: 'indigo',
        message: 'blue',
    },
    fallbackUrl(type) {
        return ({
            task: this.routes.tasks,
            comment: this.routes.comments,
            new_event: this.routes.newEvents,
            event: this.routes.events,
            pending_cancellation_event: this.routes.pendingCancellationEvents,
            invoice_request: this.routes.invoiceRequests,
            message: this.routes.messages,
        })[type] || this.routes.inbox;
    },
    snapshotCounts() {
        return {
            tasks: this.newTasksCount,
            messages: this.unreadMessagesCount,
            comments: this.commentsCount,
            new_events: this.newEventsCount,
            confirmed_events: this.confirmedEventsCount,
            pending_cancellation_events: this.pendingCancellationEventsCount,
            invoice_requests: this.invoiceRequestsCount,
            work: this.workCount,
            events: this.eventsCount,
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
            parts.push(this.polishCount(invoiceDelta, 'wniosek o fakturę', 'wnioski o fakturę', 'wniosków o fakturę'));
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
        this.workCount = counts.work ?? (this.newTasksCount + this.commentsCount);
        this.eventsCount = counts.events ?? (
            this.newEventsCount
            + this.confirmedEventsCount
            + this.pendingCancellationEventsCount
            + this.invoiceRequestsCount
        );
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
        const list = this.itemsByType?.[type];
        return Array.isArray(list) ? list.filter((item) => !!item) : [];
    },
    itemKey(prefix, item, index) {
        return `${prefix}-${item?.fingerprint || item?.id || 'x'}-${index}`;
    },
    badgeText(count) {
        const n = Number(count) || 0;
        return n > 99 ? '99+' : String(n);
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
            .catch(error => console.error('Błąd odświeżania powiadomień:', error));
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
    bindLivewireRefresh() {
        if (typeof Livewire === 'undefined') {
            return;
        }

        Livewire.on('refresh-notifications', () => setTimeout(() => this.refreshNotifications(), 100));
    },
}" x-init="
    setTimeout(() => { alertsEnabled = true; }, 5000);
    setInterval(() => refreshNotifications(), pollIntervalMs);
    window.addEventListener('focus', () => refreshNotifications());
    window.addEventListener('refresh-notifications', () => setTimeout(() => refreshNotifications(), 100));
    document.addEventListener('livewire:navigated', () => setTimeout(() => refreshNotifications(), 200));
    if (typeof Livewire !== 'undefined') {
        bindLivewireRefresh();
    } else {
        document.addEventListener('livewire:init', () => bindLivewireRefresh(), { once: true });
    }
" @refresh-notifications.window="refreshNotifications()" @click.away="openPanel = null">

    <button
        type="button"
        class="inline-flex lg:hidden items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
        @click="mobileOpen = !mobileOpen"
        :aria-expanded="mobileOpen"
    >
        <span>Powiadomienia</span>
        <span
            class="inline-flex min-w-[1.65rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold"
            :class="totalUnread > 0 ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'"
            x-text="badgeText(totalUnread)"
        ></span>
    </button>

    <a
        href="{{ $inboxUrl }}"
        class="hidden text-xs font-semibold text-primary-600 hover:underline lg:inline"
    >
        Centrum →
    </a>

    <div
        class="topbar-notifications-items"
        :class="mobileOpen ? 'mt-2 flex w-full flex-wrap items-center gap-1.5' : 'hidden lg:flex lg:items-center lg:gap-1.5'"
    >
        {{-- Do zrobienia: zadania + komentarze --}}
        <div class="relative" @mouseenter="openPanelHover('work')" @mouseleave="closePanel('work')">
            <button
                type="button"
                @click.stop="togglePanel('work')"
                class="topbar-notify-trigger group"
                :class="workCount > 0 ? 'topbar-notify-trigger--active' : ''"
                :aria-expanded="openPanel === 'work'"
                title="Do zrobienia"
            >
                <span class="topbar-notify-icon topbar-notify-icon--orange">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3-7.5H21m-9.75-3.75h9.75m-9.75 3.75h9.75M3.375 7.5h.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.75a1.125 1.125 0 01-1.125-1.125V8.625c0-.621.504-1.125 1.125-1.125z" />
                    </svg>
                </span>
                <span class="topbar-notify-label">
                    <span class="topbar-notify-title">Do zrobienia</span>
                    <span class="topbar-notify-sub" x-text="workCount > 0 ? workCount + ' nowych' : 'Brak nowych'"></span>
                </span>
                <span
                    class="topbar-notify-badge"
                    :class="workCount > 0 ? 'topbar-notify-badge--hot' : 'topbar-notify-badge--muted'"
                    x-text="badgeText(workCount)"
                ></span>
            </button>

            <div x-show="openPanel === 'work'" x-cloak x-transition class="topbar-notify-dropdown">
                <div class="topbar-notification-panel">
                    <div class="topbar-notify-panel-head">
                        <span>Do zrobienia</span>
                        <a :href="routes.tasks" class="topbar-notify-panel-link">Lista zadań</a>
                    </div>

                    <div class="topbar-notification-scroll" @wheel.stop>
                        <template x-if="itemList('task').length === 0 && itemList('comment').length === 0">
                            <div class="topbar-notify-empty">Brak nowych zadań i komentarzy.</div>
                        </template>

                        <template x-if="itemList('task').length > 0">
                            <div>
                                <div class="topbar-notify-section">
                                    <span>Zadania</span>
                                    <span class="topbar-notify-section-count" x-text="newTasksCount"></span>
                                </div>
                                <template x-for="(item, index) in itemList('task')" :key="itemKey('task', item, index)">
                                    <a
                                        :href="item.url || routes.tasks"
                                        @click="markNotificationRead(item)"
                                        class="topbar-notify-item"
                                        :class="item.is_read ? 'opacity-60' : 'topbar-notify-item--unread-orange'"
                                    >
                                        <div class="topbar-notify-item-main">
                                            <div class="topbar-notify-item-title" :class="!item.is_read && 'font-semibold'" x-text="item.title || 'Zadanie'"></div>
                                            <div class="topbar-notify-item-meta" x-text="item.meta || ''"></div>
                                        </div>
                                        <div class="topbar-notify-item-time" x-text="item.time || ''"></div>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="itemList('comment').length > 0">
                            <div>
                                <div class="topbar-notify-section">
                                    <span>Komentarze</span>
                                    <span class="topbar-notify-section-count" x-text="commentsCount"></span>
                                </div>
                                <template x-for="(item, index) in itemList('comment')" :key="itemKey('comment', item, index)">
                                    <a
                                        :href="item.url || routes.comments"
                                        @click="markNotificationRead(item)"
                                        class="topbar-notify-item"
                                        :class="item.is_read ? 'opacity-60' : 'topbar-notify-item--unread-emerald'"
                                    >
                                        <div class="topbar-notify-item-main">
                                            <div class="topbar-notify-item-title" :class="!item.is_read && 'font-semibold'" x-text="item.title || 'Komentarz'"></div>
                                            <div class="topbar-notify-item-meta" x-text="item.meta || ''"></div>
                                        </div>
                                        <div class="topbar-notify-item-time" x-text="item.time || ''"></div>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Imprezy: nowe + potwierdzenia + anulacje + wnioski --}}
        <div class="relative" @mouseenter="openPanelHover('events')" @mouseleave="closePanel('events')">
            <button
                type="button"
                @click.stop="togglePanel('events')"
                class="topbar-notify-trigger group"
                :class="eventsCount > 0 ? 'topbar-notify-trigger--active' : ''"
                :aria-expanded="openPanel === 'events'"
                title="Imprezy"
            >
                <span class="topbar-notify-icon topbar-notify-icon--violet">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V8.25A2.25 2.25 0 015.25 6h13.5A2.25 2.25 0 0121 8.25v10.5M3 18.75A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75M3 18.75v-6.75A2.25 2.25 0 015.25 9.75h13.5A2.25 2.25 0 0121 12v6.75" />
                    </svg>
                </span>
                <span class="topbar-notify-label">
                    <span class="topbar-notify-title">Imprezy</span>
                    <span class="topbar-notify-sub" x-text="eventsCount > 0 ? eventsCount + ' zmian' : 'Brak zmian'"></span>
                </span>
                <span
                    class="topbar-notify-badge"
                    :class="eventsCount > 0 ? 'topbar-notify-badge--violet' : 'topbar-notify-badge--muted'"
                    x-text="badgeText(eventsCount)"
                ></span>
            </button>

            <div x-show="openPanel === 'events'" x-cloak x-transition class="topbar-notify-dropdown">
                <div class="topbar-notification-panel">
                    <div class="topbar-notify-panel-head">
                        <span>Zmiany imprez</span>
                        <a :href="routes.newEvents" class="topbar-notify-panel-link">Lista imprez</a>
                    </div>

                    <div class="topbar-notification-scroll" @wheel.stop>
                        <template x-if="eventsCount === 0">
                            <div class="topbar-notify-empty">Brak nowych zmian w imprezach.</div>
                        </template>

                        <template x-if="itemList('new_event').length > 0">
                            <div>
                                <div class="topbar-notify-section">
                                    <span>Nowe</span>
                                    <span class="topbar-notify-section-count" x-text="newEventsCount"></span>
                                </div>
                                <template x-for="(item, index) in itemList('new_event')" :key="itemKey('new-event', item, index)">
                                    <a :href="item.url || routes.newEvents" @click="markNotificationRead(item)" class="topbar-notify-item topbar-notify-item--unread-sky">
                                        <div class="topbar-notify-item-main">
                                            <div class="topbar-notify-item-title" x-text="item.title || 'Nowa impreza'"></div>
                                            <div class="topbar-notify-item-meta" x-text="item.meta || ''"></div>
                                        </div>
                                        <div class="topbar-notify-item-time" x-text="item.time || ''"></div>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="itemList('event').length > 0">
                            <div>
                                <div class="topbar-notify-section">
                                    <span>Potwierdzenia</span>
                                    <span class="topbar-notify-section-count" x-text="confirmedEventsCount"></span>
                                </div>
                                <template x-for="(item, index) in itemList('event')" :key="itemKey('event', item, index)">
                                    <a :href="item.url || routes.events" @click="markNotificationRead(item)" class="topbar-notify-item topbar-notify-item--unread-violet">
                                        <div class="topbar-notify-item-main">
                                            <div class="topbar-notify-item-title" x-text="item.title || 'Impreza'"></div>
                                            <div class="topbar-notify-item-meta" x-text="item.meta || ''"></div>
                                        </div>
                                        <div class="topbar-notify-item-time" x-text="item.time || ''"></div>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="itemList('pending_cancellation_event').length > 0">
                            <div>
                                <div class="topbar-notify-section">
                                    <span>Do anulacji</span>
                                    <span class="topbar-notify-section-count" x-text="pendingCancellationEventsCount"></span>
                                </div>
                                <template x-for="(item, index) in itemList('pending_cancellation_event')" :key="itemKey('pending', item, index)">
                                    <a :href="item.url || routes.pendingCancellationEvents" @click="markNotificationRead(item)" class="topbar-notify-item topbar-notify-item--unread-rose">
                                        <div class="topbar-notify-item-main">
                                            <div class="topbar-notify-item-title" x-text="item.title || 'Impreza do anulacji'"></div>
                                            <div class="topbar-notify-item-meta" x-text="item.meta || ''"></div>
                                        </div>
                                        <div class="topbar-notify-item-time" x-text="item.time || ''"></div>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="canSeeInvoiceRequests && itemList('invoice_request').length > 0">
                            <div>
                                <div class="topbar-notify-section">
                                    <span>Wnioski o fakturę</span>
                                    <a :href="routes.invoiceRequests" class="topbar-notify-panel-link text-[11px]">Pełna lista</a>
                                </div>
                                <template x-for="(item, index) in itemList('invoice_request')" :key="itemKey('invoice', item, index)">
                                    <a
                                        :href="item.url || routes.invoiceRequests"
                                        @click="markNotificationRead(item)"
                                        class="topbar-notify-item"
                                        :class="item.is_read ? 'opacity-60' : 'topbar-notify-item--unread-indigo'"
                                    >
                                        <div class="topbar-notify-item-main">
                                            <div class="topbar-notify-item-title" :class="!item.is_read && 'font-semibold'" x-text="item.title || 'Wniosek o fakturę'"></div>
                                            <div class="topbar-notify-item-meta" x-text="item.meta || ''"></div>
                                        </div>
                                        <div class="topbar-notify-item-time" x-text="item.time || ''"></div>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Wiadomości --}}
        <div class="relative" @mouseenter="openPanelHover('messages')" @mouseleave="closePanel('messages')">
            <button
                type="button"
                @click.stop="togglePanel('messages')"
                class="topbar-notify-trigger group"
                :class="unreadMessagesCount > 0 ? 'topbar-notify-trigger--active' : ''"
                :aria-expanded="openPanel === 'messages'"
                title="Wiadomości"
            >
                <span class="topbar-notify-icon topbar-notify-icon--blue">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                    </svg>
                </span>
                <span class="topbar-notify-label">
                    <span class="topbar-notify-title">Wiadomości</span>
                    <span class="topbar-notify-sub" x-text="unreadMessagesCount > 0 ? unreadMessagesCount + ' nowych' : 'Brak nowych'"></span>
                </span>
                <span
                    class="topbar-notify-badge"
                    :class="unreadMessagesCount > 0 ? 'topbar-notify-badge--blue' : 'topbar-notify-badge--muted'"
                    x-text="badgeText(unreadMessagesCount)"
                ></span>
            </button>

            <div x-show="openPanel === 'messages'" x-cloak x-transition class="topbar-notify-dropdown">
                <div class="topbar-notification-panel">
                    <div class="topbar-notify-panel-head">
                        <span>Wiadomości</span>
                        <a :href="routes.messages" class="topbar-notify-panel-link">Otwórz chat</a>
                    </div>
                    <div class="topbar-notification-scroll" @wheel.stop>
                        <template x-if="itemList('message').length === 0">
                            <div class="topbar-notify-empty">Brak nowych wiadomości.</div>
                        </template>
                        <template x-for="(item, index) in itemList('message')" :key="itemKey('message', item, index)">
                            <a :href="item.url || routes.messages" @click="markNotificationRead(item)" class="topbar-notify-item">
                                <div class="topbar-notify-item-main">
                                    <div class="topbar-notify-item-title" x-text="item.title || 'Wiadomość'"></div>
                                    <div class="topbar-notify-item-meta" x-text="item.meta || ''"></div>
                                </div>
                                <div class="topbar-notify-item-time" x-text="item.time || ''"></div>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="hidden lg:flex items-center gap-0.5 border-l border-gray-200 pl-2 ml-1" x-data="{
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
            <button @click="decreaseSize()" type="button" class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors" title="Zmniejsz rozmiar">
                <span class="text-sm font-semibold">A-</span>
            </button>
            <button @click="resetSize()" type="button" class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors" title="Resetuj rozmiar">
                <span class="text-xs font-semibold">Reset</span>
            </button>
            <button @click="increaseSize()" type="button" class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors" title="Powiększ rozmiar">
                <span class="text-sm font-semibold">A+</span>
            </button>
        </div>
    </div>
</div>
