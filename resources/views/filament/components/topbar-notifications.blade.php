@php
    $taskIndexUrl = route('filament.admin.resources.tasks.index');
    $eventsIndexUrl = route('filament.admin.resources.events.index');
    $chatUrl = route('filament.admin.pages.chat');
    $inboxUrl = \App\Filament\Pages\NotificationsInboxPage::getUrl();
    $newEventsIndexUrl = $eventsIndexUrl.'?tableFilters[status][value]=inquiry';
    $confirmedEventsIndexUrl = $eventsIndexUrl.'?tableFilters[status][value]=confirmed';
    $pendingCancellationEventsIndexUrl = $eventsIndexUrl.'?tableFilters[status][value]=pending_cancellation';
    $invoiceRequestsInboxUrl = \App\Filament\Pages\ClientInvoiceRequestsInboxPage::getUrl();
    /**
     * Dane bootujemy przez <script type="application/json"> + factory JS,
     * NIE przez @js() / inline w atrybucie x-data.
     * Powód: w HTML atrybut w "..." sekwencja \" zamyka atrybut (CSRF),
     * a duże JSON-e z tytułami psują parser Alpine → Invalid or unexpected token.
     */
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
    $topbarBoot = [
        'routes' => [
            'tasks' => $taskIndexUrl,
            'inbox' => $inboxUrl,
            'comments' => $inboxUrl.'?typeFilter=comment',
            'newEvents' => $newEventsIndexUrl,
            'events' => $confirmedEventsIndexUrl,
            'pendingCancellationEvents' => $pendingCancellationEventsIndexUrl,
            'messages' => $chatUrl,
            'invoiceRequests' => $invoiceRequestsInboxUrl,
        ],
        'newTasksCount' => (int) $newTasksCount,
        'commentsCount' => (int) ($commentsCount ?? 0),
        'newEventsCount' => (int) ($newEventsCount ?? 0),
        'confirmedEventsCount' => (int) ($confirmedEventsCount ?? 0),
        'pendingCancellationEventsCount' => (int) ($pendingCancellationEventsCount ?? 0),
        'invoiceRequestsCount' => (int) ($invoiceRequestsCount ?? 0),
        'unreadMessagesCount' => (int) $unreadMessagesCount,
        'totalUnread' => (int) ($totalUnread ?? 0),
        'canSeeInvoiceRequests' => (bool) ($canSeeInvoiceRequests ?? false),
        'itemsByType' => $itemsByType,
        'countsUrl' => route('admin.notifications.counts'),
        'markReadUrl' => route('admin.notifications.mark-read'),
        'pollIntervalMs' => 15000,
    ];
@endphp

