@if (\App\Support\ViteAssetResolver::manifestExists())
    @vite('resources/js/filament-calendar.js')
@endif

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const elementId = @json($elementId);
            const livewireId = @json($livewireId ?? null);
            const el = document.getElementById(elementId);
            if (!el) return;

            const readEvents = () => {
                const source = document.getElementById(elementId + '-events') || el;
                try {
                    return JSON.parse(source.dataset.calendarEvents || '[]');
                } catch (e) {
                    return [];
                }
            };

            const syncEventsDataset = () => {
                const source = document.getElementById(elementId + '-events');
                if (source && el) {
                    el.dataset.calendarEvents = source.dataset.calendarEvents || '[]';
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
                const description = info.event.extendedProps?.descriptionPreview;
                const comment = info.event.extendedProps?.commentPreview;
                const parts = [info.event.title];
                if (description) parts.push('Opis: ' + description);
                if (comment) parts.push('Komentarz: ' + comment);
                if (description || comment) {
                    info.el.classList.add('task-has-preview');
                    info.el.setAttribute('title', parts.join('\n\n'));
                }

                info.el.style.cursor = 'pointer';
            };

            const refreshEvents = () => {
                if (!calendar) {
                    return false;
                }

                calendar.removeAllEvents();
                readEvents().forEach((event) => calendar.addEvent(event));

                return true;
            };

            const renderWithBundle = () => {
                if (typeof window.initSorFullCalendar !== 'function') {
                    return false;
                }

                if (calendar) {
                    return refreshEvents();
                }

                calendar = window.initSorFullCalendar(elementId, readEvents(), {
                    dateClick: dateClickHandler,
                    eventClick: eventClickHandler,
                    eventDidMount: eventDidMountHandler,
                });

                return true;
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
                    locale: 'pl',
                    height: 'auto',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,listMonth',
                    },
                    events: readEvents(),
                    eventDidMount: eventDidMountHandler,
                    dateClick: dateClickHandler,
                    eventClick: eventClickHandler,
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

            if (typeof Livewire !== 'undefined') {
                Livewire.hook('message.processed', () => {
                    syncEventsDataset();
                    ensureCalendar();
                });
            }
        });
    </script>
@endpush
