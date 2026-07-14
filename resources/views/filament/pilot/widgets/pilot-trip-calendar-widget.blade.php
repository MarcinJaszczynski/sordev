<x-filament-widgets::widget>
    <x-filament::section>
        <div class="mb-3 text-sm text-gray-600">
            {{ $eventsCount }} wycieczek w kalendarzu. Kliknij wydarzenie, aby otworzyć teczkę.
        </div>

        <div
            id="pilot-trip-calendar"
            wire:ignore
            class="min-h-[28rem] rounded-xl border border-gray-200 bg-white p-2"
        ></div>
    </x-filament::section>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const el = document.getElementById('pilot-trip-calendar');

                if (!el || typeof FullCalendar === 'undefined') {
                    return;
                }

                const events = @json($calendarEvents);

                const calendar = new FullCalendar.Calendar(el, {
                    initialView: 'dayGridMonth',
                    locale: 'pl',
                    height: 'auto',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,listMonth',
                    },
                    events,
                    eventClick(info) {
                        if (info.event.url) {
                            info.jsEvent.preventDefault();
                            window.location.href = info.event.url;
                        }
                    },
                });

                calendar.render();
            });
        </script>
    @endpush
</x-filament-widgets::widget>