{{-- Boot JSON poza atrybutem HTML — bezpieczne dla cudzysłowów i UTF-8 --}}
<script type="application/json" id="sor-topbar-notifications-boot">@json($topbarBoot)</script>
<script>
    window.sorTopbarNotifications = function sorTopbarNotifications() {
        const bootEl = document.getElementById('sor-topbar-notifications-boot');
        let boot = {};
        try {
            boot = JSON.parse(bootEl?.textContent || '{}');
        } catch (e) {
            console.error('Topbar notifications boot JSON invalid:', e);
        }

        return {
            routes: boot.routes || {},
            newTasksCount: Number(boot.newTasksCount || 0),
            commentsCount: Number(boot.commentsCount || 0),
            newEventsCount: Number(boot.newEventsCount || 0),
            confirmedEventsCount: Number(boot.confirmedEventsCount || 0),
            pendingCancellationEventsCount: Number(boot.pendingCancellationEventsCount || 0),
            invoiceRequestsCount: Number(boot.invoiceRequestsCount || 0),
            unreadMessagesCount: Number(boot.unreadMessagesCount || 0),
            totalUnread: Number(boot.totalUnread || 0),
            canSeeInvoiceRequests: Boolean(boot.canSeeInvoiceRequests),
            itemsByType: boot.itemsByType || {
                task: [],
                comment: [],
                new_event: [],
                event: [],
                pending_cancellation_event: [],
                invoice_request: [],
                message: [],
            },
            countsUrl: boot.countsUrl || '',
            markReadUrl: boot.markReadUrl || '',
            openPanel: null,
            panelCloseTimeout: null,
            pollIntervalMs: Number(boot.pollIntervalMs || 15000),
            alertsEnabled: false,
            get activityCount() {
                return Number(this.newTasksCount || 0) + Number(this.commentsCount || 0);
            },
            get eventsCount() {
                return Number(this.newEventsCount || 0) + Number(this.confirmedEventsCount || 0);
            },
            activityItems() {
                return [...(this.itemsByType.task || []), ...(this.itemsByType.comment || [])]
                    .sort((a, b) => Number(b.revision || 0) - Number(a.revision || 0));
            },
            eventsItems() {
                return [...(this.itemsByType.new_event || []), ...(this.itemsByType.event || [])]
                    .sort((a, b) => Number(b.revision || 0) - Number(a.revision || 0));
            },
            itemList(type) {
                return this.itemsByType[type] ?? [];
            },
            snapshotCounts() {
                return {
                    tasks: this.activityCount,
                    messages: this.unreadMessagesCount,
                    events: this.eventsCount,
                    pending_cancellation_events: this.pendingCancellationEventsCount,
                    invoice_requests: this.invoiceRequestsCount,
                    total_unread: this.totalUnread,
                };
            },
            polishCount(count, one, few, many) {
                const n = Math.abs(Number(count) || 0);
                const mod10 = n % 10;
                const mod100 = n % 100;
                const word = (mod10 === 1 && mod100 !== 11)
                    ? one
                    : ((mod10 >= 2 && mod10 <= 4) && (mod100 < 12 || mod100 > 14) ? few : many);
                return `${n} ${word}`;
            },
            notifyNewItems(previous, next) {
                const delta = (key) => (Number(next?.[key] ?? 0) - Number(previous?.[key] ?? 0));
                const parts = [];
                const taskDelta = delta('tasks');
                if (taskDelta > 0) parts.push(this.polishCount(taskDelta, 'nowe zadanie', 'nowe zadania', 'nowych zadań'));
                const eventDelta = delta('events');
                if (eventDelta > 0) parts.push(this.polishCount(eventDelta, 'nowa impreza', 'nowe imprezy', 'nowych imprez'));
                const pendingCancellationDelta = delta('pending_cancellation_events');
                if (pendingCancellationDelta > 0) {
                    parts.push(this.polishCount(pendingCancellationDelta, 'impreza do anulacji', 'imprezy do anulacji', 'imprez do anulacji'));
                }
                const invoiceDelta = delta('invoice_requests');
                if (invoiceDelta > 0) parts.push(this.polishCount(invoiceDelta, 'wniosek o fakturę', 'wnioski o fakturę', 'wniosków o fakturę'));
                const messageDelta = delta('messages');
                if (messageDelta > 0) parts.push(this.polishCount(messageDelta, 'nowa wiadomość', 'nowe wiadomości', 'nowych wiadomości'));
                if (parts.length === 0 || typeof FilamentNotification === 'undefined' || !this.alertsEnabled) return;
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
                this.commentsCount = counts.comments ?? 0;
                this.newEventsCount = counts.new_events ?? 0;
                this.confirmedEventsCount = counts.confirmed_events ?? 0;
                this.pendingCancellationEventsCount = counts.pending_cancellation_events ?? 0;
                this.invoiceRequestsCount = counts.invoice_requests ?? 0;
                this.unreadMessagesCount = counts.messages ?? 0;
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
                    if (this.openPanel === panel) this.openPanel = null;
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
            csrfToken() {
                return document.querySelector('meta[name=csrf-token]')?.content || '';
            },
            refreshNotifications() {
                if (!this.countsUrl) return;
                const previous = this.snapshotCounts();
                fetch(this.countsUrl + '?_=' + Date.now(), {
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
                        const next = data.counts ?? data;
                        this.syncPayload(data);
                        this.notifyNewItems(previous, {
                            tasks: Number(next.tasks || 0) + Number(next.comments || 0),
                            events: Number(next.new_events || 0) + Number(next.confirmed_events || 0),
                            pending_cancellation_events: next.pending_cancellation_events,
                            invoice_requests: next.invoice_requests,
                            messages: next.messages,
                        });
                    })
                    .catch(error => console.error('Blad odswiezania powiadomien:', error));
            },
            markNotificationRead(item) {
                if (!item?.fingerprint || !this.markReadUrl) return;
                fetch(this.markReadUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({ fingerprint: item.fingerprint }),
                }).then(() => this.refreshNotifications()).catch(() => {});
            },
            init() {
                this.refreshNotifications();
                setInterval(() => this.refreshNotifications(), this.pollIntervalMs);
                window.addEventListener('refresh-notifications', () => this.refreshNotifications());
                this.$nextTick(() => { this.alertsEnabled = true; });
            },
        };
    };
</script>

