@if (\App\Support\ViteAssetResolver::manifestExists())
    @vite('resources/js/filament-calendar.js')
@endif

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const elementId = @json($elementId);
            const livewireId = @json($livewireId ?? null);
            const initialDate = @json($initialDate ?? null);
            const syncVisibleRange = @json($syncVisibleRange ?? false);
            const el = document.getElementById(elementId);
            if (!el) return;

            const readEvents = () => {
                const source = document.getElementById(elementId + '-events');
                if (!source) {
                    return [];
                }

                try {
                    if (source.tagName === 'SCRIPT') {
                        return JSON.parse(source.textContent || '[]');
                    }

                    return JSON.parse(source.dataset.calendarEvents || '[]');
                } catch (e) {
                    console.warn('[operations-calendar] Nie udało się sparsować eventów', e);
                    return [];
                }
            };

            const getLivewire = () => {
                if (!livewireId || typeof Livewire === 'undefined') {
                    return null;
                }

                return Livewire.find(livewireId);
            };

            let calendar = null;
            let cdnRequested = false;
            let lastSyncedRangeKey = null;
            let rangeSyncTimer = null;
            let refreshTimer = null;

            const eventClickHandler = function(info) {
                info.jsEvent.preventDefault();
                info.jsEvent.stopPropagation();

                const component = getLivewire();
                if (component && typeof component.call === 'function') {
                    component.call('openCalendarEntry', info.event.id);
                    return;
                }

                const links = info.event.extendedProps?.links ?? [];
                const fallbackUrl = links[0]?.url ?? null;
                if (fallbackUrl) {
                    window.open(fallbackUrl, '_blank');
                }
            };

            const dateClickHandler = function(info) {
                const component = getLivewire();
                if (component && typeof component.call === 'function') {
                    component.call('openCreateTaskModal', info.dateStr);
                }
            };

            const eventDidMountHandler = function(info) {
                const ownership = info.event.extendedProps?.ownershipPreview;
                const description = info.event.extendedProps?.descriptionPreview;
                const comment = info.event.extendedProps?.commentPreview;
                const parts = [info.event.title];
                if (ownership) parts.push(ownership);
                if (description) parts.push('Opis: ' + description);
                if (comment) parts.push('Komentarz: ' + comment);
                if (description || comment) {
                    info.el.classList.add('task-has-preview');
                    info.el.setAttribute('title', parts.join('\n\n'));
                }

                info.el.style.cursor = 'pointer';
                info.el.setAttribute('title', info.el.getAttribute('title') || info.event.title);
            };

            const datesSetHandler = function(info) {
                if (!syncVisibleRange) {
                    return;
                }

                const from = info.startStr?.slice(0, 10) || null;
                const to = info.endStr?.slice(0, 10) || null;
                const rangeKey = (from || '') + '|' + (to || '');

                if (rangeKey === lastSyncedRangeKey) {
                    return;
                }

                lastSyncedRangeKey = rangeKey;

                const component = getLivewire();
                if (!component || typeof component.call !== 'function') {
                    return;
                }

                clearTimeout(rangeSyncTimer);
                rangeSyncTimer = setTimeout(() => {
                    component.call('setVisibleRange', from, to);
                }, 150);
            };

            const refreshEvents = () => {
                if (!calendar) {
                    return false;
                }

                const nextEvents = readEvents();
                calendar.removeAllEvents();
                nextEvents.forEach((event) => calendar.addEvent(event));

                return true;
            };

            const scheduleRefresh = () => {
                clearTimeout(refreshTimer);
                refreshTimer = setTimeout(() => {
                    ensureCalendar();
                }, 30);
            };

            const calendarOptions = {
                dateClick: dateClickHandler,
                eventClick: eventClickHandler,
                eventDidMount: eventDidMountHandler,
                datesSet: datesSetHandler,
                dayMaxEvents: 4,
                moreLinkClick: 'popover',
                eventDisplay: 'block',
            };

            if (initialDate) {
                calendarOptions.initialDate = initialDate;
            }

            const renderWithBundle = () => {
                if (typeof window.initSorFullCalendar !== 'function') {
                    return false;
                }

                if (calendar) {
                    return refreshEvents();
                }

                calendar = window.initSorFullCalendar(elementId, readEvents(), calendarOptions);

                return Boolean(calendar);
            };

            const renderWithCdn = () => {
                if (typeof FullCalendar === 'undefined') {
                    return;
                }

                if (calendar) {
                    refreshEvents();
                    return;
                }

                calendar = new FullCalendar.Calendar(el, {
                    initialView: 'dayGridMonth',
                    initialDate: initialDate || undefined,
                    locale: 'pl',
                    height: 'auto',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,listMonth',
                    },
                    dayMaxEvents: 4,
                    moreLinkClick: 'popover',
                    eventDisplay: 'block',
                    events: readEvents(),
                    eventDidMount: eventDidMountHandler,
                    dateClick: dateClickHandler,
                    eventClick: eventClickHandler,
                    datesSet: datesSetHandler,
                });
                calendar.render();
            };

            const ensureCalendar = () => {
                if (renderWithBundle()) {
                    return;
                }

                if (!cdnRequested) {
                    cdnRequested = true;
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js';
                    script.onload = () => renderWithCdn();
                    document.head.appendChild(script);
                    return;
                }

                renderWithCdn();
            };

            ensureCalendar();

            const bindLivewireRefresh = () => {
                if (typeof Livewire === 'undefined' || typeof Livewire.hook !== 'function') {
                    return;
                }

                // Livewire 3: message.processed już nie istnieje — odświeżamy po morph.
                Livewire.hook('morph.updated', ({ el: updatedEl }) => {
                    if (!updatedEl) {
                        scheduleRefresh();
                        return;
                    }

                    const isEventsSource = updatedEl.id === elementId + '-events'
                        || updatedEl.querySelector?.('#' + elementId + '-events');

                    if (isEventsSource || updatedEl.contains?.(document.getElementById(elementId + '-events'))) {
                        scheduleRefresh();
                        return;
                    }

                    // Filtry są w tym samym komponencie — po ich zmianie też dociągamy eventy.
                    const componentRoot = updatedEl.closest?.('[wire\\:id]')
                        || (updatedEl.hasAttribute?.('wire:id') ? updatedEl : null);
                    if (componentRoot && componentRoot.getAttribute('wire:id') === livewireId) {
                        scheduleRefresh();
                    }
                });

                Livewire.on('operations-calendar-refresh', () => {
                    scheduleRefresh();
                });
            };

            if (window.Livewire) {
                bindLivewireRefresh();
            } else {
                document.addEventListener('livewire:init', bindLivewireRefresh, { once: true });
            }
        });
    </script>
@endpush