{{-- Uproszczona belka: Zadania (+komentarze) · Imprezy · Do anulacji · Faktury · Wiadomości --}}
<div class="custom-topbar-notifications flex items-center" wire:ignore x-data="window.sorTopbarNotifications()">

    <a
        href="{{ $inboxUrl }}"
        class="sor-topbar-inbox inline-flex lg:hidden items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
        title="Centrum powiadomień"
    >
        <svg class="h-4 w-4 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        <span>Inbox</span>
        <span class="inline-flex min-w-[1.65rem] items-center justify-center rounded-full bg-amber-600 px-1.5 py-0.5 text-[11px] font-bold text-white" x-text="totalUnread > 99 ? '99+' : totalUnread"></span>
    </a>

    <div class="topbar-notifications-items hidden lg:flex lg:items-center lg:gap-1.5 lg:mr-2">

        {{-- Zadania = zadania + komentarze --}}
        <div class="relative" @mouseenter="openPanelHover('tasks')" @mouseleave="closePanel('tasks')">
            <button type="button" @click.stop="togglePanel('tasks')" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800" :aria-expanded="openPanel === 'tasks'">
                <span>Zadania</span>
                <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold ring-1" :class="activityCount > 0 ? 'bg-red-600 text-white ring-red-600' : 'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700'" x-text="activityCount > 99 ? '99+' : activityCount"></span>
            </button>
            <div x-show="openPanel === 'tasks'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] z-[9999]">
                <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                        <span>Zadania i komentarze</span>
                        <a :href="routes.tasks" class="text-xs font-medium text-primary-600 hover:underline">Lista zadań</a>
                    </div>
                    <div class="topbar-notification-scroll max-h-[16rem] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800" @wheel.stop>
                        <template x-if="activityItems().length === 0">
                            <div class="px-4 py-6 text-center text-sm text-gray-600">Brak nowych pozycji.</div>
                        </template>
                        <template x-for="item in activityItems()" :key="(item.type || 'item') + '-' + (item.id || 0) + '-' + (item.fingerprint || '')">
                            <a :href="item.url || routes.tasks" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800" :class="item.is_read ? 'opacity-60' : 'bg-orange-50/40 dark:bg-orange-500/10'">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100" :class="!item.is_read && 'font-semibold'" x-text="item.title || 'Pozycja'"></div>
                                        <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-400" x-text="item.meta || ''"></div>
                                    </div>
                                    <div class="whitespace-nowrap text-[11px] text-gray-500" x-text="item.time || ''"></div>
                                </div>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Imprezy = zapytania + potwierdzenia --}}
        <div class="relative" @mouseenter="openPanelHover('events')" @mouseleave="closePanel('events')">
            <button type="button" @click.stop="togglePanel('events')" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800" :aria-expanded="openPanel === 'events'">
                <span>Imprezy</span>
                <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold ring-1" :class="eventsCount > 0 ? 'bg-sky-600 text-white ring-sky-600' : 'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700'" x-text="eventsCount > 99 ? '99+' : eventsCount"></span>
            </button>
            <div x-show="openPanel === 'events'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] z-[9999]">
                <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                        <span>Nowe i potwierdzone</span>
                        <a :href="routes.newEvents" class="text-xs font-medium text-primary-600 hover:underline">Lista</a>
                    </div>
                    <div class="topbar-notification-scroll max-h-[16rem] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800" @wheel.stop>
                        <template x-if="eventsItems().length === 0">
                            <div class="px-4 py-6 text-center text-sm text-gray-600">Brak nowych imprez.</div>
                        </template>
                        <template x-for="item in eventsItems()" :key="(item.type || 'event') + '-' + (item.id || 0) + '-' + (item.fingerprint || '')">
                            <a :href="item.url || routes.events" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="item.title || 'Impreza'"></div>
                                        <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-400" x-text="item.meta || ''"></div>
                                    </div>
                                    <div class="whitespace-nowrap text-[11px] text-gray-500" x-text="item.time || ''"></div>
                                </div>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Do anulacji — tylko gdy coś jest --}}
        <div class="relative" x-show="pendingCancellationEventsCount > 0" @mouseenter="openPanelHover('pending')" @mouseleave="closePanel('pending')">
            <button type="button" @click.stop="togglePanel('pending')" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40" :aria-expanded="openPanel === 'pending'">
                <span>Anulacje</span>
                <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-rose-600 px-1.5 py-0.5 text-[11px] font-bold text-white" x-text="pendingCancellationEventsCount > 99 ? '99+' : pendingCancellationEventsCount"></span>
            </button>
            <div x-show="openPanel === 'pending'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] z-[9999]">
                <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">Do anulacji</div>
                    <div class="topbar-notification-scroll max-h-[16rem] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800" @wheel.stop>
                        <template x-for="item in itemList('pending_cancellation_event')" :key="(item.type || 'pending') + '-' + (item.id || 0) + '-' + (item.fingerprint || '')">
                            <a :href="item.url || routes.pendingCancellationEvents" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="item.title || 'Impreza'"></div>
                                <div class="mt-0.5 text-xs text-gray-600" x-text="item.meta || ''"></div>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        @if ($canSeeInvoiceRequests ?? false)
        <div class="relative" @mouseenter="openPanelHover('invoices')" @mouseleave="closePanel('invoices')">
            <button type="button" @click.stop="togglePanel('invoices')" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800" :aria-expanded="openPanel === 'invoices'">
                <span>Faktury</span>
                <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold ring-1" :class="invoiceRequestsCount > 0 ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700'" x-text="invoiceRequestsCount > 99 ? '99+' : invoiceRequestsCount"></span>
            </button>
            <div x-show="openPanel === 'invoices'" x-transition class="absolute top-full left-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] z-[9999]">
                <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-700">
                        <span>Wnioski o fakturę</span>
                        <a :href="routes.invoiceRequests" class="text-xs font-medium text-primary-600 hover:underline">Skrzynka</a>
                    </div>
                    <div class="topbar-notification-scroll max-h-[16rem] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800" @wheel.stop>
                        <template x-if="itemList('invoice_request').length === 0">
                            <div class="px-4 py-6 text-center text-sm text-gray-600">Brak nowych wniosków.</div>
                        </template>
                        <template x-for="item in itemList('invoice_request')" :key="(item.type || 'inv') + '-' + (item.id || 0) + '-' + (item.fingerprint || '')">
                            <a :href="item.url || routes.invoiceRequests" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="item.title || 'Wniosek'"></div>
                                <div class="mt-0.5 text-xs text-gray-600" x-text="item.meta || ''"></div>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="relative" @mouseenter="openPanelHover('messages')" @mouseleave="closePanel('messages')">
            <button type="button" @click.stop="togglePanel('messages')" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800" :aria-expanded="openPanel === 'messages'">
                <span>Wiadomości</span>
                <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold ring-1" :class="unreadMessagesCount > 0 ? 'bg-blue-600 text-white ring-blue-600' : 'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700'" x-text="unreadMessagesCount > 99 ? '99+' : unreadMessagesCount"></span>
            </button>
            <div x-show="openPanel === 'messages'" x-transition class="absolute top-full right-0 pt-2 w-[28rem] max-w-[calc(100vw-1.5rem)] z-[9999]">
                <div class="topbar-notification-panel w-full rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-700">
                        <span>Wiadomości</span>
                        <a :href="routes.messages" class="text-xs font-medium text-primary-600 hover:underline">Czat</a>
                    </div>
                    <div class="topbar-notification-scroll max-h-[16rem] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800" @wheel.stop>
                        <template x-if="itemList('message').length === 0">
                            <div class="px-4 py-6 text-center text-sm text-gray-600">Brak nowych wiadomości.</div>
                        </template>
                        <template x-for="item in itemList('message')" :key="(item.type || 'msg') + '-' + (item.id || 0) + '-' + (item.fingerprint || '')">
                            <a :href="item.url || routes.messages" @click="markNotificationRead(item)" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="item.title || 'Wiadomość'"></div>
                                <div class="mt-0.5 text-xs text-gray-600" x-text="item.meta || ''"></div>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ $inboxUrl }}" class="ml-1 text-xs font-semibold text-primary-600 hover:underline">Wszystkie →</a>
    </div>

    <div class="hidden lg:flex items-center gap-0.5 border-l border-gray-200 pl-2 dark:border-gray-700" x-data="{
        fontSize: 18,
        minSize: 16,
        maxSize: 24,
        initSize() {
            const saved = localStorage.getItem('ui-font-size');
            if (saved) { this.fontSize = parseInt(saved); this.applySize(); }
        },
        decreaseSize() { if (this.fontSize > this.minSize) { this.fontSize--; this.applySize(); localStorage.setItem('ui-font-size', this.fontSize); } },
        resetSize() { this.fontSize = 18; this.applySize(); localStorage.setItem('ui-font-size', this.fontSize); },
        increaseSize() { if (this.fontSize < this.maxSize) { this.fontSize++; this.applySize(); localStorage.setItem('ui-font-size', this.fontSize); } },
        applySize() { document.documentElement.style.fontSize = this.fontSize + 'px'; },
    }" x-init="initSize()">
        <button @click="decreaseSize()" type="button" class="rounded p-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800" title="Zmniejsz">A-</button>
        <button @click="resetSize()" type="button" class="rounded p-1.5 text-[10px] font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800" title="Reset">Reset</button>
        <button @click="increaseSize()" type="button" class="rounded p-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800" title="Powiększ">A+</button>
    </div>
</div>
